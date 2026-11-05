<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductMediaController extends Controller
{
    public function update(
        Request $request,
        Product $product,
        ProductMedia $media,
    ): JsonResponse {
        $this->ensureProductImage($product, $media);

        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($validated);

        return response()->json([
            'message' => 'Product image details updated successfully.',
            'data' => $media->refresh(),
        ]);
    }

    public function setPrimary(
        Product $product,
        ProductMedia $media,
    ): JsonResponse {
        $this->ensureProductImage($product, $media);

        $primaryMedia = DB::transaction(function () use ($product, $media): ProductMedia {
            Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMedia = ProductMedia::query()
                ->whereKey($media->id)
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->lockForUpdate()
                ->firstOrFail();

            ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->where('is_primary', true)
                ->whereKeyNot($lockedMedia->id)
                ->update(['is_primary' => false]);

            $lockedMedia->forceFill(['is_primary' => true])->save();

            $orderedMedia = ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $orderedMedia = $orderedMedia
                ->sortBy(static fn (ProductMedia $item): int => $item->id === $lockedMedia->id ? -1 : $item->sort_order)
                ->values();

            foreach ($orderedMedia as $sortOrder => $item) {
                if ($item->sort_order !== $sortOrder) {
                    $item->forceFill(['sort_order' => $sortOrder])->save();
                }
            }

            return $lockedMedia->refresh();
        }, 3);

        return response()->json([
            'message' => 'Primary product image updated successfully.',
            'data' => $primaryMedia,
        ]);
    }

    public function reorder(
        Request $request,
        Product $product,
    ): JsonResponse {
        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1', 'max:10'],
            'media_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('product_media', 'id')
                    ->where('product_id', $product->id)
                    ->where('collection', 'images')
                    ->whereNull('deleted_at'),
            ],
        ]);

        /** @var Collection<int, ProductMedia> $mediaItems */
        $mediaItems = DB::transaction(function () use ($product, $validated): Collection {
            Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentIds = ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();

            $requestedIds = array_map('intval', $validated['media_ids']);
            $currentSorted = $currentIds;
            $requestedSorted = $requestedIds;
            sort($currentSorted);
            sort($requestedSorted);

            if ($currentSorted !== $requestedSorted) {
                throw ValidationException::withMessages([
                    'media_ids' => 'The media order must include every active product image exactly once.',
                ]);
            }

            $primaryMediaId = ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->where('is_primary', true)
                ->value('id');

            if ($primaryMediaId !== null && $requestedIds[0] !== (int) $primaryMediaId) {
                throw ValidationException::withMessages([
                    'media_ids' => 'The primary product image must remain first in the gallery order.',
                ]);
            }

            foreach ($requestedIds as $sortOrder => $mediaId) {
                ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->whereKey($mediaId)
                    ->update(['sort_order' => $sortOrder]);
            }

            return ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }, 3);

        return response()->json([
            'message' => 'Product gallery order updated successfully.',
            'data' => $mediaItems,
        ]);
    }

    public function destroy(
        Product $product,
        ProductMedia $media,
    ): JsonResponse {
        $this->ensureProductImage($product, $media);

        $deletedMedia = DB::transaction(function () use ($product, $media): ProductMedia {
            Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMedia = ProductMedia::query()
                ->whereKey($media->id)
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMedia->forceFill(['is_primary' => false])->save();
            $lockedMedia->delete();

            $hasPrimary = ProductMedia::query()
                ->where('product_id', $product->id)
                ->where('collection', 'images')
                ->where('is_primary', true)
                ->exists();

            if (! $hasPrimary) {
                $replacement = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('collection', 'images')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $replacement?->forceFill(['is_primary' => true])->save();
            }

            return $lockedMedia;
        }, 3);

        try {
            Storage::disk($deletedMedia->disk)->delete($deletedMedia->path);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $primaryMediaId = $product->media()
            ->where('collection', 'images')
            ->where('is_primary', true)
            ->value('id');

        return response()->json([
            'message' => 'Product image deleted successfully.',
            'data' => [
                'id' => $deletedMedia->id,
                'primary_media_id' => $primaryMediaId === null ? null : (int) $primaryMediaId,
            ],
        ]);
    }

    private function ensureProductImage(Product $product, ProductMedia $media): void
    {
        if ($media->product_id !== $product->id || $media->collection !== 'images') {
            abort(404);
        }
    }
}
