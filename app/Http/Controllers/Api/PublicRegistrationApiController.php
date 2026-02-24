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
     * Returns: paybillNumber, membershipFee, minContribution, kinTypes
     */
    public function meta(Request $request)
    {
        // Fetch sacco_defaults in a single query
        $saccoDefaults = DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'member_ship_fee',
                'min_share_contribution',
            ])
            ->pluck('default_value', 'default_name');

        // Fetch paybill number from mpesa_configs
        $paybillNumber = DB::table('mpesa_configs')
            ->where('api_type', 'c2b')
            ->orderBy('id')
            ->value('shortcode');

        // Fetch kin types ordered alphabetically
        $kinTypes = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->orderBy('kin_type_name', 'asc')
            ->get(['kin_type_id', 'kin_type_name']);

        return response()->json([
            'paybillNumber'   => (string) ($paybillNumber ?? ''),
            'membershipFee'   => (int) ($saccoDefaults['member_ship_fee'] ?? 1000),
            'minContribution' => (int) ($saccoDefaults['min_share_contribution'] ?? 0),
            'kinTypes'        => $kinTypes->map(function ($k) {
                return [
                    'id'   => (int) $k->kin_type_id,
                    'name' => (string) $k->kin_type_name,
                ];
            })->values(),
        ], 200);
    }

    /**
     * POST /api/public/registration/submit
     * Accepts JSON payload from mobile (no login).
     *
     * Security:
     * - Use throttle middleware on the route
     * - Duplicate checks against sacco_members_new_applications + sacco_members
     */
    public function submit(Request $request)
    {
        // Fetch valid kin relationships
        $validKinRelationships = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', 'N')
            ->pluck('kin_type_name')
            ->toArray();

        // Validation rules (aligned to your desktop controller, minus recaptcha + files)
        $validator = Validator::make($request->all(), [
            // Personal Details
            'first_name'    => 'required|string|max:50',
            'last_name'     => 'required|string|max:50',
            'birth_date'    => 'required|date|before:today',
            'national_id'   => 'required|string|max:20',
            'kra_pin_no'    => 'required|string|max:20',
            'gender'        => 'nullable|in:Male,Female',
            'marital_status'=> 'nullable|in:Single,Married,Divorced,Widowed',

            // Additional Information
            'occupation'                    => 'nullable|string|max:255',
            'dependents'                    => 'nullable|integer|min:0',
            'preferred_monthly_contribution'=> 'nullable|numeric',
            'reason_for_joining'            => 'nullable|string|max:1000',

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

            // Next of Kin (optional, up to 3)
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

            // Terms
            'certification_statement' => 'accepted',
            'terms'                   => 'accepted',

            // Optional device fingerprint (accepted but not stored unless you add columns)
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

        // Normalize + validate Next of Kin share total (<= 100)
        $nextOfKin = $request->input('next_of_kin', []);
        if (!is_array($nextOfKin)) $nextOfKin = [];

        // Keep only kin rows that have at least one field filled
        $nextOfKinFiltered = collect($nextOfKin)->map(function ($row) {
            $row = is_array($row) ? $row : [];
            return [
                'name'         => trim((string)($row['name'] ?? '')),
                'relationship' => trim((string)($row['relationship'] ?? '')),
                'phone'        => trim((string)($row['phone'] ?? '')),
                'id_or_cert_no'=> trim((string)($row['id_or_cert_no'] ?? '')),
                'share_percent'=> trim((string)($row['share_percent'] ?? '')),
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

        // Fetch payment meta (for response)
        $saccoDefaults = DB::table('sacco_defaults')
            ->whereIn('default_name', ['member_ship_fee'])
            ->pluck('default_value', 'default_name');

        $membershipFee = (int) ($saccoDefaults['member_ship_fee'] ?? 1000);

        $paybillNumber = DB::table('mpesa_configs')
            ->where('api_type', 'c2b')
            ->orderBy('id')
            ->value('shortcode');

        // Prepare insert (aligned with your desktop controller)
        $birthDate = Carbon::parse($request->birth_date)->format('Y-m-d');

        $nationalIdRaw = trim((string)$request->national_id);
        $accountNumber = 'REG' . $nationalIdRaw;

        // Store kin arrays in the same JSON-column style you already use
        $kinNames   = $nextOfKinFiltered->pluck('name')->all();
        $kinRel     = $nextOfKinFiltered->pluck('relationship')->all();
        $kinPhones  = $nextOfKinFiltered->pluck('phone')->all();
        $kinIds     = $nextOfKinFiltered->pluck('id_or_cert_no')->all();
        $kinShares  = $nextOfKinFiltered->pluck('share_percent')->all();

        $emailKeyUnique = (string) Str::uuid();

        DB::beginTransaction();

        try {
            $id = DB::table('sacco_members_new_applications')->insertGetId([
                'first_name' => strtoupper($request->first_name),
                'last_name'  => strtoupper($request->last_name),
                'birth_date' => $birthDate,
                'national_id'=> strtoupper($request->national_id),
                'kra_pin_no' => strtoupper($request->kra_pin_no),

                'gender'        => $request->gender,
                'marital_status'=> $request->marital_status,

                'occupation' => $request->occupation,
                'dependents' => $request->dependents,

                'preferred_monthly_contribution' =>
                    is_numeric($request->preferred_monthly_contribution)
                        ? $request->preferred_monthly_contribution
                        : 0,

                'reason_for_joining' => strtoupper($request->reason_for_joining ?? ''),

                'email' => strtolower($request->email),
                'phone' => $request->phone,
                'physical_location' => strtoupper($request->physical_location ?? ''),

                // files are not handled in this mobile v1 API
                'passport_photo' => null,
                'signature' => null,
                'id_copy_front' => null,
                'id_copy_back' => null,
                'payslips_bank_statements' => null,

                // bank details optional (mobile not collecting now)
                'bank_name' => '',
                'bank_branch' => '',
                'bank_account_number' => null,

                'next_of_kin_name' => json_encode($kinNames),
                'next_of_kin_relationship' => json_encode($kinRel),
                'next_of_kin_phone' => json_encode($kinPhones),
                'next_of_kin_id_or_cert_no' => json_encode($kinIds),
                'kin_share_percent' => json_encode($kinShares),

                'certification_statement' => 1,
                'email_key_unique' => $emailKeyUnique,

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // ✅ Return JSON response for the app
            return response()->json([
                'status'         => 'ok',
                'message'        => 'Application received. Pay the registration fee to complete your application.',
                'applicationId'  => (int) $id,
                'emailKeyUnique' => $emailKeyUnique,

                // Payment instructions
                'paybillNumber'  => (string) ($paybillNumber ?? ''),
                'amount'         => $membershipFee,
                'accountNumber'  => $accountNumber,

                // Helpful echoes
                'reference'      => $accountNumber,
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