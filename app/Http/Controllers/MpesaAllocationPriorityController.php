<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MpesaAllocationPriorityController extends Controller
{
    private const TYPE_CORE = 'CORE';
    private const TYPE_LOAN = 'LOAN_TYPE';
    private const TYPE_FOSA = 'FOSA_TYPE';
    private const TYPE_SPECIAL_SAVING = 'SPECIAL_SAVING_PRODUCT';

    public function index()
    {
        $this->ensureRequiredDefaults();

        $this->logConfigurationWarnings();

        $this->syncPriorityItems();

        $this->normaliseVisibleOrder();

        $priorities = $this->buildVisiblePriorities();

        $readiness = $this->buildReadiness();

        return view(
            'mpesa.allocation_priorities.index',
            compact('priorities', 'readiness')
        );
    }

    public function sync(Request $request)
    {
        $this->ensureRequiredDefaults();

        $added = $this->syncPriorityItems();

        $this->normaliseVisibleOrder();

        $this->logConfigurationWarnings();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $added > 0
                    ? $added . ' new product(s) were added to the bottom of the allocation list.'
                    : 'The allocation product list is already up to date.',
                'priorities' => $this->buildAjaxPriorities(),
                'count' => $this->buildVisiblePriorities()->count(),
            ]);
        }

        if ($added > 0) {
            return redirect()
                ->route('mpesa.allocation.priorities.index')
                ->with(
                    'success',
                    $added . ' new product(s) were added to the bottom of the allocation list.'
                );
        }

        return redirect()
            ->route('mpesa.allocation.priorities.index')
            ->with(
                'success',
                'The allocation product list is already up to date.'
            );
    }

    public function move(Request $request, int $priority)
    {
        $validated = $request->validate([
            'direction' => [
                'required',
                'in:up,down',
            ],
        ]);

        $this->syncPriorityItems();

        $this->normaliseVisibleOrder();

        $catalogue = $this->buildCatalogue();

        $moved = false;

        DB::transaction(function () use (
            $priority,
            $validated,
            $catalogue,
            &$moved
        ) {
            $rows = DB::table('sacco_mpesa_allocation_priorities')
                ->orderBy('priority_order')
                ->orderBy('priority_id')
                ->lockForUpdate()
                ->get()
                ->filter(function ($row) use ($catalogue) {
                    return isset($catalogue[$row->priority_key]);
                })
                ->values();

            $currentIndex = $rows->search(function ($row) use ($priority) {
                return (int) $row->priority_id === (int) $priority;
            });

            if ($currentIndex === false) {
                abort(404, 'Allocation priority item was not found.');
            }

            $targetIndex = $validated['direction'] === 'up'
                ? $currentIndex - 1
                : $currentIndex + 1;

            if ($targetIndex < 0 || $targetIndex >= $rows->count()) {
                return;
            }

            $current = $rows[$currentIndex];
            $target = $rows[$targetIndex];

            $currentOrder = (int) $current->priority_order;
            $targetOrder = (int) $target->priority_order;

            DB::table('sacco_mpesa_allocation_priorities')
                ->where('priority_id', $current->priority_id)
                ->update([
                    'priority_order' => $targetOrder,
                    'priority_updated_by' => auth()->id(),
                    'priority_updated_ip' => request()->ip(),
                    'updated_at' => now(),
                ]);

            DB::table('sacco_mpesa_allocation_priorities')
                ->where('priority_id', $target->priority_id)
                ->update([
                    'priority_order' => $currentOrder,
                    'priority_updated_by' => auth()->id(),
                    'priority_updated_ip' => request()->ip(),
                    'updated_at' => now(),
                ]);

            $moved = true;
        });

        $this->normaliseVisibleOrder();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'moved' => $moved,
                'message' => $moved
                    ? 'Allocation priority updated.'
                    : 'Item is already at the limit.',
                'priorities' => $this->buildAjaxPriorities(),
                'count' => $this->buildVisiblePriorities()->count(),
            ]);
        }

        return redirect()
            ->route('mpesa.allocation.priorities.index')
            ->with(
                'success',
                $moved
                    ? 'Allocation priority updated.'
                    : 'Item is already at the limit.'
            );
    }

    private function buildVisiblePriorities()
    {
        $catalogue = $this->buildCatalogue();

        $priorityRows = DB::table('sacco_mpesa_allocation_priorities')
            ->orderBy('priority_order')
            ->orderBy('priority_id')
            ->get();

        return $priorityRows
            ->filter(function ($row) use ($catalogue) {
                return isset($catalogue[$row->priority_key]);
            })
            ->values()
            ->map(function ($row) use ($catalogue) {
                $source = $catalogue[$row->priority_key];

                $row->display_name = $source['label'];
                $row->display_code = $source['code'];
                $row->display_type = $source['type_label'];
                $row->source_active = $source['source_active'];
                $row->source_status = $source['source_status'];
                $row->details = $source['details'];

                return $row;
            });
    }

    private function buildAjaxPriorities(): array
    {
        $priorities = $this->buildVisiblePriorities();
        $total = $priorities->count();

        return $priorities
            ->values()
            ->map(function ($priority, $index) use ($total) {
                return [
                    'priority_id' => (int) $priority->priority_id,
                    'priority_order' => (int) $priority->priority_order,
                    'priority_key' => $priority->priority_key,
                    'priority_type' => $priority->priority_type,
                    'priority_source_id' => $priority->priority_source_id,
                    'display_name' => $priority->display_name,
                    'display_code' => $priority->display_code,
                    'display_type' => $priority->display_type,
                    'details' => $priority->details,
                    'source_active' => (bool) $priority->source_active,
                    'source_status' => $priority->source_status,
                    'move_url' => route(
                        'mpesa.allocation.priorities.move',
                        $priority->priority_id
                    ),
                    'is_first' => $index === 0,
                    'is_last' => $index === ($total - 1),
                ];
            })
            ->toArray();
    }

    private function buildCatalogue(): array
    {
        $catalogue = [];

        $catalogue['CORE:REGISTRATION_FEE'] = [
            'priority_type' => self::TYPE_CORE,
            'source_table' => null,
            'source_id' => null,
            'label' => 'Registration Fee',
            'code' => 'RF',
            'type_label' => 'Core',
            'source_active' => true,
            'source_status' => 'Available',
            'details' => 'Membership / registration fee.',
        ];

        $catalogue['CORE:CAPITAL'] = [
            'priority_type' => self::TYPE_CORE,
            'source_table' => null,
            'source_id' => null,
            'label' => 'Capital Shares',
            'code' => 'CA',
            'type_label' => 'Core',
            'source_active' => true,
            'source_status' => 'Available',
            'details' => 'Member share capital.',
        ];

        $catalogue['CORE:SHARES'] = [
            'priority_type' => self::TYPE_CORE,
            'source_table' => null,
            'source_id' => null,
            'label' => 'Savings / Deposits',
            'code' => 'SH',
            'type_label' => 'Core',
            'source_active' => true,
            'source_status' => 'Available',
            'details' => 'Ordinary member savings / deposits.',
        ];

        if (Schema::hasTable('sacco_loan_types')) {
            $loanTypes = DB::table('sacco_loan_types')
                ->orderBy('loan_type_name')
                ->get();

            foreach ($loanTypes as $loanType) {
                if (
                    property_exists($loanType, 'loan_type_deleted')
                    &&
                    strtoupper(trim((string) $loanType->loan_type_deleted)) === 'Y'
                ) {
                    continue;
                }

                $loanTypeId = (int) $loanType->loan_type_id;

                $active = true;

                if (property_exists($loanType, 'loan_type_active')) {
                    $active = $this->valueIsActive($loanType->loan_type_active);
                }

                $loanCode = 'LN';

                if (
                    property_exists($loanType, 'loan_type_code')
                    &&
                    trim((string) $loanType->loan_type_code) !== ''
                ) {
                    $loanCode = trim((string) $loanType->loan_type_code);
                }

                $catalogue['LOAN_TYPE:' . $loanTypeId] = [
                    'priority_type' => self::TYPE_LOAN,
                    'source_table' => 'sacco_loan_types',
                    'source_id' => $loanTypeId,
                    'label' => $loanType->loan_type_name ?: 'Loan Type #' . $loanTypeId,
                    'code' => $loanCode,
                    'type_label' => 'Loan',
                    'source_active' => $active,
                    'source_status' => $active ? 'Active' : 'Inactive',
                    'details' => 'Loan Type ID: ' . $loanTypeId,
                ];
            }
        }

        if (Schema::hasTable('sacco_fosa_types')) {
            $fosaTypes = DB::table('sacco_fosa_types')
                ->orderBy('type_name')
                ->get();

            foreach ($fosaTypes as $fosaType) {
                $fosaTypeId = (int) $fosaType->type_id;

                $active = $this->valueIsActive($fosaType->type_active ?? 'Y');

                $details = [];

                if (
                    property_exists($fosaType, 'expected_amount')
                    &&
                    $fosaType->expected_amount !== null
                    &&
                    (float) $fosaType->expected_amount > 0
                ) {
                    $details[] = 'Expected: KES ' . number_format(
                        (float) $fosaType->expected_amount,
                        2
                    );
                }

                if (
                    property_exists($fosaType, 'expected_period')
                    &&
                    trim((string) $fosaType->expected_period) !== ''
                ) {
                    $details[] = ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            trim((string) $fosaType->expected_period)
                        )
                    );
                }

                $catalogue['FOSA_TYPE:' . $fosaTypeId] = [
                    'priority_type' => self::TYPE_FOSA,
                    'source_table' => 'sacco_fosa_types',
                    'source_id' => $fosaTypeId,
                    'label' => $fosaType->type_name ?: 'FOSA Type #' . $fosaTypeId,
                    'code' => $fosaType->type_prefix ?? null,
                    'type_label' => 'FOSA',
                    'source_active' => $active,
                    'source_status' => $active ? 'Active' : 'Inactive',
                    'details' => count($details) > 0
                        ? implode(' · ', $details)
                        : 'FOSA contribution type.',
                ];
            }
        }

        if (Schema::hasTable('sacco_special_saving_products')) {
            $specialProducts = DB::table('sacco_special_saving_products')
                ->orderBy('special_saving_product_name')
                ->get();

            foreach ($specialProducts as $product) {
                if (
                    property_exists($product, 'special_saving_product_deleted')
                    &&
                    strtoupper(trim((string) $product->special_saving_product_deleted)) === 'Y'
                ) {
                    continue;
                }

                $productId = (int) $product->special_saving_product_id;

                $productActive = true;

                if (property_exists($product, 'special_saving_product_status')) {
                    $productActive =
                        strtoupper(trim((string) $product->special_saving_product_status))
                        === 'ACTIVE';
                }

                $mpesaAllowed = true;

                if (property_exists($product, 'special_saving_product_allow_mpesa_collection')) {
                    $mpesaAllowed =
                        strtoupper(trim((string) $product->special_saving_product_allow_mpesa_collection))
                        === 'Y';
                }

                $active = $productActive && $mpesaAllowed;

                $details = [];

                if (
                    property_exists($product, 'special_saving_product_minimum_monthly_contribution')
                    &&
                    (float) $product->special_saving_product_minimum_monthly_contribution > 0
                ) {
                    $details[] = 'Monthly target: KES ' . number_format(
                        (float) $product->special_saving_product_minimum_monthly_contribution,
                        2
                    );
                }

                if (!$mpesaAllowed) {
                    $details[] = 'M-PESA collection disabled';
                }

                $catalogue['SPECIAL_SAVING_PRODUCT:' . $productId] = [
                    'priority_type' => self::TYPE_SPECIAL_SAVING,
                    'source_table' => 'sacco_special_saving_products',
                    'source_id' => $productId,
                    'label' => $product->special_saving_product_name
                        ?: 'Special Savings #' . $productId,
                    'code' => $product->special_saving_product_code ?? null,
                    'type_label' => 'Special Savings',
                    'source_active' => $active,
                    'source_status' => $active
                        ? 'Active'
                        : (!$productActive ? 'Inactive' : 'M-PESA disabled'),
                    'details' => count($details) > 0
                        ? implode(' · ', $details)
                        : 'Special Savings product.',
                ];
            }
        }

        return $catalogue;
    }

    private function syncPriorityItems(): int
    {
        $catalogue = $this->buildCatalogue();

        $maxOrder = (int) (
            DB::table('sacco_mpesa_allocation_priorities')
                ->max('priority_order')
            ?? 0
        );

        $added = 0;

        foreach ($catalogue as $key => $item) {
            $existing = DB::table('sacco_mpesa_allocation_priorities')
                ->where('priority_key', $key)
                ->first();

            if ($existing) {
                DB::table('sacco_mpesa_allocation_priorities')
                    ->where('priority_id', $existing->priority_id)
                    ->update([
                        'priority_type' => $item['priority_type'],
                        'priority_source_table' => $item['source_table'],
                        'priority_source_id' => $item['source_id'],
                        'priority_label' => $item['label'],
                        'priority_code' => $item['code'],
                        'priority_last_seen_at' => now(),
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $maxOrder++;

            DB::table('sacco_mpesa_allocation_priorities')
                ->insert([
                    'priority_key' => $key,
                    'priority_type' => $item['priority_type'],
                    'priority_source_table' => $item['source_table'],
                    'priority_source_id' => $item['source_id'],
                    'priority_label' => $item['label'],
                    'priority_code' => $item['code'],
                    'priority_order' => $maxOrder,
                    'priority_active' => 'Y',
                    'priority_options' => null,
                    'priority_notes' => null,
                    'priority_discovered_at' => now(),
                    'priority_last_seen_at' => now(),
                    'priority_created_by' => auth()->id(),
                    'priority_created_ip' => request()->ip(),
                    'priority_updated_by' => auth()->id(),
                    'priority_updated_ip' => request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $added++;
        }

        return $added;
    }

    private function normaliseVisibleOrder(): void
    {
        $catalogue = $this->buildCatalogue();

        $rows = DB::table('sacco_mpesa_allocation_priorities')
            ->orderBy('priority_order')
            ->orderBy('priority_id')
            ->get()
            ->filter(function ($row) use ($catalogue) {
                return isset($catalogue[$row->priority_key]);
            })
            ->values();

        foreach ($rows as $index => $row) {
            $expectedOrder = $index + 1;

            if ((int) $row->priority_order === $expectedOrder) {
                continue;
            }

            DB::table('sacco_mpesa_allocation_priorities')
                ->where('priority_id', $row->priority_id)
                ->update([
                    'priority_order' => $expectedOrder,
                    'updated_at' => now(),
                ]);
        }
    }

    private function ensureRequiredDefaults(): void
    {
        $this->ensureDefault('min_capital_contribution', '1000');

        $this->ensureDefault('member_ship_fee', '1000');
    }

    private function ensureDefault(string $name, string $value): void
    {
        if (! Schema::hasTable('sacco_defaults')) {
            Log::error(
                'M-PESA allocation configuration cannot create required default because sacco_defaults does not exist.',
                [
                    'default_name' => $name,
                ]
            );

            return;
        }

        $exists = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        $payload = [
            'default_name' => $name,
            'default_value' => $value,
        ];

        if (Schema::hasColumn('sacco_defaults', 'default_transdate')) {
            $payload['default_transdate'] = now();
        }

        if (Schema::hasColumn('sacco_defaults', 'default_userid')) {
            $payload['default_userid'] = auth()->id() ?? 999;
        }

        if (Schema::hasColumn('sacco_defaults', 'default_ip')) {
            $payload['default_ip'] = request()->ip() ?? '127.0.0.1';
        }

        DB::table('sacco_defaults')->insert($payload);

        Log::warning(
            'M-PESA Smart Allocation automatically created a missing SACCO default.',
            [
                'default_name' => $name,
                'default_value' => $value,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]
        );
    }

    private function buildReadiness(): array
    {
        $mpesaAccount = $this->getDefault('default_mpesa_in_account');
        $capitalAccount = $this->getDefault('default_share_capital_account');
        $registrationFeeAccount = $this->getDefault('default_member_ship_fee_account');
        $sharesAccount = $this->getDefault('default_share_account');
        $fosaAccount = $this->getDefault('default_fosa_account');

        return [
            'mpesa' => [
                'ledger_account' => $mpesaAccount,
                'ledger_account_name' => $this->getSubAccountName($mpesaAccount),
                'ledger_ready' => $this->defaultHasValue('default_mpesa_in_account'),
            ],

            'capital' => [
                'required_amount' => (float) (
                    $this->getDefault('min_capital_contribution') ?? 1000
                ),
                'ledger_account' => $capitalAccount,
                'ledger_account_name' => $this->getSubAccountName($capitalAccount),
                'ledger_ready' => $this->defaultHasValue('default_share_capital_account'),
            ],

            'registration_fee' => [
                'required_amount' => (float) (
                    $this->getDefault('member_ship_fee') ?? 1000
                ),
                'ledger_account' => $registrationFeeAccount,
                'ledger_account_name' => $this->getSubAccountName($registrationFeeAccount),
                'ledger_ready' => $this->defaultHasValue('default_member_ship_fee_account'),
            ],

            'shares' => [
                'ledger_account' => $sharesAccount,
                'ledger_account_name' => $this->getSubAccountName($sharesAccount),
                'ledger_ready' => $this->defaultHasValue('default_share_account'),
            ],

            'fosa' => [
                'ledger_account' => $fosaAccount,
                'ledger_account_name' => $this->getSubAccountName($fosaAccount),
                'ledger_ready' => $this->defaultHasValue('default_fosa_account'),
            ],
        ];
    }

    private function getSubAccountName($accountId): ?string
    {
        if (
            empty($accountId)
            ||
            ! Schema::hasTable('sacco_sub_accounts')
        ) {
            return null;
        }

        $idColumns = [
            'sub_account_id',
            'account_id',
            'id',
        ];

        $availableIdColumns = [];

        foreach ($idColumns as $column) {
            if (Schema::hasColumn('sacco_sub_accounts', $column)) {
                $availableIdColumns[] = $column;
            }
        }

        if (count($availableIdColumns) === 0) {
            return null;
        }

        $account = DB::table('sacco_sub_accounts')
            ->where(function ($query) use ($availableIdColumns, $accountId) {
                foreach ($availableIdColumns as $column) {
                    $query->orWhere($column, $accountId);
                }
            })
            ->first();

        if (! $account) {
            return null;
        }

        $nameColumns = [
            'sub_account_name',
            'account_name',
            'sub_account',
            'name',
        ];

        foreach ($nameColumns as $column) {
            if (
                property_exists($account, $column)
                &&
                trim((string) $account->{$column}) !== ''
            ) {
                return trim((string) $account->{$column});
            }
        }

        return null;
    }

    private function logConfigurationWarnings(): void
    {
        $importantDefaults = [
            'default_mpesa_in_account' => 'M-PESA incoming ledger',
            'default_share_account' => 'Savings / deposits ledger',
            'default_share_capital_account' => 'Capital ledger',
            'default_member_ship_fee_account' => 'Registration / membership fee ledger',
            'default_fosa_account' => 'FOSA ledger',
        ];

        foreach ($importantDefaults as $name => $description) {
            if ($this->defaultHasValue($name)) {
                continue;
            }

            Log::error(
                'M-PESA Smart Allocation accounting configuration is incomplete.',
                [
                    'missing_default' => $name,
                    'description' => $description,
                    'action' => 'Future smart allocation must skip this destination until configuration is corrected.',
                ]
            );
        }
    }

    private function getDefault(string $name)
    {
        if (! Schema::hasTable('sacco_defaults')) {
            return null;
        }

        return DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');
    }

    private function defaultHasValue(string $name): bool
    {
        $value = $this->getDefault($name);

        return $value !== null && trim((string) $value) !== '';
    }

    private function valueIsActive($value): bool
    {
        if ($value === null) {
            return true;
        }

        $value = strtoupper(trim((string) $value));

        return in_array(
            $value,
            [
                'Y',
                'YES',
                '1',
                'TRUE',
                'ACTIVE',
            ],
            true
        );
    }
}