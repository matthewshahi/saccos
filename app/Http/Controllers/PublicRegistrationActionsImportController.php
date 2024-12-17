<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Redirect;
use Illuminate\Support\Facades\URL;
 
 
class PublicRegistrationActionsImportController extends Controller
{
    private function mapGender($gender)
{
    if (strtolower($gender) === 'male') {
        return 'M';
    } elseif (strtolower($gender) === 'female') {
        return 'F';
    } else {
        return null; // If gender is invalid or empty
    }
}

public function exportLiveData(Request $request)
{
    // Check for DEFAULT_DEPT
    $defaultDept = DB::table('sacco_defaults')
        ->where('default_name', 'DEFAULT_DEPT')
        ->value('default_value');

    if (!$defaultDept) {
        return response()->json(['success' => false, 'message' => 'DEFAULT_DEPT is not configured.']);
    }

    // Fetch data from request
    $applications = $request->input('members');
    $lastSaccoId = DB::table('sacco_members')->max('member_sacco_id');

    foreach ($applications as $application) {
        // Increment SACCO ID
        $nextSaccoId = $this->incrementSaccoId($lastSaccoId);

        // Check for duplicates
        $duplicate = DB::table('sacco_members')
            ->where('member_sacco_id', $nextSaccoId)
            ->orWhere('member_national_id', $application['national_id'])
            ->orWhere('member_phone_no', $application['phone'])
            ->orWhere('member_email', $application['email'])
            ->first();

        if ($duplicate) {
            $errorFields = [];
            if ($duplicate->member_sacco_id === $nextSaccoId) {
                $errorFields[] = "SACCO ID ($nextSaccoId)";
            }
            if ($duplicate->member_national_id === $application['national_id']) {
                $errorFields[] = "National ID ({$application['national_id']})";
            }
            if ($duplicate->member_phone_no === $application['phone']) {
                $errorFields[] = "Phone Number ({$application['phone']})";
            }
            if ($duplicate->member_email === $application['email']) {
                $errorFields[] = "Email ({$application['email']})";
            }

            return response()->json([
                'success' => false,
                'message' => 'Duplicate record found: ' . implode(', ', $errorFields)
            ]);
        }

        // Insert member into sacco_members table
        $memberId = DB::table('sacco_members')->insertGetId([
            'member_name' => $application['first_name'] . ' ' . $application['last_name'],
            'member_date_joined' => Carbon::now(),
            'member_dept' => $defaultDept,
            'member_sacco_id' => $nextSaccoId,
            'member_national_id' => $application['national_id'],
            'member_phone_no' => $application['phone'],
            'member_postal_address' => $application['physical_location'],
            'member_gender' => $this->mapGender($application['gender']),
            'member_email' => $application['email'],
            'member_active' => 'Y',
            'member_image' => $application['passport_photo'],
            'member_signature' => $application['signature'],
            'member_id_copy_front' => $application['id_copy_front'],
            'member_id_copy_back' => $application['id_copy_back'],
            'member_payslips_bank_statements' => $application['payslips_bank_statements'],
            'member_kra_pin' => $application['kra_pin_no'],
            'member_dob' => $application['birth_date'],
            'bank_name' => $application['bank_name'],
            'bank_branch' => $application['bank_branch'],
            'bank_account_number' => $application['bank_account_number'],
            'member_position'=> 1,
            'member_ip' => $request->ip(),
            'member_transdate' => Carbon::now(),
        ]);

        // Handle Next of Kin
        $this->importNextOfKin($application, $memberId);

        $lastSaccoId = $nextSaccoId;
    }

    return response()->json(['success' => true, 'message' => 'Members and Next of Kin successfully exported to live data.']);
}

// Helper: Import Next of Kin
private function importNextOfKin($application, $memberId)
{
    $nextOfKinNames = json_decode($application['next_of_kin_name'] ?? '[]', true);
    $nextOfKinRelations = json_decode($application['next_of_kin_relationship'] ?? '[]', true);
    $nextOfKinPhones = json_decode($application['next_of_kin_phone'] ?? '[]', true);
    $nextOfKinIds = json_decode($application['next_of_kin_id_or_cert_no'] ?? '[]', true);
    $kinSharePercents = json_decode($application['kin_share_percent'] ?? '[]', true);

    foreach ($nextOfKinNames as $index => $name) {
        $relationship = $nextOfKinRelations[$index] ?? 'Other';
        $phone = $nextOfKinPhones[$index] ?? '';
        $idOrCertNo = $nextOfKinIds[$index] ?? '';
        $sharePercent = $kinSharePercents[$index] ?? 0;

        // Map relationship to kin_type_id
        $kinTypeId = DB::table('sacco_kin_type')
            ->where('kin_type_name', $relationship)
            ->value('kin_type_id') ?? 1; // Default to 'Other'

        DB::table('sacco_next_of_kin')->insert([
            'kin_member_id' => $memberId,
            'kin_names' => $name,
            'kin_address' => $phone,
            'kin_national_id' => $idOrCertNo,
            'kin_percent' => $sharePercent,
            'kin_relationship' => $kinTypeId,
            'kin_ip' => request()->ip(),
            'kin_transdate' => Carbon::now(),
        ]);
    }
}


    // Helper to increment SACCO ID
    private function incrementSaccoId($lastSaccoId)
    {
        // If no last ID is available, return the default starting ID
        if (!$lastSaccoId) return 'S-ID001';
    
        // Regular expression to split prefix and numeric part
        preg_match('/^([^\d]*)(\d*)$/', $lastSaccoId, $matches);
    
        // Extract prefix (non-numeric part), default to empty string
        $prefix = isset($matches[1]) ? $matches[1] : '';
    
        // Extract numeric part, default to 0 and increment by 1
        $number = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] + 1 : 1;
    
        // Format the incremented number with leading zeros (3 digits)
        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}