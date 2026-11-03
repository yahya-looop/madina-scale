<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLog\ActivityLogIndexRequest;
use App\Models\ActivityLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                filled($validated['from'] ?? null),
                fn ($query) => $query->whereDate('created_at', '>=', $validated['from']),
            )
            ->when(
                filled($validated['to'] ?? null),
                fn ($query) => $query->whereDate('created_at', '<=', $validated['to']),
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

    /** Export a bounded, permission-protected CSV audit extract. */
    public function export(ActivityLogIndexRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $query = ActivityLog::query()
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $term = $validated['search'];
                $query->where(function ($nested) use ($term): void {
                    $nested->where('description', 'ilike', "%{$term}%")
                        ->orWhere('module', 'ilike', "%{$term}%")
                        ->orWhere('action', 'ilike', "%{$term}%")
                        ->orWhere('request_id', 'ilike', "%{$term}%");
                });
            })
            ->when(filled($validated['module'] ?? null), fn ($q) => $q->where('module', $validated['module']))
            ->when(filled($validated['action'] ?? null), fn ($q) => $q->where('action', $validated['action']))
            ->when(filled($validated['actor_type'] ?? null), fn ($q) => $q->where('actor_type', $validated['actor_type']))
            ->when(filled($validated['from'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $validated['from']))
            ->when(filled($validated['to'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $validated['to']))
            ->with('actor:id,name,email')
            ->latest('created_at')->latest('id');

        // Prevent unbounded exports; a maximum 90-day window is mandatory.
        abort_unless(filled($validated['from'] ?? null) && filled($validated['to'] ?? null), 422, 'Select both From and To dates before exporting.');
        abort_if(Carbon::parse($validated['from'])->diffInDays(Carbon::parse($validated['to'])) > 90, 422, 'Audit exports are limited to 90 days.');

        $filename = 'madina-scale-audit-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Timestamp', 'Actor', 'Actor Type', 'Action', 'Module', 'Description', 'Subject Type', 'Subject ID', 'IP Address', 'Request ID', 'Integrity']);
            $query->chunk(500, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    $cells = [$log->id, $log->created_at?->toISOString(), $log->actor?->name ?? ($log->actor_type === 'system' ? 'System' : 'Unknown'), $log->actor_type, $log->action, $log->module, $log->description, $log->subject_type, $log->subject_id, $log->ip_address, $log->request_id, $log->hasValidIntegrityHash() ? 'valid' : 'invalid'];
                    // Neutralize spreadsheet formula injection in all textual cells.
                    $cells = array_map(static function ($value) {
                        if (! is_string($value)) {
                            return $value;
                        }

                        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
                    }, $cells);
                    fputcsv($out, $cells);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Return integrity status for a single immutable audit entry. */
    public function verify(Request $request, ActivityLog $activityLog): JsonResponse
    {
        abort_unless($request->user() && $this->authorization->can($request->user(), 'system.activity_logs.view'), 403);

        return response()->json(['data' => [
            'id' => $activityLog->id,
            'integrity' => $activityLog->hasValidIntegrityHash() ? 'valid' : 'invalid_or_legacy',
        ]]);
    }
}
