<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Unit::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('short_name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%');
            });
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $units = $query->get([
            'id',
            'name',
            'short_name',
            'slug',
            'description',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $units,
        ]);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'data' => $unit,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUnit($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        $unit = Unit::create($validated);

        return response()->json([
            'message' => 'Unit created successfully.',
            'data' => $unit,
        ], 201);
    }

    public function update(
        Request $request,
        Unit $unit,
    ): JsonResponse {
        $validated = $this->validateUnit(
            $request,
            $unit,
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $unit,
        );

        $unit->update($validated);
        $unit->refresh();

        return response()->json([
            'message' => 'Unit updated successfully.',
            'data' => $unit,
        ]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if (Product::withTrashed()->where('unit_id', $unit->id)->exists()) {
            return response()->json([
                'message' => 'This unit cannot be deleted while products are using it. Reassign those products first.',
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUnit(
        Request $request,
        ?Unit $unit = null,
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'short_name' => [
                'required',
                'string',
                'max:30',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
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
        ?Unit $unit = null,
    ): string {
        $baseSlug = Str::slug(
            trim($slug ?? ''),
        );

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the unit name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            Unit::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $unit !== null,
                    fn ($query) => $query->where('id', '!=', $unit->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
