<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MpesaConfigController extends Controller
{
    // List all M-Pesa configurations
    public function index()
    {
        $configs = DB::table('mpesa_configs')->get();
        return view('mpesa.configs.index', compact('configs'));
    }

    // Show form to create a new configuration
    public function create()
    {
        return view('mpesa.configs.create');
    }

    // Store a new configuration
    public function store(Request $request)
    {
        // Validate the input data
        $request->validate([
            'shortcode' => 'required|string|max:255',
            'api_type' => 'required|string',
            'response_type' => 'required|string',
            'confirmation_url' => 'required|url',
            'validation_url' => 'required|url',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'passkey' => 'required|string',
        ]);

        // Insert the data into the database
        DB::table('mpesa_configs')->insert([
            'shortcode' => $request->shortcode,
            'api_type' => $request->api_type,
            'response_type' => $request->response_type,
            'confirmation_url' => $request->confirmation_url,
            'validation_url' => $request->validation_url,
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'passkey' => $request->passkey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back to the list with a success message
        return redirect()->route('mpesa_config.index')->with('success', 'Configuration added successfully.');
    }

    // Show form to edit an existing configuration
    public function edit($id)
    {
        $config = DB::table('mpesa_configs')->find($id);

        if (!$config) {
            return redirect()->route('mpesa_config.index')->with('error', 'Configuration not found.');
        }

        return view('mpesa.configs.edit', compact('config'));
    }

    // Update an existing configuration
    public function update(Request $request, $id)
    {
        // Validate the input data
        $request->validate([
            'shortcode' => 'required|string|max:255',
            'api_type' => 'required|string',
            'response_type' => 'required|string',
            'confirmation_url' => 'required|url',
            'validation_url' => 'required|url',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'passkey' => 'required|string',
        ]);

        // Update the record in the database
        DB::table('mpesa_configs')->where('id', $id)->update([
            'shortcode' => $request->shortcode,
            'api_type' => $request->api_type,
            'response_type' => $request->response_type,
            'confirmation_url' => $request->confirmation_url,
            'validation_url' => $request->validation_url,
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'passkey' => $request->passkey,
            'updated_at' => now(),
        ]);

        // Redirect back to the list with a success message
        return redirect()->route('mpesa_config.index')->with('success', 'Configuration updated successfully.');
    }
}