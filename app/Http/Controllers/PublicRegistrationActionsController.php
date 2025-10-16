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

        $nid = trim($member->national_id ?? '');
        $phone = trim($member->phone ?? '');
        $email = trim($member->email ?? '');

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
                'message' => "Duplicate record found: National ID ($nid), Phone ($phone), Email ($email)"
            ]);
        }

        try {
            DB::table('sacco_members')->insert([
                'member_name'           => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                'member_email'          => $email,
                'member_phone_no'       => $phone,
                'member_national_id'    => $nid,
                'member_postal_address' => $member->physical_location ?? '',
                'member_kra_pin'        => $member->kra_pin_no ?? '',
                'member_date_joined'    => now()->toDateString(),
                'member_transdate'      => now(),
                'member_ip'             => $request->ip(),
                'member_user_id'        => auth()->id() ?? 0,
                'member_active'         => 'Y',
            ]);

            DB::table('sacco_members_new_applications')
                ->where('id', $id)
                ->update(['exported' => 'Y', 'exported_on' => now()]);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Export to live failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }
}