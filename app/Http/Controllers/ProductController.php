<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'unit:id,name,short_name,slug',
                'primaryMedia',
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('slug', 'ilike', '%' . $search . '%')
                    ->orWhere('sku', 'ilike', '%' . $search . '%')
                    ->orWhere('model_number', 'ilike', '%' . $search . '%')
                    ->orWhere('barcode', 'ilike', '%' . $search . '%');
            });
        }

        $categoryId = $request->query('category_id');

        if (is_numeric($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        $brandId = $request->query('brand_id');

        if (is_numeric($brandId)) {
            $query->where('brand_id', (int) $brandId);
        }

        $unitId = $request->query('unit_id');

        if (is_numeric($unitId)) {
            $query->where('unit_id', (int) $unitId);
        }

        $productType = $request->query('product_type');

        if (is_string($productType) && $productType !== '') {
            $query->where('product_type', $productType);
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $visibility = $request->query('visibility');

        if (is_string($visibility) && $visibility !== '') {
            $query->where('visibility', $visibility);
        }

        $products = $query->get([
            'id',
            'category_id',
            'brand_id',
            'unit_id',
            'name',
            'slug',
            'sku',
            'model_number',
            'barcode',
            'product_type',
            'short_description',
            'description',
            'status',
            'visibility',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
        ]);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        $validated['sku'] = $this->resolveSku(
            $validated['sku'] ?? null,
        );

        $validated['barcode'] = $this->normalizeNullableValue(
            $validated['barcode'] ?? null,
        );

        $this->ensureBarcodeAvailable(
            $validated['barcode'],
        );

        // The uploaded file is media, not a products table column.
        unset($validated['image']);

        $uploadedFile = $request->file('image');
        $storedPath = null;

        try {
            $product = DB::transaction(function () use (
                $validated,
                $uploadedFile,
                &$storedPath
            ) {
                $product = Product::create($validated);

                if ($uploadedFile !== null) {
                    $storedPath = $uploadedFile->store(
                        'products/' . $product->id . '/images',
                        'public'
                    );

                    if ($storedPath === false) {
                        throw new \RuntimeException(
                            'The product image could not be stored.'
                        );
                    }

                    $imageSize = @getimagesize(
                        $uploadedFile->getRealPath()
                    );

                    ProductMedia::create([
                        'product_id' => $product->id,
                        'disk' => 'public',
                        'path' => $storedPath,
                        'collection' => 'images',
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false
                            ? ($imageSize[0] ?? null)
                            : null,
                        'height' => $imageSize !== false
                            ? ($imageSize[1] ?? null)
                            : null,
                        'alt_text' => $product->name,
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }

        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
        ]);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    public function update(
        Request $request,
        Product $product
    ): JsonResponse {
        $validated = $this->validateProduct(
            $request,
            $product
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $product,
        );

        $validated['sku'] = $this->resolveSku(
            $validated['sku'] ?? null,
            $product,
        );

        $validated['barcode'] = $this->normalizeNullableValue(
            $validated['barcode'] ?? null,
        );

        $this->ensureBarcodeAvailable(
            $validated['barcode'],
            $product,
        );

        // Keep image data out of the products table update.
        unset($validated['image']);

        $uploadedFile = $request->file('image');
        $storedPath = null;
        $oldMediaDisk = null;
        $oldMediaPath = null;

        try {
            $product = DB::transaction(function () use (
                $product,
                $validated,
                $uploadedFile,
                &$storedPath,
                &$oldMediaDisk,
                &$oldMediaPath
            ) {
                $product->update($validated);

                if ($uploadedFile !== null) {
                    // Store the replacement before changing the current
                    // primary media record.
                    $storedPath = $uploadedFile->store(
                        'products/' . $product->id . '/images',
                        'public'
                    );

                    if ($storedPath === false) {
                        throw new \RuntimeException(
                            'The replacement product image could not be stored.'
                        );
                    }

                    $imageSize = @getimagesize(
                        $uploadedFile->getRealPath()
                    );

                    // Lock the existing primary media row while replacing it.
                    $oldPrimaryMedia = ProductMedia::query()
                        ->where('product_id', $product->id)
                        ->where('collection', 'images')
                        ->where('is_primary', true)
                        ->lockForUpdate()
                        ->first();

                    if ($oldPrimaryMedia !== null) {
                        $oldMediaDisk = $oldPrimaryMedia->disk;
                        $oldMediaPath = $oldPrimaryMedia->path;

                        // Demote before inserting the new primary record
                        // to satisfy the PostgreSQL partial unique index.
                        $oldPrimaryMedia->is_primary = false;
                        $oldPrimaryMedia->save();

                        // Retain the historical row but exclude it from
                        // active media relationships.
                        $oldPrimaryMedia->delete();
                    }

                    ProductMedia::create([
                        'product_id' => $product->id,
                        'disk' => 'public',
                        'path' => $storedPath,
                        'collection' => 'images',
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false
                            ? ($imageSize[0] ?? null)
                            : null,
                        'height' => $imageSize !== false
                            ? ($imageSize[1] ?? null)
                            : null,
                        'alt_text' => $product->name,
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            // The DB transaction rolls back on failure. Remove only the
            // newly uploaded file; do not delete the previous image.
            if ($storedPath !== null) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }

        // The database replacement has committed. The old file can now
        // be removed without risking the active primary image.
        if (
            $uploadedFile !== null
            && $oldMediaDisk !== null
            && $oldMediaPath !== null
            && $oldMediaPath !== $storedPath
        ) {
            try {
                Storage::disk($oldMediaDisk)->delete($oldMediaPath);
            } catch (\Throwable $exception) {
                // Do not fail an otherwise successful product update if
                // physical cleanup fails. A cleanup job can handle orphans.
                report($exception);
            }
        }

        $product->refresh();

        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
        ]);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        // Soft-delete the product only. Keep media records and files so
        // a future restore does not leave the product without its images.
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    private function validateProduct(
        Request $request,
        ?Product $product = null
    ): array {
        return $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'name' => [
                'required',
                'string',
                'max:200',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                'required_if:product_type,simple',
            ],

            'model_number' => [
                'nullable',
                'string',
                'max:120',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
            ],

            'product_type' => [
                'required',
                'string',
                Rule::in([
                    'simple',
                    'variable',
                ]),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'draft',
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],

            'visibility' => [
                'sometimes',
                'string',
                Rule::in([
                    'visible',
                    'hidden',
                ]),
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);
    }

    private function resolveSlug(
        ?string $slug,
        string $name,
        ?Product $product = null
    ): string {
        $baseSlug = Str::slug(trim($slug ?? ''));

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the product name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            Product::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $product !== null,
                    fn ($query) => $query->where('id', '!=', $product->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function resolveSku(
        ?string $sku,
        ?Product $product = null
    ): ?string {
        $sku = $this->normalizeNullableValue($sku);

        if ($sku === null) {
            return null;
        }

        $sku = strtoupper($sku);

        $exists = Product::query()
            ->whereRaw('LOWER(sku) = LOWER(?)', [$sku])
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where('id', '!=', $product->id),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => 'The SKU has already been taken.',
            ]);
        }

        return $sku;
    }

    private function ensureBarcodeAvailable(
        ?string $barcode,
        ?Product $product = null
    ): void {
        if ($barcode === null) {
            return;
        }

        $exists = Product::query()
            ->whereRaw('LOWER(barcode) = LOWER(?)', [$barcode])
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where('id', '!=', $product->id),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barcode' => 'The barcode has already been taken.',
            ]);
        }
    }

    private function normalizeNullableValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
