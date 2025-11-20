<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LoanRepaymentImportController extends Controller
{
    /**
     * Show CSV import interface
     */
    public function index()
    {
        return view('loans.import_repayments');
    }

    /**
     * Handle Dropzone upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:4096',
        ]);

        $path = $request->file('file')->store('temp');

        return response()->json([
            'status' => 'success',
            'path' => $path
        ]);
    }

    /**
     * Process CSV and insert repayments
     */
    public function process(Request $request)
    {
        $path = $request->input('path');

        if (!$path) {
            return back()->with('error', 'No file uploaded. Please upload a CSV file first.');
        }

        $filePath = storage_path("app/" . $path);

        if (!file_exists($filePath)) {
            return back()->with('error', 'Uploaded CSV file cannot be found. Upload it again.');
        }

        $rows = array_map('str_getcsv', file($filePath));

        if (count($rows) < 2) {
            return back()->with('error', 'CSV file is empty or has no data rows.');
        }

        // Extract header row
        $header = array_map('trim', $rows[0]);
        unset($rows[0]); // remove header

        $errors = [];
        $successCount = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if (count($row) !== count($header)) {
                    $errors[] = "Row " . ($index + 2) . " has incorrect number of columns.";
                    continue;
                }

                // Combine header with row data
                $data = array_combine($header, $row);

                // Basic validation
                if (!is_numeric($data['loan_id']) || $data['loan_id'] <= 0) {
                    $errors[] = "Row " . ($index + 2) . " invalid loan_id.";
                    continue;
                }

                if (!is_numeric($data['amount']) || $data['amount'] < 0) {
                    $errors[] = "Row " . ($index + 2) . " invalid amount.";
                    continue;
                }

                if (!is_numeric($data['interest']) || $data['interest'] < 0) {
                    $errors[] = "Row " . ($index + 2) . " invalid interest.";
                    continue;
                }

                if (!strtotime($data['paid_on'])) {
                    $errors[] = "Row " . ($index + 2) . " invalid date format (paid_on).";
                    continue;
                }

                // Check if loan exists
                $loanExists = DB::table('sacco_loans')
                    ->where('loan_id', $data['loan_id'])
                    ->exists();

                if (!$loanExists) {
                    $errors[] = "Row " . ($index + 2) . " loan_id " . $data['loan_id'] . " not found.";
                    continue;
                }

                // All good → Insert into sacco_loan_payments
                DB::table('sacco_loan_payments')->insert([
                    'loan_payments_amount'     => $data['amount'],
                    'loan_payments_interest'   => $data['interest'],
                    'loan_payments_description'=> $data['description'],
                    'loan_payments_docno'      => $data['docno'],
                    'loan_payments_paid_in_by' => 'CSV Import',
                    'loan_payments_period'     => date('Ym', strtotime($data['paid_on'])),
                    'loan_payments_paid_on'    => $data['paid_on'],
                    'loan_payments_loan_id'    => $data['loan_id'],
                    'loan_end_month_proc'      => 'N',
                    'loan_payments_by'         => Auth::id(),
                    'loan_payments_ip'         => $request->ip(),
                ]);

                // Success counter
                $successCount++;
            }

            DB::commit();

        } catch (\Exception $ex) {
            DB::rollBack();
            return back()->with('error', "Processing failed: " . $ex->getMessage());
        }

        $message = "$successCount repayments imported successfully.";

        if (!empty($errors)) {
            $message .= " However, some rows had issues.";
            return back()->with('warning', $message)->with('row_errors', $errors);
        }

        return back()->with('success', $message);
    }

    /**
     * Serve sample CSV download
     */
    public function sample()
    {
        $content =
            "loan_id,amount,interest,description,docno,paid_on\n" .
            "123,5000,300,Monthly repayment,MPESA123X,2025-11-20\n";

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=sample_loan_repayments.csv');
    }
}
