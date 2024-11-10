<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicFileController extends Controller
{
    /**
     * Show the public files listing with pagination and search.
     */
    public function publicDownloads(Request $request)
    {
        $query = DB::table('sacco_files')
            ->where('file_accessibility', 'public')
            ->where('file_deleted', 'N')
            ->orderBy('file_uploaded_at', 'desc');

        // Apply search if there's a search term
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('file_description', 'like', '%' . $search . '%');
        }

        // Paginate results
        $files = $query->paginate(10);

        return view('public.downloads', compact('files'));
    }

    /**
     * Download a specific public file.
     */
    public function downloadFile($fileId)
    {
        $file = DB::table('sacco_files')
            ->where('id', $fileId)
            ->where('file_accessibility', 'public')
            ->where('file_deleted', 'N')
            ->first();

        if ($file && Storage::exists($file->file_path)) {
            return Storage::download($file->file_path, $file->file_description);
        }

        return redirect()->route('public.downloads')->with('error', 'File not found or no longer available.');
    }
}