<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PublicRegistrationController extends Controller
{


    public function showForm()
    {
        // Fetch sacco_defaults in a single query to avoid multiple calls
        $saccoDefaults = DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'BANK_NAME',
                'BANK_BRANCH',
                'BANK_ACCOUNT_NAME',
                'BANK_ACCOUNT_NUMBER',
                'member_ship_fee',
                'min_share_contribution'
            ])
            ->pluck('default_value', 'default_name');

        // Fetch the paybill number from mpesa_configs
        $paybillNumber = DB::table('mpesa_configs')
            ->where('api_type', 'c2b')
            ->orderBy('id') // Ensure to pick the first value by order
            ->value('shortcode');


        // Fetch kin types ordered alphabetically
        $kinTypes = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->orderBy('kin_type_name', 'asc') // Order by ascending
            ->get(['kin_type_id', 'kin_type_name']);

        // Prepare data for view
        $data = [
            'bankDetails' => [
                'bank_name' => $saccoDefaults['BANK_NAME'] ?? '',
                'branch_name' => $saccoDefaults['BANK_BRANCH'] ?? '',
                'account_name' => $saccoDefaults['BANK_ACCOUNT_NAME'] ?? '',
                'account_number' => $saccoDefaults['BANK_ACCOUNT_NUMBER'] ?? '',
            ],
            'membershipFee' => $saccoDefaults['member_ship_fee'] ?? 1000, // Default to 1000 if missing
            'minContribution' => $saccoDefaults['min_share_contribution'] ?? 0,
            'paybillNumber' => $paybillNumber,
            'kinTypes' => $kinTypes,
        ];

        // Return the view with all required data
        return view('public.register', $data);
    }
    // public function showForm(Request $request, $code = null)
    // {
    //     if ($code) {
    //         // Handle the "complete your registration" step
    //         $member = DB::table('sacco_members_new_applications')
    //             ->where('email_key_unique', $code)
    //             ->first();

    //         if (!$member) {
    //             abort(404, 'Invalid or expired link.');
    //         }

    //         // Check if all required files are already uploaded
    //         $isUpdated = $member->passport_photo && $member->signature && $member->id_copy_front &&
    //                      $member->id_copy_back && $member->payslips_bank_statements;

    //         return view('public.register', [
    //             'isCompleteStep' => true,
    //             'member' => $member,
    //             'isUpdated' => $isUpdated,
    //         ]);
    //     } else {
    //         // Handle the initial registration form
    //         $bankDetails = [
    //             'bank_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_NAME')->value('default_value'),
    //             'branch_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_BRANCH')->value('default_value'),
    //             'account_name' => DB::table('sacco_defaults')->where('default_name', 'BANK_ACCOUNT_NAME')->value('default_value'),
    //             'account_number' => DB::table('sacco_defaults')->where('default_name', 'BANK_ACCOUNT_NUMBER')->value('default_value'),
    //         ];

    //         $kinTypes = DB::table('sacco_kin_type')
    //             ->where('kin_type_deleted', 'N')
    //             ->orderBy('kin_type_name')
    //             ->get(['kin_type_id', 'kin_type_name']);

    //         $minContribution = DB::table('sacco_defaults')->where('default_name', 'min_share_contribution')->value('default_value');

    //         return view('public.register', compact('bankDetails', 'kinTypes', 'minContribution'))
    //             ->with('isCompleteStep', false);
    //     }
    // }

    public function submit(Request $request)
    {
        // Fetch minimum contribution value
        $minContribution = DB::table('sacco_defaults')
            ->where('default_name', 'min_share_contribution')
            ->value('default_value') ?? 1000; // Default value if not set

        // Fetch valid kin relationships
        $validKinRelationships = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->pluck('kin_type_name')
            ->toArray();

        // Validation Rules
        $validator = Validator::make($request->all(), [
            // Personal Details
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'birth_date' => 'required|date|before:today',
            'national_id' => 'required|string|max:20',
            'kra_pin_no' => 'required|string|max:20',
            'gender' => 'nullable|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',

            // Additional Information
            // 'monthly_income' => 'required|numeric|min:0',
            'occupation' => 'nullable|string|max:255',
            'dependents' => 'nullable|integer|min:0',
            'preferred_monthly_contribution' => [
                'nullable',
                'numeric',
            ],
            'reason_for_joining' => 'nullable|string|max:1000',

            // Contact Details
            'email' => [
                'required',
                'email',
                'max:100',
                'unique:sacco_members_new_applications,email',
                function ($attribute, $value, $fail) {
                    $existsInMembers = DB::table('sacco_members')
                        ->where('member_email', $value)
                        ->exists();
                    if ($existsInMembers) {
                        $fail('The email address already exists in the SACCO member database.');
                    }
                },
            ],
            'phone' => [
                'required',
                'string',
                'max:15',
                'unique:sacco_members_new_applications,phone',
                function ($attribute, $value, $fail) {
                    $existsInMembers = DB::table('sacco_members')
                        ->where('member_phone_no', $value)
                        ->exists();
                    if ($existsInMembers) {
                        $fail('The phone number already exists in the SACCO member database.');
                    }
                },
            ],
            'physical_location' => 'nullable|string|max:100',

            // File Uploads (All Optional)
            'passport_photo' => 'nullable|mimes:jpeg,jpg,pdf|max:300',
            'signature' => 'nullable|mimes:jpeg,jpg,pdf|max:300',
            'id_copy_front' => 'nullable|mimes:jpeg,jpg,pdf|max:300',
            'id_copy_back' => 'nullable|mimes:jpeg,jpg,pdf|max:300',
            'payslips_bank_statements' => 'nullable|mimes:jpeg,jpg,pdf|max:300',




            // Bank Details
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',

            // // Next of Kin
            // 'next_of_kin' => 'nullable|array|max:3',
            // 'next_of_kin.*.name' => 'required_with:next_of_kin|string|max:100',
            // 'next_of_kin.*.relationship' => [
            //     'required_with:next_of_kin',
            //     function ($attribute, $value, $fail) use ($validKinRelationships) {
            //         if (!in_array($value, $validKinRelationships)) {
            //             $fail('Invalid relationship value.');
            //         }
            //     },
            // ],
            // 'next_of_kin.*.phone' => 'nullable|string|max:30',
            // 'next_of_kin.*.id_or_cert_no' => 'nullable|string|max:30',
            // 'next_of_kin.*.share_percent' => 'nullable|numeric|min:0|max:100',

            // Next of Kin (All Optional)
            'next_of_kin' => 'nullable|array|max:3',
            'next_of_kin.*.name' => 'nullable|string|max:100',
            'next_of_kin.*.relationship' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($validKinRelationships) {
                    if (!is_null($value) && !in_array($value, $validKinRelationships)) {
                        $fail('Invalid relationship value.');
                    }
                },
            ],
            'next_of_kin.*.phone' => 'nullable|string|max:30',
            'next_of_kin.*.id_or_cert_no' => 'nullable|string|max:30',
            'next_of_kin.*.share_percent' => 'nullable|numeric|min:0|max:100',


            // Terms and Conditions
            'certification_statement' => 'accepted',
            'terms' => 'accepted',
            'g-recaptcha-response' => 'required',
        ]);

        // Validate Total Share Percentage
        if (!empty($request->next_of_kin)) {
            $totalSharePercent = collect($request->next_of_kin)->sum('share_percent');
            if ($totalSharePercent > 100) {
                return redirect()->back()->withErrors([
                    'next_of_kin' => 'The total share percentage for next of kin cannot exceed 100%.',
                ])->withInput();
            }
        }

        // If Validation Fails
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // reCAPTCHA Validation
        $recaptchaResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        $recaptchaData = $recaptchaResponse->json();
        if (!($recaptchaData['success'] ?? false)) {
            return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed.'])->withInput();
        }

        // File Upload Handling
        $fileFields = [
            'passport_photo',
            'signature',
            'id_copy_front',
            'id_copy_back',
            'payslips_bank_statements'
        ];
        $uploadedFiles = [];
        foreach ($fileFields as $file) {
            if ($request->hasFile($file)) {
                $uploadedFiles[$file] = $request->file($file)->store($file . '_uploads', 'member_files');
            }
        }

        // Prepare the data to save
        // $data = [
        //     'first_name' => strtoupper($request->first_name),
        //     'last_name' => strtoupper($request->last_name),
        //     'birth_date' => $request->birth_date,
        //     'national_id' => strtoupper($request->national_id),
        //     'kra_pin_no' => strtoupper($request->kra_pin_no),
        //     'gender' => $request->gender,
        //     'marital_status' => $request->marital_status,
        //     'monthly_income' => $request->monthly_income,
        //     'occupation' => $request->occupation,
        //     'dependents' => $request->dependents,
        //     'preferred_monthly_contribution' => $request->preferred_monthly_contribution,
        //     'reason_for_joining' => strtoupper($request->reason_for_joining),
        //     'email' => strtolower($request->email),
        //     'phone' => $request->phone,
        //     'physical_location' => strtoupper($request->physical_location),
        //     'bank_name' => strtoupper($request->bank_name),
        //     'bank_branch' => strtoupper($request->bank_branch),
        //     'bank_account_number' => $request->bank_account_number,
        //     'passport_photo' => $uploadedFiles['passport_photo'] ?? null,
        //     'signature' => $uploadedFiles['signature'] ?? null,
        //     'id_copy_front' => $uploadedFiles['id_copy_front'] ?? null,
        //     'id_copy_back' => $uploadedFiles['id_copy_back'] ?? null,
        //     'payslips_bank_statements' => $uploadedFiles['payslips_bank_statements'] ?? null,
        //     'next_of_kin_name' => json_encode(array_column($request->next_of_kin ?? [], 'name')),
        //     'next_of_kin_relationship' => json_encode(array_column($request->next_of_kin ?? [], 'relationship')),
        //     'next_of_kin_phone' => json_encode(array_column($request->next_of_kin ?? [], 'phone')),
        //     'next_of_kin_id_or_cert_no' => json_encode(array_column($request->next_of_kin ?? [], 'id_or_cert_no')),
        //     'kin_share_percent' => json_encode(array_column($request->next_of_kin ?? [], 'share_percent')),
        //     'certification_statement' => 1,
        //     'email_key_unique' => Str::uuid(),
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ];

        // // Dump and Die the Prepared Data
        // dd($data);

        // Save Data to Database
        DB::table('sacco_members_new_applications')->insert([
            'first_name' => strtoupper($request->first_name),
            'last_name' => strtoupper($request->last_name),
            'birth_date' => $request->birth_date,
            'national_id' => strtoupper($request->national_id),
            'kra_pin_no' => strtoupper($request->kra_pin_no),
            'gender' => $request->gender,
            'marital_status' => $request->marital_status,
            // 'monthly_income' => $request->monthly_income,
            'occupation' => $request->occupation,
            'dependents' => $request->dependents,
            // 'preferred_monthly_contribution' => $request->preferred_monthly_contribution,
            'preferred_monthly_contribution' => is_numeric($request->preferred_monthly_contribution) ? $request->preferred_monthly_contribution : 0,
            'reason_for_joining' => strtoupper($request->reason_for_joining ?? ''),
            'email' => strtolower($request->email),
            'phone' => $request->phone,
            'physical_location' => strtoupper($request->physical_location ?? ''),

            'bank_name' => strtoupper($request->bank_name ?? ''),
            'bank_branch' => strtoupper($request->bank_branch ?? ''),
            'bank_account_number' => $request->bank_account_number,
            // 'passport_photo' => $uploadedFiles['passport_photo'],
            // 'signature' => $uploadedFiles['signature'],
            // 'id_copy_front' => $uploadedFiles['id_copy_front'],
            // 'id_copy_back' => $uploadedFiles['id_copy_back'],
            // 'payslips_bank_statements' => $uploadedFiles['payslips_bank_statements'],
            'passport_photo' => $uploadedFiles['passport_photo'] ?? null,
            'signature' => $uploadedFiles['signature'] ?? null,
            'id_copy_front' => $uploadedFiles['id_copy_front'] ?? null,
            'id_copy_back' => $uploadedFiles['id_copy_back'] ?? null,
            'payslips_bank_statements' => $uploadedFiles['payslips_bank_statements'] ?? null,

            'next_of_kin_name' => json_encode(array_column($request->next_of_kin ?? [], 'name')),
            'next_of_kin_relationship' => json_encode(array_column($request->next_of_kin ?? [], 'relationship')),
            'next_of_kin_phone' => json_encode(array_column($request->next_of_kin ?? [], 'phone')),
            'next_of_kin_id_or_cert_no' => json_encode(array_column($request->next_of_kin ?? [], 'id_or_cert_no')),
            'kin_share_percent' => json_encode(array_column($request->next_of_kin ?? [], 'share_percent')),
            'certification_statement' => 1,
            'email_key_unique' => Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('register.form')->with('success', 'Registration successful! Please check your email for further instructions.');
    }

    // public function submit(Request $request)
    // {
    //     // Fetch minimum contribution value
    //     $minContribution = DB::table('sacco_defaults')->where('default_name', 'min_share_contribution')->value('default_value');

    //     // Fetch valid kin relationships
    //     $validKinRelationships = DB::table('sacco_kin_type')
    //         ->where('kin_type_deleted', 'N') // Only fetch non-deleted relationships
    //         ->pluck('kin_type_name')
    //         ->toArray();

    //     // Validate the input fields
    //     $validator = Validator::make($request->all(), [
    //         'first_name' => 'required|string|max:50',
    //         'last_name' => 'required|string|max:50',
    //         'birth_date' => 'required|date|before:today',
    //         'national_id' => 'required|string|max:20',
    //         'kra_pin_no' => 'required|string|max:20',
    //         'gender' => 'nullable|string|in:Male,Female',
    //         'marital_status' => 'nullable|string|in:Single,Married,Divorced,Widowed',
    //         'monthly_income' => 'required|numeric|min:0',
    //         'occupation' => 'nullable|string|max:255',
    //         'dependents' => 'nullable|integer|min:0',
    //         'preferred_monthly_contribution' => [
    //             'required',
    //             'numeric',
    //             "min:$minContribution",
    //         ],
    //         'reason_for_joining' => 'nullable|string|max:1000',
    //         'email' => [
    //             'required',
    //             'email',
    //             'max:100',
    //             'unique:sacco_members_new_applications,email',
    //             function ($attribute, $value, $fail) {
    //                 $existsInMembers = DB::table('sacco_members')->where('member_email', $value)->exists();
    //                 if ($existsInMembers) {
    //                     $fail('The email address already exists in the SACCO member database.');
    //                 }
    //             },
    //         ],
    //         'phone' => [
    //             'required',
    //             'string',
    //             'max:15',
    //             'unique:sacco_members_new_applications,phone',
    //             function ($attribute, $value, $fail) {
    //                 $existsInMembers = DB::table('sacco_members')->where('member_phone_no', $value)->exists();
    //                 if ($existsInMembers) {
    //                     $fail('The phone number already exists in the SACCO member database.');
    //                 }
    //             },
    //         ],
    //         'physical_location' => 'required|string|max:100',
    //         'next_of_kin' => 'nullable|array|max:3',
    //         'next_of_kin.*.name' => 'nullable|string|max:100',
    //         'next_of_kin.*.relationship' => [
    //             'nullable',
    //             'string',
    //             function ($attribute, $value, $fail) use ($validKinRelationships) {
    //                 if (!in_array(strtoupper($value), array_map('strtoupper', $validKinRelationships))) {
    //                     $fail('Invalid relationship value.');
    //                 }
    //             },
    //         ],
    //         'next_of_kin.*.phone' => 'nullable|string|max:30',
    //         'next_of_kin.*.id_or_cert_no' => 'nullable|string|max:30',
    //         'next_of_kin.*.share_percent' => 'nullable|numeric|min:0|max:100',
    //         'terms' => 'accepted',
    //         'g-recaptcha-response' => 'required',
    //     ]);

    //     // Ensure the total kin_share_percent does not exceed 100%
    //     if (!empty($request->next_of_kin)) {
    //         $totalSharePercent = collect($request->next_of_kin)->sum('share_percent');
    //         if ($totalSharePercent > 100) {
    //             return redirect()->back()->withErrors([
    //                 'next_of_kin' => 'The total share percentage for next of kin cannot exceed 100%.',
    //             ])->withInput();
    //         }
    //     }

    //     // If validation fails, redirect back with errors
    //     if ($validator->fails()) {
    //         return redirect()->back()->withErrors($validator)->withInput();
    //     }

    //     // Verify reCAPTCHA
    //     $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
    //         'secret' => env('RECAPTCHA_SECRET_KEY'),
    //         'response' => $request->input('g-recaptcha-response'),
    //         'remoteip' => $request->ip(),
    //     ]);

    //     $recaptchaData = $response->json();

    //     if (!($recaptchaData['success'] ?? false)) {
    //         logger()->error('reCAPTCHA verification failed', ['response' => $recaptchaData]);
    //         return redirect()->back()->withErrors(['captcha' => 'reCAPTCHA verification failed.'])->withInput();
    //     }

    //     if (($recaptchaData['score'] ?? 0) < 0.5) {
    //         return redirect()->back()->withErrors(['captcha' => 'Suspicious activity detected. Try again.'])->withInput();
    //     }

    //     // Generate a unique key for the email
    //     $emailKeyUnique = Str::uuid();

    //     // Save the validated data to the database
    //     DB::table('sacco_members_new_applications')->insert([
    //         'first_name' => strtoupper($request->input('first_name')),
    //         'last_name' => strtoupper($request->input('last_name')),
    //         'birth_date' => $request->input('birth_date'),
    //         'national_id' => strtoupper($request->input('national_id')),
    //         'kra_pin_no' => strtoupper($request->input('kra_pin_no')),
    //         'email' => strtolower($request->input('email')),
    //         'phone' => strtoupper($request->input('phone')),
    //         'physical_location' => strtoupper($request->input('physical_location')),
    //         'gender' => strtoupper($request->input('gender')),
    //         'marital_status' => strtoupper($request->input('marital_status')),
    //         'monthly_income' => $request->input('monthly_income'),
    //         'occupation' => strtoupper($request->input('occupation')),
    //         'dependents' => $request->input('dependents'),
    //         'preferred_monthly_contribution' => $request->input('preferred_monthly_contribution'),
    //         'reason_for_joining' => strtoupper($request->input('reason_for_joining')),
    //         'next_of_kin_name' => json_encode(array_map('strtoupper', array_column($request->next_of_kin ?? [], 'name'))),
    //         'next_of_kin_relationship' => json_encode(array_map('strtoupper', array_column($request->next_of_kin ?? [], 'relationship'))),
    //         'next_of_kin_phone' => json_encode(array_map('strtoupper', array_column($request->next_of_kin ?? [], 'phone'))),
    //         'next_of_kin_id_or_cert_no' => json_encode(array_map('strtoupper', array_column($request->next_of_kin ?? [], 'id_or_cert_no'))),
    //         'kin_share_percent' => json_encode(array_column($request->next_of_kin ?? [], 'share_percent')),
    //         'email_key_unique' => $emailKeyUnique,
    //         'created_at' => now(),
    //         'updated_at' => now(),
    //     ]);

    //     // Redirect with a success message
    //     return redirect()->route('register.form')->with('success', 'Registration successful! Please check your email and follow the link we’ve sent to upload additional required documents. We will get in touch with you soon.');
    // }


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
