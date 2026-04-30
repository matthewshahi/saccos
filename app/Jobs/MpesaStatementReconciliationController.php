<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MpesaStatementReconciliationController extends Controller
{
    public function index()
    {
        return view('mpesa.statement_reconciliation');
    }

    public function store(Request $request)
    {
        $request->validate([
            'statement_file' => 'required|file|max:300|mimes:xlsx,xls,csv,txt',
            'sheet_name' => 'nullable|string|max:150',
        ]);

        $file = $request->file('statement_file');
        $extension = strtolower($file->getClientOriginalExtension());

        $path = $file->storeAs(
            'mpesa_statement_reconciliation',
            'statement_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension
        );

        $fullPath = storage_path('app/' . $path);

        $summary = [
            'total_rows_seen' => 0,
            'valid_paybill_rows' => 0,
            'inserted' => 0,
            'skipped_existing_c2b' => 0,
            'skipped_existing_stk_response' => 0,
            'skipped_missing_data' => 0,
            'skipped_no_matching_stk' => 0,
            'skipped_conflict' => 0,
            'skipped_not_completed' => 0,
            'skipped_not_paybill' => 0,
            'errors' => 0,
            'rows' => [],
        ];

        try {
            $rows = $this->readStatementRows($fullPath, $extension, $request->input('sheet_name'));

            foreach ($rows as $rowNumber => $row) {
                $summary['total_rows_seen']++;

                try {
                    $receipt = $this->normalizeReceipt($row['receipt_no'] ?? null);
                    $completionTime = $this->parseCompletionTime($row['completion_time'] ?? null);
                    $details = trim((string) ($row['details'] ?? ''));
                    $status = strtoupper(trim((string) ($row['transaction_status'] ?? '')));
                    $paidIn = $this->normalizeAmount($row['paid_in'] ?? null);
                    $withdrawn = $this->normalizeAmount($row['withdrawn'] ?? null);
                    $transactionType = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
                    $otherParty = trim((string) ($row['other_party'] ?? ''));

                    if ($transactionType !== '' && stripos($transactionType, 'PAY BILL') === false) {
                        $summary['skipped_not_paybill']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, null, $paidIn, 'skipped_not_paybill', 'Not a Pay Bill transaction.');
                        continue;
                    }

                    if ($status !== '' && $status !== 'COMPLETED') {
                        $summary['skipped_not_completed']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, null, $paidIn, 'skipped_not_completed', 'Transaction status is not Completed.');
                        continue;
                    }

                    if ($withdrawn !== null && $withdrawn > 0) {
                        $summary['skipped_not_paybill']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, null, $paidIn, 'skipped_withdrawal', 'Row is a withdrawal/outgoing transaction.');
                        continue;
                    }

                    $accountReference = $this->extractAccountReference($details);
                    $maskedPhone = $this->extractMaskedPhone($details);
                    $customerName = $this->extractCustomerName($details);
                    $shortcode = $this->extractShortcode($otherParty);

                    if (!$receipt || !$completionTime || !$accountReference || !$paidIn || $paidIn <= 0) {
                        $summary['skipped_missing_data']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_missing_data', 'Receipt, completion time, account reference or amount is missing.');
                        continue;
                    }

                    $summary['valid_paybill_rows']++;

                    if ($this->existsInC2B($receipt)) {
                        $summary['skipped_existing_c2b']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_existing_c2b', 'Receipt already exists in c2b_payments.');
                        continue;
                    }

                    if ($this->existsInStkResponses($receipt)) {
                        $summary['skipped_existing_stk_response']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_existing_stk_response', 'Receipt already exists in stk_push_responses.');
                        continue;
                    }

                    $stkLog = $this->findMatchingStkLog($accountReference, $paidIn, $completionTime, $maskedPhone, $shortcode);

                    if (!$stkLog) {
                        $summary['skipped_no_matching_stk']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_no_matching_stk', 'No matching STK log found by account, amount and time.');
                        continue;
                    }

                    if (empty($stkLog->checkout_request_id) || empty($stkLog->phone_number)) {
                        $summary['skipped_missing_data']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_missing_stk_data', 'Matching STK log lacks checkout_request_id or phone_number.');
                        continue;
                    }

                    $existingCheckout = DB::table('stk_push_responses')
                        ->where('checkout_request_id', $stkLog->checkout_request_id)
                        ->first();

                    if ($existingCheckout && !empty($existingCheckout->mpesa_receipt_number)) {
                        $existingReceipt = $this->normalizeReceipt($existingCheckout->mpesa_receipt_number);

                        if ($existingReceipt !== $receipt) {
                            $summary['skipped_conflict']++;
                            $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_conflict', 'CheckoutRequestID already has a different receipt.');
                            continue;
                        }
                    }

                    DB::transaction(function () use (
                        $stkLog,
                        $receipt,
                        $completionTime,
                        $paidIn,
                        $accountReference,
                        $customerName,
                        $details,
                        $existingCheckout
                    ) {
                        $payload = [
                            'unique_number' => $accountReference,
                            'merchant_request_id' => $stkLog->merchant_request_id ?? null,
                            'checkout_request_id' => $stkLog->checkout_request_id,
                            'result_code' => 0,
                            'result_description' => 'Payment imported from M-Pesa Business statement.',
                            'mpesa_receipt_number' => $receipt,
                            'transaction_date' => $completionTime['eat_string'],
                            'phone_number' => $stkLog->phone_number,
                            'amount' => $paidIn,
                            'processed' => 'N',
                            'processed_date' => null,
                            'updated_at' => now(),
                        ];

                        if ($existingCheckout) {
                            DB::table('stk_push_responses')
                                ->where('id', $existingCheckout->id)
                                ->update($payload);

                            $stkResponseId = $existingCheckout->id;
                        } else {
                            $payload['created_at'] = now();

                            $stkResponseId = DB::table('stk_push_responses')->insertGetId($payload);
                        }

                        $this->updateStkLogAfterStatementImport($stkLog, $receipt, $completionTime['eat_string']);

                        $this->writeReconAuditIfAvailable($stkLog, [
                            'stk_push_response_id' => $stkResponseId,
                            'unique_number' => $accountReference,
                            'recon_status' => 'statement_import_success',
                            'recon_source' => 'mpesa_business_statement',
                            'match_confidence' => 'high',
                            'safaricom_result_code' => '0',
                            'safaricom_result_description' => 'Payment imported from M-Pesa Business statement.',
                            'mpesa_receipt_number' => $receipt,
                            'transaction_time' => $completionTime['eat_string'],
                            'raw_response' => json_encode([
                                'receipt' => $receipt,
                                'account_reference' => $accountReference,
                                'amount' => $paidIn,
                                'customer_name' => $customerName,
                                'details' => $details,
                            ]),
                            'notes' => 'Statement import inserted/updated stk_push_responses with processed=N.',
                        ]);
                    });

                    $summary['inserted']++;
                    $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'inserted', 'Inserted into stk_push_responses with processed=N.');
                } catch (Exception $e) {
                    $summary['errors']++;

                    Log::error('M-Pesa statement reconciliation row failed', [
                        'row_number' => $rowNumber,
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);

                    $this->addRowResult($summary, $rowNumber, $row['receipt_no'] ?? null, null, null, 'error', $e->getMessage());
                }
            }
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['statement_file' => $e->getMessage()]);
        }

        return redirect()
            ->route('mpesa.statement.reconciliation.index')
            ->with('mpesa_statement_recon_summary', $summary);
    }

    private function readStatementRows(string $path, string $extension, ?string $sheetName = null): array
    {
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readExcelRows($path, $sheetName);
        }

        return $this->readCsvRows($path);
    }

    private function readExcelRows(string $path, ?string $sheetName = null): array
    {
        $spreadsheet = IOFactory::load($path);

        $worksheet = null;

        if ($sheetName) {
            $worksheet = $spreadsheet->getSheetByName($sheetName);
        }

        if (!$worksheet) {
            $worksheet = $spreadsheet->getSheetByName('M-PESA FULL STATEMENT - Utility');
        }

        if (!$worksheet) {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                if (stripos($sheet->getTitle(), 'utility') !== false) {
                    $worksheet = $sheet;
                    break;
                }
            }
        }

        if (!$worksheet) {
            $worksheet = $spreadsheet->getActiveSheet();
        }

        $rawRows = $worksheet->toArray(null, true, true, true);

        return $this->normalizeStatementRows($rawRows);
    }

    private function readCsvRows(string $path): array
    {
        $rawRows = [];
        $handle = fopen($path, 'r');

        if (!$handle) {
            throw new Exception('Unable to open CSV file.');
        }

        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];

            foreach ($data as $index => $value) {
                $row[$index] = $value;
            }

            $rawRows[$rowNumber] = $row;
            $rowNumber++;
        }

        fclose($handle);

        return $this->normalizeStatementRows($rawRows);
    }

    private function normalizeStatementRows(array $rawRows): array
    {
        $headerRowNumber = null;
        $headerMap = [];

        foreach ($rawRows as $rowNumber => $row) {
            $normalizedValues = [];

            foreach ($row as $column => $value) {
                $normalizedValues[$column] = $this->normalizeHeader($value);
            }

            if (in_array('receipt_no', $normalizedValues, true)
                && in_array('completion_time', $normalizedValues, true)) {
                $headerRowNumber = $rowNumber;

                foreach ($normalizedValues as $column => $normalizedHeader) {
                    if ($normalizedHeader !== '') {
                        $headerMap[$normalizedHeader] = $column;
                    }
                }

                break;
            }
        }

        if (!$headerRowNumber) {
            throw new Exception('Could not find statement header row. Expected columns like Receipt No and Completion Time.');
        }

        $rows = [];

        foreach ($rawRows as $rowNumber => $row) {
            if ($rowNumber <= $headerRowNumber) {
                continue;
            }

            $receipt = $this->valueFromMappedRow($row, $headerMap, ['receipt_no', 'receipt_number', 'receipt']);
            $completionTime = $this->valueFromMappedRow($row, $headerMap, ['completion_time']);
            $details = $this->valueFromMappedRow($row, $headerMap, ['details']);
            $status = $this->valueFromMappedRow($row, $headerMap, ['transaction_status', 'status']);
            $paidIn = $this->valueFromMappedRow($row, $headerMap, ['paid_in', 'paidin']);
            $withdrawn = $this->valueFromMappedRow($row, $headerMap, ['withdrawn']);
            $transactionType = $this->valueFromMappedRow($row, $headerMap, ['transaction_type']);
            $otherParty = $this->valueFromMappedRow($row, $headerMap, ['other_party']);

            if (
                trim((string) $receipt) === ''
                && trim((string) $completionTime) === ''
                && trim((string) $details) === ''
                && trim((string) $paidIn) === ''
            ) {
                continue;
            }

            $rows[$rowNumber] = [
                'receipt_no' => $receipt,
                'completion_time' => $completionTime,
                'details' => $details,
                'transaction_status' => $status,
                'paid_in' => $paidIn,
                'withdrawn' => $withdrawn,
                'transaction_type' => $transactionType,
                'other_party' => $otherParty,
            ];
        }

        return $rows;
    }

    private function valueFromMappedRow(array $row, array $headerMap, array $possibleHeaders)
    {
        foreach ($possibleHeaders as $header) {
            if (isset($headerMap[$header])) {
                $column = $headerMap[$header];

                return $row[$column] ?? null;
            }
        }

        return null;
    }

    private function normalizeHeader($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim($value, '_');

        if ($value === 'receipt_no') {
            return 'receipt_no';
        }

        if ($value === 'completion_time') {
            return 'completion_time';
        }

        return $value;
    }

    private function normalizeReceipt($receipt): ?string
    {
        $receipt = strtoupper(trim((string) $receipt));

        return $receipt !== '' ? $receipt : null;
    }

    private function normalizeAmount($amount): ?float
    {
        if ($amount === null) {
            return null;
        }

        $amount = trim((string) $amount);

        if ($amount === '' || $amount === '-') {
            return null;
        }

        $amount = str_replace([',', 'KES', 'KSH', ' '], '', strtoupper($amount));

        if (!is_numeric($amount)) {
            return null;
        }

        return round((float) $amount, 2);
    }

    private function parseCompletionTime($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $dateTime = ExcelDate::excelToDateTimeObject((float) $value);
            $eat = Carbon::instance($dateTime)->timezone('Africa/Nairobi');

            return [
                'eat' => $eat,
                'eat_string' => $eat->format('Y-m-d H:i:s'),
                'utc' => $eat->copy()->timezone('UTC'),
            ];
        }

        $value = trim((string) $value);

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/y H:i:s',
            'd/m/y H:i',
            'd/m/Y h:i:s A',
            'd/m/Y h:i A',
            'd/m/y h:i:s A',
            'd/m/y h:i A',
            'j/n/y g:i A',
            'j/n/Y g:i A',
        ];

        foreach ($formats as $format) {
            try {
                $eat = Carbon::createFromFormat($format, $value, 'Africa/Nairobi');

                if ($eat) {
                    return [
                        'eat' => $eat,
                        'eat_string' => $eat->format('Y-m-d H:i:s'),
                        'utc' => $eat->copy()->timezone('UTC'),
                    ];
                }
            } catch (Exception $e) {
                // try next format
            }
        }

        try {
            $eat = Carbon::parse($value, 'Africa/Nairobi');

            return [
                'eat' => $eat,
                'eat_string' => $eat->format('Y-m-d H:i:s'),
                'utc' => $eat->copy()->timezone('UTC'),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function extractAccountReference(string $details): ?string
    {
        if (preg_match('/\bAcc\.?\s*([A-Za-z0-9\-\/]+)/i', $details, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if (preg_match('/\bAccount\.?\s*[:\-]?\s*([A-Za-z0-9\-\/]+)/i', $details, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    private function extractMaskedPhone(string $details): ?string
    {
        if (preg_match('/from\s+([0-9\*]+)/i', $details, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractCustomerName(string $details): ?string
    {
        if (preg_match('/-\s*(.*?)\s+Acc\.?/i', $details, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractShortcode(string $otherParty): ?string
    {
        if (preg_match('/^(\d+)/', trim($otherParty), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function existsInC2B(string $receipt): bool
    {
        return DB::table('c2b_payments')
            ->where('transaction_id', $receipt)
            ->exists();
    }

    private function existsInStkResponses(string $receipt): bool
    {
        return DB::table('stk_push_responses')
            ->where('mpesa_receipt_number', $receipt)
            ->exists();
    }

    private function findMatchingStkLog(string $accountReference, float $amount, array $completionTime, ?string $maskedPhone = null, ?string $shortcode = null): ?object
    {
        $eat = $completionTime['eat'];
        $utc = $completionTime['utc'];

        $eatStart = $eat->copy()->subMinutes(30)->format('Y-m-d H:i:s');
        $eatEnd = $eat->copy()->addMinutes(30)->format('Y-m-d H:i:s');

        $utcStart = $utc->copy()->subMinutes(30)->format('Y-m-d H:i:s');
        $utcEnd = $utc->copy()->addMinutes(30)->format('Y-m-d H:i:s');

        $query = DB::table('stk_push_logs')
            ->where(function ($q) use ($accountReference) {
                $q->where('unique_number', $accountReference)
                    ->orWhere('account_reference', $accountReference);
            })
            ->whereBetween('amount', [$amount - 0.01, $amount + 0.01])
            ->whereNotNull('checkout_request_id')
            ->where(function ($q) use ($eatStart, $eatEnd, $utcStart, $utcEnd) {
                $q->whereBetween('created_at', [$utcStart, $utcEnd])
                    ->orWhereBetween('created_at', [$eatStart, $eatEnd]);
            });

        if ($shortcode && Schema::hasColumn('stk_push_logs', 'shortcode')) {
            $query->where(function ($q) use ($shortcode) {
                $q->whereNull('shortcode')
                    ->orWhere('shortcode', '')
                    ->orWhere('shortcode', $shortcode);
            });
        }

        $candidates = $query
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $phoneSuffix = $this->extractPhoneSuffix($maskedPhone);
        $best = null;
        $bestScore = null;

        foreach ($candidates as $candidate) {
            $score = 0;

            if ($phoneSuffix && !empty($candidate->phone_number)) {
                if (substr((string) $candidate->phone_number, -strlen($phoneSuffix)) === $phoneSuffix) {
                    $score -= 100000;
                } else {
                    $score += 100000;
                }
            }

            $createdAt = Carbon::parse($candidate->created_at);
            $diffUtc = abs($createdAt->diffInSeconds($utc, false));
            $diffEat = abs($createdAt->diffInSeconds($eat, false));

            $score += min($diffUtc, $diffEat);

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function extractPhoneSuffix(?string $maskedPhone): ?string
    {
        if (!$maskedPhone) {
            return null;
        }

        if (preg_match('/(\d{3,4})$/', $maskedPhone, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function updateStkLogAfterStatementImport(object $stkLog, string $receipt, string $transactionTime): void
    {
        $updates = [];

        $possible = [
            'result_code' => '0',
            'result_description' => 'Payment imported from M-Pesa Business statement.',
            'transaction_id' => $receipt,
            'transaction_time' => $transactionTime,
            'status' => 'completed',
            'recon_status' => 'statement_import_success',
            'recon_source' => 'mpesa_business_statement',
            'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
            'last_reconciled_at' => now(),
            'recon_message' => 'Payment imported into stk_push_responses with processed=N from statement.',
            'updated_at' => now(),
        ];

        foreach ($possible as $column => $value) {
            if (Schema::hasColumn('stk_push_logs', $column)) {
                $updates[$column] = $value;
            }
        }

        if (!empty($updates)) {
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update($updates);
        }
    }

    private function writeReconAuditIfAvailable(object $stkLog, array $data): void
    {
        if (!Schema::hasTable('mpesa_stk_reconciliations')) {
            return;
        }

        DB::table('mpesa_stk_reconciliations')->insert([
            'stk_push_log_id' => $stkLog->id,
            'c2b_payment_id' => null,
            'stk_push_response_id' => $data['stk_push_response_id'] ?? null,

            'unique_number' => $data['unique_number'] ?? ($stkLog->unique_number ?? null),
            'checkout_request_id' => $stkLog->checkout_request_id ?? null,
            'merchant_request_id' => $stkLog->merchant_request_id ?? null,

            'phone_number' => $stkLog->phone_number ?? null,
            'amount' => $stkLog->amount ?? null,
            'shortcode' => $stkLog->shortcode ?? null,

            'recon_status' => $data['recon_status'] ?? null,
            'recon_source' => $data['recon_source'] ?? null,
            'match_confidence' => $data['match_confidence'] ?? null,

            'safaricom_result_code' => $data['safaricom_result_code'] ?? null,
            'safaricom_result_description' => $data['safaricom_result_description'] ?? null,

            'mpesa_receipt_number' => $data['mpesa_receipt_number'] ?? null,
            'transaction_time' => $data['transaction_time'] ?? null,

            'raw_response' => $data['raw_response'] ?? null,
            'notes' => $data['notes'] ?? null,

            'attempt_no' => 1,
            'attempted_at' => now(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addRowResult(array &$summary, $rowNumber, $receipt, $account, $amount, string $status, string $message): void
    {
        if (count($summary['rows']) >= 100) {
            return;
        }

        $summary['rows'][] = [
            'row' => $rowNumber,
            'receipt' => $receipt,
            'account' => $account,
            'amount' => $amount,
            'status' => $status,
            'message' => $message,
        ];
    }
}