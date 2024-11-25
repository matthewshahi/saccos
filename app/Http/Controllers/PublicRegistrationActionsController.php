<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicRegistrationActionsController extends Controller
{
    // Display a list of new member applications with pagination, search, and sorting
    public function listMembers(Request $request)
    {
        // Search functionality
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

        $members = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('public.new_member_applications', compact('members', 'search'));
    }

    // Update a specific field of a new member application (auto-save functionality)
    public function updateField(Request $request, $id)
    {
        $validatedData = $request->validate([
            'field' => 'required|string|in:contacted,contacted_by,contacted_on,comments',
            'value' => 'nullable|string|max:255',
        ]);

        $field = $validatedData['field']; 
        $value = $validatedData['value'];

        DB::table('sacco_members_new_applications')
            ->where('id', $id)
            ->update([$field => $value]);

        return response()->json(['status' => 'success', 'message' => 'Field updated successfully']);
    }
    public function getMemberDetails($id)
        {
            $member = DB::table('sacco_members_new_applications')->where('id', $id)->first();

            if (!$member) {
                return response()->json(['error' => 'Member not found'], 404);
            }

            return response()->json($member);
        }
}