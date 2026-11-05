<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductCategoryController extends Controller
{
    /**
     * List product categories.
     *
     * Returns the category hierarchy as a flat collection ordered
     * by parent and sort order. The frontend can build the tree
     * using parent_id when required.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::query()
            ->with([
                'parent:id,name,slug',
            ])
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        /*
         * Optional search.
         */
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%');
            });
        }

        /*
         * Optional status filter.
         */
        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        /*
         * Optional visibility filter.
         */
        $visibility = $request->query('visibility');

        if (is_string($visibility) && $visibility !== '') {
            $query->where('visibility', $visibility);
        }

        $categories = $query->get([
            'id',
            'parent_id',
            'name',
            'slug',
            'description',
            'image_path',
            'status',
            'visibility',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Show a single product category.
     */
    public function show(ProductCategory $productCategory): JsonResponse
    {
        $productCategory->load([
            'parent:id,name,slug',
        ]);

        $productCategory->loadCount('children');

        return response()->json([
            'data' => $productCategory,
        ]);
    }

    /**
     * Create a new product category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCategory($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        $this->validateParent($validated['parent_id'] ?? null);

        $category = ProductCategory::create($validated);

        $category->load([
            'parent:id,name,slug',
        ]);

        return response()->json([
            'message' => 'Product category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update an existing product category.
     */
    public function update(
        Request $request,
        ProductCategory $productCategory,
    ): JsonResponse {
        $validated = $this->validateCategory(
            $request,
            $productCategory,
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $productCategory,
        );

        $this->validateParent(
            $validated['parent_id'] ?? null,
            $productCategory,
        );

        $productCategory->update($validated);

        $productCategory->refresh();

        $productCategory->load([
            'parent:id,name,slug',
        ]);

        return response()->json([
            'message' => 'Product category updated successfully.',
            'data' => $productCategory,
        ]);
    }

    /**
     * Soft-delete a product category.
     *
     * Categories with children cannot be deleted until their
     * children are moved or removed.
     */
    public function destroy(
        ProductCategory $productCategory,
    ): JsonResponse {
        if (ProductCategory::withTrashed()->where('parent_id', $productCategory->id)->exists()) {
            return response()->json([
                'message' => 'This product category cannot be deleted because it contains child categories.',
            ], 422);
        }

        if (Product::withTrashed()->where('category_id', $productCategory->id)->exists()) {
            return response()->json([
                'message' => 'This product category cannot be deleted while products are assigned to it. Reassign those products first.',
            ], 422);
        }

        $productCategory->delete();

        return response()->json([
            'message' => 'Product category deleted successfully.',
        ]);
    }

    /**
     * Validate category input.
     *
     * @return array<string, mixed>
     */
    private function validateCategory(
        Request $request,
        ?ProductCategory $productCategory = null,
    ): array {
        return $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->whereNull('deleted_at'),
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

            'description' => [
                'nullable',
                'string',
            ],

            'image_path' => [
                'nullable',
                'string',
                'max:500',
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
        ]);
    }

    /**
     * Resolve the category slug.
     *
     * If no slug is supplied, generate one from the category name.
     */
    private function resolveSlug(
        ?string $slug,
        string $name,
        ?ProductCategory $productCategory = null,
    ): string {
        $baseSlug = Str::slug(
            trim($slug ?? ''),
        );

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the category name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            ProductCategory::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $productCategory !== null,
                    fn ($query) => $query->whereKeyNot($productCategory->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Validate category hierarchy rules.
     */
    private function validateParent(
        ?int $parentId,
        ?ProductCategory $productCategory = null,
    ): void {
        if ($parentId === null) {
            return;
        }

        /*
         * A category cannot be its own parent.
         */
        if (
            $productCategory !== null &&
            $parentId === $productCategory->id
        ) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        /*
         * A category cannot be moved underneath one of its descendants.
         */
        if ($productCategory !== null) {
            $descendantIds = $this->descendantIds($productCategory);

            if (in_array($parentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot be moved under one of its child categories.',
                ]);
            }
        }
    }

    /**
     * Get all descendant category IDs.
     *
     * @return array<int, int>
     */
    private function descendantIds(ProductCategory $category): array
    {
        $descendantIds = [];

        $children = ProductCategory::query()
            ->where('parent_id', $category->id)
            ->get(['id']);

        foreach ($children as $child) {
            $descendantIds[] = $child->id;

            $descendantIds = array_merge(
                $descendantIds,
                $this->descendantIds($child),
            );
        }

        return $descendantIds;
    }
}
