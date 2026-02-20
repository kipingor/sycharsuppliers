<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Audit Log Controller
 * 
 * Provides interface for viewing and exporting audit logs.
 * Supports filtering, searching, and exporting audit trails.
 * 
 * @package App\Http\Controllers
 */
class AuditLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display audit logs
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Audit::class);

        $query = Audit::with(['user', 'auditable']);

        // Apply filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        if ($auditableType = $request->input('auditable_type')) {
            $query->where('auditable_type', $auditableType);
        }

        if ($auditableId = $request->input('auditable_id')) {
            $query->where('auditable_id', $auditableId);
        }

        if ($startDate = $request->input('start_date')) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->where('created_at', '<=', $endDate);
        }

        if ($tags = $request->input('tags')) {
            $query->where('tags', 'like', "%{$tags}%");
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $audits = $query->paginate(50);

        return Inertia::render('auditLogs/index', [
            'audits' => $audits,
            'filters' => $request->only([
                'search', 'user_id', 'event', 
                'auditable_type', 'auditable_id',
                'start_date', 'end_date', 'tags'
            ]),
            'events' => $this->getAvailableEvents(),
            'types' => $this->getAvailableTypes(),
        ]);
    }

    /**
     * Show specific audit log
     */
    public function show(Audit $audit): Response
    {
        $this->authorize('view', $audit);

        $audit->load(['user', 'auditable']);

        return Inertia::render('auditLogs/show', [
            'audit' => [
                'id' => $audit->id,
                'user' => $audit->user ? [
                    'id' => $audit->user->id,
                    'name' => $audit->user->name,
                    'email' => $audit->user->email,
                ] : null,
                'event' => $audit->event,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'url' => $audit->url,
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'tags' => $audit->tags,
                'created_at' => $audit->created_at->toDateTimeString(),
            ],
            'related_audits' => $this->getRelatedAudits($audit),
        ]);
    }

    /**
     * Get audit trail for specific entity
     */
    public function entityTrail(Request $request): Response
    {
        $this->authorize('viewAny', Audit::class);

        $request->validate([
            'auditable_type' => 'required|string',
            'auditable_id' => 'required|integer',
        ]);

        $auditableType = $request->input('auditable_type');
        $auditableId = $request->input('auditable_id');

        $trail = $this->auditService->getAuditTrail($auditableType, $auditableId);

        return Inertia::render('auditLogs/entity-trail', [
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'trail' => $trail,
        ]);
    }

    /**
     * Get user activity
     */
    public function userActivity(Request $request, User $user): Response
    {
        $this->authorize('viewAny', Audit::class);

        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $user->id;

        $activity = $this->auditService->getUserActivity($user, $startDate, $endDate);

        return Inertia::render('auditLogs/user-activity', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'activity' => $activity,
        ]);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request)
    {
        $this->authorize('export', Audit::class);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,json',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $format = $request->input('format');

        $filename = "audit_log_{$startDate}_to_{$endDate}.{$format}";

        if ($format === 'csv') {
            $csv = $this->auditService->exportAuditLog($startDate, $endDate);
            
            return response()->streamDownload(
                function () use ($csv) {
                    echo $csv;
                },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } else {
            // JSON export
            $audits = Audit::whereBetween('created_at', [$startDate, $endDate])
                ->with(['user', 'auditable'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $audits,
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total' => $audits->count(),
                    'exported_at' => now()->toDateTimeString(),
                ],
            ])->header('Content-Disposition', "attachment; filename={$filename}");
        }
    }

    /**
     * Get audit statistics
     */
    public function statistics(Request $request): Response
    {
        $this->authorize('viewAny', Audit::class);

        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $stats = $this->auditService->getAuditStatistics($startDate, $endDate);

        return Inertia::render('auditLogs/statistics', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get available event types
     */
    protected function getAvailableEvents(): array
    {
        return Audit::distinct()
            ->pluck('event')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get available auditable types
     */
    protected function getAvailableTypes(): array
    {
        return Audit::distinct()
            ->pluck('auditable_type')
            ->filter()
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type),
                ];
            })
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get related audits
     */
    protected function getRelatedAudits(Audit $audit, int $limit = 10): array
    {
        $related = Audit::where('auditable_type', $audit->auditable_type)
            ->where('auditable_id', $audit->auditable_id)
            ->where('id', '!=', $audit->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $related->map(function ($item) {
            return [
                'id' => $item->id,
                'event' => $item->event,
                'user' => $item->user?->name,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        })->toArray();
    }
}
