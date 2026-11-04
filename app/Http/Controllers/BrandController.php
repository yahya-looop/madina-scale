<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%');
            });
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $brands = $query->get([
            'id',
            'name',
            'slug',
            'description',
            'logo_path',
            'website_url',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $brands,
        ]);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'data' => $brand,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateBrand($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        $brand = Brand::create($validated);

        return response()->json([
            'message' => 'Brand created successfully.',
            'data' => $brand,
        ], 201);
    }

    public function update(
        Request $request,
        Brand $brand,
    ): JsonResponse {
        $validated = $this->validateBrand(
            $request,
            $brand,
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $brand,
        );

        $brand->update($validated);

        $brand->refresh();

        return response()->json([
            'message' => 'Brand updated successfully.',
            'data' => $brand,
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBrand(
        Request $request,
        ?Brand $brand = null,
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

            'description' => [
                'nullable',
                'string',
            ],

            'logo_path' => [
                'nullable',
                'string',
                'max:500',
            ],

            'website_url' => [
                'nullable',
                'string',
                'url',
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
        ?Brand $brand = null,
    ): string {
        $baseSlug = Str::slug(
            trim($slug ?? ''),
        );

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the brand name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            Brand::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $brand !== null,
                    fn ($query) => $query->where('id', '!=', $brand->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
