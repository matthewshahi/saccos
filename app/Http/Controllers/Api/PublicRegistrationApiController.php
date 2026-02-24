<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicRegistrationApiController extends Controller
{
    /**
     * GET /api/public/registration/meta
     */
    public function meta(Request $request)
    {
        $saccoDefaults = DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'member_ship_fee',
                'min_share_contribution',
            ])
            ->pluck('default_value', 'default_name');

        $paybillNumber = DB::table('mpesa_configs')
            ->where('api_type', 'c2b')
            ->orderBy('id')
            ->value('shortcode');

        $kinTypes = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->orderBy('kin_type_name', 'asc')
            ->get(['kin_type_id', 'kin_type_name']);

        return response()->json([
            'paybillNumber'   => (string)($paybillNumber ?? ''),
            'membershipFee'   => (int)($saccoDefaults['member_ship_fee'] ?? 1000),
            'minContribution' => (int)($saccoDefaults['min_share_contribution'] ?? 0),
            'kinTypes'        => $kinTypes->map(fn ($k) => [
                'id'   => (int)$k->kin_type_id,
                'name' => (string)$k->kin_type_name,
            ])->values(),
        ], 200);
    }

    /**
     * POST /api/public/registration/submit
     * Accepts JSON from mobile.
     *
     * NOTE:
     * - Table column is `dob` (date)
     * - Table has `terms` (tinyint) - must be accepted
     * - Table `physical_location` is NOT NULL, so we store '' if missing
     * - Files upload is not handled in this JSON v1 endpoint
     */
    public function submit(Request $request)
    {
        $validKinRelationships = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->pluck('kin_type_name')
            ->toArray();

        $validator = Validator::make($request->all(), [
            // Personal
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',

            // App sends birth_date; DB column is dob
            'birth_date'   => 'required|date|before:today',

            'national_id'  => 'required|string|max:255',
            'kra_pin_no'   => 'nullable|string|max:20',

            'gender'       => 'nullable|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',

            // Contact
            'email' => [
                'required','email','max:255',
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

            // Your table does NOT have a unique index on phone,
            // but we still enforce uniqueness at validation level (good security).
            'phone' => [
                'required','string','max:255',
                function ($attribute, $value, $fail) {
                    $existsInApps = DB::table('sacco_members_new_applications')
                        ->where('phone', $value)
                        ->where('deleted', 'N')
                        ->exists();
                    if ($existsInApps) {
                        $fail('This phone number already has a pending application.');
                    }

                    $existsInMembers = DB::table('sacco_members')
                        ->where('member_phone_no', $value)
                        ->exists();
                    if ($existsInMembers) {
                        $fail('The phone number already exists in the SACCO member database.');
                    }
                },
            ],

            // DB column is NOT NULL, but app can treat it optional; we store '' if missing
            'physical_location' => 'nullable|string|max:255',

            // Additional
            'occupation' => 'nullable|string|max:255',
            'dependents' => 'nullable|string|max:5',
            'preferred_monthly_contribution' => 'nullable|numeric',
            'reason_for_joining' => 'nullable|string',

            // Next of Kin (optional up to 3)
            'next_of_kin' => 'nullable|array|max:3',
            'next_of_kin.*.name' => 'nullable|string|max:100',
            'next_of_kin.*.relationship' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($validKinRelationships) {
                    if (!is_null($value) && $value !== '' && !in_array($value, $validKinRelationships)) {
                        $fail('Invalid relationship value.');
                    }
                },
            ],
            'next_of_kin.*.phone' => 'nullable|string|max:30',
            'next_of_kin.*.id_or_cert_no' => 'nullable|string|max:30',
            'next_of_kin.*.share_percent' => 'nullable|numeric|min:0|max:100',

            // Consent (must be accepted)
            'certification_statement' => 'accepted',
            'terms' => 'accepted',

            // Optional device fields (not stored unless you add columns later)
            'device_id'   => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Normalize Next of Kin rows + enforce share total <= 100
        $nextOfKin = $request->input('next_of_kin', []);
        if (!is_array($nextOfKin)) $nextOfKin = [];

        $nextOfKinFiltered = collect($nextOfKin)->map(function ($row) {
            $row = is_array($row) ? $row : [];
            return [
                'name'          => trim((string)($row['name'] ?? '')),
                'relationship'  => trim((string)($row['relationship'] ?? '')),
                'phone'         => trim((string)($row['phone'] ?? '')),
                'id_or_cert_no' => trim((string)($row['id_or_cert_no'] ?? '')),
                'share_percent' => trim((string)($row['share_percent'] ?? '')),
            ];
        })->filter(function ($row) {
            return $row['name'] !== '' ||
                $row['relationship'] !== '' ||
                $row['phone'] !== '' ||
                $row['id_or_cert_no'] !== '' ||
                $row['share_percent'] !== '';
        })->values();

        $totalSharePercent = $nextOfKinFiltered->sum(function ($row) {
            return is_numeric($row['share_percent']) ? (float)$row['share_percent'] : 0;
        });

        if ($totalSharePercent > 100) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => [
                    'next_of_kin' => ['The total share percentage for next of kin cannot exceed 100%.'],
                ],
            ], 422);
        }

        // Payment meta for response
        $saccoDefaults = DB::table('sacco_defaults')
            ->whereIn('default_name', ['member_ship_fee'])
            ->pluck('default_value', 'default_name');

        $membershipFee = (int)($saccoDefaults['member_ship_fee'] ?? 1000);

        $paybillNumber = DB::table('mpesa_configs')
            ->where('api_type', 'c2b')
            ->orderBy('id')
            ->value('shortcode');

        // Map request birth_date -> DB dob
        $dob = Carbon::parse($request->birth_date)->format('Y-m-d');

        $nationalIdRaw = trim((string)$request->national_id);
        $accountNumber = 'REG' . $nationalIdRaw;

        $kinNames  = $nextOfKinFiltered->pluck('name')->all();
        $kinRel    = $nextOfKinFiltered->pluck('relationship')->all();
        $kinPhones = $nextOfKinFiltered->pluck('phone')->all();
        $kinIds    = $nextOfKinFiltered->pluck('id_or_cert_no')->all();
        $kinShares = $nextOfKinFiltered->pluck('share_percent')->all();

        $emailKeyUnique = (string)Str::uuid();

        DB::beginTransaction();
        try {
            $id = DB::table('sacco_members_new_applications')->insertGetId([
                'first_name'   => strtoupper($request->first_name),
                'last_name'    => strtoupper($request->last_name),
                'dob'          => $dob,
                'national_id'  => strtoupper($request->national_id),
                'kra_pin_no'   => strtoupper((string)($request->kra_pin_no ?? '')),

                // consent flags
                'terms' => 1,

                'email' => strtolower($request->email),
                'phone' => $request->phone,

                // NOT NULL in DB
                'physical_location' => strtoupper((string)($request->physical_location ?? '')),

                'occupation' => $request->occupation,
                'marital_status' => $request->marital_status,
                'gender' => $request->gender,
                'dependents' => $request->dependents,

                'preferred_monthly_contribution' =>
                    is_numeric($request->preferred_monthly_contribution)
                        ? $request->preferred_monthly_contribution
                        : null,

                'reason_for_joining' => $request->reason_for_joining,

                // JSON Kin columns
                'next_of_kin_name' => json_encode($kinNames),
                'next_of_kin_relationship' => json_encode($kinRel),
                'next_of_kin_phone' => json_encode($kinPhones),
                'next_of_kin_id_or_cert_no' => json_encode($kinIds),
                'kin_share_percent' => json_encode($kinShares),

                // docs are later
                'passport_photo' => null,
                'signature' => null,
                'id_copy_front' => null,
                'id_copy_back' => null,
                'payslips_bank_statements' => null,

                // bank (later)
                'bank_name' => null,
                'bank_branch' => null,
                'bank_account_number' => null,

                // your DB uses varchar here
                'certification_statement' => '1',

                // defaults exist in table
                'email_sent' => 'N',
                'exported'   => 'N',
                'deleted'    => 'N',

                'email_key_unique' => $emailKeyUnique,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status'        => 'ok',
                'message'       => 'Application received. Pay the registration fee to complete your application.',
                'applicationId' => (int)$id,
                'emailKeyUnique'=> $emailKeyUnique,

                // Payment instructions
                'paybillNumber' => (string)($paybillNumber ?? ''),
                'amount'        => $membershipFee,
                'accountNumber' => $accountNumber,
                'reference'     => $accountNumber,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to submit application',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }
}