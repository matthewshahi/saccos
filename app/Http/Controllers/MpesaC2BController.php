<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class MpesaC2BController extends Controller
{
    protected $shortCode;

    public function __construct()
    {
        $this->initializeConfig();
    }

    /**
     * Initialize M-Pesa configuration specifically for C2B from the database.
     */
    private function initializeConfig()
    {
        // Fetch the C2B shortcode configuration
        $config = DB::table('mpesa_configs')->where('api_type', 'C2B')->first();

        if ($config) {
            $this->shortCode = $config->shortcode;
        } else {
            $this->logTransaction('Initialization Error', ['message' => 'C2B configuration not found in mpesa_configs table'], 'error');
        }

        // Log initialization
        $this->logTransaction('Initialization', [
            'shortcode' => $this->shortCode,
        ]);
    }

    /**
     * Handle M-Pesa Confirmation Request.
     */
    public function confirmation(Request $request)
    {
        $this->logTransaction('Confirmation URL Accessed', ['data_received' => $request->all()]);

        try {
            DB::table('mpesa_confirmations')->insert([
                'transaction_id' => $request->get('TransID'),
                'transaction_type' => $request->get('TransactionType'),
                'amount' => $request->get('TransAmount'),
                'account_reference' => $request->get('BillRefNumber'),
                'phone_number' => $request->get('MSISDN'),
                'invoice_number' => $request->get('InvoiceNumber'),
                'organization_balance' => $request->get('OrgAccountBalance'),
                'third_party_transaction_id' => $request->get('ThirdPartyTransID'),
                'bill_ref_number' => $request->get('BillRefNumber'),
                'shortcode' => $this->shortCode,
                'transaction_time' => Carbon::parse($request->get('TransTime')),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            $this->logTransaction('Confirmation Data Saved', ['data_saved' => $request->all()]);

        } catch (Exception $e) {
            $this->logTransaction('Failed to save Confirmation Data', ['error' => $e->getMessage()], 'error');
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save confirmation data.']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Confirmation received successfully']);
    }

    /**
     * Handle M-Pesa Validation Request.
     */
    public function validation(Request $request)
    {
        $this->logTransaction('Validation URL Accessed', ['data_received' => $request->all()]);

        try {
            DB::table('mpesa_validations')->insert([
                'transaction_id' => $request->get('TransID'),
                'transaction_type' => $request->get('TransactionType'),
                'amount' => $request->get('TransAmount'),
                'account_reference' => $request->get('BillRefNumber'),
                'phone_number' => $request->get('MSISDN'),
                'invoice_number' => $request->get('InvoiceNumber'),
                'bill_ref_number' => $request->get('BillRefNumber'),
                'shortcode' => $this->shortCode,
                'transaction_time' => Carbon::parse($request->get('TransTime')),
                'validation_result_code' => '0',
                'validation_result_description' => 'Validation successful',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            $this->logTransaction('Validation Data Saved', ['data_saved' => $request->all()]);

        } catch (Exception $e) {
            $this->logTransaction('Failed to save Validation Data', ['error' => $e->getMessage()], 'error');
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save validation data.']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Validation successful']);
    }

    /**
     * Log M-Pesa transactions to a custom log file.
     */
    private function logTransaction($message, $context = [], $level = 'info')
    {
        Log::channel('single')->{$level}($message, $context);
        $logFile = storage_path('logs/mpesa_trans.txt');
        file_put_contents($logFile, "[" . now() . "] " . strtoupper($level) . ": " . $message . ' ' . json_encode($context) . PHP_EOL, FILE_APPEND);
    }
}
