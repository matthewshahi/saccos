<?php

                namespace App\Http\Controllers;
                
                use Illuminate\Support\Facades\DB;
                
                class LoanLedgerController extends Controller
                {
                    public function importLoansToLedger()
                    {
                        // Fetch loan types for mapping
                        $loanTypes = DB::table('sacco_loan_types')->get()->keyBy('loan_type_id');
                
                        // Fetch members for mapping
                        $members = DB::table('sacco_members')->get()->keyBy('member_id');
                
                        // Loan data (replace this with actual parsed data from the uploaded file or database)
                        $loans = [
                            [
                                "loan_id" => 2600,
                                "loan_member" => 358,
                                "loan_loan_type" => 12,
                                "loan_loan_category" => 8,
                                "loan_amount" => 50500,
                                "loan_insurance" => 500,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202412,
                                "loan_payment_period" => 6,
                                "loan_interest_payable" => 1784.0,
                                "loan_monthly_repayment_amount" => 8714,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 50000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2515",
                                "loan_batch_no" => "Self Applied Loan-2515-KANGOGO",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2601,
                                "loan_member" => 302,
                                "loan_loan_type" => 12,
                                "loan_loan_category" => 8,
                                "loan_amount" => 35350,
                                "loan_insurance" => 350,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202412,
                                "loan_payment_period" => 8,
                                "loan_interest_payable" => 1610.0,
                                "loan_monthly_repayment_amount" => 4620,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 35000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2514",
                                "loan_batch_no" => "Self Applied Loan-2514-MUGO",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2602,
                                "loan_member" => 353,
                                "loan_loan_type" => 13,
                                "loan_loan_category" => 8,
                                "loan_amount" => 90900,
                                "loan_insurance" => 900,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202412,
                                "loan_payment_period" => 12,
                                "loan_interest_payable" => 6024.0,
                                "loan_monthly_repayment_amount" => 0,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 90000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2512",
                                "loan_batch_no" => "Self Applied Loan-2512-NDEGE",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2603,
                                "loan_member" => 372,
                                "loan_loan_type" => 13,
                                "loan_loan_category" => 8,
                                "loan_amount" => 30300,
                                "loan_insurance" => 300,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202501,
                                "loan_payment_period" => 6,
                                "loan_interest_payable" => 1069.293068,
                                "loan_monthly_repayment_amount" => 5229,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 30000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2524",
                                "loan_batch_no" => "Self Applied Loan-2524-MUTUVI",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2604,
                                "loan_member" => 25,
                                "loan_loan_type" => 13,
                                "loan_loan_category" => 8,
                                "loan_amount" => 121200,
                                "loan_insurance" => 1200,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202501,
                                "loan_payment_period" => 12,
                                "loan_interest_payable" => 8021.678254,
                                "loan_monthly_repayment_amount" => 10769,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 120000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2519",
                                "loan_batch_no" => "Self Applied Loan-2519-OKUMU",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2605,
                                "loan_member" => 37,
                                "loan_loan_type" => 16,
                                "loan_loan_category" => 9,
                                "loan_amount" => 797900,
                                "loan_insurance" => 7900,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202501,
                                "loan_payment_period" => 60,
                                "loan_interest_payable" => 263650.5532,
                                "loan_monthly_repayment_amount" => 37722,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 780000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2523",
                                "loan_batch_no" => "Self Applied Loan-2523-NJUGUNA",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2606,
                                "loan_member" => 217,
                                "loan_loan_type" => 16,
                                "loan_loan_category" => 8,
                                "loan_amount" => 181800,
                                "loan_insurance" => 1800,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202412,
                                "loan_payment_period" => 24,
                                "loan_interest_payable" => 23592.0,
                                "loan_monthly_repayment_amount" => 8558,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 180000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2493",
                                "loan_batch_no" => "Self Applied Loan-2493-MAIYO",
                                "loan_start_deduction_period" => null
                            ],
                            [
                                "loan_id" => 2607,
                                "loan_member" => 217,
                                "loan_loan_type" => 16,
                                "loan_loan_category" => 8,
                                "loan_amount" => 181800,
                                "loan_insurance" => 1800,
                                "loan_commision" => 0,
                                "loan_taken_period" => 202412,
                                "loan_payment_period" => 24,
                                "loan_interest_payable" => 23592.0,
                                "loan_monthly_repayment_amount" => 8558,
                                "loan_monthly_repayment_principal" => 0,
                                "loan_amount_guaranteed" => 180000,
                                "loan_loan_paid" => 0,
                                "loan_doc_no" => null,
                                "loan_description" => "Self Application - Batch No: 2493",
                                "loan_batch_no" => "Self Applied Loan-2493-MAIYO",
                                "loan_start_deduction_period" => null
                            ]
                        ];
                
                        // Process each loan
                        foreach ($loans as $loan) {
                            $loanType = $loanTypes[$loan['loan_loan_type']] ?? null;
                            $member = $members[$loan['loan_member']] ?? null;
                
                            if (!$loanType || !$member) {
                                continue; // Skip if loan type or member is not found
                            }
                
                            // Format the transaction date (15th of the month based on loan_taken_period)
                            $loanTakenPeriod = (string) $loan['loan_taken_period'];
                            $transactionDate = sprintf(
                                '%04d-%02d-15',
                                substr($loanTakenPeriod, 0, 4), // Year
                                substr($loanTakenPeriod, 4, 2)  // Month
                            );
                
                            // Loan type and member details
                            $loanName = $loanType->loan_type_name ?? 'Unknown Loan Type';
                            $loanAccount = $loanType->loan_type_acount ?? null;
                            $memberName = $member->member_name ?? 'Unknown Member';
                
                            if (!$loanAccount) {
                                continue; // Skip if loan account is not found
                            }
                
                            // Ledger entries
                            // Credit Cash at Bank
                            DB::table('sacco_accounts_trans')->insert([
                                'accounts_trans_sub_account' => '5',
                                'accounts_trans_debit' => 0,
                                'accounts_trans_credit' => $loan['loan_amount'] - $loan['loan_insurance'],
                                'accounts_trans_doc_no' => "Loan ID" . $loan['loan_id'],
                                'accounts_trans_period' => $loan['loan_taken_period'],
                                'accounts_trans_dat_date' => $transactionDate,
                                'accounts_trans_decription' => "Loan disbursement ({$loanName}) for {$memberName}",
                            ]);
                
                            // Credit Insurance Account (if applicable)
                            if ($loan['loan_insurance'] > 0) {
                                DB::table('sacco_accounts_trans')->insert([
                                    'accounts_trans_sub_account' => '62',
                                    'accounts_trans_debit' => 0,
                                    'accounts_trans_credit' => $loan['loan_insurance'],
                                    'accounts_trans_doc_no' => "Loan ID" . $loan['loan_id'],
                                    'accounts_trans_period' => $loan['loan_taken_period'],
                                    'accounts_trans_dat_date' => $transactionDate,
                                    'accounts_trans_decription' => "Insurance deduction ({$loanName}) for {$memberName}",
                                ]);
                            }
                
                            // Debit Loan Account
                            DB::table('sacco_accounts_trans')->insert([
                                'accounts_trans_sub_account' => $loanAccount,
                                'accounts_trans_debit' => $loan['loan_amount'],
                                'accounts_trans_credit' => 0,
                                'accounts_trans_doc_no' => "Loan ID" . $loan['loan_id'],
                                'accounts_trans_period' => $loan['loan_taken_period'],
                                'accounts_trans_dat_date' => $transactionDate,
                                'accounts_trans_decription' => "Loan disbursement ({$loanName}) for {$memberName}",
                            ]);
                        }
                
                        return response()->json(['message' => 'Loans imported and ledger updated successfully.']);
                    }
                }