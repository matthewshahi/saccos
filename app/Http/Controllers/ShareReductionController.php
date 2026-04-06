<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShareReductionController extends Controller
{
    protected $recordLimit = 200;

    protected const SHARE_PAID_BY = 'SAVINGS REDUCTION';
    protected const LEDGER_SOURCE = 'Member Savings Reduction';

    public function index(Request $request)
    {
        $rows = DB::table('sacco_shares')
            ->join('sacco_members', 'sacco_shares.share_member_id', '=', 'sacco_members.member_id')
            ->leftJoin('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->leftJoin('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_shares.share_amount_paying', '<', 0)
            ->where('sacco_shares.share_paid_by', self::SHARE_PAID_BY)
            ->orderBy('sacco_shares.share_id', 'desc')
            ->select(
                'sacco_shares.*',
                'sacco_members.member_name',
                'sacco_members.member_sacco_id',
                'sacco_company.company_name',
                'sacco_department.department_name'
            )
            ->limit($this->recordLimit)
            ->get();

        return view('shares.reductions.index', [
            'rows' => $rows,
        ]);
    }

    public function create()
    {
        $currentPeriod = $this->getCurrentPeriod();

        return view('shares.reductions.create', [
            'currentPeriod' => $currentPeriod,
            'defaultPeriod' => $currentPeriod ? $currentPeriod->period_name : date('Ym'),
            'defaultDate'   => date('Y-m-d'),
        ]);
    }

    public function store(Request $request)
    {
        $currentPeriod = $this->getCurrentPeriod();

        if (!$currentPeriod) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'No active period found in sacco_period.']);
        }

        $validator = Validator::make($request->all(), [
            'period'                                 => 'nullable|digits:6',
            'date_paid'                              => 'required|date',
            'amount'                                 => 'required|numeric|min:0.01',
            'document_no'                            => 'required|string|max:100',
            'description'                            => 'required|string|max:255',
            'reference_code'                         => 'nullable|string|max:50',
            'sub_account_id'                         => 'nullable|integer|exists:sacco_sub_account,sub_account_id',
            'account_id'                             => 'nullable|integer|exists:sacco_sub_account,sub_account_id',
            'sub_account_name'                       => 'nullable|string|max:255',
            'member_id'                              => 'nullable',
            'member_ids'                             => 'nullable|array',
            'member_ids.*'                           => 'nullable|integer',
            'apply_to_all'                           => 'nullable|in:0,1',
            'prevent_negative_balances'              => 'nullable|in:0,1',
            'only_members_active_in_selected_period' => 'nullable|in:0,1',
        ], [
            'date_paid.required'   => 'Reduction date is required.',
            'amount.required'      => 'Amount is required.',
            'document_no.required' => 'Document number is required.',
            'description.required' => 'Description is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $period        = trim((string) $request->input('period', $currentPeriod->period_name));
        $datePaid      = $request->input('date_paid');
        $amount        = round((float) $request->input('amount'), 2);
        $documentNo    = trim((string) $request->input('document_no'));
        $description   = trim((string) $request->input('description'));
        $referenceCode = trim((string) $request->input('reference_code', ''));

        $applyToAll                 = $request->boolean('apply_to_all', true);
        $preventNegativeBalances    = $request->boolean('prevent_negative_balances', true);
        $onlyActiveInSelectedPeriod = $request->boolean('only_members_active_in_selected_period', true);

        $periodEndDate = $this->getPeriodEndDate($period);
        if (!$periodEndDate) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Invalid period supplied. Use valid YYYYMM format, for example 202604.']);
        }

        $selectedAccountId = $request->input('sub_account_id', $request->input('account_id'));

        if (!$selectedAccountId && $request->filled('sub_account_name')) {
            $resolvedAccount = $this->resolveAccountFromString($request->input('sub_account_name'));
            if (!$resolvedAccount) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['error' => 'Selected account could not be resolved.']);
            }
            $selectedAccountId = $resolvedAccount->sub_account_id;
        }

        if (!$selectedAccountId) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Please select an account.']);
        }

        $defaultShareAccount = $this->getDefaultAccount('default_share_account');
        if (!$defaultShareAccount) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Default share account is missing in sacco_defaults.']);
        }

        $selectedMemberIds = $this->extractSelectedMemberIds($request);

        if (!$applyToAll && empty($selectedMemberIds)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Please select at least one member, or switch on "Apply to all active, non-deleted members".']);
        }

        $candidateMembers = $this->resolveTargetMembers($applyToAll, $selectedMemberIds);

        if ($candidateMembers->isEmpty()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'No active members found to process.']);
        }

        $skippedByPeriod = collect();
        if ($onlyActiveInSelectedPeriod) {
            [$eligibleMembers, $ineligibleMembers] = $candidateMembers->partition(function ($member) use ($periodEndDate) {
                return $this->memberWasActiveInPeriod($member, $periodEndDate);
            });

            $candidateMembers = $eligibleMembers->values();
            $skippedByPeriod  = $ineligibleMembers->values();
        }

        $skippedByNegativeRule = collect();
        if ($preventNegativeBalances) {
            [$eligibleMembers, $ineligibleMembers] = $candidateMembers->partition(function ($member) use ($amount) {
                return (float) $member->member_total_share >= $amount;
            });

            $candidateMembers = $eligibleMembers->values();
            $skippedByNegativeRule = $ineligibleMembers->values();
        }

        if ($candidateMembers->isEmpty()) {
            $errors = [];

            if ($onlyActiveInSelectedPeriod && $skippedByPeriod->isNotEmpty()) {
                $errors[] = 'No deductions were done because none of the selected members were active in the selected period.';
            }

            if ($preventNegativeBalances && $skippedByNegativeRule->isNotEmpty()) {
                $errors[] = 'No deductions were done because the deduction amount would make the affected member balances go negative.';
            }

            if (empty($errors)) {
                $errors[] = 'No eligible members were found to process.';
            }

            return redirect()->back()
                ->withInput()
                ->withErrors($errors);
        }

        $baseDescription = $referenceCode !== ''
            ? '[' . $referenceCode . '] ' . $description
            : $description;

        DB::beginTransaction();

        try {
            foreach ($candidateMembers as $member) {
                $memberShareDescription = mb_substr(
                    'SAVINGS REDUCTION - ' . $baseDescription,
                    0,
                    100
                );

                DB::table('sacco_shares')->insert([
                    'share_member_id'      => $member->member_id,
                    'share_amount_paying'  => -1 * $amount,
                    'share_paid_by'        => self::SHARE_PAID_BY,
                    'share_period'         => $period,
                    'share_description'    => $memberShareDescription,
                    'share_doc_no'         => $documentNo,
                    'share_date_paid'      => $datePaid,
                    'share_by'             => Auth::id(),
                    'share_ip'             => $request->ip(),
                    'share_end_month_proc' => 'N',
                ]);

                DB::table('sacco_members')
                    ->where('member_id', $member->member_id)
                    ->decrement('member_total_share', $amount);

                $ledgerDescription = 'Savings reduction for '
                    . $member->member_name
                    . ' - ('
                    . $member->member_sacco_id
                    . ') - '
                    . $baseDescription;

                $this->updateSaccoAccountsTrans(
                    $defaultShareAccount,
                    0,
                    $amount,
                    $documentNo,
                    $ledgerDescription,
                    $datePaid,
                    $period,
                    self::LEDGER_SOURCE,
                    $member->member_id
                );

                $this->updateSaccoAccountsTrans(
                    $selectedAccountId,
                    $amount,
                    0,
                    $documentNo,
                    $ledgerDescription,
                    $datePaid,
                    $period,
                    self::LEDGER_SOURCE,
                    $member->member_id
                );
            }

            DB::commit();

            $successMessage = 'Savingss reductions processed successfully for ' . $candidateMembers->count() . ' member(s).';

            $warningParts = [];

            if ($skippedByPeriod->count() > 0) {
                $warningParts[] = $skippedByPeriod->count() . ' member(s) were skipped because they were not active in the selected period.';
            }

            if ($skippedByNegativeRule->count() > 0) {
                $warningParts[] = $skippedByNegativeRule->count() . ' member(s) were skipped because the deduction would have made their share balance go negative.';
            }

            $flashData = [
                'success' => $successMessage,
            ];

            if (!empty($warningParts)) {
                $flashData['warning'] = implode(' ', $warningParts);
            }

            return redirect()
                ->route('shares_reductions.index')
                ->with($flashData);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process savings reductions. ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $row = DB::table('sacco_shares')
            ->join('sacco_members', 'sacco_shares.share_member_id', '=', 'sacco_members.member_id')
            ->leftJoin('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->leftJoin('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_shares.share_id', $id)
            ->select(
                'sacco_shares.*',
                'sacco_members.member_name',
                'sacco_members.member_sacco_id',
                'sacco_company.company_name',
                'sacco_department.department_name'
            )
            ->first();

        if (!$row) {
            return redirect()->route('shares_reductions.index')
                ->withErrors(['error' => 'Reduction record not found.']);
        }

        $ledgerRows = DB::table('sacco_accounts_trans')
            ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_accounts_trans.accounts_trans_doc_no', $row->share_doc_no)
            ->where('sacco_accounts_trans.accounts_trans_member_id', $row->share_member_id)
            ->select(
                'sacco_accounts_trans.*',
                'sacco_sub_account.sub_account_name',
                'sacco_sub_account.sub_account_code',
                'sacco_main_account.main_account_name',
                'sacco_main_account.main_account_code'
            )
            ->orderBy('sacco_accounts_trans.accounts_trans_id', 'asc')
            ->get();

        return view('shares.reductions.show', [
            'row'        => $row,
            'ledgerRows' => $ledgerRows,
        ]);
    }

    public function searchMembers(Request $request)
    {
        $query = trim((string) $request->input('query', $request->input('q', '')));

        $members = DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->where(function ($q) use ($query) {
                if ($query !== '') {
                    $q->where('member_name', 'like', '%' . $query . '%')
                        ->orWhere('member_sacco_id', 'like', '%' . $query . '%')
                        ->orWhere('member_national_id', 'like', '%' . $query . '%')
                        ->orWhere('member_phone_no', 'like', '%' . $query . '%')
                        ->orWhere('member_email', 'like', '%' . $query . '%');
                }
            })
            ->orderBy('member_name')
            ->limit(5)
            ->get([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_total_share',
            ]);

        $results = [];
        foreach ($members as $member) {
            $results[] = [
                'id'       => $member->member_id,
                'label'    => $member->member_name . ' - (' . $member->member_sacco_id . ')',
                'value'    => $member->member_name . ' - (' . $member->member_sacco_id . ')',
                'name'     => $member->member_name,
                'sacco_id' => $member->member_sacco_id,
                'shares'   => (float) $member->member_total_share,
            ];
        }

        return response()->json($results);
    }

    public function searchAccounts(Request $request)
    {
        $query = trim((string) $request->input('query', $request->input('q', '')));

        $accounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->where(function ($q) use ($query) {
                if ($query !== '') {
                    $q->where('sacco_sub_account.sub_account_name', 'like', '%' . $query . '%')
                        ->orWhere('sacco_sub_account.sub_account_code', 'like', '%' . $query . '%')
                        ->orWhere('sacco_main_account.main_account_name', 'like', '%' . $query . '%')
                        ->orWhere('sacco_main_account.main_account_code', 'like', '%' . $query . '%');
                }
            })
            ->orderBy('sacco_sub_account.sub_account_name')
            ->limit(5)
            ->get([
                'sacco_sub_account.sub_account_id',
                'sacco_sub_account.sub_account_name',
                'sacco_sub_account.sub_account_code',
                'sacco_main_account.main_account_name',
                'sacco_main_account.main_account_code',
            ]);

        $results = [];
        foreach ($accounts as $account) {
            $label = $account->sub_account_name . ' - ' . $account->main_account_code . '/' . $account->sub_account_code;

            $results[] = [
                'id'                => $account->sub_account_id,
                'label'             => $label,
                'value'             => $label,
                'sub_account_name'  => $account->sub_account_name,
                'sub_account_code'  => $account->sub_account_code,
                'main_account_name' => $account->main_account_name,
                'main_account_code' => $account->main_account_code,
            ];
        }

        return response()->json($results);
    }

    protected function getCurrentPeriod()
    {
        return DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    protected function getDefaultAccount(string $defaultName)
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $defaultName)
            ->value('default_value');

        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }

    protected function extractSelectedMemberIds(Request $request): array
    {
        $memberIds = collect();

        if ($request->filled('member_id')) {
            if (is_array($request->input('member_id'))) {
                $memberIds = $memberIds->merge($request->input('member_id'));
            } else {
                $memberIds->push($request->input('member_id'));
            }
        }

        if ($request->has('member_ids') && is_array($request->input('member_ids'))) {
            $memberIds = $memberIds->merge($request->input('member_ids'));
        }

        return $memberIds
            ->filter(function ($id) {
                return $id !== null && $id !== '';
            })
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveTargetMembers(bool $applyToAll, array $selectedMemberIds = [])
    {
        $query = DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y');

        if (!$applyToAll) {
            $query->whereIn('member_id', $selectedMemberIds);
        }

        return $query->orderBy('member_name')->get([
            'member_id',
            'member_name',
            'member_sacco_id',
            'member_total_share',
            'member_date_joined',
        ]);
    }

    protected function getPeriodEndDate(string $period): ?Carbon
    {
        $period = trim($period);

        if (!preg_match('/^\d{6}$/', $period)) {
            return null;
        }

        $year  = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        try {
            return Carbon::createFromDate($year, $month, 1)->endOfMonth()->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function memberWasActiveInPeriod($member, Carbon $periodEndDate): bool
    {
        if (empty($member->member_date_joined)) {
            return false;
        }

        try {
            return Carbon::parse($member->member_date_joined)->startOfDay()->lte($periodEndDate);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function resolveAccountFromString(string $accountString)
    {
        $accountString = trim($accountString);

        if ($accountString === '' || mb_strpos($accountString, ' - ') === false) {
            return null;
        }

        [$subAccountName, $codes] = explode(' - ', $accountString, 2);

        if (mb_strpos($codes, '/') === false) {
            return null;
        }

        [$mainCode, $subCode] = explode('/', $codes, 2);

        return DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->where('sacco_sub_account.sub_account_name', trim($subAccountName))
            ->where('sacco_sub_account.sub_account_code', trim($subCode))
            ->where('sacco_main_account.main_account_code', trim($mainCode))
            ->select('sacco_sub_account.sub_account_id')
            ->first();
    }

    protected function updateSaccoAccountsTrans(
        int $subAccountId,
        float $debit,
        float $credit,
        string $docNo,
        string $description,
        string $date,
        string $period,
        string $sourceDescription,
        ?int $memberId = null
    ) {
        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $subAccountId,
            'accounts_trans_period'      => $period,
            'accounts_trans_debit'       => $debit,
            'accounts_trans_credit'      => $credit,
            'accounts_trans_doc_no'      => $docNo,
            'accounts_trans_decription'  => $description,
            'accounts_trans_source'      => $sourceDescription,
            'accounts_trans_dat_date'    => $date,
            'accounts_trans_user_id'     => Auth::id(),
            'accounts_trans_ip'          => request()->ip(),
            'accounts_trans_member_id'   => $memberId,
            'accounts_trans_app_name'    => 'iSacco',
        ]);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_debit', $debit);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_credit', $credit);

        $mainAccountId = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->value('sub_account_main_account');

        if ($mainAccountId) {
            DB::table('sacco_main_account')
                ->where('main_account_id', $mainAccountId)
                ->increment('main_account_debit', $debit);

            DB::table('sacco_main_account')
                ->where('main_account_id', $mainAccountId)
                ->increment('main_account_credit', $credit);
        }
    }
}