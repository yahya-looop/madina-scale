<?php

namespace App\Http\Controllers;

use App\Http\Requests\Module\StoreModuleRequest;
use App\Http\Requests\Module\UpdateModuleRequest;
use App\Models\Module;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleService $modules,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * Display all modules available for management.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->modules
                ->list()
                ->values()
                ->toArray(),
        ]);
    }

    /**
     * Display a single module.
     */
    public function show(Module $module): JsonResponse
    {
        return response()->json([
            'data' => $module->toArray(),
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
         * } $attributes
         */
        $attributes = $request->validated();

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $module = $this->modules->create($attributes, $user);

        return response()->json([
            'data' => $module->toArray(),
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
         * } $attributes
         */
        $attributes = $request->validated();

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $module = $this->modules->update(
            $module,
            $attributes,
            $user,
        );

        return response()->json([
            'data' => $module->toArray(),
        ]);
    }

    /** Change a module lifecycle state. */
    public function updateState(Request $request, Module $module): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->authorization->isDeveloper($user), 403);

        $validated = $request->validate([
            'state' => ['required', 'string', Rule::in(['enabled', 'disabled', 'locked', 'hidden'])],
        ]);

        return response()->json([
            'data' => $this->modules->changeState($module, $validated['state'], $user),
        ]);
    }
}
