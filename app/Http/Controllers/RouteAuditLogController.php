<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteAuditLogController extends Controller
{
    private string $table = 'sacco_route_audit_logs';

    public function index()
    {
        return view('admin.route_audit_logs.index', [
            'eventTypes' => DB::table($this->table)
                ->whereNotNull('route_audit_log_event_type')
                ->where('route_audit_log_event_type', '!=', '')
                ->distinct()
                ->orderBy('route_audit_log_event_type')
                ->pluck('route_audit_log_event_type'),

            'outcomes' => DB::table($this->table)
                ->whereNotNull('route_audit_log_outcome')
                ->where('route_audit_log_outcome', '!=', '')
                ->distinct()
                ->orderBy('route_audit_log_outcome')
                ->pluck('route_audit_log_outcome'),

            'methods' => DB::table($this->table)
                ->whereNotNull('route_audit_log_method')
                ->where('route_audit_log_method', '!=', '')
                ->distinct()
                ->orderBy('route_audit_log_method')
                ->pluck('route_audit_log_method'),
        ]);
    }

    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 50);

        // Hard cap: never return more than 50 rows.
        if ($length < 1 || $length > 50) {
            $length = 50;
        }

        $baseQuery = DB::table($this->table);

        $recordsTotal = (clone $baseQuery)->count();

        $query = DB::table($this->table);

        if ($request->filled('event_type')) {
            $query->where('route_audit_log_event_type', $request->event_type);
        }

        if ($request->filled('outcome')) {
            $query->where('route_audit_log_outcome', $request->outcome);
        }

        if ($request->filled('method')) {
            $query->where('route_audit_log_method', $request->method);
        }

        if ($request->filled('status_code')) {
            $query->where('route_audit_log_status_code', (int) $request->status_code);
        }

        if ($request->filled('ip_address')) {
            $query->where('route_audit_log_ip_address', 'like', '%' . trim($request->ip_address) . '%');
        }

        if ($request->filled('user_search')) {
            $userSearch = trim($request->user_search);

            $query->where(function ($q) use ($userSearch) {
                $q->where('route_audit_log_user_name', 'like', '%' . $userSearch . '%')
                    ->orWhere('route_audit_log_user_email', 'like', '%' . $userSearch . '%')
                    ->orWhere('route_audit_log_attempted_login', 'like', '%' . $userSearch . '%')
                    ->orWhere('route_audit_log_user_id', 'like', '%' . $userSearch . '%');
            });
        }

        if ($request->filled('route_search')) {
            $routeSearch = trim($request->route_search);

            $query->where(function ($q) use ($routeSearch) {
                $q->where('route_audit_log_route_name', 'like', '%' . $routeSearch . '%')
                    ->orWhere('route_audit_log_path', 'like', '%' . $routeSearch . '%')
                    ->orWhere('route_audit_log_url', 'like', '%' . $routeSearch . '%')
                    ->orWhere('route_audit_log_controller_action', 'like', '%' . $routeSearch . '%');
            });
        }

        if ($request->filled('request_id')) {
            $query->where('route_audit_log_request_id', 'like', '%' . trim($request->request_id) . '%');
        }

        if ($request->filled('date_from')) {
            try {
                $query->where(
                    'route_audit_log_created_at',
                    '>=',
                    Carbon::parse($request->date_from)->startOfDay()
                );
            } catch (\Throwable $e) {
                // Ignore invalid date filter.
            }
        }

        if ($request->filled('date_to')) {
            try {
                $query->where(
                    'route_audit_log_created_at',
                    '<=',
                    Carbon::parse($request->date_to)->endOfDay()
                );
            } catch (\Throwable $e) {
                // Ignore invalid date filter.
            }
        }

        $globalSearch = trim((string) $request->input('search.value', ''));

        if ($globalSearch !== '') {
            $query->where(function ($q) use ($globalSearch) {
                $q->where('route_audit_log_request_id', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_event_type', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_outcome', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_user_name', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_user_email', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_attempted_login', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_route_name', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_controller_action', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_method', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_path', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_url', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_referer', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_status_code', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_ip_address', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_user_agent', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_exception_class', 'like', '%' . $globalSearch . '%')
                    ->orWhere('route_audit_log_exception_message', 'like', '%' . $globalSearch . '%');
            });
        }

        $recordsFiltered = (clone $query)->count();

        $orderableColumns = [
            0 => 'route_audit_log_id',
            1 => 'route_audit_log_created_at',
            2 => 'route_audit_log_event_type',
            3 => 'route_audit_log_outcome',
            4 => 'route_audit_log_user_name',
            5 => 'route_audit_log_method',
            6 => 'route_audit_log_route_name',
            7 => 'route_audit_log_status_code',
            8 => 'route_audit_log_ip_address',
            9 => 'route_audit_log_duration_ms',
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 1);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderColumn = $orderableColumns[$orderColumnIndex] ?? 'route_audit_log_created_at';

        $logs = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $logs->map(function ($log) {
            $details = [
                'Log ID' => $log->route_audit_log_id,
                'Request ID' => $log->route_audit_log_request_id,
                'Event Type' => $log->route_audit_log_event_type,
                'Outcome' => $log->route_audit_log_outcome,
                'User ID' => $log->route_audit_log_user_id,
                'User Name' => $log->route_audit_log_user_name,
                'User Email' => $log->route_audit_log_user_email,
                'Attempted Login' => $log->route_audit_log_attempted_login,
                'Route Name' => $log->route_audit_log_route_name,
                'Controller Action' => $log->route_audit_log_controller_action,
                'Method' => $log->route_audit_log_method,
                'Path' => $log->route_audit_log_path,
                'URL' => $log->route_audit_log_url,
                'Referer' => $log->route_audit_log_referer,
                'Status Code' => $log->route_audit_log_status_code,
                'IP Address' => $log->route_audit_log_ip_address,
                'User Agent' => $log->route_audit_log_user_agent,
                'Payload' => $this->safeJsonForDisplay($log->route_audit_log_payload),
                'Files' => $this->safeJsonForDisplay($log->route_audit_log_files),
                'Exception Class' => $log->route_audit_log_exception_class,
                'Exception Message' => $log->route_audit_log_exception_message,
                'Duration MS' => $log->route_audit_log_duration_ms,
                'Created At' => $this->formatDate($log->route_audit_log_created_at),
            ];

            $encodedDetails = base64_encode(json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return [
                'route_audit_log_id' => e($log->route_audit_log_id),
                'route_audit_log_created_at' => e($this->formatDate($log->route_audit_log_created_at)),
                'route_audit_log_event_type' => $this->eventBadge($log->route_audit_log_event_type),
                'route_audit_log_outcome' => $this->outcomeBadge($log->route_audit_log_outcome),
                'user_display' => e($this->userDisplay($log)),
                'route_audit_log_method' => $this->methodBadge($log->route_audit_log_method),
                'route_display' => e($this->shorten($log->route_audit_log_route_name ?: $log->route_audit_log_path, 45)),
                'route_audit_log_status_code' => $this->statusCodeBadge($log->route_audit_log_status_code),
                'route_audit_log_ip_address' => e($log->route_audit_log_ip_address ?: '-'),
                'route_audit_log_duration_ms' => e($log->route_audit_log_duration_ms !== null ? number_format($log->route_audit_log_duration_ms) . ' ms' : '-'),
                'action' => '
                    <button type="button"
                        class="btn btn-sm btn-outline-primary view-log-details"
                        data-details="' . e($encodedDetails) . '">
                        View
                    </button>
                ',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function userDisplay(object $log): string
    {
        if (!empty($log->route_audit_log_user_name)) {
            return $log->route_audit_log_user_name;
        }

        if (!empty($log->route_audit_log_user_email)) {
            return $log->route_audit_log_user_email;
        }

        if (!empty($log->route_audit_log_attempted_login)) {
            return 'Attempt: ' . $log->route_audit_log_attempted_login;
        }

        if (!empty($log->route_audit_log_user_id)) {
            return 'User #' . $log->route_audit_log_user_id;
        }

        return '-';
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function shorten($value, int $limit = 60): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '-';
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit) . '...'
            : $value;
    }

    private function eventBadge($value): string
    {
        $label = e($value ?: '-');

        return '<span class="badge bg-info">' . $label . '</span>';
    }

    private function outcomeBadge($value): string
    {
        $raw = strtoupper(trim((string) $value));

        if ($raw === '') {
            return '<span class="badge bg-secondary">-</span>';
        }

        $class = match (true) {
            in_array($raw, ['SUCCESS', 'LOGIN_SUCCESS'], true) => 'bg-success',
            in_array($raw, ['FAILED', 'ERROR', 'LOGIN_FAILED'], true) => 'bg-danger',
            in_array($raw, ['FORBIDDEN', 'UNAUTHENTICATED', 'LOCKED', 'LOGIN_LOCKOUT'], true) => 'bg-dark',
            in_array($raw, ['REDIRECT', 'NOT_FOUND'], true) => 'bg-warning',
            default => 'bg-secondary',
        };

        return '<span class="badge ' . $class . '">' . e($raw) . '</span>';
    }

    private function methodBadge($value): string
    {
        $method = strtoupper(trim((string) $value));

        if ($method === '') {
            return '<span class="badge bg-secondary">-</span>';
        }

        $class = match ($method) {
            'GET' => 'bg-success',
            'POST' => 'bg-primary',
            'PUT', 'PATCH' => 'bg-warning',
            'DELETE' => 'bg-danger',
            default => 'bg-secondary',
        };

        return '<span class="badge ' . $class . '">' . e($method) . '</span>';
    }

    private function statusCodeBadge($value): string
    {
        $code = (int) $value;

        if (!$code) {
            return '<span class="badge bg-secondary">-</span>';
        }

        $class = match (true) {
            $code >= 200 && $code < 300 => 'bg-success',
            $code >= 300 && $code < 400 => 'bg-warning',
            $code >= 400 && $code < 500 => 'bg-danger',
            $code >= 500 => 'bg-dark',
            default => 'bg-secondary',
        };

        return '<span class="badge ' . $class . '">' . e($code) . '</span>';
    }

    private function safeJsonForDisplay($value): string
    {
        if (!$value) {
            return '-';
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return (string) $value;
        }

        $masked = $this->maskSensitiveValues($decoded);

        return json_encode(
            $masked,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function maskSensitiveValues($value)
    {
        if (is_array($value)) {
            $masked = [];

            foreach ($value as $key => $item) {
                $lowerKey = strtolower((string) $key);

                if (
                    str_contains($lowerKey, 'password') ||
                    str_contains($lowerKey, 'token') ||
                    str_contains($lowerKey, 'otp') ||
                    str_contains($lowerKey, 'secret') ||
                    str_contains($lowerKey, 'api_key') ||
                    str_contains($lowerKey, 'apikey') ||
                    str_contains($lowerKey, 'authorization')
                ) {
                    $masked[$key] = '[MASKED]';
                } else {
                    $masked[$key] = $this->maskSensitiveValues($item);
                }
            }

            return $masked;
        }

        return $value;
    }
}