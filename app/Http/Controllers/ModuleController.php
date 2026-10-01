<?php

namespace App\Http\Controllers;

use App\Http\Requests\Module\StoreModuleRequest;
use App\Http\Requests\Module\UpdateModuleRequest;
use App\Models\Module;
use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleService $modules,
    ) {}

    /**
     * Display all modules available for management.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->modules->list(),
        ]);
    }

    /**
     * Display a single module.
     */
    public function show(Module $module): JsonResponse
    {
        return response()->json([
            'data' => $module,
        ]);
    }

    /**
     * Store a newly created module.
     */
    public function store(StoreModuleRequest $request): JsonResponse
    {
        /** @var array{
         *     name: string,
         *     slug: string,
         *     description?: string|null,
         *     icon?: string|null,
         *     sort_order?: int,
         *     is_active?: bool
         * } $attributes
         */
        $attributes = $request->validated();

        $module = $this->modules->create($attributes);

        return response()->json([
            'data' => $module,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing module.
     */
    public function update(
        UpdateModuleRequest $request,
        Module $module,
    ): JsonResponse {
        /** @var array{
         *     name: string,
         *     slug: string,
         *     description?: string|null,
         *     icon?: string|null,
         *     sort_order?: int,
         *     is_active?: bool
         * } $attributes
         */
        $attributes = $request->validated();

        $module = $this->modules->update(
            $module,
            $attributes,
        );

        return response()->json([
            'data' => $module,
        ]);
    }
}
