<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    /**
     * Display the STK Push form.
     */
    public function showStkPushForm()
    {
        $configs = DB::table('mpesa_configs')->get();
        return view('mpesa.stkpush', compact('configs'));
    }

}