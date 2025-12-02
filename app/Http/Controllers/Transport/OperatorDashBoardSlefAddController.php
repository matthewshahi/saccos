<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorDashBoardSlefAddController extends Controller
{
    public function addFromDashboard(Request $request)
    {
        // ----------------------------------------------
        // 1. VALIDATION (Clear and SACCO-friendly)
        // ----------------------------------------------
        $validated = $request->validate([
            'full_name'   => 'required|string|max:255',

            'phone' => [
    'required',
    'regex:/^\+?[0-9]{7,15}$/',
],


            'id_number'   => 'required|string|max:20',
            'kra_pin'     => 'required|string|max:20',

            'ntsa_number'     => 'required|string|max:50',
            'driving_licence' => 'required|string|max:50',

            'jacket_number'   => 'nullable|string|max:50',

            'operator_type'   => 'required|string|max:50',

            'vehicle_reg_no'  => 'required|string|max:50',

            'start_stage'     => 'required|string|max:255',

            'stage_chair'      => 'nullable|string|max:255',
            'stage_chair_phone' => [
    'nullable',
    'regex:/^\+?[0-9]{7,15}$/',
],

        ],
        [
            // Custom SACCO-friendly messages
            'phone.regex' => 'Please enter a valid Kenyan phone number.',
            'stage_chair_phone.regex' => 'Stage chair phone must be a valid Kenyan number.',
            'vehicle_reg_no.required' => 'Vehicle registration number is required.',
            'start_stage.required' => 'Stage name is required.',
        ]);

        // Open modal again if validation fails
        session()->flash('openModal', 'addOperatorModal');

        // Logged-in member
        $memberId = auth()->user()->member_id;

        // ----------------------------------------------
        // 2. NORMALISATION
        // ----------------------------------------------
        $vehicleReg = strtoupper(str_replace(' ', '', $request->vehicle_reg_no));
        $kra = strtoupper(trim($request->kra_pin));
        $ntsa = strtoupper(trim($request->ntsa_number));
        $licence = strtoupper(trim($request->driving_licence));
        $jacket = strtoupper(trim($request->jacket_number));
        $stageName = trim($request->start_stage);

        // Phone normalisation
        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (str_starts_with($phone, '7')) {
            $phone = '0' . $phone;
        }

        $chairPhone = null;
        if ($request->stage_chair_phone) {
            $chairPhone = preg_replace('/[^0-9]/', '', $request->stage_chair_phone);
            if (str_starts_with($chairPhone, '7')) {
                $chairPhone = '0' . $chairPhone;
            }
        }

        // ----------------------------------------------
        // 3. DUPLICATE OPERATOR CHECK
        // ----------------------------------------------
        $duplicate = DB::table('sacco_matatus_operators')
            ->where('id_number', $validated['id_number'])
            ->orWhere('kra_pin', $kra)
            ->orWhere('ntsa_number', $ntsa)
            ->first();

        if ($duplicate) {
            return back()
                ->withErrors(['error' => 'This operator already exists in the system.'])
                ->withInput()
                ->with('openModal', 'addOperatorModal');
        }

        DB::beginTransaction();

        try {
            // ----------------------------------------------
            // 4. VEHICLE PROCESSING
            // ----------------------------------------------
            $vehicleId = null;

            $vehicle = DB::table('sacco_matatus_vehicles')
                ->where('vehicles_registration_number', $vehicleReg)
                ->first();

            if ($vehicle) {
                $vehicleId = $vehicle->id;
            } else {
                // Auto-create minimal fleet record
                $vehicleId = DB::table('sacco_matatus_vehicles')->insertGetId([
                    'vehicles_registration_number' => $vehicleReg,
                    'vehicles_member_id' => $memberId,
                    'vehicles_status' => 'pending_approval',
                    'created_at' => now(),
                ]);
            }

            // ----------------------------------------------
            // 5. STAGE CREATION / FETCH
            // ----------------------------------------------
            $stage = DB::table('sacco_matatus_stages')
                ->where('stage_name', $stageName)
                ->first();

            if ($stage) {
                $stageId = $stage->id;
            } else {
                $stageId = DB::table('sacco_matatus_stages')->insertGetId([
                    'stage_name'   => $stageName,
                    'created_at'   => now(),
                ]);
            }

            // ----------------------------------------------
            // 6. STAGE CHAIR HANDLING
            // ----------------------------------------------
            $chairId = null;

            if (!empty($request->stage_chair)) {
                $existingChair = DB::table('sacco_matatus_stage_chairs')
                    ->where('chair_name', $request->stage_chair)
                    ->where('chair_stage_id', $stageId)
                    ->first();

                if ($existingChair) {
                    $chairId = $existingChair->id;
                } else {
                    $chairId = DB::table('sacco_matatus_stage_chairs')->insertGetId([
                        'chair_name'     => $request->stage_chair,
                        'chair_phone'    => $chairPhone,
                        'chair_stage_id' => $stageId,
                        'created_at'     => now(),
                    ]);
                }
            }

            // ----------------------------------------------
            // 7. OPERATOR CREATION
            // ----------------------------------------------
            $operatorId = DB::table('sacco_matatus_operators')->insertGetId([
                'full_name'     => $validated['full_name'],
                'phone'         => $phone,
                'operator_type' => $validated['operator_type'],

                'id_number'     => $validated['id_number'],
                'kra_pin'       => $kra,
                'ntsa_number'   => $ntsa,
                'driving_licence' => $licence,
                'jacket_number'   => $jacket,

                'introduced_by_member_id' => $memberId,

                // new fields
                'operator_stage_id'    => $stageId,
                'operator_chair_id'    => $chairId,

                'created_at' => now(),
            ]);

            // ----------------------------------------------
            // 8. VEHICLE ASSIGNMENT (Avoid double assign)
            // ----------------------------------------------
            $assigned = DB::table('sacco_matatus_operator_vehicle_assignments')
                ->where('v_assignment_operator_id', $operatorId)
                ->where('v_assignment_status', 'assigned')
                ->first();

            if (!$assigned) {
                DB::table('sacco_matatus_operator_vehicle_assignments')->insert([
                    'v_assignment_operator_id' => $operatorId,
                    'v_assignment_vehicle_id'  => $vehicleId,
                    'v_assignment_start_date'  => now()->toDateString(),
                    'v_assignment_status'      => 'assigned',
                    'created_at'               => now(),
                ]);
            }

            DB::commit();

            return back()->with('success', 'Operator added successfully!');

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error("SELF ADD OPERATOR ERROR: " . $e->getMessage());

            return back()
                ->withErrors(['error' => 'Failed to add operator. Please try again.'])
                ->withInput()
                ->with('openModal', 'addOperatorModal');
        }
    }
}
