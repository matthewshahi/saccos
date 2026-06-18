<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        $logs = $query
            ->orderByDesc('route_audit_log_id')
            ->paginate(50)
            ->withQueryString();

        $eventTypes = DB::table('sacco_route_audit_logs')
            ->select('route_audit_log_event_type')
            ->whereNotNull('route_audit_log_event_type')
            ->distinct()
            ->orderBy('route_audit_log_event_type')
            ->pluck('route_audit_log_event_type');

        $outcomes = DB::table('sacco_route_audit_logs')
            ->select('route_audit_log_outcome')
            ->whereNotNull('route_audit_log_outcome')
            ->distinct()
            ->orderBy('route_audit_log_outcome')
            ->pluck('route_audit_log_outcome');

        return view('admin.route_audit_logs.index', compact(
            'logs',
            'eventTypes',
            'outcomes'
        ));
    }

    public function data(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        $limit = (int) $request->input('limit', 100);

        if ($limit < 1) {
            $limit = 100;
        }

        if ($limit > 500) {
            $limit = 500;
        }

        $logs = $query
            ->orderByDesc('route_audit_log_id')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }

    public function show($id)
    {
        $log = DB::table('sacco_route_audit_logs')
            ->where('route_audit_log_id', $id)
            ->first();

        abort_if(!$log, 404);

        return view('admin.route_audit_logs.show', compact('log'));
    }

    private function baseQuery()
    {
        return DB::table('sacco_route_audit_logs')
            ->select([
                'route_audit_log_id',
                'route_audit_log_request_id',
                'route_audit_log_event_type',
                'route_audit_log_outcome',
                'route_audit_log_user_id',
                'route_audit_log_user_name',
                'route_audit_log_user_email',
                'route_audit_log_attempted_login',
                'route_audit_log_route_name',
                'route_audit_log_controller_action',
                'route_audit_log_method',
                'route_audit_log_path',
                'route_audit_log_url',
                'route_audit_log_referer',
                'route_audit_log_status_code',
                'route_audit_log_ip_address',
                'route_audit_log_user_agent',
                'route_audit_log_payload',
                'route_audit_log_files',
                'route_audit_log_exception_class',
                'route_audit_log_exception_message',
                'route_audit_log_duration_ms',
                'route_audit_log_created_at',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('event_type')) {
            $query->where('route_audit_log_event_type', $request->event_type);
        }

        if ($request->filled('outcome')) {
            $query->where('route_audit_log_outcome', $request->outcome);
        }

        if ($request->filled('method')) {
            $query->where('route_audit_log_method', $request->method);
        }

        if ($request->filled('user_id')) {
            $query->where('route_audit_log_user_id', $request->user_id);
        }

        if ($request->filled('ip_address')) {
            $query->where('route_audit_log_ip_address', $request->ip_address);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('route_audit_log_user_name', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_user_email', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_attempted_login', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_route_name', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_controller_action', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_path', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_url', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_ip_address', 'LIKE', "%{$search}%")
                    ->orWhere('route_audit_log_exception_message', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('route_audit_log_created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('route_audit_log_created_at', '<=', $request->date_to);
        }
    }
}