<?php

namespace App\Http\Controllers;

use App\Services\BulkSms\BulkSmsConfigService;
use App\Services\BulkSms\BulkSmsDispatchService;
use App\Services\BulkSms\BulkSmsOutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkSmsController extends Controller
{
    public function __construct(
        protected BulkSmsConfigService $config,
        protected BulkSmsOutboxService $outbox,
        protected BulkSmsDispatchService $dispatcher
    ) {
    }

    public function index()
    {
        $readiness = $this->config->readiness();

        $stats = [
            'total' => DB::table('sacco_bulk_sms_messages')->count(),
            'queued' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'queued')->count(),
            'sent' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'sent')->count(),
            'delivered' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'delivered')->count(),
            'failed' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'failed')->count(),
            'skipped' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'skipped')->count(),
            'demo' => DB::table('sacco_bulk_sms_messages')->where('sms_status', 'demo')->count(),
        ];

        $recentMessages = DB::table('sacco_bulk_sms_messages')
            ->orderByDesc('sms_id')
            ->limit(10)
            ->get();

        return view('bulk_sms.index', compact('readiness', 'stats', 'recentMessages'));
    }

    public function settings()
    {
        $defaultNames = [
            'BULK_SMS_ENABLED',
            'BULK_SMS_PROVIDER',
            'BULK_SMS_DEMO_MODE',
            'BULK_SMS_DEFAULT_NETWORK',
            'BULK_SMS_DEFAULT_SENDER_ID',
            'BULK_SMS_FAIL_CLOSED',
            'BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS',
            'BULK_SMS_MAX_RETRY_ATTEMPTS',
            'BULK_SMS_RETRY_DELAY_SECONDS',
            'BULK_SMS_PHONE_FORMAT',
        ];

        $defaults = DB::table('sacco_defaults')
            ->whereIn('default_name', $defaultNames)
            ->orderBy('default_name')
            ->get()
            ->keyBy('default_name');

        $providers = DB::table('sacco_bulk_sms_providers')
            ->orderBy('provider_name')
            ->get();

        $networks = DB::table('sacco_bulk_sms_provider_networks')
            ->where('provider_code', $this->config->providerCode())
            ->where('network_enabled', 'Y')
            ->orderBy('network_sort_order')
            ->get();

        $readiness = $this->config->readiness();

        return view('bulk_sms.settings', compact('defaults', 'providers', 'networks', 'readiness'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'BULK_SMS_ENABLED' => ['required', 'in:Y,N'],
            'BULK_SMS_PROVIDER' => ['required', 'string', 'max:60'],
            'BULK_SMS_DEMO_MODE' => ['required', 'in:Y,N'],
            'BULK_SMS_DEFAULT_NETWORK' => ['required', 'string', 'max:30'],
            'BULK_SMS_DEFAULT_SENDER_ID' => ['nullable', 'string', 'max:60'],
            'BULK_SMS_FAIL_CLOSED' => ['required', 'in:Y,N'],
            'BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS' => ['required', 'in:Y,N'],
            'BULK_SMS_MAX_RETRY_ATTEMPTS' => ['required', 'integer', 'min:0', 'max:10'],
            'BULK_SMS_RETRY_DELAY_SECONDS' => ['required', 'integer', 'min:0', 'max:3600'],
            'BULK_SMS_PHONE_FORMAT' => ['required', 'string', 'max:20'],
        ]);

        $providerExists = DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', strtolower(trim($validated['BULK_SMS_PROVIDER'])))
            ->exists();

        if (!$providerExists) {
            return back()
                ->withInput()
                ->with('error', 'Selected Bulk SMS provider does not exist.');
        }

        foreach ($validated as $name => $value) {
            $this->upsertDefault($name, $value);
        }

        return redirect()
            ->route('bulk_sms.settings')
            ->with('success', 'Bulk SMS settings updated successfully.');
    }

    public function providers()
    {
        $providers = DB::table('sacco_bulk_sms_providers')
            ->orderBy('provider_name')
            ->get();

        return view('bulk_sms.providers.index', compact('providers'));
    }

    public function createProvider()
    {
        return view('bulk_sms.providers.create');
    }

    public function storeProvider(Request $request)
    {
        $validated = $request->validate([
            'provider_code' => ['required', 'string', 'max:60'],
            'provider_name' => ['required', 'string', 'max:150'],
            'provider_type' => ['required', 'string', 'max:60'],
            'provider_driver' => ['required', 'string', 'max:120'],
            'provider_base_url' => ['nullable', 'url', 'max:255'],
            'provider_token_url' => ['nullable', 'url', 'max:255'],
            'provider_send_url' => ['nullable', 'url', 'max:255'],
            'provider_balance_url' => ['nullable', 'url', 'max:255'],
            'provider_delivery_status_url' => ['nullable', 'url', 'max:255'],
            'provider_default_network' => ['required', 'string', 'max:30'],
            'provider_requires_network' => ['required', 'in:Y,N'],
            'provider_enabled' => ['required', 'in:Y,N'],
            'provider_notes' => ['nullable', 'string'],
        ]);

        $validated['provider_code'] = strtolower(Str::slug($validated['provider_code'], '_'));
        $validated['provider_driver'] = strtolower(Str::slug($validated['provider_driver'], '_'));
        $validated['provider_default_network'] = strtolower(trim($validated['provider_default_network']));

        $exists = DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', $validated['provider_code'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Provider code already exists.');
        }

        $validated['provider_created_at'] = now();
        $validated['provider_updated_at'] = now();

        DB::table('sacco_bulk_sms_providers')->insert($validated);

        return redirect()
            ->route('bulk_sms.providers')
            ->with('success', 'Bulk SMS provider added successfully.');
    }

    public function editProvider(string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        return view('bulk_sms.providers.edit', compact('providerRow'));
    }

    public function updateProvider(Request $request, string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:150'],
            'provider_type' => ['required', 'string', 'max:60'],
            'provider_driver' => ['required', 'string', 'max:120'],
            'provider_base_url' => ['nullable', 'url', 'max:255'],
            'provider_token_url' => ['nullable', 'url', 'max:255'],
            'provider_send_url' => ['nullable', 'url', 'max:255'],
            'provider_balance_url' => ['nullable', 'url', 'max:255'],
            'provider_delivery_status_url' => ['nullable', 'url', 'max:255'],
            'provider_default_network' => ['required', 'string', 'max:30'],
            'provider_requires_network' => ['required', 'in:Y,N'],
            'provider_enabled' => ['required', 'in:Y,N'],
            'provider_notes' => ['nullable', 'string'],
        ]);

        $validated['provider_driver'] = strtolower(Str::slug($validated['provider_driver'], '_'));
        $validated['provider_default_network'] = strtolower(trim($validated['provider_default_network']));
        $validated['provider_updated_at'] = now();

        DB::table('sacco_bulk_sms_providers')
            ->where('provider_id', $providerRow->provider_id)
            ->update($validated);

        return redirect()
            ->route('bulk_sms.providers')
            ->with('success', 'Bulk SMS provider updated successfully.');
    }

    public function toggleProvider(string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        $newStatus = strtoupper((string) $providerRow->provider_enabled) === 'Y' ? 'N' : 'Y';

        DB::table('sacco_bulk_sms_providers')
            ->where('provider_id', $providerRow->provider_id)
            ->update([
                'provider_enabled' => $newStatus,
                'provider_updated_at' => now(),
            ]);

        return back()->with('success', 'Provider status updated successfully.');
    }

    public function providerConfigs(string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        $configs = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $providerRow->provider_code)
            ->orderBy('config_key')
            ->get();

        return view('bulk_sms.providers.configs', compact('providerRow', 'configs'));
    }

    public function updateProviderConfig(Request $request, int $config)
    {
        $configRow = DB::table('sacco_bulk_sms_provider_configs')
            ->where('config_id', $config)
            ->first();

        abort_if(!$configRow, 404);

        $validated = $request->validate([
            'config_value' => ['nullable', 'string'],
            'config_env_key' => ['nullable', 'string', 'max:100'],
            'config_is_secret' => ['required', 'in:Y,N'],
            'config_is_required' => ['required', 'in:Y,N'],
            'config_description' => ['nullable', 'string', 'max:255'],
        ]);

        /*
         * For secret fields, blank value means "do not overwrite".
         */
        if (
            strtoupper((string) $configRow->config_is_secret) === 'Y'
            && ($validated['config_value'] === null || $validated['config_value'] === '')
        ) {
            unset($validated['config_value']);
        }

        $validated['config_env_key'] = $validated['config_env_key']
            ? strtoupper(trim($validated['config_env_key']))
            : null;

        $validated['config_updated_at'] = now();

        DB::table('sacco_bulk_sms_provider_configs')
            ->where('config_id', $configRow->config_id)
            ->update($validated);

        return back()->with('success', 'Provider configuration updated successfully.');
    }

    public function providerNetworks(string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        $networks = DB::table('sacco_bulk_sms_provider_networks')
            ->where('provider_code', $providerRow->provider_code)
            ->orderBy('network_sort_order')
            ->get();

        return view('bulk_sms.providers.networks', compact('providerRow', 'networks'));
    }

    public function storeProviderNetwork(Request $request, string $provider)
    {
        $providerRow = $this->findProviderOrFail($provider);

        $validated = $request->validate([
            'network_code' => ['required', 'string', 'max:30'],
            'network_name' => ['required', 'string', 'max:100'],
            'provider_network_value' => ['nullable', 'string', 'max:60'],
            'network_is_default' => ['required', 'in:Y,N'],
            'network_enabled' => ['required', 'in:Y,N'],
            'network_sort_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $validated['provider_code'] = $providerRow->provider_code;
        $validated['network_code'] = strtolower(Str::slug($validated['network_code'], '_'));
        $validated['network_created_at'] = now();
        $validated['network_updated_at'] = now();

        $exists = DB::table('sacco_bulk_sms_provider_networks')
            ->where('provider_code', $providerRow->provider_code)
            ->where('network_code', $validated['network_code'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Network code already exists for this provider.');
        }

        if ($validated['network_is_default'] === 'Y') {
            DB::table('sacco_bulk_sms_provider_networks')
                ->where('provider_code', $providerRow->provider_code)
                ->update([
                    'network_is_default' => 'N',
                    'network_updated_at' => now(),
                ]);
        }

        DB::table('sacco_bulk_sms_provider_networks')->insert($validated);

        return back()->with('success', 'Provider network added successfully.');
    }

    public function updateProviderNetwork(Request $request, int $network)
    {
        $networkRow = DB::table('sacco_bulk_sms_provider_networks')
            ->where('network_id', $network)
            ->first();

        abort_if(!$networkRow, 404);

        $validated = $request->validate([
            'network_name' => ['required', 'string', 'max:100'],
            'provider_network_value' => ['nullable', 'string', 'max:60'],
            'network_is_default' => ['required', 'in:Y,N'],
            'network_enabled' => ['required', 'in:Y,N'],
            'network_sort_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        if ($validated['network_is_default'] === 'Y') {
            DB::table('sacco_bulk_sms_provider_networks')
                ->where('provider_code', $networkRow->provider_code)
                ->where('network_id', '<>', $networkRow->network_id)
                ->update([
                    'network_is_default' => 'N',
                    'network_updated_at' => now(),
                ]);
        }

        $validated['network_updated_at'] = now();

        DB::table('sacco_bulk_sms_provider_networks')
            ->where('network_id', $networkRow->network_id)
            ->update($validated);

        return back()->with('success', 'Provider network updated successfully.');
    }

    public function toggleProviderNetwork(int $network)
    {
        $networkRow = DB::table('sacco_bulk_sms_provider_networks')
            ->where('network_id', $network)
            ->first();

        abort_if(!$networkRow, 404);

        $newStatus = strtoupper((string) $networkRow->network_enabled) === 'Y' ? 'N' : 'Y';

        DB::table('sacco_bulk_sms_provider_networks')
            ->where('network_id', $networkRow->network_id)
            ->update([
                'network_enabled' => $newStatus,
                'network_updated_at' => now(),
            ]);

        return back()->with('success', 'Network status updated successfully.');
    }

    public function messages(Request $request)
    {
        $query = DB::table('sacco_bulk_sms_messages');

        if ($request->filled('status')) {
            $query->where('sms_status', $request->status);
        }

        if ($request->filled('mode')) {
            $query->where('sms_mode', $request->mode);
        }

        if ($request->filled('provider')) {
            $query->where('provider_code', $request->provider);
        }

        if ($request->filled('phone')) {
            $query->where(function ($q) use ($request) {
                $q->where('recipient_phone_raw', 'like', '%' . $request->phone . '%')
                    ->orWhere('recipient_phone_normalized', 'like', '%' . $request->phone . '%');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', '%' . $search . '%')
                    ->orWhere('sms_subject', 'like', '%' . $search . '%')
                    ->orWhere('sms_message', 'like', '%' . $search . '%')
                    ->orWhere('request_reference', 'like', '%' . $search . '%')
                    ->orWhere('provider_message_id', 'like', '%' . $search . '%');
            });
        }

        $messages = $query
            ->orderByDesc('sms_id')
            ->paginate(25)
            ->withQueryString();

        $providers = DB::table('sacco_bulk_sms_providers')
            ->orderBy('provider_name')
            ->get();

        return view('bulk_sms.messages.index', compact('messages', 'providers'));
    }

    public function showMessage(int $sms)
    {
        $message = DB::table('sacco_bulk_sms_messages')
            ->where('sms_id', $sms)
            ->first();

        abort_if(!$message, 404);

        $callbacks = DB::table('sacco_bulk_sms_callbacks')
            ->where('sms_id', $sms)
            ->orderByDesc('callback_id')
            ->get();

        return view('bulk_sms.messages.show', compact('message', 'callbacks'));
    }

    public function dispatchMessage(int $sms)
    {
        $result = $this->dispatcher->dispatchOne($sms);

        $type = ($result['sent'] ?? false) ? 'success' : 'info';

        if (($result['success'] ?? false) === false) {
            $type = 'error';
        }

        return back()->with($type, $result['error_message'] ?? $result['message'] ?? 'Dispatch processed.');
    }

    public function dispatchQueued(Request $request)
    {
        $limit = (int) $request->input('limit', 20);
        $result = $this->dispatcher->dispatchQueued($limit);

        return back()->with('success', 'Queued SMS dispatch processed. Count: ' . $result['count']);
    }

    public function testForm()
    {
        $readiness = $this->config->readiness();

        return view('bulk_sms.test', compact('readiness'));
    }

    public function sendTest(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:1000'],
            'network' => ['nullable', 'string', 'max:30'],
            'sender_id' => ['nullable', 'string', 'max:60'],
        ]);

        $result = $this->outbox->queue([
            'recipient_name' => $validated['recipient_name'] ?? 'Manual Test',
            'phone' => $validated['phone'],
            'subject' => $validated['subject'] ?? 'Bulk SMS Test',
            'message' => $validated['message'],
            'network' => $validated['network'] ?? null,
            'sender_id' => $validated['sender_id'] ?? null,
            'meta' => [
                'source' => 'bulk_sms_manual_test',
                'created_from' => route('bulk_sms.test'),
            ],
        ]);

        return redirect()
            ->route('bulk_sms.messages.show', $result['sms_id'])
            ->with('success', 'Test SMS logged successfully. Status: ' . $result['status']);
    }

    public function diagnostics()
    {
        $readiness = $this->config->readiness();
        $provider = $this->config->provider();
        $configs = $this->config->providerConfigs();

        return view('bulk_sms.diagnostics', compact('readiness', 'provider', 'configs'));
    }

    public function diagnosticsJson()
    {
        return response()->json([
            'readiness' => $this->config->readiness(),
            'provider' => $this->config->provider(),
            'configs' => $this->config->providerConfigs(),
        ]);
    }

    public function reportSummary(Request $request)
    {
        $summaryByStatus = DB::table('sacco_bulk_sms_messages')
            ->select('sms_status', DB::raw('COUNT(*) as total'), DB::raw('SUM(sms_segments) as segments'))
            ->groupBy('sms_status')
            ->orderBy('sms_status')
            ->get();

        $summaryByProvider = DB::table('sacco_bulk_sms_messages')
            ->select('provider_code', DB::raw('COUNT(*) as total'), DB::raw('SUM(sms_segments) as segments'))
            ->groupBy('provider_code')
            ->orderBy('provider_code')
            ->get();

        $summaryByDate = DB::table('sacco_bulk_sms_messages')
            ->select(DB::raw('DATE(created_at) as sms_date'), DB::raw('COUNT(*) as total'), DB::raw('SUM(sms_segments) as segments'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('sms_date')
            ->limit(31)
            ->get();

        return view('bulk_sms.reports.summary', compact(
            'summaryByStatus',
            'summaryByProvider',
            'summaryByDate'
        ));
    }

    public function exportMessages(Request $request): StreamedResponse
{
    $filename = 'bulk_sms_messages_' . now()->format('Ymd_His') . '.csv';

    return response()->streamDownload(function () {
        $handle = fopen('php://output', 'w');

        /*
         * UTF-8 BOM helps Excel open the CSV cleanly.
         */
        fwrite($handle, "\xEF\xBB\xBF");

        /*
         * Excel wrongly converts phone numbers and dates.
         * Prefixing with a tab keeps selected values as text.
         * Also protects against CSV formula injection for user-provided text.
         */
        $csvSafe = function ($value, bool $forceText = false): string {
            if ($value === null || $value === '') {
                return '';
            }

            $value = (string) $value;

            $startsWithFormulaChar = preg_match('/^[=\-+@]/', ltrim($value)) === 1;

            if ($forceText || $startsWithFormulaChar) {
                return "\t" . $value;
            }

            return $value;
        };

        fputcsv($handle, [
            'SMS ID',
            'Provider',
            'Mode',
            'Status',
            'Recipient Name',
            'Raw Phone',
            'Normalized Phone',
            'Network',
            'Subject',
            'Segments',
            'Request Reference',
            'Provider Message ID',
            'Error Code',
            'Error Message',
            'Created At',
            'Sent At',
            'Delivered At',
        ]);

        DB::table('sacco_bulk_sms_messages')
            ->orderByDesc('sms_id')
            ->chunk(500, function ($rows) use ($handle, $csvSafe) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->sms_id,
                        $csvSafe($row->provider_code),
                        $csvSafe($row->sms_mode),
                        $csvSafe($row->sms_status),
                        $csvSafe($row->recipient_name),
                        $csvSafe($row->recipient_phone_raw, true),
                        $csvSafe($row->recipient_phone_normalized, true),
                        $csvSafe($row->recipient_network),
                        $csvSafe($row->sms_subject),
                        $row->sms_segments,
                        $csvSafe($row->request_reference, true),
                        $csvSafe($row->provider_message_id, true),
                        $csvSafe($row->error_code),
                        $csvSafe($row->error_message),
                        $csvSafe($row->created_at, true),
                        $csvSafe($row->sent_at, true),
                        $csvSafe($row->delivered_at, true),
                    ]);
                }
            });

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Cache-Control' => 'no-store, no-cache',
    ]);
}

    protected function upsertDefault(string $name, mixed $value): void
    {
        $exists = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->exists();

        if ($exists) {
            DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->update([
                    'default_value' => $value,
                    'default_transdate' => now(),
                    'default_userid' => auth()->id(),
                    'default_ip' => request()?->ip(),
                ]);

            return;
        }

        DB::table('sacco_defaults')->insert([
            'default_name' => $name,
            'default_value' => $value,
            'default_transdate' => now(),
            'default_userid' => auth()->id(),
            'default_ip' => request()?->ip(),
        ]);
    }

    protected function findProviderOrFail(string $provider): object
    {
        $providerCode = strtolower(trim($provider));

        $providerRow = DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', $providerCode)
            ->first();

        abort_if(!$providerRow, 404);

        return $providerRow;
    }

    public function adtelTokenTest()
{
    try {
        $provider = app(\App\Services\BulkSms\Providers\AdtelBulkSmsProvider::class);

        $result = $provider->getAccessToken();

        if (($result['success'] ?? false) === true) {
            return redirect()
                ->route('bulk_sms.diagnostics')
                ->with('success', 'ADTEL token test successful. Token received. Expires in: ' . ($result['expires_in'] ?? 'unknown') . ' seconds.');
        }

        return redirect()
            ->route('bulk_sms.diagnostics')
            ->with('error', 'ADTEL token test failed: ' . ($result['error_message'] ?? 'Unknown error.'));
    } catch (\Throwable $e) {
        return redirect()
            ->route('bulk_sms.diagnostics')
            ->with('error', 'ADTEL token test exception: ' . $e->getMessage());
    }
}


}