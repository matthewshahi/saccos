<?php

namespace App\Http\Controllers;

use App\Services\BulkSms\BulkSmsConfigService;
use App\Services\BulkSms\BulkSmsDispatchService;
use App\Services\BulkSms\BulkSmsOutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkSmsController extends Controller
{
    public function __construct(
        protected BulkSmsConfigService $config,
        protected BulkSmsOutboxService $outbox,
        protected BulkSmsDispatchService $dispatcher
    ) {
        /*
         * Ensure all Bulk SMS defaults, providers, provider configurations
         * and network records exist before any Bulk SMS controller action runs.
         *
         * Existing values are never overwritten.
         */
        $this->bootstrapBulkSmsModule();
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



    /**
     * Show the Bulk SMS composer.
     *
     * Counts include only non-deleted members with valid Kenyan mobile numbers.
     *
     * Members:
     * All active members, including officials.
     *
     * Officials:
     * Active members whose member_position is 2.
     *
     * Inactive members:
     * All inactive, non-deleted members.
     */
    public function compose()
    {
        $membersResult = $this->resolveBulkSmsRecipients('members');
        $officialsResult = $this->resolveBulkSmsRecipients('officials');
        $inactiveMembersResult = $this->resolveBulkSmsRecipients('inactive_members');

        $activeMembersCount = $membersResult['recipients']->count();
        $activeOfficialsCount = $officialsResult['recipients']->count();
        $inactiveMembersCount = $inactiveMembersResult['recipients']->count();

        $invalidMembersPhoneCount = $membersResult['invalid_phone_count'];
        $invalidOfficialsPhoneCount = $officialsResult['invalid_phone_count'];
        $invalidInactiveMembersPhoneCount = $inactiveMembersResult['invalid_phone_count'];

        $duplicateMembersPhoneCount = $membersResult['duplicate_phone_count'];
        $duplicateOfficialsPhoneCount = $officialsResult['duplicate_phone_count'];
        $duplicateInactiveMembersPhoneCount = $inactiveMembersResult['duplicate_phone_count'];

        return view('bulk_sms.compose', compact(
            'activeMembersCount',
            'activeOfficialsCount',
            'inactiveMembersCount',
            'invalidMembersPhoneCount',
            'invalidOfficialsPhoneCount',
            'invalidInactiveMembersPhoneCount',
            'duplicateMembersPhoneCount',
            'duplicateOfficialsPhoneCount',
            'duplicateInactiveMembersPhoneCount'
        ));
    }

    /**
     * Load personalised messages into the Bulk SMS outbox.
     *
     * Only recipients with valid Kenyan mobile numbers are added.
     * Invalid, blank, textual and duplicate phone numbers are skipped.
     *
     * This method does not send SMS messages.
     */
    public function queueBulkMessages(Request $request)
    {
        $validated = $request->validate([
            'audience' => [
                'required',
                'in:members,officials,inactive_members',
            ],

            'message' => [
                'required',
                'string',
                'max:1000',
            ],
        ], [
            'audience.required' => 'Please select the recipients.',
            'audience.in' => 'The selected recipient group is invalid.',
            'message.required' => 'Please enter the SMS message.',
            'message.max' => 'The SMS message may not exceed 1,000 characters.',
        ]);

        $audience = $validated['audience'];
        $genericMessage = trim($validated['message']);

        /*
         * Retrieve the selected recipients and remove:
         *
         * - Missing numbers
         * - Blank numbers
         * - Text such as N/A, NONE or UNKNOWN
         * - Invalid Kenyan mobile numbers
         * - Duplicate mobile numbers
         */
        $recipientResult = $this->resolveBulkSmsRecipients($audience);
        $recipients = $recipientResult['recipients'];

        if ($recipients->isEmpty()) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'No recipients with valid mobile numbers were found for the selected group.'
                );
        }

        /*
         * One reference identifies the entire batch.
         */
        $batchReference = 'BULK-'
            . now()->format('YmdHis')
            . '-'
            . strtoupper(Str::random(8));

        $subject = match ($audience) {
            'officials' => 'Bulk SMS to Officials',
            'inactive_members' => 'Bulk SMS to Inactive Members',
            default => 'Bulk SMS to Active Members',
        };

        $summary = [
            'selected' => $recipients->count(),
            'queued' => 0,
            'failed' => 0,
            'demo' => 0,
            'skipped' => 0,
            'other' => 0,
            'invalid_phone' => $recipientResult['invalid_phone_count'],
            'duplicate_phone' => $recipientResult['duplicate_phone_count'],
        ];

        /*
         * All valid messages are inserted as one atomic batch.
         *
         * No SMS provider request is made here.
         */
        DB::transaction(function () use (
            $recipients,
            $genericMessage,
            $audience,
            $subject,
            $batchReference,
            &$summary
        ) {
            foreach ($recipients as $member) {
                $personalisedMessage = $this->buildBulkSmsMessage(
                    $member->member_name,
                    $genericMessage
                );

                $result = $this->outbox->queue([
                    'member_id' => $member->member_id,
                    'recipient_name' => $member->member_name,
                    'phone' => $member->member_phone_no,
                    'subject' => $subject,
                    'message' => $personalisedMessage,
                    'request_reference' => $batchReference . '-' . $member->member_id,
                    'meta' => [
                        'source' => 'bulk_sms_composer',
                        'batch_reference' => $batchReference,
                        'audience' => $audience,
                        'member_id' => $member->member_id,
                        'member_position' => $member->member_position,
                        'member_active' => $member->member_active,
                        'validated_phone' => $member->normalized_phone,
                    ],
                ]);

                $status = strtolower(
                    trim((string) ($result['status'] ?? 'other'))
                );

                if (array_key_exists($status, $summary)) {
                    $summary[$status]++;
                } else {
                    $summary['other']++;
                }
            }
        });

        $message = sprintf(
            'Bulk SMS batch %s loaded to the outbox. Valid recipients: %d, queued: %d, invalid phone numbers skipped: %d, duplicate phone numbers skipped: %d.',
            $batchReference,
            $summary['selected'],
            $summary['queued'],
            $summary['invalid_phone'],
            $summary['duplicate_phone']
        );

        return redirect()
            ->route('bulk_sms.messages', [
                'search' => $batchReference,
            ])
            ->with('success', $message);
    }

    /**
     * Retrieve the selected audience and retain only recipients with valid
     * and unique Kenyan mobile numbers.
     *
     * A valid mobile number is normalised to one of these formats:
     *
     * 2547XXXXXXXX
     * 2541XXXXXXXX
     */
    protected function resolveBulkSmsRecipients(string $audience): array
    {
        $query = $this->bulkSmsMembersQuery();

        switch ($audience) {
            case 'officials':
                $query
                    ->where('member_active', 'Y')
                    ->where('member_position', 2);
                break;

            case 'inactive_members':
                $query->whereIn('member_active', ['N', 'n', '0']);
                break;

            case 'members':
            default:
                $query->where('member_active', 'Y');
                break;
        }

        $members = $query
            ->orderBy('member_id')
            ->get();

        $validRecipients = collect();
        $invalidPhoneCount = 0;
        $duplicatePhoneCount = 0;
        $seenPhoneNumbers = [];

        foreach ($members as $member) {
            $rawPhone = trim((string) $member->member_phone_no);

            if ($rawPhone === '') {
                $invalidPhoneCount++;
                continue;
            }

            $normalizedPhone = $this->outbox
                ->normalizeKenyanPhone($rawPhone);

            if ($normalizedPhone === null) {
                $invalidPhoneCount++;
                continue;
            }

            if (isset($seenPhoneNumbers[$normalizedPhone])) {
                $duplicatePhoneCount++;
                continue;
            }

            $seenPhoneNumbers[$normalizedPhone] = true;
            $member->normalized_phone = $normalizedPhone;
            $validRecipients->push($member);
        }

        return [
            'recipients' => $validRecipients,
            'invalid_phone_count' => $invalidPhoneCount,
            'duplicate_phone_count' => $duplicatePhoneCount,
        ];
    }

    /**
     * Return all non-deleted members. Audience-specific activity and position
     * filters are applied in resolveBulkSmsRecipients().
     */
    protected function bulkSmsMembersQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('sacco_members')
            ->select([
                'member_id',
                'member_name',
                'member_phone_no',
                'member_position',
                'member_active',
            ])
            ->where(function ($query) {
                $query->whereNull('member_deleted')
                    ->orWhere('member_deleted', 'N')
                    ->orWhere('member_deleted', 0)
                    ->orWhere('member_deleted', '0');
            });
    }

