<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MpesaController extends Controller
{
    /**
     * Display the M-Pesa configuration form.
     *
     * @return \Illuminate\View\View
     */
    public function showMpesaConfig()
    {
        // You can fetch existing configurations here if needed
        // $config = ...;

        return view('admin.mpesa.config'); // Assumes the Blade template is in resources/views/admin/mpesa/config.blade.php
    }
}
