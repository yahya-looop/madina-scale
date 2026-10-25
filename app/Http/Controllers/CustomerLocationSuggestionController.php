<?php

namespace App\Http\Controllers;

use App\Models\CustomerLocationSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerLocationSuggestionController extends Controller
{
    private const TYPES = ['division', 'district', 'upazila', 'postcode'];

    public function index(): JsonResponse
    {
        $data = [];
        foreach (['division' => 'divisions', 'district' => 'districts', 'upazila' => 'upazilas', 'postcode' => 'postcodes'] as $type => $key) {
            $data[$key] = CustomerLocationSuggestion::query()->where('type', $type)->orderBy('name')->pluck('name')->all();
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $name = trim(preg_replace('/\s+/u', ' ', $validated['name']) ?? $validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'Location name is required.'], 422);
        }
        $normalized = Str::lower($name);
        $item = CustomerLocationSuggestion::query()->firstOrCreate(
            ['type' => $validated['type'], 'normalized_name' => $normalized],
            ['name' => $name, 'created_by' => $request->user()?->id]
        );

        return response()->json(['message' => $item->wasRecentlyCreated ? 'Location suggestion added.' : 'This location already exists.', 'data' => $item], $item->wasRecentlyCreated ? 201 : 200);
    }
}
