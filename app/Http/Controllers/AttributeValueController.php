<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttributeValueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AttributeValue::query()
            ->with([
                'attribute:id,name,slug,type',
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        $attributeId = $request->query('attribute_id');

        if (is_numeric($attributeId)) {
            $query->where('attribute_id', (int) $attributeId);
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%')
                    ->orWhere('value', 'ilike', '%'.$search.'%');
            });
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $attributeValues = $query->get([
            'id',
            'attribute_id',
            'name',
            'slug',
            'value',
            'color_code',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $attributeValues,
        ]);
    }

    public function show(AttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->load([
            'attribute:id,name,slug,type',
        ]);

        return response()->json([
            'data' => $attributeValue,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAttributeValue($request);

        $attributeValue = DB::transaction(function () use (&$validated): AttributeValue {
            // Serialize value creation with parent-attribute soft deletion.
            $attribute = Attribute::query()
                ->whereKey($validated['attribute_id'])
                ->lockForUpdate()
                ->first();

            if ($attribute === null) {
                throw ValidationException::withMessages([
                    'attribute_id' => 'The selected attribute does not exist.',
                ]);
            }

            $validated['slug'] = $this->resolveSlug(
                $validated['slug'] ?? null,
                $validated['name'],
                $attribute->id,
            );

            return AttributeValue::create($validated);
        }, 3);

        $attributeValue->load([
            'attribute:id,name,slug,type',
        ]);

        return response()->json([
            'message' => 'Attribute value created successfully.',
            'data' => $attributeValue,
        ], 201);
    }

    public function update(
        Request $request,
        AttributeValue $attributeValue
    ): JsonResponse {
        $validated = $this->validateAttributeValue(
            $request,
            $attributeValue
        );

        DB::transaction(function () use ($attributeValue, &$validated): void {
            $attributeIds = collect([
                $attributeValue->attribute_id,
                (int) $validated['attribute_id'],
            ])->unique()->sort()->values();

            $lockedAttributes = Attribute::query()
                ->whereIn('id', $attributeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedAttributes->count() !== $attributeIds->count()) {
                throw ValidationException::withMessages([
                    'attribute_id' => 'The selected attribute does not exist.',
                ]);
            }

            $lockedValue = AttributeValue::query()
                ->whereKey($attributeValue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedValue->variantAttributeValues()->exists()
                && (int) $validated['attribute_id'] !== $lockedValue->attribute_id
            ) {
                throw ValidationException::withMessages([
                    'attribute_id' => 'An attribute value assigned to product variants cannot be moved to another attribute.',
                ]);
            }

            $validated['slug'] = $this->resolveSlug(
                $validated['slug'] ?? null,
                $validated['name'],
                $validated['attribute_id'],
                $lockedValue,
            );

            $lockedValue->update($validated);
        }, 3);

        $attributeValue->refresh();
        $attributeValue->load([
            'attribute:id,name,slug,type',
        ]);

        return response()->json([
            'message' => 'Attribute value updated successfully.',
            'data' => $attributeValue,
        ]);
    }

    public function destroy(AttributeValue $attributeValue): JsonResponse
    {
        $canDelete = DB::transaction(function () use ($attributeValue): bool {
            // Keep the same parent-then-value lock order used by variant writes.
            Attribute::query()
                ->whereKey($attributeValue->attribute_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedValue = AttributeValue::query()
                ->whereKey($attributeValue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedValue->variantAttributeValues()->exists()) {
                return false;
            }

            $lockedValue->delete();

            return true;
        }, 3);

        if (! $canDelete) {
            return response()->json([
                'message' => 'This attribute value cannot be deleted while it is assigned to product variants.',
            ], 422);
        }

        return response()->json([
            'message' => 'Attribute value deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAttributeValue(
        Request $request,
        ?AttributeValue $attributeValue = null
    ): array {
        return $request->validate([
            'attribute_id' => [
                'required',
                'integer',
                'exists:attributes,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'value' => [
                'required',
                'string',
                'max:255',
            ],

            'color_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);
    }

    private function resolveSlug(
        ?string $slug,
        string $name,
        int $attributeId,
        ?AttributeValue $attributeValue = null
    ): string {
        $baseSlug = Str::slug(trim($slug ?? ''));

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the attribute value name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            AttributeValue::query()
                ->where('attribute_id', $attributeId)
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $attributeValue !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $attributeValue->id
                    ),
                )
                ->exists()
        ) {
            $candidate = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
