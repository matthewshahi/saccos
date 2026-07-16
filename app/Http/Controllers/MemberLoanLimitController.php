<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberLoanLimitController extends Controller
{
    /**
     * Display individual member loan limits.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $loanTypeId = $request->query('loan_type_id');
        $status = $request->query('status');

        $query = DB::table('sacco_member_loan_limits as limits')
            ->join(
                'sacco_members as members',
                'members.member_id',
                '=',
                'limits.member_loan_limit_member_id'
            )
            ->join(
                'sacco_loan_types as loan_types',
                'loan_types.loan_type_id',
                '=',
                'limits.member_loan_limit_loan_type_id'
            )
            ->where(
                'limits.member_loan_limit_deleted',
                '<>',
                'Y'
            )
            ->select([
                'limits.member_loan_limit_id',
                'limits.member_loan_limit_member_id',
                'limits.member_loan_limit_loan_type_id',
                'limits.member_loan_limit_amount',
                'limits.member_loan_limit_active',
                'limits.member_loan_limit_by',
                'limits.member_loan_limit_transdate',
                'limits.member_loan_limit_ip',

                'members.member_name',
                'members.member_sacco_id',
                'members.member_national_id',
                'members.member_phone_no',
                'members.member_active',

                'loan_types.loan_type_name',
                'loan_types.loan_type_code',
                'loan_types.loan_type_max_amount',
                'loan_types.loan_type_active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */
        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $likeSearch = '%' . $search . '%';

                $subQuery
                    ->where(
                        'members.member_name',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_sacco_id',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_national_id',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_phone_no',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'loan_types.loan_type_name',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'loan_types.loan_type_code',
                        'like',
                        $likeSearch
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Loan type filter
        |--------------------------------------------------------------------------
        */
        if (is_numeric($loanTypeId)) {
            $query->where(
                'limits.member_loan_limit_loan_type_id',
                (int) $loanTypeId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status filter
        |--------------------------------------------------------------------------
        */
        if ($status === 'active') {
            $query->where(
                'limits.member_loan_limit_active',
                1
            );
        } elseif ($status === 'inactive') {
            $query->where(
                'limits.member_loan_limit_active',
                0
            );
        }

        $memberLoanLimits = $query
            ->orderBy('members.member_name')
            ->orderBy('loan_types.loan_type_name')
            ->paginate(25)
            ->withQueryString();

        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->select([
                'loan_type_id',
                'loan_type_name',
                'loan_type_code',
                'loan_type_max_amount',
                'loan_type_active',
            ])
            ->get();

        $currentPeriod = $this->getCurrentPeriod();

        return view(
            'loans.member_limits.index',
            compact(
                'memberLoanLimits',
                'loanTypes',
                'currentPeriod',
                'search',
                'loanTypeId',
                'status'
            )
        );
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Restore selected member after validation failure
        |--------------------------------------------------------------------------
        */
        $oldMemberId = (int) session()->getOldInput(
            'member_loan_limit_member_id',
            0
        );

        $selectedMember = $this->getMemberForDisplay(
            $oldMemberId
        );

        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->where('loan_type_active', 1)
            ->orderBy('loan_type_name')
            ->select([
                'loan_type_id',
                'loan_type_name',
                'loan_type_code',
                'loan_type_max_amount',
            ])
            ->get();

        $currentPeriod = $this->getCurrentPeriod();

        return view(
            'loans.member_limits.create',
            compact(
                'selectedMember',
                'loanTypes',
                'currentPeriod'
            )
        );
    }

    /**
     * Store an individual member loan limit.
     */
    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'member_loan_limit_member_id' => [
                'required',
                'integer',

                Rule::exists(
                    'sacco_members',
                    'member_id'
                )->where(function ($query) {
                    $query
                        ->where(
                            'member_deleted',
                            '<>',
                            'Y'
                        )
                        ->where(
                            'member_active',
                            'Y'
                        );
                }),
            ],

            'member_loan_limit_loan_type_id' => [
                'required',
                'integer',

                Rule::exists(
                    'sacco_loan_types',
                    'loan_type_id'
                )->where(function ($query) {
                    $query
                        ->where(
                            'loan_type_deleted',
                            '<>',
                            'Y'
                        )
                        ->where(
                            'loan_type_active',
                            1
                        );
                }),
            ],

            'member_loan_limit_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'member_loan_limit_active' => [
                'required',
                'boolean',
            ],
        ]);

        $memberId = (int) $validated['member_loan_limit_member_id'];

        $loanTypeId = (int) $validated['member_loan_limit_loan_type_id'];

        $limitAmount = round(
            (float) $validated['member_loan_limit_amount'],
            2
        );

        $active = (int) $validated['member_loan_limit_active'];

        $loanType = $this->getLoanType(
            $loanTypeId
        );

        $this->validateLimitAgainstLoanType(
            $limitAmount,
            $loanType
        );

        if ($active === 1) {
            $this->validateActivationEligibility(
                $memberId,
                $loanType
            );
        }

        try {
            $result = DB::transaction(function () use (
                $memberId,
                $loanTypeId,
                $limitAmount,
                $active,
                $request
            ) {
                /*
                |--------------------------------------------------------------------------
                | Check for an existing member-and-loan-type record
                |--------------------------------------------------------------------------
                */
                $existing = DB::table(
                    'sacco_member_loan_limits'
                )
                    ->where(
                        'member_loan_limit_member_id',
                        $memberId
                    )
                    ->where(
                        'member_loan_limit_loan_type_id',
                        $loanTypeId
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | Restore a soft-deleted record
                |--------------------------------------------------------------------------
                */
                if ($existing) {
                    if (
                        $existing->member_loan_limit_deleted
                        !== 'Y'
                    ) {
                        throw ValidationException::withMessages([
                            'member_loan_limit_member_id' =>
                            'This member already has an individual limit for the selected loan type.',
                        ]);
                    }

                    DB::table(
                        'sacco_member_loan_limits'
                    )
                        ->where(
                            'member_loan_limit_id',
                            $existing->member_loan_limit_id
                        )
                        ->update([
                            'member_loan_limit_amount' =>
                            $limitAmount,

                            'member_loan_limit_active' =>
                            $active,

                            'member_loan_limit_deleted' =>
                            'N',

                            'member_loan_limit_by' =>
                            Auth::id(),

                            'member_loan_limit_transdate' =>
                            now(),

                            'member_loan_limit_ip' =>
                            $request->ip(),
                        ]);

                    return 'restored';
                }

                /*
                |--------------------------------------------------------------------------
                | Create a new record
                |--------------------------------------------------------------------------
                */
                DB::table(
                    'sacco_member_loan_limits'
                )->insert([
                    'member_loan_limit_member_id' =>
                    $memberId,

                    'member_loan_limit_loan_type_id' =>
                    $loanTypeId,

                    'member_loan_limit_amount' =>
                    $limitAmount,

                    'member_loan_limit_active' =>
                    $active,

                    'member_loan_limit_deleted' =>
                    'N',

                    'member_loan_limit_by' =>
                    Auth::id(),

                    'member_loan_limit_transdate' =>
                    now(),

                    'member_loan_limit_ip' =>
                    $request->ip(),
                ]);

                return 'created';
            });
        } catch (QueryException $exception) {
            /*
            |--------------------------------------------------------------------------
            | Duplicate unique member-and-loan-type record
            |--------------------------------------------------------------------------
            */
            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return back()
                    ->withErrors([
                        'member_loan_limit_member_id' =>
                        'This member already has an individual limit for the selected loan type.',
                    ])
                    ->withInput();
            }

            throw $exception;
        }

        $message = $result === 'restored'
            ? 'The previously deleted individual loan limit was restored successfully.'
            : 'Individual member loan limit created successfully.';

        return redirect()
            ->route(
                'loans.member_limits.index'
            )
            ->with(
                'success',
                $message
            );
    }

    /**
     * Show the edit form.
     */
    public function edit(
        int $id
    ): View|RedirectResponse {
        $memberLoanLimit = DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->where(
                'member_loan_limit_deleted',
                '<>',
                'Y'
            )
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route(
                    'loans.member_limits.index'
                )
                ->with(
                    'error',
                    'Individual member loan limit not found.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Load only the currently selected member
        |--------------------------------------------------------------------------
        | Other members are searched through the AJAX endpoint.
        */
        $selectedMember = $this->getMemberForDisplay(
            (int) $memberLoanLimit
                ->member_loan_limit_member_id
        );

        /*
        |--------------------------------------------------------------------------
        | Include active loan types and the currently selected type
        |--------------------------------------------------------------------------
        */
        $loanTypes = DB::table(
            'sacco_loan_types'
        )
            ->where(
                'loan_type_deleted',
                '<>',
                'Y'
            )
            ->where(function ($query) use (
                $memberLoanLimit
            ) {
                $query
                    ->where(
                        'loan_type_active',
                        1
                    )
                    ->orWhere(
                        'loan_type_id',
                        $memberLoanLimit
                            ->member_loan_limit_loan_type_id
                    );
            })
            ->orderBy('loan_type_name')
            ->select([
                'loan_type_id',
                'loan_type_name',
                'loan_type_code',
                'loan_type_max_amount',
                'loan_type_active',
            ])
            ->get();

        $currentPeriod = $this->getCurrentPeriod();

        return view(
            'loans.member_limits.edit',
            compact(
                'memberLoanLimit',
                'selectedMember',
                'loanTypes',
                'currentPeriod'
            )
        );
    }

    /**
     * Update an individual member loan limit.
     */
    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $memberLoanLimit = DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->where(
                'member_loan_limit_deleted',
                '<>',
                'Y'
            )
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route(
                    'loans.member_limits.index'
                )
                ->with(
                    'error',
                    'Individual member loan limit not found.'
                );
        }

        $validated = $request->validate([
            'member_loan_limit_member_id' => [
                'required',
                'integer',

                Rule::exists(
                    'sacco_members',
                    'member_id'
                )->where(function ($query) {
                    $query->where(
                        'member_deleted',
                        '<>',
                        'Y'
                    );
                }),
            ],

            'member_loan_limit_loan_type_id' => [
                'required',
                'integer',

                Rule::exists(
                    'sacco_loan_types',
                    'loan_type_id'
                )->where(function ($query) {
                    $query->where(
                        'loan_type_deleted',
                        '<>',
                        'Y'
                    );
                }),
            ],

            'member_loan_limit_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'member_loan_limit_active' => [
                'required',
                'boolean',
            ],
        ]);

        $memberId = (int) $validated['member_loan_limit_member_id'];

        $loanTypeId = (int) $validated['member_loan_limit_loan_type_id'];

        $limitAmount = round(
            (float) $validated['member_loan_limit_amount'],
            2
        );

        $active = (int) $validated['member_loan_limit_active'];

        $loanType = $this->getLoanType(
            $loanTypeId
        );

        $this->validateLimitAgainstLoanType(
            $limitAmount,
            $loanType
        );

        if ($active === 1) {
            $this->validateActivationEligibility(
                $memberId,
                $loanType
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate member-and-loan-type combinations
        |--------------------------------------------------------------------------
        */
        $duplicate = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_member_id', $memberId)
            ->where('member_loan_limit_loan_type_id', $loanTypeId)
            ->where('member_loan_limit_id', '<>', $id)
            ->where('member_loan_limit_deleted', '<>', 'Y')
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors([
                    'member_loan_limit_member_id' =>
                    'Another record already exists for this member and loan type.',
                ])
                ->withInput();
        }

        try {
            DB::transaction(function () use (
                $id,
                $memberId,
                $loanTypeId,
                $limitAmount,
                $active,
                $request
            ) {
                $record = DB::table(
                    'sacco_member_loan_limits'
                )
                    ->where(
                        'member_loan_limit_id',
                        $id
                    )
                    ->where(
                        'member_loan_limit_deleted',
                        '<>',
                        'Y'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$record) {
                    throw ValidationException::withMessages([
                        'member_loan_limit_member_id' =>
                        'The individual loan limit no longer exists.',
                    ]);
                }

                DB::table(
                    'sacco_member_loan_limits'
                )
                    ->where(
                        'member_loan_limit_id',
                        $id
                    )
                    ->update([
                        'member_loan_limit_member_id' =>
                        $memberId,

                        'member_loan_limit_loan_type_id' =>
                        $loanTypeId,

                        'member_loan_limit_amount' =>
                        $limitAmount,

                        'member_loan_limit_active' =>
                        $active,

                        'member_loan_limit_by' =>
                        Auth::id(),

                        'member_loan_limit_transdate' =>
                        now(),

                        'member_loan_limit_ip' =>
                        $request->ip(),
                    ]);
            });
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return back()
                    ->withErrors([
                        'member_loan_limit_member_id' =>
                        'Another record already exists for this member and loan type.',
                    ])
                    ->withInput();
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'loans.member_limits.index'
            )
            ->with(
                'success',
                'Individual member loan limit updated successfully.'
            );
    }

    /**
     * Activate or deactivate an individual member loan limit.
     */
    public function updateStatus(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'member_loan_limit_active' => [
                'required',
                'boolean',
            ],
        ]);

        $memberLoanLimit = DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->where(
                'member_loan_limit_deleted',
                '<>',
                'Y'
            )
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route(
                    'loans.member_limits.index'
                )
                ->with(
                    'error',
                    'Individual member loan limit not found.'
                );
        }

        $active = (int) $validated['member_loan_limit_active'];

        /*
        |--------------------------------------------------------------------------
        | Revalidate before activation
        |--------------------------------------------------------------------------
        */
        if ($active === 1) {
            $loanType = $this->getLoanType(
                (int) $memberLoanLimit
                    ->member_loan_limit_loan_type_id
            );

            $this->validateActivationEligibility(
                (int) $memberLoanLimit
                    ->member_loan_limit_member_id,
                $loanType
            );

            $this->validateLimitAgainstLoanType(
                (float) $memberLoanLimit
                    ->member_loan_limit_amount,
                $loanType
            );
        }

        DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->update([
                'member_loan_limit_active' =>
                $active,

                'member_loan_limit_by' =>
                Auth::id(),

                'member_loan_limit_transdate' =>
                now(),

                'member_loan_limit_ip' =>
                $request->ip(),
            ]);

        return back()->with(
            'success',
            $active === 1
                ? 'Individual member loan limit activated successfully.'
                : 'Individual member loan limit deactivated successfully.'
        );
    }

    /**
     * Soft-delete an individual member loan limit.
     */
    public function destroy(
        Request $request,
        int $id
    ): RedirectResponse {
        $memberLoanLimit = DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->where(
                'member_loan_limit_deleted',
                '<>',
                'Y'
            )
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route(
                    'loans.member_limits.index'
                )
                ->with(
                    'error',
                    'Individual member loan limit not found.'
                );
        }

        DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_id',
                $id
            )
            ->update([
                'member_loan_limit_active' =>
                0,

                'member_loan_limit_deleted' =>
                'Y',

                'member_loan_limit_by' =>
                Auth::id(),

                'member_loan_limit_transdate' =>
                now(),

                'member_loan_limit_ip' =>
                $request->ip(),
            ]);

        return redirect()
            ->route(
                'loans.member_limits.index'
            )
            ->with(
                'success',
                'Individual member loan limit deleted successfully.'
            );
    }

    /**
     * Search active members for the smart member search field.
     */
    public function searchMembers(
        Request $request
    ): JsonResponse {
        $search = trim(
            preg_replace(
                '/\s+/',
                ' ',
                (string) $request->query(
                    'q',
                    ''
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent excessively long search terms
        |--------------------------------------------------------------------------
        */
        $search = mb_substr(
            $search,
            0,
            100
        );

        /*
        |--------------------------------------------------------------------------
        | Require at least two characters
        |--------------------------------------------------------------------------
        */
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $likeSearch = '%' . $search . '%';
        $startsWithSearch = $search . '%';

        /*
        |--------------------------------------------------------------------------
        | Split names into searchable words
        |--------------------------------------------------------------------------
        | Example:
        | "mwangi maina" can match "MAINA PETER MWANGI".
        */
        $nameTokens = preg_split(
            '/\s+/',
            $search,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $members = DB::table(
            'sacco_members as members'
        )
            ->where(
                'members.member_active',
                'Y'
            )
            ->where(
                'members.member_deleted',
                '<>',
                'Y'
            )
            ->where(function ($query) use (
                $likeSearch,
                $nameTokens
            ) {
                $query
                    ->where(
                        'members.member_name',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_sacco_id',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_national_id',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_phone_no',
                        'like',
                        $likeSearch
                    )
                    ->orWhere(
                        'members.member_email',
                        'like',
                        $likeSearch
                    );

                /*
                |--------------------------------------------------------------------------
                | Match all entered name words regardless of order
                |--------------------------------------------------------------------------
                */
                if (count($nameTokens) >= 2) {
                    $query->orWhere(
                        function ($nameQuery) use (
                            $nameTokens
                        ) {
                            foreach (
                                $nameTokens as $token
                            ) {
                                $nameQuery->where(
                                    'members.member_name',
                                    'like',
                                    '%' . $token . '%'
                                );
                            }
                        }
                    );
                }
            })
            ->orderByRaw(
                "
                    CASE
                        WHEN members.member_sacco_id = ? THEN 1
                        WHEN members.member_national_id = ? THEN 2
                        WHEN members.member_phone_no = ? THEN 3
                        WHEN members.member_email = ? THEN 4
                        WHEN members.member_name = ? THEN 5
                        WHEN members.member_sacco_id LIKE ? THEN 6
                        WHEN members.member_national_id LIKE ? THEN 7
                        WHEN members.member_name LIKE ? THEN 8
                        ELSE 20
                    END
                ",
                [
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $startsWithSearch,
                    $startsWithSearch,
                    $startsWithSearch,
                ]
            )
            ->orderBy(
                'members.member_name'
            )
            ->select([
                'members.member_id',
                'members.member_name',
                'members.member_sacco_id',
                'members.member_national_id',
                'members.member_phone_no',
                'members.member_email',
            ])
            ->limit(20)
            ->get();

        $results = $members->map(
            function ($member) {
                return [
                    'id' => (int) $member
                        ->member_id,

                    'name' => (string) $member
                        ->member_name,

                    'sacco_id' =>
                    $member->member_sacco_id
                        ? (string) $member
                            ->member_sacco_id
                        : null,

                    'national_id' =>
                    $member->member_national_id
                        ? (string) $member
                            ->member_national_id
                        : null,

                    'phone' =>
                    $member->member_phone_no
                        ? (string) $member
                            ->member_phone_no
                        : null,

                    'email' =>
                    $member->member_email
                        ? (string) $member
                            ->member_email
                        : null,

                    'label' => trim(
                        (string) $member
                            ->member_name
                            . (
                                $member->member_sacco_id
                                ? ' — '
                                . $member
                                ->member_sacco_id
                                : ''
                            )
                    ),
                ];
            }
        );

        return response()->json(
            $results->values()
        );
    }

    /**
     * Get the current active accounting period.
     */
    private function getCurrentPeriod(): ?object
    {
        return DB::table('sacco_period')
            ->where(
                'period_active',
                'Y'
            )
            ->where(
                'period_deleted',
                '<>',
                'Y'
            )
            ->first();
    }

    /**
     * Retrieve a valid loan type.
     */
    private function getLoanType(
        int $loanTypeId
    ): object {
        $loanType = DB::table(
            'sacco_loan_types'
        )
            ->where(
                'loan_type_id',
                $loanTypeId
            )
            ->where(
                'loan_type_deleted',
                '<>',
                'Y'
            )
            ->select([
                'loan_type_id',
                'loan_type_name',
                'loan_type_code',
                'loan_type_max_amount',
                'loan_type_active',
            ])
            ->first();

        if (!$loanType) {
            throw ValidationException::withMessages([
                'member_loan_limit_loan_type_id' =>
                'The selected loan type does not exist or has been deleted.',
            ]);
        }

        return $loanType;
    }

    /**
     * Get one member for display in the smart search field.
     */
    private function getMemberForDisplay(
        ?int $memberId
    ): ?object {
        if (
            !$memberId
            || $memberId <= 0
        ) {
            return null;
        }

        return DB::table('sacco_members')
            ->where(
                'member_id',
                $memberId
            )
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_phone_no',
                'member_email',
                'member_active',
                'member_deleted',
            ])
            ->first();
    }

    /**
     * Ensure an individual limit can be active.
     */
    private function validateActivationEligibility(
        int $memberId,
        object $loanType
    ): void {
        $memberIsActive = DB::table(
            'sacco_members'
        )
            ->where(
                'member_id',
                $memberId
            )
            ->where(
                'member_deleted',
                '<>',
                'Y'
            )
            ->where(
                'member_active',
                'Y'
            )
            ->exists();

        if (!$memberIsActive) {
            throw ValidationException::withMessages([
                'member_loan_limit_member_id' =>
                'The individual limit cannot be active because the selected member is inactive or unavailable.',
            ]);
        }

        if (
            (int) $loanType->loan_type_active
            !== 1
        ) {
            throw ValidationException::withMessages([
                'member_loan_limit_loan_type_id' =>
                'The individual limit cannot be active because the selected loan type is inactive.',
            ]);
        }
    }

    /**
     * Ensure the individual limit is below the loan-type maximum.
     */
    private function validateLimitAgainstLoanType(
        float $individualLimit,
        object $loanType
    ): void {
        $individualLimit = round(
            $individualLimit,
            2
        );

        $loanTypeMaximum = round(
            (float) $loanType
                ->loan_type_max_amount,
            2
        );

        if ($loanTypeMaximum <= 0) {
            throw ValidationException::withMessages([
                'member_loan_limit_loan_type_id' =>
                'The selected loan type does not have a valid maximum loan amount.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | The personal limit must be strictly below the standard maximum
        |--------------------------------------------------------------------------
        */
        if (
            $individualLimit
            >= $loanTypeMaximum
        ) {
            throw ValidationException::withMessages([
                'member_loan_limit_amount' =>
                'The individual limit must be lower than the selected loan type maximum of KES '
                    . number_format(
                        $loanTypeMaximum,
                        2
                    )
                    . '.',
            ]);
        }
    }
}
