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
     * Store a newly created non-system module.
     */
    public function store(StoreModuleRequest $request): JsonResponse
    {
        $module = $this->modules->create(
            $request->validated(),
        );

        return response()->json([
            'data' => $module,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing non-system module.
     */
    public function update(
        UpdateModuleRequest $request,
        Module $module,
    ): JsonResponse {
        $module = $this->modules->update(
            $module,
            $request->validated(),
        );

        return response()->json([
            'data' => $module,
        ]);
    }
}
