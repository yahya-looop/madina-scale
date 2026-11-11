<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttributeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attribute::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
            });
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $type = $request->query('type');

        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $attributes = $query->get([
            'id',
            'name',
            'slug',
            'type',
            'description',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $attributes,
        ]);
    }

    public function show(Attribute $attribute): JsonResponse
    {
        return response()->json([
            'data' => $attribute,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAttribute($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        $attribute = Attribute::create($validated);

        return response()->json([
            'message' => 'Attribute created successfully.',
            'data' => $attribute,
        ], 201);
    }

    public function update(Request $request, Attribute $attribute): JsonResponse
    {
        $validated = $this->validateAttribute($request, $attribute);

        DB::transaction(function () use ($attribute, &$validated): void {
            $lockedAttribute = Attribute::query()
                ->whereKey($attribute->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nextType = $validated['type'] ?? $lockedAttribute->type;

            if (
                $lockedAttribute->variantAttributeValues()->exists()
                && ! in_array($nextType, ['select', 'multiselect', 'boolean', 'color'], true)
            ) {
                throw ValidationException::withMessages([
                    'type' => 'An attribute used by product variants must remain selectable.',
                ]);
            }

            $validated['slug'] = $this->resolveSlug(
                $validated['slug'] ?? null,
                $validated['name'],
                $lockedAttribute,
            );

            $lockedAttribute->update($validated);
        }, 3);

        $attribute->refresh();

        return response()->json([
            'message' => 'Attribute updated successfully.',
            'data' => $attribute,
        ]);
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $canDelete = DB::transaction(function () use ($attribute): bool {
            $lockedAttribute = Attribute::query()
                ->whereKey($attribute->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAttribute->values()->exists()
                || $lockedAttribute->variantAttributeValues()->exists()
            ) {
                return false;
            }

            $lockedAttribute->delete();

            return true;
        }, 3);

        if (! $canDelete) {
            return response()->json([
                'message' => 'This attribute cannot be deleted while it has attribute values or is assigned to product variants.',
            ], 422);
        }

        return response()->json([
            'message' => 'Attribute deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAttribute(
        Request $request,
        ?Attribute $attribute = null
    ): array {
        return $request->validate([
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

            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'text',
                    'number',
                    'select',
                    'multiselect',
                    'boolean',
                    'color',
                ]),
            ],

            'description' => [
                'nullable',
                'string',
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
        ?Attribute $attribute = null
    ): string {
        $baseSlug = Str::slug(trim($slug ?? ''));

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the attribute name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            Attribute::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $attribute !== null,
                    fn ($query) => $query->where('id', '!=', $attribute->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
