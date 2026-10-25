<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLog\ActivityLogIndexRequest;
use App\Models\ActivityLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        protected AuthorizationService $authorization,
    ) {}

    /**
     * Display activity logs.
     *
     * Activity logs are read-only. No create/update/delete
     * endpoints are exposed through this controller.
     */
    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $perPage = (int) ($validated['per_page'] ?? 25);

        $logs = ActivityLog::query()
            ->with([
                'actor:id,name,email',
                'subject',
            ])
            ->when(
                filled($validated['search'] ?? null),
                function ($query) use ($validated): void {
                    $search = $validated['search'];

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where(
                                'description',
                                'ilike',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'module',
                                'ilike',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'action',
                                'ilike',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'request_id',
                                'ilike',
                                "%{$search}%",
                            );
                    });
                },
            )
            ->when(
                filled($validated['module'] ?? null),
                fn ($query) => $query->where(
                    'module',
                    $validated['module'],
                ),
            )
            ->when(
                filled($validated['action'] ?? null),
                fn ($query) => $query->where(
                    'action',
                    $validated['action'],
                ),
            )
            ->when(
                filled($validated['actor_type'] ?? null),
                fn ($query) => $query->where(
                    'actor_type',
                    $validated['actor_type'],
                ),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    /**
     * Display one immutable activity log record.
     */
    public function show(
        Request $request,
        ActivityLog $activityLog,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorization->can(
                $user,
                'system.activity_logs.view',
            ),
            403,
        );

        $activityLog->load([
            'actor:id,name,email',
            'subject',
        ]);

        return response()->json([
            'data' => $activityLog,
        ]);
    }
}
