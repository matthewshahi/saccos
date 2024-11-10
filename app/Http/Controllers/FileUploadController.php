<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileUploadController extends Controller
{
    /**
     * Display the file upload form.
     *
     * @return \Illuminate\View\View
     */
    public function showUploadForm()
    {
        return view('file.upload');
    }

    /**
     * Handle file upload and store it in a private directory within storage/app/admin_uploads.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
{
    // Validate the input
    $request->validate([
        'files.*' => 'required|file|mimes:jpeg,jpg,png,pdf|max:2048', // Each file should be max 2MB and in specified formats
        'file_description' => 'required|string|max:255',
        'accessibility' => 'required|in:public,admin',
        'start_date' => 'required|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
    ]);

    $uploadedFiles = $request->file('files');
    $filePaths = [];

    try {
        foreach ($uploadedFiles as $file) {
            // Store each file in 'admin_uploads' directory within storage/app
            $filePath = $file->store('admin_uploads');
            $filePaths[] = $filePath;

            // Insert file information into the database
            DB::table('sacco_files')->insert([
                'file_description' => $request->input('file_description'),
                'file_accessibility' => $request->input('accessibility'),
                'file_start_date' => $request->input('start_date'),
                'file_end_date' => $request->input('end_date') ?? '9999-12-31',
                'file_path' => $filePath,
                'file_uploaded_by' => auth()->id(),
                'file_uploaded_at' => now(),
            ]);
        }

        // Redirect to the file listing page with a success message
        return redirect()->route('files.list')->with('success', 'Files uploaded successfully.');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'An error occurred during file upload: ' . $e->getMessage());
    }
}
    public function listFiles()
        {
            $files = DB::table('sacco_files')
                        ->where('file_deleted', 'N')
                        ->orderBy('file_uploaded_at', 'desc')
                        ->paginate(10);

            return view('file.list', compact('files'));
        }
    public function download($id)
        {
            // Find the file in the database
            $file = DB::table('sacco_files')->where('id', $id)->first();
        
            if (!$file) {
                return redirect()->route('files.list')->with('error', 'File not found.');
            }
        
            // Define the file path
            $filePath = storage_path("app/{$file->file_path}");
        
            // Check if the file exists
            if (!file_exists($filePath)) {
                return redirect()->route('files.list')->with('error', 'File not found on server.');
            }
        
            // Return the file as a download
            return response()->download($filePath, $file->file_description);
        }
    public function delete($id)
        {
            // Find the file in the database
            $file = DB::table('sacco_files')->where('id', $id)->first();
        
            if (!$file) {
                return redirect()->route('files.list')->with('error', 'File not found.');
            }
        
            try {
                // Mark the file as deleted
                DB::table('sacco_files')->where('id', $id)->update(['file_deleted' => 'Y']);
        
                return redirect()->route('files.list')->with('success', 'File marked as deleted successfully.');
            } catch (\Exception $e) {
                return redirect()->route('files.list')->with('error', 'An error occurred while deleting the file.');
            }
        }
}