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

            'inserted_c2b' => 0,
            'inserted_stk_response' => 0,
            'updated_existing_stk_response' => 0,
            'already_existing_c2b' => 0,
            'already_existing_stk_response' => 0,
            'inserted_direct_c2b' => 0,
            'already_existing_direct_c2b' => 0,
            'normalized_operator_references' => 0,

            'skipped_missing_data' => 0,
            'skipped_no_matching_stk' => 0,
            'skipped_conflict' => 0,
            'skipped_not_completed' => 0,
            'skipped_not_paybill' => 0,
            'skipped_invalid_operator_reference' => 0,
            'errors' => 0,

            'rows' => [],
        ];

        try {
            $rows = $this->readStatementRows(
                $fullPath,
                $extension,
                $request->input('sheet_name')
            );

            foreach ($rows as $rowNumber => $row) {
                $summary['total_rows_seen']++;

                try {
                    $receipt = $this->normalizeReceipt($row['receipt_no'] ?? null);
                    $completionTime = $this->parseCompletionTime($row['completion_time'] ?? null);
                    $details = trim((string) ($row['details'] ?? ''));
                    $status = strtoupper(trim((string) ($row['transaction_status'] ?? '')));
                    $paidIn = $this->normalizeAmount($row['paid_in'] ?? null);
                    $withdrawn = $this->normalizeAmount($row['withdrawn'] ?? null);
                    $balance = $this->normalizeAmount($row['balance'] ?? null);
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

                    $rawAccountReference = $this->extractRawAccountReference($details);
                    $accountReference = $this->normalizeAccountReference($rawAccountReference);
                    $maskedPhone = $this->extractMaskedPhone($details);
                    $customerName = $this->extractCustomerName($details);
                    $shortcode = $this->extractShortcode($otherParty);

                    if (!$receipt || !$completionTime || !$accountReference || !$paidIn || $paidIn <= 0) {
                        $summary['skipped_missing_data']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_missing_data', 'Receipt, completion time, account reference or amount is missing.');
                        continue;
                    }

                    if ($this->looksLikeOperatorReference($accountReference) && !$this->isOperatorReference($accountReference)) {
                        $summary['skipped_invalid_operator_reference']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_invalid_operator_reference', 'Operator reference is incomplete or invalid. Expected format like OPM-65, OPSH-65, OPCA-65 or OPLN-65-12.');
                        continue;
                    }

                    if ($rawAccountReference && $accountReference !== strtoupper(trim((string) $rawAccountReference)) && $this->isOperatorReference($accountReference)) {
                        $summary['normalized_operator_references']++;
                    }

                    $summary['valid_paybill_rows']++;

                    $stkLog = $this->findMatchingStkLog(
                        $accountReference,
                        $paidIn,
                        $completionTime,
                        $maskedPhone,
                        $shortcode
                    );

                    if (!$stkLog) {
                        if ($this->isOperatorReference($accountReference)) {
                            $directResult = DB::transaction(function () use (
                                $receipt,
                                $completionTime,
                                $paidIn,
                                $balance,
                                $accountReference,
                                $customerName,
                                $details,
                                $otherParty,
                                $shortcode,
                                $maskedPhone
                            ) {
                                $existingC2B = DB::table('c2b_payments')
                                    ->where('transaction_id', $receipt)
                                    ->first();

                                if ($existingC2B) {
                                    return [
                                        'c2b_payment_id' => (int) $existingC2B->id,
                                        'inserted_c2b' => false,
                                    ];
                                }

                                $c2bPaymentId = $this->insertC2BPaymentFromStatement([
                                    'receipt' => $receipt,
                                    'transaction_time' => $completionTime['eat_string'],
                                    'amount' => $paidIn,
                                    'balance' => $balance,
                                    'shortcode' => $shortcode,
                                    'account_reference' => $accountReference,
                                    'phone_number' => $this->fallbackPhoneFromMaskedPhone($maskedPhone),
                                    'customer_name' => $customerName,
                                    'details' => $details,
                                    'other_party' => $otherParty,
                                    'source' => 'mpesa_business_statement_direct_operator_import',
                                ]);

                                if (!$c2bPaymentId) {
                                    throw new Exception('Unable to insert direct operator c2b_payments record safely.');
                                }

                                return [
                                    'c2b_payment_id' => $c2bPaymentId,
                                    'inserted_c2b' => true,
                                ];
                            });

                            if ($directResult['inserted_c2b']) {
                                $summary['inserted_c2b']++;
                                $summary['inserted_direct_c2b']++;
                            } else {
                                $summary['already_existing_c2b']++;
                                $summary['already_existing_direct_c2b']++;
                            }

                            $this->addRowResult(
                                $summary,
                                $rowNumber,
                                $receipt,
                                $accountReference,
                                $paidIn,
                                'imported_direct_operator_c2b',
                                'No matching STK log found, but operator reference was imported directly into c2b_payments as processed=No/picked=No.'
                            );

                            continue;
                        }

                        $summary['skipped_no_matching_stk']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_no_matching_stk', 'No matching STK log found by account, amount and time.');
                        continue;
                    }

                    if (empty($stkLog->checkout_request_id) || empty($stkLog->phone_number)) {
                        $summary['skipped_missing_data']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_missing_stk_data', 'Matching STK log lacks checkout_request_id or phone_number.');
                        continue;
                    }

                    $existingC2B = DB::table('c2b_payments')
                        ->where('transaction_id', $receipt)
                        ->first();

                    $existingReceiptResponse = DB::table('stk_push_responses')
                        ->where('mpesa_receipt_number', $receipt)
                        ->first();

                    if ($existingReceiptResponse && $existingReceiptResponse->checkout_request_id !== $stkLog->checkout_request_id) {
                        $summary['skipped_conflict']++;
                        $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_conflict', 'Receipt already exists in stk_push_responses under a different CheckoutRequestID.');
                        continue;
                    }

                    $existingCheckout = DB::table('stk_push_responses')
                        ->where('checkout_request_id', $stkLog->checkout_request_id)
                        ->first();

                    if ($existingCheckout && !empty($existingCheckout->mpesa_receipt_number)) {
                        $existingCheckoutReceipt = $this->normalizeReceipt($existingCheckout->mpesa_receipt_number);

                        if ($existingCheckoutReceipt !== $receipt) {
                            $summary['skipped_conflict']++;
                            $this->addRowResult($summary, $rowNumber, $receipt, $accountReference, $paidIn, 'skipped_conflict', 'CheckoutRequestID already has a different receipt.');
                            continue;
                        }
                    }

                    $result = DB::transaction(function () use (
                        $stkLog,
                        $receipt,
                        $completionTime,
                        $paidIn,
                        $balance,
                        $accountReference,
                        $customerName,
                        $details,
                        $otherParty,
                        $shortcode,
                        $existingC2B,
                        $existingCheckout
                    ) {
                        $c2bPaymentId = null;
                        $stkResponseId = null;
                        $insertedC2B = false;
                        $insertedStkResponse = false;
                        $updatedExistingStkResponse = false;
                        $alreadyExistingC2B = false;
                        $alreadyExistingStkResponse = false;

                        if ($existingC2B) {
                            $c2bPaymentId = (int) $existingC2B->id;
                            $alreadyExistingC2B = true;
                        } else {
                            $c2bPaymentId = $this->insertC2BPaymentFromStatement([
                                'receipt' => $receipt,
                                'transaction_time' => $completionTime['eat_string'],
                                'amount' => $paidIn,
                                'balance' => $balance,
                                'shortcode' => $shortcode ?: ($stkLog->shortcode ?? null),
                                'account_reference' => $accountReference,
                                'phone_number' => $stkLog->phone_number ?? null,
                                'customer_name' => $customerName,
                                'details' => $details,
                                'other_party' => $otherParty,
                            ]);

                            if (!$c2bPaymentId) {
                                throw new Exception('Unable to insert c2b_payments record safely.');
                            }

                            $insertedC2B = true;
                        }

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

                            /*
                             * Important:
                             * Final member/account posting is done by your normal processor.
                             */
                            'processed' => 'N',
                            'processed_date' => null,
                            'updated_at' => now(),
                        ];

                        if ($existingCheckout) {
                            $alreadyExistingStkResponse = true;

                            DB::table('stk_push_responses')
                                ->where('id', $existingCheckout->id)
                                ->update($payload);

                            $stkResponseId = (int) $existingCheckout->id;
                            $updatedExistingStkResponse = true;
                        } else {
                            $payload['created_at'] = now();

                            $stkResponseId = (int) DB::table('stk_push_responses')->insertGetId($payload);
                            $insertedStkResponse = true;
                        }

                        $this->updateStkLogAfterStatementImport(
                            $stkLog,
                            $receipt,
                            $completionTime['eat_string']
                        );

                        $this->writeReconAuditIfAvailable($stkLog, [
                            'c2b_payment_id' => $c2bPaymentId,
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
                                'balance' => $balance,
                                'phone_number' => $stkLog->phone_number ?? null,
                                'customer_name' => $customerName,
                                'details' => $details,
                                'other_party' => $otherParty,
                            ]),
                            'notes' => 'Statement import inserted/linked c2b_payments as processed=No/picked=No and stk_push_responses as processed=N.',
                        ]);

                        return [
                            'c2b_payment_id' => $c2bPaymentId,
                            'stk_response_id' => $stkResponseId,
                            'inserted_c2b' => $insertedC2B,
                            'inserted_stk_response' => $insertedStkResponse,
                            'updated_existing_stk_response' => $updatedExistingStkResponse,
                            'already_existing_c2b' => $alreadyExistingC2B,
                            'already_existing_stk_response' => $alreadyExistingStkResponse,
                        ];
                    });

                    if ($result['inserted_c2b']) {
                        $summary['inserted_c2b']++;
                    } else {
                        $summary['already_existing_c2b']++;
                    }

                    if ($result['inserted_stk_response']) {
                        $summary['inserted_stk_response']++;
                    } elseif ($result['updated_existing_stk_response']) {
                        $summary['updated_existing_stk_response']++;
                    } else {
                        $summary['already_existing_stk_response']++;
                    }

                    $this->addRowResult(
                        $summary,
                        $rowNumber,
                        $receipt,
                        $accountReference,
                        $paidIn,
                        'imported',
                        'Inserted/linked c2b_payments as processed=No/picked=No and stk_push_responses as processed=N.'
                    );
                } catch (Exception $e) {
                    $summary['errors']++;

                    Log::error('M-Pesa statement reconciliation row failed', [
                        'row_number' => $rowNumber,
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);

                    $this->addRowResult(
                        $summary,
                        $rowNumber,
                        $row['receipt_no'] ?? null,
                        null,
                        null,
                        'error',
                        $e->getMessage()
                    );
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

            if (
                in_array('receipt_no', $normalizedValues, true)
                && in_array('completion_time', $normalizedValues, true)
            ) {
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
            $balance = $this->valueFromMappedRow($row, $headerMap, ['balance']);
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
                'balance' => $balance,
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

        if (in_array($value, ['receipt_no', 'receipt_number', 'receipt'], true)) {
            return 'receipt_no';
        }

        if ($value === 'completion_time') {
            return 'completion_time';
        }

        if (in_array($value, ['paid_in', 'paidin'], true)) {
            return 'paid_in';
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
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/y H:i:s',
            'm/d/y H:i',
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
                // Try next format.
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
        return $this->normalizeAccountReference($this->extractRawAccountReference($details));
    }

    private function extractRawAccountReference(string $details): ?string
    {
        $raw = null;

        if (preg_match('/\bAcc\.?\s*[:\-]?\s*(.+)$/i', $details, $matches)) {
            $raw = $matches[1];
        } elseif (preg_match('/\bAccount\.?\s*[:\-]?\s*(.+)$/i', $details, $matches)) {
            $raw = $matches[1];
        }

        if ($raw === null) {
            return null;
        }

        $raw = trim((string) $raw);

        /*
         * Business statement rows usually end at the reference, for example:
         *   Acc. OPSH 65
         * SMS-style text can continue after the reference, for example:
         *   account OPSH-65 on 23/5/26 at 10:26 AM New M-PESA balance...
         */
        $parts = preg_split('/\s+(?:on|new\s+m[\-\s]?pesa|transaction\s+cost|amount\s+you\s+can|save\s+frequent)\b/i', $raw, 2);
        $raw = trim($parts[0] ?? $raw);

        return rtrim($raw, " \t\n\r\0\x0B.,;");
    }

    private function normalizeAccountReference($reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $reference = strtoupper(trim((string) $reference));
        $reference = str_replace("\xC2\xA0", ' ', $reference);
        $reference = preg_replace('/\s+/', ' ', $reference);
        $reference = preg_replace('/[^A-Z0-9\-\/\s_]+/', '', $reference);
        $reference = trim($reference, " -/_\t\n\r\0\x0B");

        if ($reference === '') {
            return null;
        }

        $withDashes = preg_replace('/[\s\/_]+/', '-', $reference);
        $withDashes = preg_replace('/-+/', '-', $withDashes);
        $withDashes = trim($withDashes, '-');

        /*
         * Canonical OP format:
         *   OPM-65
         *   OPSH-65
         *   OPCA-65
         *   OPLN-65-12
         * Accept common dirty formats:
         *   OPM 65, OPSH 65, OP SH 65, OPSH/65, OPSH65
         */
        if (preg_match('/^OP-?([A-Z]+)-(\d+)(?:-(\d+))?$/', $withDashes, $matches)) {
            return 'OP' . $matches[1] . '-' . $matches[2] . (!empty($matches[3]) ? '-' . $matches[3] : '');
        }

        $compact = preg_replace('/[^A-Z0-9]+/', '', $reference);

        if (preg_match('/^OP([A-Z]+)(\d+)$/', $compact, $matches)) {
            return 'OP' . $matches[1] . '-' . $matches[2];
        }

        /*
         * Do not guess missing operator IDs. OPSH/OPM alone must be rejected,
         * not posted to member 0 or a wrong fallback account.
         */
        if (preg_match('/^OP[A-Z]+$/', $compact)) {
            return $compact;
        }

        /*
         * Light cleanup for ordinary member references when users type spaces:
         * SH 192 -> SH192, CA 323 -> CA323, REG 39199929 -> REG39199929.
         */
        if (preg_match('/^(SH|CA|WE|RE|LN|REG|RF|FA|FO|SS)-?(\d+)$/', $withDashes, $matches)) {
            return $matches[1] . $matches[2];
        }

        return $withDashes;
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
        if (preg_match('/-\s*(.*?)\s+(?:Acc\.?|Account\.?)/i', $details, $matches)) {
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

    private function findMatchingStkLog(
        string $accountReference,
        float $amount,
        array $completionTime,
        ?string $maskedPhone = null,
        ?string $shortcode = null
    ): ?object {
        $eat = $completionTime['eat'];
        $utc = $completionTime['utc'];

        $eatStart = $eat->copy()->subMinutes(30)->format('Y-m-d H:i:s');
        $eatEnd = $eat->copy()->addMinutes(30)->format('Y-m-d H:i:s');

        $utcStart = $utc->copy()->subMinutes(30)->format('Y-m-d H:i:s');
        $utcEnd = $utc->copy()->addMinutes(30)->format('Y-m-d H:i:s');

        $accountReferenceVariants = $this->accountReferenceSearchVariants($accountReference);

        $query = DB::table('stk_push_logs')
            ->where(function ($q) use ($accountReferenceVariants) {
                $q->whereIn('unique_number', $accountReferenceVariants)
                    ->orWhereIn('account_reference', $accountReferenceVariants);
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

    private function isOperatorReference(string $reference): bool
    {
        $reference = $this->normalizeAccountReference($reference);

        return $reference !== null && (bool) preg_match('/^OP[A-Z]+-\d+(-\d+)?$/', $reference);
    }

    private function looksLikeOperatorReference(?string $reference): bool
    {
        $reference = $this->normalizeAccountReference($reference);

        return $reference !== null && str_starts_with($reference, 'OP');
    }

    private function accountReferenceSearchVariants(string $accountReference): array
    {
        $normalized = $this->normalizeAccountReference($accountReference) ?: strtoupper(trim($accountReference));
        $variants = [$normalized];

        if (preg_match('/^(OP[A-Z]+)-(\d+)(?:-(\d+))?$/', $normalized, $matches)) {
            $prefix = $matches[1];
            $operatorId = $matches[2];
            $extraId = $matches[3] ?? null;

            $variants[] = $prefix . ' ' . $operatorId . ($extraId ? ' ' . $extraId : '');
            $variants[] = $prefix . '/' . $operatorId . ($extraId ? '/' . $extraId : '');
            $variants[] = $prefix . $operatorId . ($extraId ? '-' . $extraId : '');
            $variants[] = 'OP ' . substr($prefix, 2) . ' ' . $operatorId . ($extraId ? ' ' . $extraId : '');
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function fallbackPhoneFromMaskedPhone(?string $maskedPhone): ?string
    {
        $suffix = $this->extractPhoneSuffix($maskedPhone);

        if ($suffix) {
            return '25400000' . $suffix;
        }

        return null;
    }

    private function resolveStatementPhoneNumber($phoneNumber): string
    {
        $phoneNumber = preg_replace('/\D+/', '', (string) $phoneNumber);

        if ($phoneNumber !== '') {
            if (strlen($phoneNumber) === 9 && str_starts_with($phoneNumber, '7')) {
                return '254' . $phoneNumber;
            }

            if (strlen($phoneNumber) === 10 && str_starts_with($phoneNumber, '0')) {
                return '254' . substr($phoneNumber, 1);
            }

            return $phoneNumber;
        }

        return '254000000000';
    }

    private function resolveBusinessShortcode($shortcode): string
    {
        $shortcode = trim((string) $shortcode);

        if ($shortcode !== '') {
            return $shortcode;
        }

        foreach ([
            'mpesa_business_shortcode',
            'mpesa_shortcode',
            'paybill_number',
            'default_mpesa_shortcode',
            'default_paybill_no',
        ] as $defaultName) {
            $value = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->value('default_value');

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        if (Schema::hasTable('mpesa_configs')) {
            foreach (['shortcode', 'business_shortcode', 'business_short_code', 'paybill_number'] as $column) {
                if (Schema::hasColumn('mpesa_configs', $column)) {
                    $value = DB::table('mpesa_configs')->value($column);

                    if ($value !== null && trim((string) $value) !== '') {
                        return trim((string) $value);
                    }
                }
            }
        }

        return 'STATEMENT';
    }

    private function insertC2BPaymentFromStatement(array $data): ?int
    {
        $receipt = $this->normalizeReceipt($data['receipt'] ?? null);

        if (!$receipt) {
            return null;
        }

        $existing = DB::table('c2b_payments')
            ->where('transaction_id', $receipt)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $amount = $this->normalizeAmount($data['amount'] ?? null);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $transactionTime = $data['transaction_time'] ?? null;
        $accountReference = $this->normalizeAccountReference($data['account_reference'] ?? null);
        $shortcode = $this->resolveBusinessShortcode($data['shortcode'] ?? null);
        $phoneNumber = $this->resolveStatementPhoneNumber($data['phone_number'] ?? null);
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $source = trim((string) ($data['source'] ?? 'mpesa_business_statement_import'));

        if (!$transactionTime || !$accountReference) {
            return null;
        }

        [$firstName, $middleName, $lastName] = $this->splitCustomerName($customerName);

        $rawPayload = [
            'source' => $source,
            'TransactionType' => 'Pay Bill',
            'TransID' => $receipt,
            'TransTime' => Carbon::parse($transactionTime, 'Africa/Nairobi')->format('YmdHis'),
            'TransAmount' => number_format($amount, 2, '.', ''),
            'BusinessShortCode' => $shortcode,
            'BillRefNumber' => $accountReference,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => $data['balance'] ?? null,
            'ThirdPartyTransID' => '',
            'MSISDN' => $phoneNumber,
            'FirstName' => $firstName,
            'MiddleName' => $middleName,
            'LastName' => $lastName,
            'Details' => $data['details'] ?? null,
            'OtherParty' => $data['other_party'] ?? null,
        ];

        return (int) DB::table('c2b_payments')->insertGetId([
            'transaction_type' => 'Pay Bill',
            'transaction_id' => $receipt,
            'transaction_time' => $transactionTime,
            'transaction_amount' => $amount,
            'business_shortcode' => $shortcode,
            'bill_ref_number' => $accountReference,
            'invoice_number' => '',
            'org_account_balance' => $data['balance'] ?? null,
            'third_party_transaction_id' => '',
            'msisdn' => $phoneNumber,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'raw_payload' => json_encode($rawPayload),
            'ip_address' => request()->ip() ?: '127.0.0.1',

            /*
             * Important:
             * Final posting is done later by your normal transaction processor.
             */
            'processed' => 'No',
            'picked' => 'No',
            'failure_reason' => null,
            'processed_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function splitCustomerName(?string $customerName): array
    {
        $customerName = trim((string) $customerName);

        if ($customerName === '') {
            return [null, null, null];
        }

        $parts = preg_split('/\s+/', $customerName);

        $firstName = $parts[0] ?? null;
        $middleName = null;
        $lastName = null;

        if (count($parts) === 2) {
            $lastName = $parts[1];
        } elseif (count($parts) >= 3) {
            $middleName = $parts[1];
            $lastName = implode(' ', array_slice($parts, 2));
        }

        return [$firstName, $middleName, $lastName];
    }

    private function updateStkLogAfterStatementImport(object $stkLog, string $receipt, string $transactionTime): void
    {
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
            'recon_message' => 'Payment imported into c2b_payments and stk_push_responses from statement.',
            'updated_at' => now(),
        ];

        $updates = [];

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
            'c2b_payment_id' => $data['c2b_payment_id'] ?? null,
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

    private function addRowResult(
        array &$summary,
        $rowNumber,
        $receipt,
        $account,
        $amount,
        string $status,
        string $message
    ): void {
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