/**
 * Create the personalised SMS message.
 *
 * Example:
 *
 * Hello Samson,
 *
 * Your generic message appears here.
 */
protected function buildBulkSmsMessage(
    ?string $memberName,
    string $genericMessage
): string {
    $firstName = $this->memberFirstName(
        $memberName
    );

    return "Hello {$firstName},\n\n"
        . trim($genericMessage);
}

/**
 * Extract and format the member's first name.
 *
 * SAMSON KIPNGETICH TUM becomes Samson.
 * DAVID OYUGI MAIKO becomes David.
 */
protected function memberFirstName(
    ?string $memberName
): string {
    $cleanName = preg_replace(
        '/\s+/u',
        ' ',
        trim((string) $memberName)
    );

    if (!$cleanName) {
        return 'Member';
    }

    $parts = explode(' ', $cleanName);

    $firstName = trim(
        (string) ($parts[0] ?? '')
    );

    if ($firstName === '') {
        return 'Member';
    }

    return Str::title(
        Str::lower($firstName)
    );
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

    /**
     * Create all missing Bulk SMS setup records.
     *
     * This runs before every Bulk SMS controller action. It is idempotent:
     * existing defaults, providers, configuration values and networks are
     * preserved exactly as they are.
     */
    protected function bootstrapBulkSmsModule(): void
    {
        if (Schema::hasTable('sacco_defaults')) {
            $this->ensureBulkSmsDefaults();
        }

        if (Schema::hasTable('sacco_bulk_sms_providers')) {
            $this->ensureBulkSmsProviders();
        }

        if (Schema::hasTable('sacco_bulk_sms_provider_configs')) {
            $this->ensureBulkSmsProviderConfigs();
        }

        if (Schema::hasTable('sacco_bulk_sms_provider_networks')) {
            $this->ensureBulkSmsProviderNetworks();
        }
    }

    /**
     * Ensure all global Bulk SMS defaults exist.
     *
     * New installations default safely to Advanta, disabled and in demo mode.
     * Existing SACCO installations retain their current provider and settings.
     */
    protected function ensureBulkSmsDefaults(): void
    {
        $defaults = [
            'BULK_SMS_ENABLED' => 'N',
            'BULK_SMS_PROVIDER' => 'advanta',
            'BULK_SMS_DEMO_MODE' => 'Y',
            'BULK_SMS_DEFAULT_NETWORK' => 'safaricom',
            'BULK_SMS_DEFAULT_SENDER_ID' => '',
            'BULK_SMS_FAIL_CLOSED' => 'Y',
            'BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS' => 'N',
            'BULK_SMS_MAX_RETRY_ATTEMPTS' => '3',
            'BULK_SMS_RETRY_DELAY_SECONDS' => '60',
            'BULK_SMS_PHONE_FORMAT' => '254',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('sacco_defaults')->insert([
                'default_name' => $name,
                'default_value' => $value,
                'default_transdate' => now(),
                'default_userid' => auth()->id(),
                'default_ip' => request()?->ip(),
            ]);
        }
    }

    /**
     * Ensure the supported provider records exist.
     */
    protected function ensureBulkSmsProviders(): void
    {
        $providers = [
            [
                'provider_code' => 'advanta',
                'provider_name' => 'Advanta SMS',
                'provider_type' => 'http_api',
                'provider_driver' => 'advanta',
                'provider_base_url' => 'https://quicksms.advantasms.com',
                'provider_token_url' => null,
                'provider_send_url' => 'https://quicksms.advantasms.com/api/services/sendsms',
                'provider_balance_url' => 'https://quicksms.advantasms.com/api/services/getbalance',
                'provider_delivery_status_url' => 'https://quicksms.advantasms.com/api/services/getdlr',
                'provider_default_network' => 'safaricom',
                'provider_requires_network' => 'N',
                'provider_enabled' => 'Y',
                'provider_notes' => 'Advanta SMS provider. Credentials are read from provider configs or environment variables.',
            ],
            [
                'provider_code' => 'adtel',
                'provider_name' => 'ADTEL Bulk SMS',
                'provider_type' => 'oauth_api',
                'provider_driver' => 'adtel',
                'provider_base_url' => 'https://api.adtel.co.ke',
                'provider_token_url' => 'https://api.adtel.co.ke/oauth/token',
                'provider_send_url' => 'https://api.adtel.co.ke/api/v2/sendsms',
                'provider_balance_url' => null,
                'provider_delivery_status_url' => null,
                'provider_default_network' => 'safaricom',
                'provider_requires_network' => 'Y',
                'provider_enabled' => 'Y',
                'provider_notes' => 'ADTEL Bulk SMS provider. Credentials are read from provider configs or environment variables.',
            ],
        ];

        foreach ($providers as $provider) {
            $exists = DB::table('sacco_bulk_sms_providers')
                ->where('provider_code', $provider['provider_code'])
                ->exists();

            if ($exists) {
                continue;
            }

            $provider['provider_created_at'] = now();
            $provider['provider_updated_at'] = now();

            DB::table('sacco_bulk_sms_providers')->insert($provider);
        }
    }

    /**
     * Ensure configuration rows exist for every supported provider.
     *
     * Credential values are intentionally blank. The environment key is
     * registered so each SACCO can keep its own secrets in its own .env file.
     */
    protected function ensureBulkSmsProviderConfigs(): void
    {
        $configs = [
            /*
             |--------------------------------------------------------------------------
             | Advanta
             |--------------------------------------------------------------------------
             */
            [
                'provider_code' => 'advanta',
                'config_key' => 'SEND_URL',
                'config_value' => 'https://quicksms.advantasms.com/api/services/sendsms',
                'config_env_key' => 'ADVANTA_SEND_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'Advanta Send SMS endpoint.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'API_KEY',
                'config_value' => null,
                'config_env_key' => 'ADVANTA_API_KEY',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Advanta API key.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'PARTNER_ID',
                'config_value' => null,
                'config_env_key' => 'ADVANTA_PARTNER_ID',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Advanta Partner ID.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'SHORTCODE',
                'config_value' => null,
                'config_env_key' => 'ADVANTA_SHORTCODE',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'Advanta approved shortcode or sender ID.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'BALANCE_URL',
                'config_value' => 'https://quicksms.advantasms.com/api/services/getbalance',
                'config_env_key' => 'ADVANTA_BALANCE_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'Advanta account balance endpoint.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'DLR_URL',
                'config_value' => 'https://quicksms.advantasms.com/api/services/getdlr',
                'config_env_key' => 'ADVANTA_DLR_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'Advanta delivery report endpoint.',
            ],
            [
                'provider_code' => 'advanta',
                'config_key' => 'CALLBACK_URL',
                'config_value' => null,
                'config_env_key' => 'ADVANTA_CALLBACK_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'Public callback URL for Advanta delivery reports.',
            ],

            /*
             |--------------------------------------------------------------------------
             | ADTEL
             |--------------------------------------------------------------------------
             */
            [
                'provider_code' => 'adtel',
                'config_key' => 'AUTH_URL',
                'config_value' => 'https://api.adtel.co.ke/oauth/token',
                'config_env_key' => 'ADTEL_AUTH_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL OAuth token endpoint.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'SEND_URL',
                'config_value' => 'https://api.adtel.co.ke/api/v2/sendsms',
                'config_env_key' => 'ADTEL_SEND_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL Send SMS endpoint.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'USERNAME',
                'config_value' => null,
                'config_env_key' => 'ADTEL_USERNAME',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL account username.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'PASSWORD',
                'config_value' => null,
                'config_env_key' => 'ADTEL_PASSWORD',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL account password.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'SENDER_ID',
                'config_value' => null,
                'config_env_key' => 'ADTEL_SENDER_ID',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL approved sender ID.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'ACTION_RESPONSE_URL',
                'config_value' => null,
                'config_env_key' => 'ADTEL_ACTION_RESPONSE_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'Public ADTEL action response callback URL.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'CLIENT_ID',
                'config_value' => null,
                'config_env_key' => 'ADTEL_CLIENT_ID',
                'config_is_secret' => 'Y',
                'config_is_required' => 'N',
                'config_description' => 'ADTEL OAuth client ID.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'CLIENT_SECRET',
                'config_value' => null,
                'config_env_key' => 'ADTEL_CLIENT_SECRET',
                'config_is_secret' => 'Y',
                'config_is_required' => 'N',
                'config_description' => 'ADTEL OAuth client secret.',
            ],
            [
                'provider_code' => 'adtel',
                'config_key' => 'BASIC_AUTH_TOKEN',
                'config_value' => null,
                'config_env_key' => 'ADTEL_BASIC_AUTH_TOKEN',
                'config_is_secret' => 'Y',
                'config_is_required' => 'N',
                'config_description' => 'Optional pre-encoded ADTEL Basic Authorization token.',
            ],
        ];

        foreach ($configs as $config) {
            $exists = DB::table('sacco_bulk_sms_provider_configs')
                ->where('provider_code', $config['provider_code'])
                ->where('config_key', $config['config_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            $config['config_created_at'] = now();
            $config['config_updated_at'] = now();

            DB::table('sacco_bulk_sms_provider_configs')->insert($config);
        }
    }

    /**
     * Ensure common Kenyan network rows exist for each provider.
     */
    protected function ensureBulkSmsProviderNetworks(): void
    {
        $providers = ['advanta', 'adtel'];

        $networks = [
            [
                'network_code' => 'safaricom',
                'network_name' => 'Safaricom',
                'provider_network_value' => null,
                'network_is_default' => 'Y',
                'network_enabled' => 'Y',
                'network_sort_order' => 1,
            ],
            [
                'network_code' => 'airtel',
                'network_name' => 'Airtel Kenya',
                'provider_network_value' => null,
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 2,
            ],
            [
                'network_code' => 'telkom',
                'network_name' => 'Telkom Kenya',
                'provider_network_value' => null,
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 3,
            ],
        ];

        foreach ($providers as $providerCode) {
            foreach ($networks as $network) {
                $exists = DB::table('sacco_bulk_sms_provider_networks')
                    ->where('provider_code', $providerCode)
                    ->where('network_code', $network['network_code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('sacco_bulk_sms_provider_networks')->insert([
                    'provider_code' => $providerCode,
                    'network_code' => $network['network_code'],
                    'network_name' => $network['network_name'],
                    'provider_network_value' => $network['provider_network_value'],
                    'network_is_default' => $network['network_is_default'],
                    'network_enabled' => $network['network_enabled'],
                    'network_sort_order' => $network['network_sort_order'],
                    'network_created_at' => now(),
                    'network_updated_at' => now(),
                ]);
            }
        }
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
/**
 * Show the Custom Bulk SMS form.
 *
 * The form must submit:
 *
 * phone_numbers - comma-separated phone numbers
 * subject       - optional internal subject
 * message       - the common SMS message
 */
public function customSend()
{
    $readiness = $this->config->readiness();

    return view(
        'bulk_sms.custom_send',
        compact('readiness')
    );
}

/**
 * Validate and queue a Custom Bulk SMS batch.
 *
 * Important rules:
 *
 * - Phone numbers must be separated by commas only.
 * - Spaces inside an individual phone number are allowed.
 * - Every phone number must be valid.
 * - Duplicate numbers are rejected.
 * - If any number is invalid, empty or duplicated, the entire batch is rejected.
 * - Every valid number creates its own independent outbox record.
 * - No provider request is made from the web request.
 * - The existing scheduled dispatch command sends the queued records.
 */
public function queueCustomMessages(Request $request)
{
    $validated = $request->validate([
        'phone_numbers' => [
            'required',
            'string',
            'max:50000',
        ],

        'subject' => [
            'nullable',
            'string',
            'max:180',
        ],

        'message' => [
            'required',
            'string',
            'max:1000',
        ],
    ], [
        'phone_numbers.required' =>
            'Please enter at least one phone number.',

        'phone_numbers.max' =>
            'The phone-number list is too large.',

        'subject.max' =>
            'The subject may not exceed 180 characters.',

        'message.required' =>
            'Please enter the SMS message.',

        'message.max' =>
            'The SMS message may not exceed 1,000 characters.',
    ]);

    /*
     * Do not create records that cannot be dispatched by the scheduled
     * outbox command.
     */
    if (!$this->config->isEnabled()) {
        return back()
            ->withInput()
            ->with(
                'error',
                'Bulk SMS is disabled. No messages were queued.'
            );
    }

    if ($this->config->isDemoMode()) {
        return back()
            ->withInput()
            ->with(
                'error',
                'Bulk SMS demo mode is enabled. Disable demo mode before queueing this batch.'
            );
    }

    $readiness = $this->config->readiness();

    if (!($readiness['ready_to_send'] ?? false)) {
        $issues = $readiness['issues']
            ?? ['Bulk SMS is not ready to send.'];

        return back()
            ->withInput()
            ->with(
                'error',
                implode(' ', $issues)
            );
    }

    $message = trim(
        (string) $validated['message']
    );

    if ($message === '') {
        return back()
            ->withInput()
            ->with(
                'error',
                'Please enter a valid SMS message.'
            );
    }

    /*
     * Validate the complete phone-number list before inserting anything.
     */
    $phoneResult = $this->parseCustomSmsPhoneNumbers(
        (string) $validated['phone_numbers']
    );

    if (!empty($phoneResult['errors'])) {
        return back()
            ->withInput()
            ->with(
                'error',
                'The entire batch was rejected. '
                    . implode(' ', $phoneResult['errors'])
            );
    }

    $recipients = $phoneResult['recipients'];

    if (empty($recipients)) {
        return back()
            ->withInput()
            ->with(
                'error',
                'No valid phone numbers were provided.'
            );
    }

    $subject = trim(
        (string) ($validated['subject'] ?? '')
    );

    if ($subject === '') {
        $subject = 'Custom Bulk SMS';
    }

    /*
     * One reference groups all independent SMS records in this submission.
     */
    $batchReference = 'CUSTOM-'
        . now()->format('YmdHis')
        . '-'
        . strtoupper(Str::random(8));

    $recipientCount = count($recipients);
    $queuedCount = 0;

    try {
        /*
         * Atomic queueing:
         *
         * If even one record fails to become queued, every insert from
         * this batch is rolled back.
         */
        DB::transaction(function () use (
            $recipients,
            $recipientCount,
            $subject,
            $message,
            $batchReference,
            $request,
            &$queuedCount
        ) {
            foreach ($recipients as $index => $recipient) {
                $sequence = $index + 1;

                $requestReference = $batchReference
                    . '-'
                    . str_pad(
                        (string) $sequence,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );

                $result = $this->outbox->queue([
                    /*
                     * Use the already-normalized number so the outbox receives
                     * a clean dispatch-ready Kenyan mobile number.
                     */
                    'phone' => $recipient['normalized'],

                    'recipient_name' => 'Custom Recipient',

                    'subject' => $subject,

                    'message' => $message,

                    'request_reference' => $requestReference,

                    'meta' => [
                        'source' => 'bulk_sms_custom_send',
                        'batch_reference' => $batchReference,
                        'batch_sequence' => $sequence,
                        'batch_recipient_count' => $recipientCount,
                        'original_phone' => $recipient['raw'],
                        'validated_phone' => $recipient['normalized'],
                        'queued_by_user_id' => auth()->id(),
                        'queued_from_ip' => $request->ip(),
                    ],
                ]);

                $status = strtolower(
                    trim(
                        (string) ($result['status'] ?? '')
                    )
                );

                /*
                 * The scheduled command selects only sms_status = queued.
                 *
                 * Therefore, anything other than queued causes the complete
                 * database transaction to roll back.
                 */
                if ($status !== 'queued') {
                    throw new \RuntimeException(
                        sprintf(
                            'SMS item %d could not be queued. Returned status: %s.',
                            $sequence,
                            $status !== '' ? $status : 'unknown'
                        )
                    );
                }

                $queuedCount++;
            }
        });
    } catch (\Throwable $exception) {
        report($exception);

        return back()
            ->withInput()
            ->with(
                'error',
                'The complete Custom SMS batch could not be queued. '
                    . 'No messages from this submission were saved.'
            );
    }

    return redirect()
        ->route('bulk_sms.messages', [
            'search' => $batchReference,
        ])
        ->with(
            'success',
            sprintf(
                'Custom SMS batch %s was queued successfully. '
                    . '%d independent SMS messages are ready for automatic dispatch.',
                $batchReference,
                $queuedCount
            )
        );
}

/**
 * Parse and validate comma-separated Custom SMS phone numbers.
 *
 * Only commas separate recipients.
 *
 * Valid examples:
 *
 * 0722400737,0712345678
 * 254 722 400737, +254 712 345678
 *
 * Invalid examples:
 *
 * 0722400737 0712345678
 * 0722400737;0712345678
 * 0722400737,
 *
 * The returned recipients are not inserted until every item is valid.
 */
protected function parseCustomSmsPhoneNumbers(
    string $phoneNumbers
): array {
    /*
     * Comma is intentionally the only recipient separator.
     */
    $entries = explode(
        ',',
        $phoneNumbers
    );

    /*
     * Protect the web request against accidentally pasted extremely
     * large lists. Adjust this number later if required.
     */
    if (count($entries) > 1000) {
        return [
            'recipients' => [],
            'errors' => [
                'A maximum of 1,000 phone numbers may be submitted in one batch.',
            ],
        ];
    }

    $recipients = [];
    $errors = [];
    $seenNumbers = [];

    foreach ($entries as $index => $entry) {
        $position = $index + 1;

        /*
         * trim() removes whitespace around the number but preserves
         * internal spaces such as 254 722 400737.
         */
        $rawPhone = trim(
            (string) $entry
        );

        /*
         * Reject trailing commas, consecutive commas and empty items.
         */
        if ($rawPhone === '') {
            $errors[] = sprintf(
                'Phone-number item %d is empty.',
                $position
            );

            continue;
        }

        $normalizedPhone = $this->outbox
            ->normalizeKenyanPhone($rawPhone);

        if ($normalizedPhone === null) {
            $errors[] = sprintf(
                'Phone-number item %d, "%s", has an invalid or unsupported format.',
                $position,
                $rawPhone
            );

            continue;
        }

        /*
         * Reject the entire submission when the same normalized number
         * appears more than once.
         *
         * For example:
         *
         * 0722400737
         * 254722400737
         *
         * are treated as the same recipient.
         */
        if (isset($seenNumbers[$normalizedPhone])) {
            $errors[] = sprintf(
                'Phone-number item %d duplicates item %d after normalization.',
                $position,
                $seenNumbers[$normalizedPhone]
            );

            continue;
        }

        $seenNumbers[$normalizedPhone] = $position;

        $recipients[] = [
            'raw' => $rawPhone,
            'normalized' => $normalizedPhone,
        ];
    }

    return [
        'recipients' => $recipients,
        'errors' => $errors,
    ];
}

}