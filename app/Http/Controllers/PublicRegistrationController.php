<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

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
            'dob' => 'required|date|before:today',
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

        // Verify reCAPTCHA v3
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
        ]);

        $recaptchaData = $response->json();
        $recaptchaSuccess = $recaptchaData['success'] ?? false;
        $recaptchaScore = $recaptchaData['score'] ?? 0;

        dd($recaptchaSuccess);
        if (!$recaptchaSuccess || $recaptchaScore < 0.5) { // 0.5 threshold for spam
            return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed or score too low.'])->withInput();
        }

        // Save the validated data to the database
        DB::table('sacco_members_new_applications')->insert([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'dob' => $request->input('dob'),
            'national_id' => $request->input('national_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'physical_location' => $request->input('physical_location'),
            'next_of_kin_name' => json_encode(array_column($request->next_of_kin, 'name')),
            'next_of_kin_relationship' => json_encode(array_column($request->next_of_kin, 'relationship')),
            'next_of_kin_phone' => json_encode(array_column($request->next_of_kin, 'phone')),
            'next_of_kin_id' => json_encode(array_column($request->next_of_kin, 'id')),
            'kin_share_percent' => json_encode(array_column($request->next_of_kin, 'share_percent')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect with a success message
        return redirect()->route('register.form')->with('success', 'Registration successful! We will get in touch with you soon.');
    }
}