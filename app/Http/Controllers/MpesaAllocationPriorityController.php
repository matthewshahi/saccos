<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MpesaAllocationPriorityController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Product Families
    |--------------------------------------------------------------------------
    |
    | These are application-level identifiers.
    |
    | There are NO database foreign-key relationships attached to them.
    |
    */
    private const TYPE_CORE = 'CORE';

    private const TYPE_LOAN = 'LOAN_TYPE';

    private const TYPE_FOSA = 'FOSA_TYPE';

    private const TYPE_SPECIAL_SAVING = 'SPECIAL_SAVING_PRODUCT';


    /*
    |--------------------------------------------------------------------------
    | Main Priority Screen
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        /*
         * Ensure agreed amount defaults exist.
         *
         * min_capital_contribution:
         *     missing => create 1000
         *
         * member_ship_fee:
         *     missing => create 1000
         */
        $this->ensureRequiredDefaults();


        /*
         * Log missing critical accounting configuration.
         *
         * Stage 1 does NOT send email yet.
         * We will connect that to your existing official-email mechanism later.
         */
        $this->logConfigurationWarnings();


        /*
         * Automatically discover newly-created products.
         *
         * This does not disturb existing ordering.
         */
        $this->syncPriorityItems();


        /*
         * Keep current visible products ordered 1, 2, 3...
         */
        $this->normaliseVisibleOrder();


        /*
         * Build current product catalogue.
         */
        $catalogue = $this->buildCatalogue();


        /*
         * Fetch priority records.
         */
        $priorityRows = DB::table(
            'sacco_mpesa_allocation_priorities'
        )
            ->orderBy('priority_order')
            ->orderBy('priority_id')
            ->get();


        /*
         * Only show items that currently exist in the product catalogue.
         *
         * If a product disappears later, its priority row remains in the
         * database for history, but it does not interfere with the current
         * priority order.
         */
        $priorities = $priorityRows
            ->filter(function ($row) use ($catalogue) {
                return isset(
                    $catalogue[$row->priority_key]
                );
            })
            ->values()
            ->map(function ($row) use ($catalogue) {

                $source = $catalogue[
                    $row->priority_key
                ];

                $row->display_name =
                    $source['label'];

                $row->display_code =
                    $source['code'];

                $row->display_type =
                    $source['type_label'];

                $row->source_active =
                    $source['source_active'];

                $row->source_status =
                    $source['source_status'];

                $row->details =
                    $source['details'];

                return $row;
            });


        /*
         * Core accounting readiness.
         */
        $readiness = $this->buildReadiness();


        return view(
            'mpesa.allocation_priorities.index',
            compact(
                'priorities',
                'readiness'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Manual Product Refresh
    |--------------------------------------------------------------------------
    */
    public function sync()
    {
        $this->ensureRequiredDefaults();

        $added = $this->syncPriorityItems();

        $this->normaliseVisibleOrder();

        $this->logConfigurationWarnings();

        if ($added > 0) {

            return redirect()
                ->route(
                    'mpesa.allocation.priorities.index'
                )
                ->with(
                    'success',
                    $added
                    . ' new product(s) were added to the bottom of the allocation list.'
                );
        }

        return redirect()
            ->route(
                'mpesa.allocation.priorities.index'
            )
            ->with(
                'success',
                'The allocation product list is already up to date.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Move One Item Up / Down
    |--------------------------------------------------------------------------
    */
    public function move(
        Request $request,
        int $priority
    ) {
        $validated = $request->validate([
            'direction' => [
                'required',
                'in:up,down',
            ],
        ]);


        /*
         * Ensure new products don't interfere with ordering.
         */
        $this->syncPriorityItems();

        $this->normaliseVisibleOrder();


        $catalogue = $this->buildCatalogue();


        DB::transaction(function () use (
            $priority,
            $validated,
            $catalogue
        ) {

            /*
             * Lock current priority rows while swapping.
             */
            $rows = DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->orderBy('priority_order')
                ->orderBy('priority_id')
                ->lockForUpdate()
                ->get()
                ->filter(function ($row) use ($catalogue) {

                    return isset(
                        $catalogue[
                            $row->priority_key
                        ]
                    );
                })
                ->values();


            $currentIndex = $rows->search(
                function ($row) use ($priority) {

                    return
                        (int) $row->priority_id
                        ===
                        $priority;
                }
            );


            if ($currentIndex === false) {
                abort(
                    404,
                    'Allocation priority item was not found.'
                );
            }


            if (
                $validated['direction']
                ===
                'up'
            ) {

                $targetIndex =
                    $currentIndex - 1;

            } else {

                $targetIndex =
                    $currentIndex + 1;
            }


            /*
             * Already at first or last position.
             */
            if (
                $targetIndex < 0
                ||
                $targetIndex >= $rows->count()
            ) {
                return;
            }


            $current = $rows[
                $currentIndex
            ];

            $target = $rows[
                $targetIndex
            ];


            $currentOrder =
                (int)
                $current->priority_order;

            $targetOrder =
                (int)
                $target->priority_order;


            /*
             * Swap in application code.
             *
             * No database relationship is involved.
             */
            DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->where(
                    'priority_id',
                    $current->priority_id
                )
                ->update([
                    'priority_order'
                        => $targetOrder,

                    'priority_updated_by'
                        => auth()->id(),

                    'priority_updated_ip'
                        => request()->ip(),

                    'updated_at'
                        => now(),
                ]);


            DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->where(
                    'priority_id',
                    $target->priority_id
                )
                ->update([
                    'priority_order'
                        => $currentOrder,

                    'priority_updated_by'
                        => auth()->id(),

                    'priority_updated_ip'
                        => request()->ip(),

                    'updated_at'
                        => now(),
                ]);
        });


        return redirect()
            ->route(
                'mpesa.allocation.priorities.index'
            )
            ->with(
                'success',
                'Allocation priority updated.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Build Current Product Catalogue
    |--------------------------------------------------------------------------
    |
    | The actual source tables remain authoritative.
    |
    | No SQL JOIN and no foreign-key relationship is required.
    |--------------------------------------------------------------------------
    */
    private function buildCatalogue(): array
    {
        $catalogue = [];


        /*
        |--------------------------------------------------------------------------
        | CORE — REGISTRATION FEE
        |--------------------------------------------------------------------------
        */
        $catalogue[
            'CORE:REGISTRATION_FEE'
        ] = [
            'priority_type'
                => self::TYPE_CORE,

            'source_table'
                => null,

            'source_id'
                => null,

            'label'
                => 'Registration Fee',

            'code'
                => 'RF',

            'type_label'
                => 'Core',

            'source_active'
                => true,

            'source_status'
                => 'Available',

            'details'
                => 'Membership / registration fee.',
        ];


        /*
        |--------------------------------------------------------------------------
        | CORE — CAPITAL
        |--------------------------------------------------------------------------
        */
        $catalogue[
            'CORE:CAPITAL'
        ] = [
            'priority_type'
                => self::TYPE_CORE,

            'source_table'
                => null,

            'source_id'
                => null,

            'label'
                => 'Capital Shares',

            'code'
                => 'CA',

            'type_label'
                => 'Core',

            'source_active'
                => true,

            'source_status'
                => 'Available',

            'details'
                => 'Member share capital.',
        ];


        /*
        |--------------------------------------------------------------------------
        | CORE — SAVINGS
        |--------------------------------------------------------------------------
        */
        $catalogue[
            'CORE:SHARES'
        ] = [
            'priority_type'
                => self::TYPE_CORE,

            'source_table'
                => null,

            'source_id'
                => null,

            'label'
                => 'Savings / Deposits',

            'code'
                => 'SH',

            'type_label'
                => 'Core',

            'source_active'
                => true,

            'source_status'
                => 'Available',

            'details'
                => 'Ordinary member savings / deposits.',
        ];


        /*
        |--------------------------------------------------------------------------
        | LOAN TYPES
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasTable(
                'sacco_loan_types'
            )
        ) {

            $loanTypes = DB::table(
                'sacco_loan_types'
            )
                ->orderBy(
                    'loan_type_name'
                )
                ->get();


            foreach (
                $loanTypes
                as
                $loanType
            ) {

                /*
                 * Respect soft deletion if the column exists.
                 */
                if (
                    property_exists(
                        $loanType,
                        'loan_type_deleted'
                    )
                    &&
                    strtoupper(
                        trim(
                            (string)
                            $loanType->loan_type_deleted
                        )
                    ) === 'Y'
                ) {
                    continue;
                }


                $loanTypeId =
                    (int)
                    $loanType->loan_type_id;


                $key =
                    'LOAN_TYPE:'
                    . $loanTypeId;


                $active = true;


                if (
                    property_exists(
                        $loanType,
                        'loan_type_active'
                    )
                ) {

                    $active =
                        $this->valueIsActive(
                            $loanType->loan_type_active
                        );
                }


                $loanCode = 'LN';


                if (
                    property_exists(
                        $loanType,
                        'loan_type_code'
                    )
                    &&
                    trim(
                        (string)
                        $loanType->loan_type_code
                    ) !== ''
                ) {

                    $loanCode =
                        trim(
                            (string)
                            $loanType->loan_type_code
                        );
                }


                $catalogue[$key] = [
                    'priority_type'
                        => self::TYPE_LOAN,

                    'source_table'
                        => 'sacco_loan_types',

                    'source_id'
                        => $loanTypeId,

                    'label'
                        => $loanType->loan_type_name
                            ?: 'Loan Type #'
                                . $loanTypeId,

                    'code'
                        => $loanCode,

                    'type_label'
                        => 'Loan',

                    'source_active'
                        => $active,

                    'source_status'
                        => $active
                            ? 'Active'
                            : 'Inactive',

                    'details'
                        => 'Loan Type ID: '
                            . $loanTypeId,
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FOSA TYPES
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasTable(
                'sacco_fosa_types'
            )
        ) {

            $fosaTypes = DB::table(
                'sacco_fosa_types'
            )
                ->orderBy(
                    'type_name'
                )
                ->get();


            foreach (
                $fosaTypes
                as
                $fosaType
            ) {

                $fosaTypeId =
                    (int)
                    $fosaType->type_id;


                $key =
                    'FOSA_TYPE:'
                    . $fosaTypeId;


                $active =
                    $this->valueIsActive(
                        $fosaType->type_active
                        ?? 'Y'
                    );


                $details = [];


                if (
                    property_exists(
                        $fosaType,
                        'expected_amount'
                    )
                    &&
                    $fosaType->expected_amount
                        !== null
                    &&
                    (float)
                    $fosaType->expected_amount
                        > 0
                ) {

                    $details[] =
                        'Expected: KES '
                        . number_format(
                            (float)
                            $fosaType->expected_amount,
                            2
                        );
                }


                if (
                    property_exists(
                        $fosaType,
                        'expected_period'
                    )
                    &&
                    trim(
                        (string)
                        $fosaType->expected_period
                    ) !== ''
                ) {

                    $details[] =
                        ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                trim(
                                    (string)
                                    $fosaType->expected_period
                                )
                            )
                        );
                }


                $catalogue[$key] = [
                    'priority_type'
                        => self::TYPE_FOSA,

                    'source_table'
                        => 'sacco_fosa_types',

                    'source_id'
                        => $fosaTypeId,

                    'label'
                        => $fosaType->type_name
                            ?: 'FOSA Type #'
                                . $fosaTypeId,

                    'code'
                        => $fosaType->type_prefix
                            ?? null,

                    'type_label'
                        => 'FOSA',

                    'source_active'
                        => $active,

                    'source_status'
                        => $active
                            ? 'Active'
                            : 'Inactive',

                    'details'
                        => count($details)
                            > 0
                            ? implode(
                                ' · ',
                                $details
                            )
                            : 'FOSA contribution type.',
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SPECIAL SAVINGS PRODUCTS
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasTable(
                'sacco_special_saving_products'
            )
        ) {

            $specialProducts = DB::table(
                'sacco_special_saving_products'
            )
                ->orderBy(
                    'special_saving_product_name'
                )
                ->get();


            foreach (
                $specialProducts
                as
                $product
            ) {

                /*
                 * Ignore deleted products.
                 */
                if (
                    property_exists(
                        $product,
                        'special_saving_product_deleted'
                    )
                    &&
                    strtoupper(
                        trim(
                            (string)
                            $product
                                ->special_saving_product_deleted
                        )
                    ) === 'Y'
                ) {
                    continue;
                }


                $productId =
                    (int)
                    $product
                        ->special_saving_product_id;


                $key =
                    'SPECIAL_SAVING_PRODUCT:'
                    . $productId;


                $productActive = true;


                if (
                    property_exists(
                        $product,
                        'special_saving_product_status'
                    )
                ) {

                    $productActive =
                        strtoupper(
                            trim(
                                (string)
                                $product
                                    ->special_saving_product_status
                            )
                        )
                        ===
                        'ACTIVE';
                }


                $mpesaAllowed = true;


                if (
                    property_exists(
                        $product,
                        'special_saving_product_allow_mpesa_collection'
                    )
                ) {

                    $mpesaAllowed =
                        strtoupper(
                            trim(
                                (string)
                                $product
                                    ->special_saving_product_allow_mpesa_collection
                            )
                        )
                        ===
                        'Y';
                }


                /*
                 * For future smart M-PESA allocation, the product is fully
                 * available only when it is active AND permits M-PESA.
                 */
                $active =
                    $productActive
                    &&
                    $mpesaAllowed;


                $details = [];


                if (
                    property_exists(
                        $product,
                        'special_saving_product_minimum_monthly_contribution'
                    )
                    &&
                    (float)
                    $product
                        ->special_saving_product_minimum_monthly_contribution
                    > 0
                ) {

                    $details[] =
                        'Monthly target: KES '
                        . number_format(
                            (float)
                            $product
                                ->special_saving_product_minimum_monthly_contribution,
                            2
                        );
                }


                if (!$mpesaAllowed) {

                    $details[] =
                        'M-PESA collection disabled';
                }


                $catalogue[$key] = [
                    'priority_type'
                        => self::TYPE_SPECIAL_SAVING,

                    'source_table'
                        => 'sacco_special_saving_products',

                    'source_id'
                        => $productId,

                    'label'
                        => $product
                            ->special_saving_product_name
                            ?: 'Special Savings #'
                                . $productId,

                    'code'
                        => $product
                            ->special_saving_product_code
                            ?? null,

                    'type_label'
                        => 'Special Savings',

                    'source_active'
                        => $active,

                    'source_status'
                        => $active
                            ? 'Active'
                            : (
                                !$productActive
                                    ? 'Inactive'
                                    : 'M-PESA disabled'
                            ),

                    'details'
                        => count($details)
                            > 0
                            ? implode(
                                ' · ',
                                $details
                            )
                            : 'Special Savings product.',
                ];
            }
        }


        return $catalogue;
    }


    /*
    |--------------------------------------------------------------------------
    | Synchronise Priority Rows
    |--------------------------------------------------------------------------
    |
    | No UNIQUE database constraint is used.
    |
    | Duplicate prevention happens here in PHP/SQL application logic.
    |--------------------------------------------------------------------------
    */
    private function syncPriorityItems(): int
    {
        $catalogue =
            $this->buildCatalogue();


        $maxOrder =
            (int)
            (
                DB::table(
                    'sacco_mpesa_allocation_priorities'
                )
                    ->max(
                        'priority_order'
                    )
                ?? 0
            );


        $added = 0;


        foreach (
            $catalogue
            as
            $key => $item
        ) {

            $existing = DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->where(
                    'priority_key',
                    $key
                )
                ->first();


            /*
             * Existing row:
             *
             * Refresh snapshot information but DO NOT change ordering.
             */
            if ($existing) {

                DB::table(
                    'sacco_mpesa_allocation_priorities'
                )
                    ->where(
                        'priority_id',
                        $existing->priority_id
                    )
                    ->update([
                        'priority_type'
                            => $item[
                                'priority_type'
                            ],

                        'priority_source_table'
                            => $item[
                                'source_table'
                            ],

                        'priority_source_id'
                            => $item[
                                'source_id'
                            ],

                        'priority_label'
                            => $item[
                                'label'
                            ],

                        'priority_code'
                            => $item[
                                'code'
                            ],

                        'priority_last_seen_at'
                            => now(),

                        'updated_at'
                            => now(),
                    ]);

                continue;
            }


            /*
             * New product:
             *
             * Add at bottom.
             */
            $maxOrder++;


            DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->insert([
                    'priority_key'
                        => $key,

                    'priority_type'
                        => $item[
                            'priority_type'
                        ],

                    'priority_source_table'
                        => $item[
                            'source_table'
                        ],

                    'priority_source_id'
                        => $item[
                            'source_id'
                        ],

                    'priority_label'
                        => $item[
                            'label'
                        ],

                    'priority_code'
                        => $item[
                            'code'
                        ],

                    'priority_order'
                        => $maxOrder,

                    'priority_active'
                        => 'Y',

                    'priority_options'
                        => null,

                    'priority_notes'
                        => null,

                    'priority_discovered_at'
                        => now(),

                    'priority_last_seen_at'
                        => now(),

                    'priority_created_by'
                        => auth()->id(),

                    'priority_created_ip'
                        => request()->ip(),

                    'priority_updated_by'
                        => auth()->id(),

                    'priority_updated_ip'
                        => request()->ip(),

                    'created_at'
                        => now(),

                    'updated_at'
                        => now(),
                ]);


            $added++;
        }


        return $added;
    }


    /*
    |--------------------------------------------------------------------------
    | Normalise Current Visible Order
    |--------------------------------------------------------------------------
    */
    private function normaliseVisibleOrder(): void
    {
        $catalogue =
            $this->buildCatalogue();


        $rows = DB::table(
            'sacco_mpesa_allocation_priorities'
        )
            ->orderBy(
                'priority_order'
            )
            ->orderBy(
                'priority_id'
            )
            ->get()
            ->filter(
                function ($row) use ($catalogue) {

                    return isset(
                        $catalogue[
                            $row->priority_key
                        ]
                    );
                }
            )
            ->values();


        foreach (
            $rows
            as
            $index => $row
        ) {

            $expectedOrder =
                $index + 1;


            if (
                (int)
                $row->priority_order
                ===
                $expectedOrder
            ) {
                continue;
            }


            DB::table(
                'sacco_mpesa_allocation_priorities'
            )
                ->where(
                    'priority_id',
                    $row->priority_id
                )
                ->update([
                    'priority_order'
                        => $expectedOrder,

                    'updated_at'
                        => now(),
                ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Required Defaults
    |--------------------------------------------------------------------------
    */
    private function ensureRequiredDefaults(): void
    {
        /*
         * Capital minimum.
         */
        $this->ensureDefault(
            'min_capital_contribution',
            '1000'
        );


        /*
         * Registration / membership fee target.
         */
        $this->ensureDefault(
            'member_ship_fee',
            '1000'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Ensure One Default Exists
    |--------------------------------------------------------------------------
    |
    | This is intentionally Schema-aware because older SACCO databases may
    | have slightly different audit columns.
    |--------------------------------------------------------------------------
    */
    private function ensureDefault(
        string $name,
        string $value
    ): void {

        if (
            !Schema::hasTable(
                'sacco_defaults'
            )
        ) {

            Log::error(
                'M-PESA allocation configuration cannot create required default because sacco_defaults does not exist.',
                [
                    'default_name'
                        => $name,
                ]
            );

            return;
        }


        $exists = DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                $name
            )
            ->exists();


        if ($exists) {
            return;
        }


        /*
         * Minimum universally-required fields.
         */
        $payload = [
            'default_name'
                => $name,

            'default_value'
                => $value,
        ];


        /*
         * Add audit columns only where they actually exist.
         */
        if (
            Schema::hasColumn(
                'sacco_defaults',
                'default_transdate'
            )
        ) {

            $payload[
                'default_transdate'
            ] = now();
        }


        if (
            Schema::hasColumn(
                'sacco_defaults',
                'default_userid'
            )
        ) {

            $payload[
                'default_userid'
            ] = auth()->id()
                ?? 999;
        }


        if (
            Schema::hasColumn(
                'sacco_defaults',
                'default_ip'
            )
        ) {

            $payload[
                'default_ip'
            ] = request()->ip()
                ?? '127.0.0.1';
        }


        DB::table(
            'sacco_defaults'
        )->insert(
            $payload
        );


        Log::warning(
            'M-PESA Smart Allocation automatically created a missing SACCO default.',
            [
                'default_name'
                    => $name,

                'default_value'
                    => $value,

                'user_id'
                    => auth()->id(),

                'ip'
                    => request()->ip(),
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Accounting Readiness
    |--------------------------------------------------------------------------
    */
    private function buildReadiness(): array
    {
        return [

            /*
             * M-PESA receiving ledger.
             */
            'mpesa' => [
                'ledger_account'
                    => $this->getDefault(
                        'default_mpesa_in_account'
                    ),

                'ledger_ready'
                    => $this->defaultHasValue(
                        'default_mpesa_in_account'
                    ),
            ],


            /*
             * Capital.
             */
            'capital' => [
                'required_amount'
                    => (float)
                    (
                        $this->getDefault(
                            'min_capital_contribution'
                        )
                        ?? 1000
                    ),

                'ledger_account'
                    => $this->getDefault(
                        'default_share_capital_account'
                    ),

                'ledger_ready'
                    => $this->defaultHasValue(
                        'default_share_capital_account'
                    ),
            ],


            /*
             * Registration Fee.
             */
            'registration_fee' => [
                'required_amount'
                    => (float)
                    (
                        $this->getDefault(
                            'member_ship_fee'
                        )
                        ?? 1000
                    ),

                'ledger_account'
                    => $this->getDefault(
                        'default_member_ship_fee_account'
                    ),

                'ledger_ready'
                    => $this->defaultHasValue(
                        'default_member_ship_fee_account'
                    ),
            ],


            /*
             * Ordinary savings.
             */
            'shares' => [
                'ledger_account'
                    => $this->getDefault(
                        'default_share_account'
                    ),

                'ledger_ready'
                    => $this->defaultHasValue(
                        'default_share_account'
                    ),
            ],


            /*
             * Existing generic FOSA ledger.
             */
            'fosa' => [
                'ledger_account'
                    => $this->getDefault(
                        'default_fosa_account'
                    ),

                'ledger_ready'
                    => $this->defaultHasValue(
                        'default_fosa_account'
                    ),
            ],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Configuration Warnings
    |--------------------------------------------------------------------------
    */
    private function logConfigurationWarnings(): void
    {
        $importantDefaults = [

            'default_mpesa_in_account'
                => 'M-PESA incoming ledger',

            'default_share_account'
                => 'Savings / deposits ledger',

            'default_share_capital_account'
                => 'Capital ledger',

            'default_member_ship_fee_account'
                => 'Registration / membership fee ledger',

            'default_fosa_account'
                => 'FOSA ledger',
        ];


        foreach (
            $importantDefaults
            as
            $name => $description
        ) {

            if (
                $this->defaultHasValue(
                    $name
                )
            ) {
                continue;
            }


            Log::error(
                'M-PESA Smart Allocation accounting configuration is incomplete.',
                [
                    'missing_default'
                        => $name,

                    'description'
                        => $description,

                    'action'
                        => 'Future smart allocation must skip this destination until configuration is corrected.',
                ]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Read One Default
    |--------------------------------------------------------------------------
    */
    private function getDefault(
        string $name
    ) {
        if (
            !Schema::hasTable(
                'sacco_defaults'
            )
        ) {
            return null;
        }


        return DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                $name
            )
            ->value(
                'default_value'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Default Has Usable Value
    |--------------------------------------------------------------------------
    */
    private function defaultHasValue(
        string $name
    ): bool {

        $value =
            $this->getDefault(
                $name
            );


        return
            $value !== null
            &&
            trim(
                (string)
                $value
            ) !== '';
    }


    /*
    |--------------------------------------------------------------------------
    | Generic Y / N / 1 / 0 Status Reader
    |--------------------------------------------------------------------------
    |
    | Different older SACCO tables may represent status differently.
    |--------------------------------------------------------------------------
    */
    private function valueIsActive(
        $value
    ): bool {

        if ($value === null) {
            return true;
        }


        $value =
            strtoupper(
                trim(
                    (string)
                    $value
                )
            );


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