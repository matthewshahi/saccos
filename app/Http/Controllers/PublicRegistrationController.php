<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PublicRegistrationController extends Controller
{ 
    // Display the registration form
    public function showForm()
    {
        // Fetch bank details from the database
        $bankDetails = [
            'bank_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_NAME')->value('default_value'),
            'branch_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_BRANCH')->value('default_value'),
            'account_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_ACCOUNT_NAME')->value('default_value'),
            'account_number' => DB::table('sacco_defaults')->where('default_name', 'BANK_ACCOUNT_NUMBER')->value('default_value'),
        ];

        return view('public.register', compact('bankDetails'));
    }

    // Handle form submission
    public function submit(Request $request)
    {
        // Validate the input fields
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'birth_date' => 'required|date|before:today',
            'national_id' => 'required|string|max:20',
            'email' => 'required|email|max:100|unique:sacco_members_new_applications,email', // Prevent duplicates
            'phone' => 'required|string|max:15',
            'physical_location' => 'required|string|max:100',
            'next_of_kin' => 'nullable|array|max:3', // Validate as array
            'next_of_kin.*.name' => 'nullable|string|max:100',
            'next_of_kin.*.relationship' => 'nullable|string|max:50',
            'next_of_kin.*.phone' => 'nullable|string|max:15',
            'next_of_kin.*.id' => 'nullable|string|max:20',
            'next_of_kin.*.share_percent' => 'nullable|numeric|min:0|max:100',
            'terms' => 'accepted',
            'g-recaptcha-response' => 'required',
        ]);

        // Ensure the total kin_share_percent does not exceed 100%
        if (!empty($request->next_of_kin)) {
            $totalSharePercent = collect($request->next_of_kin)->sum('share_percent');
            if ($totalSharePercent > 100) {
                return redirect()->back()->withErrors([
                    'next_of_kin' => 'The total share percentage for next of kin cannot exceed 100%.',
                ])->withInput();
            }
        }

        // If validation fails, redirect back with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(), // Optional
        ]);
        
        $recaptchaData = $response->json();
        
        if (!($recaptchaData['success'] ?? false)) {
            logger()->error('reCAPTCHA verification failed', ['response' => $recaptchaData]);
            return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed.'])->withInput();
        }
        
        if (($recaptchaData['score'] ?? 0) < 0.5) {
            return redirect()->back()->withErrors(['captcha' => 'Suspicious activity detected. Try again.'])->withInput();
        }
        $emailKeyUnique = Str::uuid();
        // Save the validated data to the database
        DB::table('sacco_members_new_applications')->insert([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'birth_date' => $request->input('birth_date'),
            'national_id' => $request->input('national_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'physical_location' => $request->input('physical_location'),
            'next_of_kin_name' => json_encode(array_column($request->next_of_kin, 'name')),
            'next_of_kin_relationship' => json_encode(array_column($request->next_of_kin, 'relationship')),
            'next_of_kin_phone' => json_encode(array_column($request->next_of_kin, 'phone')),
            'next_of_kin_id' => json_encode(array_column($request->next_of_kin, 'id')),
            'kin_share_percent' => json_encode(array_column($request->next_of_kin, 'share_percent')),
            'email_key_unique' => $emailKeyUnique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect with a success message
        // Redirect with a success message
return redirect()->route('register.form')->with('success', 'Registration successful! Please check your email and follow the link we’ve sent to upload additional required documents, including your passport photo, signature, copies of your ID (front and back), and payslips or bank statements. We will get in touch with you soon.');
        // return redirect()->route('register.form')->with('success', 'Registration successful! We will get in touch with you soon.');
    }

    public function completeRegistrationForm($code)
{
    $member = DB::table('sacco_members_new_applications')
        ->where('email_key_unique', $code)
        ->first();

    if (!$member) {
        abort(404, 'Invalid or expired link.');
    }

    // Check if all required files are already uploaded
    $isUpdated = $member->passport_photo && $member->signature && $member->id_copy_front &&
                 $member->id_copy_back && $member->payslips_bank_statements;

    return view('public.complete-registration', ['member' => $member, 'isUpdated' => $isUpdated]);
}

public function completeSubmit(Request $request)
{
    // Validate the input
    $request->validate([
        'member_id' => 'required|exists:sacco_members_new_applications,id',
        'passport_photo' => 'required|mimes:jpeg,jpg,pdf|max:300',
        'signature' => 'required|mimes:jpeg,jpg,pdf|max:300',
        'id_copy_front' => 'required|mimes:jpeg,jpg,pdf|max:300',
        'id_copy_back' => 'required|mimes:jpeg,jpg,pdf|max:300',
        'payslips_bank_statements' => 'required|mimes:jpeg,jpg,pdf|max:300',
        'bank_name' => 'required|string|max:255',
        'bank_branch' => 'required|string|max:255',
        'bank_account_number' => 'required|string|max:50',
    ]);

    // Handle file uploads
    $uploadedFiles = [];
    if ($request->hasFile('passport_photo')) {
        $uploadedFiles['passport_photo'] = $request->file('passport_photo')->store('passport_photos', 'member_files');
    }
    if ($request->hasFile('signature')) {
        $uploadedFiles['signature'] = $request->file('signature')->store('signatures', 'member_files');
    }
    if ($request->hasFile('id_copy_front')) {
        $uploadedFiles['id_copy_front'] = $request->file('id_copy_front')->store('id_copies', 'member_files');
    }
    if ($request->hasFile('id_copy_back')) {
        $uploadedFiles['id_copy_back'] = $request->file('id_copy_back')->store('id_copies', 'member_files');
    }
    if ($request->hasFile('payslips_bank_statements')) {
        $uploadedFiles['payslips_bank_statements'] = $request->file('payslips_bank_statements')->store('bank_statements', 'member_files');
    }

    // Update the member's record in the database
    DB::table('sacco_members_new_applications')->where('id', $request->input('member_id'))->update([
        'passport_photo' => $uploadedFiles['passport_photo'] ?? null,
        'signature' => $uploadedFiles['signature'] ?? null,
        'id_copy_front' => $uploadedFiles['id_copy_front'] ?? null,
        'id_copy_back' => $uploadedFiles['id_copy_back'] ?? null,
        'payslips_bank_statements' => $uploadedFiles['payslips_bank_statements'] ?? null,
        'bank_name' => $request->input('bank_name'),
        'bank_branch' => $request->input('bank_branch'),
        'bank_account_number' => $request->input('bank_account_number'),
        'updated_at' => now(),
    ]);

    return redirect()->route('register.form')->with('success', 'Your documents and bank details have been uploaded successfully!');
}
}