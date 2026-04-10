public function updateLoanApplication(Request $request, $id)
{
    $isApiRequest = $request->expectsJson()
        || $request->wantsJson()
        || $request->input('context') === 'api'
        || $request->is('api/*');

    $respondError = function (string $message, int $status = 400, array $errors = []) use ($request, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);
        }

        return redirect()->back()
            ->withErrors(!empty($errors) ? $errors : ['error' => $message])
            ->withInput();
    };

    $respondSuccess = function (string $message, array $payload = []) use ($id, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
                'batch_trans_id' => (int) $id,
            ], $payload), 200);
        }

        return redirect()
            ->route('loans.pending.approval.selfedit', ['id' => $id])
            ->with('success', $message);
    };

    /*
    |--------------------------------------------------------------------------
    | 1. Resolve trusted member principal
    |--------------------------------------------------------------------------
    | API/mobile: $request->user() is authoritative
    | Web: fall back to session user if it carries member_id
    |--------------------------------------------------------------------------
    */
    $principal = $request->user();

    if (!$principal || !isset($principal->member_id)) {
        $principal = Auth::user();
    }

    $trustedMemberId = isset($principal->member_id) ? (int) $principal->member_id : null;
    $actorUserId = Auth::id();

    if (!$trustedMemberId) {
        return $respondError('Unauthenticated.', 401);
    }

    DB::beginTransaction();

    try {
        /*
        |--------------------------------------------------------------------------
        | 2. Load only the member’s own pending, non-deleted application
        |--------------------------------------------------------------------------
        */
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->first();

        if (!$loan) {
            DB::rollBack();
            return $respondError('Loan not found, already processed, or unauthorized.', 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Normalize both web and API payload names
        |--------------------------------------------------------------------------
        */
        $normalized = [
            'batch_trans_loan_amount' => $request->input(
                'batch_trans_loan_amount',
                $request->input('amount', $loan->batch_trans_loan_amount)
            ),
            'batch_trans_loan_type' => $request->input(
                'batch_trans_loan_type',
                $request->input('loan_type_id', $loan->batch_trans_loan_type)
            ),
            'batch_trans_loan_category' => $request->input(
                'batch_trans_loan_category',
                $request->input('loan_category_id', $loan->batch_trans_loan_category)
            ),
            'batch_trans_loan_duration' => $request->input(
                'batch_trans_loan_duration',
                $request->input('duration_months', $loan->batch_trans_loan_duration)
            ),
            'batch_trans_description' => $request->input(
                'batch_trans_description',
                $request->input('reason', $loan->batch_trans_description)
            ),
        ];

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_loan_to_top_up')) {
            $normalized['batch_trans_loan_to_top_up'] = $request->input(
                'batch_trans_loan_to_top_up',
                $request->input('topup_loan_id', $loan->batch_trans_loan_to_top_up ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $normalized['batch_trans_payroll_number'] = $request->input(
                'batch_trans_payroll_number',
                $request->input('payroll_number', $loan->batch_trans_payroll_number ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $normalized['batch_trans_present_designation'] = $request->input(
                'batch_trans_present_designation',
                $request->input('designation', $loan->batch_trans_present_designation ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $normalized['batch_trans_terms_of_employment'] = $request->input(
                'batch_trans_terms_of_employment',
                $request->input('employment_terms', $loan->batch_trans_terms_of_employment ?? null)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Validate without redirect-only behaviour
        |--------------------------------------------------------------------------
        */
        $validationPayload = array_merge($request->all(), $normalized);

        $validator = \Illuminate\Support\Facades\Validator::make($validationPayload, [
            'batch_trans_loan_amount' => 'required|numeric|min:1',
            'batch_trans_loan_type' => 'required|integer|exists:sacco_loan_types,loan_type_id',
            'batch_trans_loan_category' => 'required|integer|exists:sacco_loan_category,loan_category_id',
            'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
            'batch_trans_description' => 'required|string|max:50',
            'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
            'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_payroll_number' => 'nullable|string|max:50',
            'batch_trans_present_designation' => 'nullable|string|max:100',
            'batch_trans_terms_of_employment' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            DB::rollBack();
            return $respondError('Validation failed.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | 5. Fetch authoritative loan type / category / member
        |--------------------------------------------------------------------------
        */
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $validated['batch_trans_loan_type'])
            ->where('loan_type_deleted', '<>', 'Y')
            ->first();

        if (!$loanType) {
            DB::rollBack();
            return $respondError('Invalid loan type.', 422, [
                'batch_trans_loan_type' => ['Invalid loan type.'],
            ]);
        }

        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $validated['batch_trans_loan_category'])
            ->where('loan_category_deleted', '<>', 'Y')
            ->first();

        if (!$loanCategory) {
            DB::rollBack();
            return $respondError('Invalid loan category.', 422, [
                'batch_trans_loan_category' => ['Invalid loan category.'],
            ]);
        }

        $member = DB::table('sacco_members')
            ->where('member_id', $trustedMemberId)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        if (!$member) {
            DB::rollBack();
            return $respondError('Invalid member.', 422, [
                'member' => ['Invalid member.'],
            ]);
        }

        $loanAmount = round((float) $validated['batch_trans_loan_amount'], 2);
        $loanDuration = (int) $validated['batch_trans_loan_duration'];

        /*
        |--------------------------------------------------------------------------
        | 6. Validate optional top-up loan ownership
        |--------------------------------------------------------------------------
        */
        $topUpLoan = null;
        $topUpLoanId = $validated['batch_trans_loan_to_top_up'] ?? null;

        if (!empty($topUpLoanId)) {
            $topUpLoan = DB::table('sacco_loans')
                ->where('loan_id', $topUpLoanId)
                ->where('loan_member', $trustedMemberId)
                ->first();

            if (!$topUpLoan) {
                DB::rollBack();
                return $respondError('Invalid top-up loan.', 422, [
                    'batch_trans_loan_to_top_up' => ['Invalid top-up loan.'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Re-run business validations
        |--------------------------------------------------------------------------
        */
        $nmsg = '';
        $nmsg .= $this->validateLoanParameters($loanAmount, $loanType, $loanDuration, $topUpLoan);
        $nmsg .= $this->validateMemberEligibility($member, $loanType);

        if (!empty($topUpLoan)) {
            $topUpOutstanding = (float) (($topUpLoan->loan_amount ?? 0) - ($topUpLoan->loan_loan_paid ?? 0));
            if ($loanAmount <= $topUpOutstanding) {
                $nmsg .= 'Invalid top-up loan. ';
            }
        }

        if (!empty($nmsg)) {
            DB::rollBack();
            return $respondError(trim($nmsg), 422, [
                'loan' => [trim($nmsg)],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Validate fresh guarantors before updating loan
        |--------------------------------------------------------------------------
        */
        $guarantorCheck = $this->validateFreshGuarantorsForEdit(
            $request->all(),
            $loanType,
            $trustedMemberId,
            $loanAmount
        );

        if (!$guarantorCheck['success']) {
            DB::rollBack();
            return $respondError(trim($guarantorCheck['message']), 422, [
                'guarantors' => [trim($guarantorCheck['message'])],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Recalculate self-service financials using existing saved charges
        |--------------------------------------------------------------------------
        | ADD_TO_LOAN charges must affect EMI/interest.
        | DEDUCT_FROM_DISBURSEMENT charges affect net cash, not EMI base.
        |--------------------------------------------------------------------------
        */
        $chargeSummary = $this->getSelfServiceChargeSummary($loan->batch_trans_id);
        $addToLoanTotal = round((float) ($chargeSummary['add_to_loan'] ?? 0), 2);
        $existingCommission = round((float) ($loan->batch_trans_commission ?? 0), 2);

        $amountForEmi = round($loanAmount + $addToLoanTotal, 2);

        $financials = $this->calculateSaccoLoanFinancials(
            $loanAmount,
            $amountForEmi,
            $loanDuration,
            $loanType,
            $existingCommission
        );

        /*
        |--------------------------------------------------------------------------
        | 9. Handle optional payslip uploads
        |--------------------------------------------------------------------------
        */
        $payslip1Path = $loan->batch_trans_payslip1 ?? null;
        if ($request->hasFile('batch_trans_pay1')) {
            $payslip1Path = $request->file('batch_trans_pay1')->store('uploads/payslips');
        }

        $payslip2Path = $loan->batch_trans_payslip2 ?? null;
        if ($request->hasFile('batch_trans_pay2')) {
            $payslip2Path = $request->file('batch_trans_pay2')->store('uploads/payslips');
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Update only this member’s own pending application
        |--------------------------------------------------------------------------
        */
        $updateData = [
            'batch_trans_loan_amount' => $loanAmount,
            'batch_trans_loan_type' => (int) $validated['batch_trans_loan_type'],
            'batch_trans_loan_category' => (int) $validated['batch_trans_loan_category'],
            'batch_trans_loan_duration' => $loanDuration,
            'batch_trans_description' => $validated['batch_trans_description'],
            'batch_trans_insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'batch_trans_monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'batch_trans_monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'batch_trans_expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
            'batch_trans_payslip1' => $payslip1Path,
            'batch_trans_payslip2' => $payslip2Path,
            'batch_trans_ip' => $request->ip(),
            'batch_trans_loan_guaranteed' => round((float) ($guarantorCheck['total_guaranteed'] ?? 0), 2),
        ];

        if ($actorUserId) {
            $updateData['batch_trans_by'] = $actorUserId;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_loan_to_top_up')) {
            $updateData['batch_trans_loan_to_top_up'] = !empty($topUpLoanId) ? (int) $topUpLoanId : null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $updateData['batch_trans_payroll_number'] = $validated['batch_trans_payroll_number'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $updateData['batch_trans_present_designation'] = $validated['batch_trans_present_designation'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $updateData['batch_trans_terms_of_employment'] = $validated['batch_trans_terms_of_employment'] ?? null;
        }

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->update($updateData);

        DB::commit();

        /*
        |--------------------------------------------------------------------------
        | 11. Replace old guarantors with fresh validated guarantors
        |--------------------------------------------------------------------------
        */
        $this->replaceLoanGuarantorsForEdit(
            (int) $id,
            $guarantorCheck['guarantors'],
            $actorUserId,
            $request->ip(),
            now(),
            (string) ($member->member_name ?? '')
        );

        return $respondSuccess('Loan updated successfully.', [
            'insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to update self-service loan application', [
            'batch_trans_id' => $id,
            'member_id' => $trustedMemberId,
            'user_id' => $actorUserId,
            'message' => $e->getMessage(),
        ]);

        return $respondError('Failed to update loan application. ' . $e->getMessage(), 500);
    }
}