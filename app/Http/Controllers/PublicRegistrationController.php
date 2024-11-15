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
        // Define validation rules
        $rules = [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'dob' => 'required|date|before:today',
            'national_id' => 'required|string|max:20',
            'email' => 'required|email|max:100|unique:sacco_members_new_applications,email',
            'phone' => 'required|string|max:15',
            'location' => 'required|string|max:100',
            'terms' => 'accepted',
            'g-recaptcha-response' => 'required'
        ];

        // Define custom error messages
        $messages = [
            'dob.before' => 'The date of birth must be a past date.',
            'email.unique' => 'A member with similar details was found. Please enter unique details.',
            'terms.accepted' => 'You must agree to the terms and conditions.',
            'g-recaptcha-response.required' => 'Please complete the reCAPTCHA.'
        ];

        // Validate the request data
        $validator = Validator::make($request->all(), $rules, $messages);

        // If validation fails, redirect back with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Verify reCAPTCHA
        if (!$this->verifyRecaptcha($request->input('g-recaptcha-response'))) {
            return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed.'])->withInput();
        }

        // Save the validated data to the temporary applications table
        DB::table('sacco_members_new_applications')->insert([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'dob' => $request->input('dob'),
            'national_id' => $request->input('national_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'location' => $request->input('location'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect with a success message
        return redirect()->route('register.form')->with('success', 'Registration successful! We will get in touch with you soon.');
    }

    // Function to verify reCAPTCHA
    private function verifyRecaptcha($recaptchaResponse)
    {
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $recaptchaResponse
        ]);

        return $response->json()['success'] ?? false;
    }
}