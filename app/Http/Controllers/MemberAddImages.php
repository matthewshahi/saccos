<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;

class MemberAddImages extends Controller
{
    public function index($member_id)
    {
        $member = DB::table('sacco_members')->where('member_id', $member_id)->first();

        if (!$member) {
            abort(404, 'Member not found');
        }

        return view('members.edit_images', compact('member'));
    }

    // public function update(Request $request, $member_id)
    // {
    //     // Max size: 280KB = 280 * 1024
    //     $maxFileSizeKB = 280;

    //     $request->validate([
    //         'member_image' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
    //         'member_signature' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
    //         'member_id_copy_front' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
    //         'member_id_copy_back' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
    //         'member_payslips_bank_statements' => "nullable|mimes:pdf,jpg,jpeg,png|max:$maxFileSizeKB",
    //     ]);

    //     $data = [];

    //     foreach (
    //         [
    //             'member_image',
    //             'member_signature',
    //             'member_id_copy_front',
    //             'member_id_copy_back',
    //             'member_payslips_bank_statements'
    //         ] as $field
    //     ) {
    //         if ($request->hasFile($field)) {
    //             $filename = $field . '.' . $request->file($field)->getClientOriginalExtension();
    //             $path = $request->file($field)->storeAs("members/{$member_id}", $filename); // private storage
    //             $data[$field] = $path;
    //         }
    //     }

    //     if (!empty($data)) {
    //         DB::table('sacco_members')->where('member_id', $member_id)->update($data);
    //     }


    //     return redirect()->back()->with('success', 'Member documents updated securely.');
    // }

    public function update(Request $request, $member_id)
    {
        $maxFileSizeKB = 280;

        // Manual validation so we can capture and respond with custom JSON
        $validator = Validator::make($request->all(), [
            'member_image' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
            'member_signature' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
            'member_id_copy_front' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
            'member_id_copy_back' => "nullable|image|mimes:jpg,jpeg,png|max:$maxFileSizeKB",
            'member_payslips_bank_statements' => "nullable|mimes:pdf,jpg,jpeg,png|max:$maxFileSizeKB",
        ]);

        // 👇 Add this block here
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Continue as usual
        $data = [];

        foreach (
            [
                'member_image',
                'member_signature',
                'member_id_copy_front',
                'member_id_copy_back',
                'member_payslips_bank_statements'
            ] as $field
        ) {
            if ($request->hasFile($field)) {
                $filename = $field . '.' . $request->file($field)->getClientOriginalExtension();
                $path = $request->file($field)->storeAs("members/{$member_id}", $filename);
                $data[$field] = $path;
            }
        }

        if (!empty($data)) {
            DB::table('sacco_members')->where('member_id', $member_id)->update($data);
        }

        return response()->json(['success' => true]);
    }

    public function download($member_id, $filename)
    {
        $path = storage_path("app/members/{$member_id}/{$filename}");

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->download($path);
    }

   

public function serveProtectedFile($path)
{
    $fullPath = storage_path('app/' . $path);

    if (!file_exists($fullPath)) {
        abort(404, 'File not found');
    }

    $mime = mime_content_type($fullPath);

    // Check if it's an image
    if (Str::startsWith($mime, 'image/')) {
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"'
        ]);
    }

    // For PDFs and others: force download
    return response()->download($fullPath);
}
}
