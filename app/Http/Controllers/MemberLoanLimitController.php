<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
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
            ->where('limits.member_loan_limit_deleted', '<>', 'Y')
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
        | Search by member or loan type
        |--------------------------------------------------------------------------
        */
        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('members.member_name', 'like', '%' . $search . '%')
                    ->orWhere('members.member_sacco_id', 'like', '%' . $search . '%')
                    ->orWhere('members.member_national_id', 'like', '%' . $search . '%')
                    ->orWhere('members.member_phone_no', 'like', '%' . $search . '%')
                    ->orWhere('loan_types.loan_type_name', 'like', '%' . $search . '%')
                    ->orWhere('loan_types.loan_type_code', 'like', '%' . $search . '%');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by loan type
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
        | Filter by status
        |--------------------------------------------------------------------------
        */
        if ($status === 'active') {
            $query->where('limits.member_loan_limit_active', 1);
        }

        if ($status === 'inactive') {
            $query->where('limits.member_loan_limit_active', 0);
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

        return view('loans.member_limits.index', compact(
            'memberLoanLimits',
            'loanTypes',
            'currentPeriod',
            'search',
            'loanTypeId',
            'status'
        ));
    }

    /**
     * Show the form for creating an individual member loan limit.
     */
    public function create(): View
    {
        $members = DB::table('sacco_members')
            ->where('member_deleted', '<>', 'Y')
            ->where('member_active', 'Y')
            ->orderBy('member_name')
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_phone_no',
            ])
            ->get();

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

        return view('loans.member_limits.create', compact(
            'members',
            'loanTypes',
            'currentPeriod'
        ));
    }

    /**
     * Store a new individual member loan limit.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_loan_limit_member_id' => [
                'required',
                'integer',
                Rule::exists('sacco_members', 'member_id')
                    ->where(function ($query) {
                        $query
                            ->where('member_deleted', '<>', 'Y')
                            ->where('member_active', 'Y');
                    }),
            ],

            'member_loan_limit_loan_type_id' => [
                'required',
                'integer',
                Rule::exists('sacco_loan_types', 'loan_type_id')
                    ->where(function ($query) {
                        $query
                            ->where('loan_type_deleted', '<>', 'Y')
                            ->where('loan_type_active', 1);
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

        $loanType = $this->getLoanType($loanTypeId);

        $this->validateLimitAgainstLoanType(
            $limitAmount,
            $loanType
        );

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
                | Check for an existing member-and-loan-type combination
                |--------------------------------------------------------------------------
                | The table has a unique index on member ID and loan type ID.
                | A previously deleted record must therefore be restored rather
                | than inserting another row with the same combination.
                */
                $existing = DB::table('sacco_member_loan_limits')
                    ->where('member_loan_limit_member_id', $memberId)
                    ->where('member_loan_limit_loan_type_id', $loanTypeId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->member_loan_limit_deleted !== 'Y') {
                        throw ValidationException::withMessages([
                            'member_loan_limit_member_id' =>
                                'This member already has an individual limit for the selected loan type.',
                        ]);
                    }

                    DB::table('sacco_member_loan_limits')
                        ->where(
                            'member_loan_limit_id',
                            $existing->member_loan_limit_id
                        )
                        ->update([
                            'member_loan_limit_amount' => $limitAmount,
                            'member_loan_limit_active' => $active,
                            'member_loan_limit_deleted' => 'N',
                            'member_loan_limit_by' => Auth::id(),
                            'member_loan_limit_transdate' => now(),
                            'member_loan_limit_ip' => $request->ip(),
                        ]);

                    return 'restored';
                }

                DB::table('sacco_member_loan_limits')
                    ->insert([
                        'member_loan_limit_member_id' => $memberId,
                        'member_loan_limit_loan_type_id' => $loanTypeId,
                        'member_loan_limit_amount' => $limitAmount,
                        'member_loan_limit_active' => $active,
                        'member_loan_limit_deleted' => 'N',
                        'member_loan_limit_by' => Auth::id(),
                        'member_loan_limit_transdate' => now(),
                        'member_loan_limit_ip' => $request->ip(),
                    ]);

                return 'created';
            });
        } catch (QueryException $exception) {
            /*
            |--------------------------------------------------------------------------
            | Catch a concurrent duplicate insert
            |--------------------------------------------------------------------------
            */
            if ((string) $exception->getCode() === '23000') {
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
            ->route('loans.member_limits.index')
            ->with('success', $message);
    }

    /**
     * Show the form for editing an individual member loan limit.
     */
    public function edit(int $id): View|RedirectResponse
    {
        $memberLoanLimit = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->where('member_loan_limit_deleted', '<>', 'Y')
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route('loans.member_limits.index')
                ->with('error', 'Individual member loan limit not found.');
        }

        $members = DB::table('sacco_members')
            ->where('member_deleted', '<>', 'Y')
            ->where(function ($query) use ($memberLoanLimit) {
                $query
                    ->where('member_active', 'Y')
                    ->orWhere(
                        'member_id',
                        $memberLoanLimit->member_loan_limit_member_id
                    );
            })
            ->orderBy('member_name')
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_phone_no',
                'member_active',
            ])
            ->get();

        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->where(function ($query) use ($memberLoanLimit) {
                $query
                    ->where('loan_type_active', 1)
                    ->orWhere(
                        'loan_type_id',
                        $memberLoanLimit->member_loan_limit_loan_type_id
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

        return view('loans.member_limits.edit', compact(
            'memberLoanLimit',
            'members',
            'loanTypes',
            'currentPeriod'
        ));
    }

    /**
     * Update an individual member loan limit.
     */
    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $memberLoanLimit = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->where('member_loan_limit_deleted', '<>', 'Y')
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route('loans.member_limits.index')
                ->with('error', 'Individual member loan limit not found.');
        }

        $validated = $request->validate([
            'member_loan_limit_member_id' => [
                'required',
                'integer',
                Rule::exists('sacco_members', 'member_id')
                    ->where(function ($query) {
                        $query->where('member_deleted', '<>', 'Y');
                    }),
            ],

            'member_loan_limit_loan_type_id' => [
                'required',
                'integer',
                Rule::exists('sacco_loan_types', 'loan_type_id')
                    ->where(function ($query) {
                        $query->where('loan_type_deleted', '<>', 'Y');
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

        $loanType = $this->getLoanType($loanTypeId);

        $this->validateLimitAgainstLoanType(
            $limitAmount,
            $loanType
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate member-and-loan-type combinations
        |--------------------------------------------------------------------------
        */
        $duplicate = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_member_id', $memberId)
            ->where('member_loan_limit_loan_type_id', $loanTypeId)
            ->where('member_loan_limit_id', '<>', $id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors([
                    'member_loan_limit_member_id' =>
                        'Another record already exists for this member and loan type.',
                ])
                ->withInput();
        }

        DB::transaction(function () use (
            $id,
            $memberId,
            $loanTypeId,
            $limitAmount,
            $active,
            $request
        ) {
            DB::table('sacco_member_loan_limits')
                ->where('member_loan_limit_id', $id)
                ->where('member_loan_limit_deleted', '<>', 'Y')
                ->lockForUpdate()
                ->first();

            DB::table('sacco_member_loan_limits')
                ->where('member_loan_limit_id', $id)
                ->update([
                    'member_loan_limit_member_id' => $memberId,
                    'member_loan_limit_loan_type_id' => $loanTypeId,
                    'member_loan_limit_amount' => $limitAmount,
                    'member_loan_limit_active' => $active,
                    'member_loan_limit_by' => Auth::id(),
                    'member_loan_limit_transdate' => now(),
                    'member_loan_limit_ip' => $request->ip(),
                ]);
        });

        return redirect()
            ->route('loans.member_limits.index')
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

        $memberLoanLimit = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->where('member_loan_limit_deleted', '<>', 'Y')
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route('loans.member_limits.index')
                ->with('error', 'Individual member loan limit not found.');
        }

        $active = (int) $validated['member_loan_limit_active'];

        /*
        |--------------------------------------------------------------------------
        | Revalidate the limit before activation
        |--------------------------------------------------------------------------
        | The loan type maximum may have been reduced after this individual
        | limit was originally created.
        */
        if ($active === 1) {
            $memberExists = DB::table('sacco_members')
                ->where(
                    'member_id',
                    $memberLoanLimit->member_loan_limit_member_id
                )
                ->where('member_deleted', '<>', 'Y')
                ->where('member_active', 'Y')
                ->exists();

            if (!$memberExists) {
                return back()->with(
                    'error',
                    'This limit cannot be activated because the member is inactive or unavailable.'
                );
            }

            $loanType = $this->getLoanType(
                (int) $memberLoanLimit->member_loan_limit_loan_type_id
            );

            if ((int) $loanType->loan_type_active !== 1) {
                return back()->with(
                    'error',
                    'This limit cannot be activated because the loan type is inactive.'
                );
            }

            $this->validateLimitAgainstLoanType(
                (float) $memberLoanLimit->member_loan_limit_amount,
                $loanType
            );
        }

        DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->update([
                'member_loan_limit_active' => $active,
                'member_loan_limit_by' => Auth::id(),
                'member_loan_limit_transdate' => now(),
                'member_loan_limit_ip' => $request->ip(),
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
        $memberLoanLimit = DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->where('member_loan_limit_deleted', '<>', 'Y')
            ->first();

        if (!$memberLoanLimit) {
            return redirect()
                ->route('loans.member_limits.index')
                ->with('error', 'Individual member loan limit not found.');
        }

        DB::table('sacco_member_loan_limits')
            ->where('member_loan_limit_id', $id)
            ->update([
                'member_loan_limit_active' => 0,
                'member_loan_limit_deleted' => 'Y',
                'member_loan_limit_by' => Auth::id(),
                'member_loan_limit_transdate' => now(),
                'member_loan_limit_ip' => $request->ip(),
            ]);

        return redirect()
            ->route('loans.member_limits.index')
            ->with(
                'success',
                'Individual member loan limit deleted successfully.'
            );
    }

    /**
     * Get the current active accounting period.
     */
    private function getCurrentPeriod(): ?object
    {
        return DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    /**
     * Retrieve a valid loan type.
     */
    private function getLoanType(int $loanTypeId): object
    {
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loanTypeId)
            ->where('loan_type_deleted', '<>', 'Y')
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
     * Ensure the individual limit is strictly below the loan-type maximum.
     */
    private function validateLimitAgainstLoanType(
        float $individualLimit,
        object $loanType
    ): void {
        $individualLimit = round($individualLimit, 2);
        $loanTypeMaximum = round(
            (float) $loanType->loan_type_max_amount,
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
        | Strictly lower, not equal
        |--------------------------------------------------------------------------
        | When the values are equal, the individual record would provide no
        | restriction beyond the standard loan-type maximum.
        */
        if ($individualLimit >= $loanTypeMaximum) {
            throw ValidationException::withMessages([
                'member_loan_limit_amount' =>
                    'The individual limit must be lower than the selected loan type maximum of KES '
                    . number_format($loanTypeMaximum, 2)
                    . '.',
            ]);
        }
    }
}