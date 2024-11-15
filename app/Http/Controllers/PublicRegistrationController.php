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
        return view('public.register');
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
            'physical_location' => 'required|string|max:100', // Updated to match DB field name
            'terms' => 'accepted',
            'g-recaptcha-response' => 'required'
        ]);

        // If validation fails, redirect back with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Verify reCAPTCHA v3
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response')
        ]);

        $recaptchaData = $response->json();
        $recaptchaSuccess = $recaptchaData['success'] ?? false;
        $recaptchaScore = $recaptchaData['score'] ?? 0;

        if (!$recaptchaSuccess || $recaptchaScore < 0.5) { // 0.5 threshold for spam
            return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed or score too low.'])->withInput();
        }

        // Check for duplicate entry using email
        $duplicate = DB::table('sacco_members_new_applications')
            ->where('email', $request->input('email'))
            ->exists();

        if ($duplicate) {
            return redirect()->back()->withErrors([
                'duplicate' => 'A member with similar details was found. Please enter unique details.'
            ])->withInput();
        }

        // Save the validated data to the temporary applications table
        DB::table('sacco_members_new_applications')->insert([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'dob' => $request->input('dob'),
            'national_id' => $request->input('national_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'physical_location' => $request->input('physical_location'), // Corrected to match DB field
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect with a success message
        return redirect()->route('register.form')->with('success', 'Registration successful! We will get in touch with you soon.');
    }
}