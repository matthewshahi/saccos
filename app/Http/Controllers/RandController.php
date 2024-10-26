<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RandController extends Controller
{
    protected $recordLimit;
    protected $minAge;
    protected $minimumLoanThreshold;
    protected $currentPeriod;
    protected $IgnoreLoanBalanceBelow;
    protected $default_company_name;

    public function __construct()
    {
        $this->middleware('auth');
        $this->recordLimit = 3000;
        $this->minAge = 18; // Minimum age to join
        $this->minimumLoanThreshold = 1; // Minimum loan threshold
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $this->IgnoreLoanBalanceBelow = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value') ?? 0;
    }

    // Function to generate random names
    private function randomName() {
        $firstNames = ['James', 'Mary', 'Robert', 'Patricia', 'Michael', 'Linda', 'William', 'Barbara'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson'];
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    // Function to generate random emails
    private function randomEmail() {
        $domains = ['example.com', 'demo.com', 'test.com'];
        $userName = Str::random(8);
        return $userName . '@' . $domains[array_rand($domains)];
    }

    // Function to generate random phone numbers
    private function randomPhoneNumber() {
        // Example Kenyan phone number format
        return '07' . random_int(10000000, 99999999);
    }

    // Function to generate random ID numbers
    private function randomIdNumber() {
        // Random 8-digit ID number
        return random_int(10000000, 99999999);
    }

    // Function to generate random postal addresses
    private function randomPostalAddress() {
        // Example postal address format
        $postalCodes = ['00100', '00200', '00300', '00400', '00500']; // Common Kenyan postal codes
        return 'P.O. Box ' . random_int(100, 9999) . '-' . $postalCodes[array_rand($postalCodes)];
    }

    // Function to generate random document numbers
    private function randomDocNumber() {
        return 'DOC-' . strtoupper(Str::random(8));
    }

    // Function to generate random descriptions
    private function randomDescription() {
        $descriptions = [
            'Payment for loan installment',
            'Monthly share contribution',
            'Interest payment for loan',
            'Annual membership fee',
            'Miscellaneous payment',
            'Adjustment entry',
            'Payment received',
            'Service fee deduction'
        ];
        return $descriptions[array_rand($descriptions)];
    }

    // Function to randomize sacco members data
    public function randomizeMembers()
    {
        DB::table('sacco_members')->orderBy('member_id')->chunk(50, function ($saccoMembers) {
            foreach ($saccoMembers as $member) {
                $newName = $this->randomName();
                $newEmail = $this->randomEmail();
                $newPhoneNumber = $this->randomPhoneNumber();
                $newIdNumber = $this->randomIdNumber();
                $newPostalAddress = $this->randomPostalAddress();

                DB::table('sacco_members')
                    ->where('member_id', $member->member_id)
                    ->update([
                        'member_name' => $newName,
                        'member_email' => $newEmail,
                        'member_phone_no' => $newPhoneNumber,
                        'member_national_id' => $newIdNumber,
                        'member_postal_address' => $newPostalAddress,
                    ]);
            }
        });

        return response()->json(['message' => 'Members randomized successfully']);
    }

    // Function to randomize loan payments data
    public function randomizeLoanPayments()
    {
        DB::table('sacco_loan_payments')->orderBy('loan_payments_id')->chunk(50, function ($loanPayments) {
            foreach ($loanPayments as $payment) {
                $newDocNo = $this->randomDocNumber();
                $newDescription = $this->randomDescription();

                DB::table('sacco_loan_payments')
                    ->where('loan_payments_id', $payment->loan_payments_id)
                    ->update([
                        'loan_payments_docno' => $newDocNo,
                        'loan_payments_description' => $newDescription,
                    ]);
            }
        });

        return response()->json(['message' => 'Loan payments randomized successfully']);
    }

    // Function to randomize shares data
    public function randomizeShares()
    {
        DB::table('sacco_shares')->orderBy('share_id')->chunk(50, function ($shares) {
            foreach ($shares as $share) {
                $newDocNo = $this->randomDocNumber();
                $newDescription = $this->randomDescription();

                DB::table('sacco_shares')
                    ->where('share_id', $share->share_id)
                    ->update([
                        'share_doc_no' => $newDocNo,
                        'share_description' => $newDescription,
                    ]);
            }
        });

        return response()->json(['message' => 'Shares randomized successfully']);
    }

    // Function to randomize accounts transactions data
    public function randomizeAccountsTransactions()
    {
        DB::table('sacco_accounts_trans')->orderBy('accounts_trans_id')->chunk(50, function ($accountsTrans) {
            foreach ($accountsTrans as $trans) {
                $newDocNo = $this->randomDocNumber();
                $newDescription = $this->randomDescription();

                DB::table('sacco_accounts_trans')
                    ->where('accounts_trans_id', $trans->accounts_trans_id)
                    ->update([
                        'accounts_trans_doc_no' => $newDocNo,
                        'accounts_trans_decription' => $newDescription,
                    ]);
            }
        });

        return response()->json(['message' => 'Accounts transactions randomized successfully']);
    }
}