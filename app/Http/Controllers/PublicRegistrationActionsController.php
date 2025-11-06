<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicRegistrationActionsController extends Controller
{
    public function listMembers(Request $request)
    {
        $search = $request->input('search');
        $query = DB::table('sacco_members_new_applications');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('national_id', 'LIKE', "%{$search}%")
                  ->orWhere('physical_location', 'LIKE', "%{$search}%");
            });
        }

        $members = $query->orderByDesc('created_at')->paginate(10);
        return view('public.new_member_applications', compact('members', 'search'));
    }

    public function getMemberDetails($id)
    {
        $member = DB::table('sacco_members_new_applications')->find($id);
        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }
        return response()->json($member);
    }

    public function updateField(Request $request, $id)
    {
        $validated = $request->validate([
            'field' => 'required|string|in:contacted,contacted_by,contacted_on,comments',
            'value' => 'nullable|string|max:255',
        ]);

        DB::table('sacco_members_new_applications')
            ->where('id', $id)
            ->update([$validated['field'] => $validated['value']]);

        return response()->json(['status' => 'success']);
    }


    public function exportLive(Request $request)
{
    $id = $request->input('member_id');

    if (!$id) {
        return response()->json(['success' => false, 'message' => 'No member ID provided.']);
    }

    $member = DB::table('sacco_members_new_applications')->find($id);
    if (!$member) {
        return response()->json(['success' => false, 'message' => 'Member not found.']);
    }

    $nid   = trim($member->national_id ?? '');
    $phone = trim($member->phone ?? '');
    $email = trim($member->email ?? '');

    // Check duplicates
    $duplicate = DB::table('sacco_members')
        ->where(function ($q) use ($nid, $phone, $email) {
            $q->where('member_national_id', $nid)
              ->orWhere('member_phone_no', $phone)
              ->orWhere('member_email', $email);
        })
        ->first();

    if ($duplicate) {
        return response()->json([
            'success' => false,
            'message' => "Duplicate record found: National ID ($nid), Phone ($phone), or Email ($email)"
        ]);
    }

    try {
        DB::beginTransaction();

        // Generate next member_sacco_id (SA0001, SA0002, etc.)
        $latest = DB::table('sacco_members')
            ->where('member_sacco_id', 'LIKE', 'SA%')
            ->orderByDesc('member_id')
            ->value('member_sacco_id');

        if ($latest && preg_match('/^SA(\d+)$/', $latest, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        $memberSaccoId = 'SA' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $gender = strtoupper(substr($member->gender ?? '', 0, 1));

        $dob = $member->dob ?? $member->birth_date ?? null;


      $newMemberId = DB::table('sacco_members')->insertGetId([
    'member_sacco_id'       => $memberSaccoId,
    'member_dept'           => 1,
    'member_name'           => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
    'member_email'          => $email,
    'member_phone_no'       => $phone,
    'member_national_id'    => $nid,
      'member_dob'            => $dob, // ✅ Added
    'member_gender'         => $gender ?? '',
    'member_postal_address' => $member->physical_location ?? '',
    'member_kra_pin'        => $member->kra_pin_no ?? '',
    'bank_name'             => $member->bank_name ?? '',
    'bank_branch'           => $member->bank_branch ?? '',
    'bank_account_number'   => $member->bank_account_number ?? '',
    'member_date_joined'    => now()->toDateString(),
    
    'member_position'       => 1,
    'member_transdate'      => now(),
    'member_ip'             => $request->ip(),
    'member_user_id'        => auth()->id() ?? 0,
    'member_active'         => 'Y',


     'member_image'                   => $member->passport_photo ?? '',
    'member_signature'               => $member->signature ?? '',
    'member_id_copy_front'           => $member->id_copy_front ?? '',
    'member_id_copy_back'            => $member->id_copy_back ?? '',
    'member_payslips_bank_statements'=> $member->payslips_bank_statements ?? '',
]);

// 👇 Add this line right after inserting:
$this->saveBankDetails($member, $newMemberId);
$this->saveNextOfKin($member, $newMemberId, $request->ip(), auth()->id() ?? 0);

        // Mark the application as exported

        
        DB::table('sacco_members_new_applications')
            ->where('id', $id)
            ->update([
                'exported'    => 'Y',
                'exported_on' => now(),
            ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "✅ Member exported successfully as {$memberSaccoId}",
            'member_code' => $memberSaccoId,
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Export to live failed', [
            'application_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
        ]);
    }
}




    // public function exportLive(Request $request)
    // {
    //     $id = $request->input('member_id');

         

    //     if (!$id) {
    //         return response()->json(['success' => false, 'message' => 'No member ID provided.']);
    //     }

    //     $member = DB::table('sacco_members_new_applications')->find($id);
    //     if (!$member) {
    //         return response()->json(['success' => false, 'message' => 'Member not found.']);
    //     }

    //     $nid = trim($member->national_id ?? '');
    //     $phone = trim($member->phone ?? '');
    //     $email = trim($member->email ?? '');

    //     $duplicate = DB::table('sacco_members')
    //         ->where(function ($q) use ($nid, $phone, $email) {
    //             $q->where('member_national_id', $nid)
    //               ->orWhere('member_phone_no', $phone)
    //               ->orWhere('member_email', $email);
    //         })
    //         ->first();

    //     if ($duplicate) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => "Duplicate record found: National ID ($nid), Phone ($phone), Email ($email)"
    //         ]);
    //     }

    //     try {
    //         DB::table('sacco_members')->insert([
    //             'member_name'           => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
    //             'member_email'          => $email,
    //             'member_phone_no'       => $phone,
    //             'member_national_id'    => $nid,
    //             'member_postal_address' => $member->physical_location ?? '',
    //             'member_kra_pin'        => $member->kra_pin_no ?? '',
    //             'member_date_joined'    => now()->toDateString(),
    //             'member_transdate'      => now(),
    //             'member_ip'             => $request->ip(),
    //             'member_user_id'        => auth()->id() ?? 0,
    //             'member_active'         => 'Y',
    //         ]);

    //         DB::table('sacco_members_new_applications')
    //             ->where('id', $id)
    //             ->update(['exported' => 'Y', 'exported_on' => now()]);

    //         return response()->json(['success' => true]);
    //     } catch (\Throwable $e) {
    //         Log::error('Export to live failed', [
    //             'id' => $id,
    //             'error' => $e->getMessage()
    //         ]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Database error: ' . $e->getMessage()
    //         ]);
    //     }
    // }

    /**
 * Save bank details for an imported member (if any).
 *
 * @param object $member   Member record from sacco_members_new_applications
 * @param int    $memberId The corresponding member_id in sacco_members
 * @return void
 */
private function saveBankDetails($member, $memberId)
{
    try {
        // Skip if no valid account number
        if (empty($member->bank_account_number)) {
            return;
        }

        // Check if already exists
        $exists = DB::table('sacco_member_bank_details')
            ->where('bank_member_id', $memberId)
            ->exists();

        if (!$exists) {
            DB::table('sacco_member_bank_details')->insert([
                'bank_member_name'           => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                'bank_member_account_number' => trim($member->bank_account_number ?? ''),
                'bank_member_branch'         => trim($member->bank_branch ?? ''),
                'bank_member_id'             => $memberId,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('Failed to save bank details', [
            'member_id' => $memberId,
            'error'     => $e->getMessage(),
        ]);
    }
}

/**
 * Save next of kin records for an imported member.
 *
 * @param object $member   Member record from sacco_members_new_applications
 * @param int    $memberId The corresponding member_id in sacco_members
 * @param string $ip       Request IP (optional)
 * @param int|null $userId Logged-in user ID (optional)
 * @return void
 */
/**
 * Save next of kin records for an imported member, mapping kin relationship to kin_type_id.
 *
 * @param object $member   Member record from sacco_members_new_applications
 * @param int    $memberId The corresponding member_id in sacco_members
 * @param string $ip       Request IP (optional)
 * @param int|null $userId Logged-in user ID (optional)
 * @return void
 */
private function saveNextOfKin($member, $memberId, $ip = null, $userId = null)
{
    try {
        // Decode JSON fields
        $names         = json_decode($member->next_of_kin_name ?? '[]', true);
        $relationships = json_decode($member->next_of_kin_relationship ?? '[]', true);
        $phones        = json_decode($member->next_of_kin_phone ?? '[]', true);
        $idsOrCerts    = json_decode($member->next_of_kin_id_or_cert_no ?? '[]', true);
        $percents      = json_decode($member->kin_share_percent ?? '[]', true);

        // Determine max count across arrays
        $max = max(
            count($names ?? []),
            count($relationships ?? []),
            count($phones ?? []),
            count($idsOrCerts ?? []),
            count($percents ?? [])
        );

        for ($i = 0; $i < $max; $i++) {
            $name  = trim($names[$i] ?? '');
            $rel   = strtoupper(trim($relationships[$i] ?? ''));
            $nid   = trim($idsOrCerts[$i] ?? '');
            $share = floatval($percents[$i] ?? 0);
            $phone = trim($phones[$i] ?? '');

            if (!$name) {
                continue;
            }

            // 🔍 Get kin_type_id from sacco_kin_type (default to 1 if not found)
            $kinTypeId = DB::table('sacco_kin_type')
                ->where(DB::raw('UPPER(TRIM(kin_type_name))'), $rel)
                ->value('kin_type_id') ?? 1;

            // Avoid duplicates
            $exists = DB::table('sacco_next_of_kin')
                ->where('kin_member_id', $memberId)
                ->where('kin_names', $name)
                ->exists();

            if (!$exists) {
                DB::table('sacco_next_of_kin')->insert([
                    'kin_member_id'    => $memberId,
                    'kin_names'        => $name,
                    'kin_address'      => $phone, // storing phone in address field
                    'kin_national_id'  => $nid,
                    'kin_percent'      => $share,
                    'kin_relationship' => $kinTypeId, // now stores numeric ID
                    'kin_user_id'      => $userId ?? auth()->id() ?? 0,
                    'kin_ip'           => $ip ?? request()->ip(),
                    'kin_transdate'    => now(),
                    'kin_deleted'      => 'N',
                ]);
            }
        }
    } catch (\Throwable $e) {
        Log::error('Failed to save next of kin', [
            'member_id' => $memberId,
            'error'     => $e->getMessage(),
        ]);
    }
}

}