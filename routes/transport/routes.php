<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Transport\FleetController;
use App\Http\Controllers\Transport\OperatorController;
use App\Http\Controllers\Transport\CollectionController;
use App\Http\Controllers\Transport\RouteController as MatatuRouteController;
use App\Http\Controllers\Transport\PenaltyController;
use App\Http\Controllers\Transport\TargetController;
use App\Http\Controllers\Transport\MaintenanceController;
use App\Http\Controllers\Transport\OperatorVehicleAssignmentController;
use App\Http\Controllers\Transport\OperatorDashBoardSlefAddController;

Route::get('/routes', [MatatuRouteController::class, 'index'])
    ->name('routes')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/fleet', [FleetController::class, 'index'])
        ->name('fleet')
        ->middleware('check_user_rights:add_new_sacco_member');

    Route::get('/fleet/create', [FleetController::class, 'create'])
        ->name('fleet.create')
        ->middleware('check_user_rights:add_new_sacco_member');

    Route::post('/fleet/store', [FleetController::class, 'store'])
        ->name('fleet.store')
        ->middleware('check_user_rights:add_new_sacco_member');

    Route::get('/fleet/edit/{id}', [FleetController::class, 'edit'])
        ->name('fleet.edit')
        ->middleware('check_user_rights:add_new_sacco_member');

    Route::post('/fleet/update/{id}', [FleetController::class, 'update'])
        ->name('fleet.update')
        ->middleware('check_user_rights:add_new_sacco_member');

    Route::get('/fleet/delete/{id}', [FleetController::class, 'destroy'])
        ->name('fleet.delete')
        ->middleware('check_user_rights:delete_fleet');

    // Other transport modules
   // Operator Routes
Route::get('/operators', [OperatorController::class, 'index'])
    ->name('operators')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/operators/create', [OperatorController::class, 'create'])
    ->name('operators.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/operators/store', [OperatorController::class, 'store'])
    ->name('operators.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/operators/edit/{id}', [OperatorController::class, 'edit'])
    ->name('operators.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/operators/update/{id}', [OperatorController::class, 'update'])
    ->name('operators.update')
    ->middleware('check_user_rights:add_new_sacco_member');

    Route::get('/operators/status/{id}', [OperatorController::class, 'toggleStatus'])
    ->name('operators.toggleStatus')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/operators/delete/{id}', [OperatorController::class, 'destroy'])
    ->name('operators.delete')
    ->middleware('check_user_rights:add_new_sacco_membe');

 

// List all collections
Route::get('/collections', [CollectionController::class, 'index'])
    ->name('collections')
    ->middleware('check_user_rights:add_new_sacco_member');

// Show the form to create a new collection
Route::get('/collections/create', [CollectionController::class, 'create'])
    ->name('collections.create')
    ->middleware('check_user_rights:add_new_sacco_member');

// Store a new collection record
Route::post('/collections/store', [CollectionController::class, 'store'])
    ->name('collections.store')
    ->middleware('check_user_rights:add_new_sacco_member');

// Show form to edit an existing collection (optional)
Route::get('/collections/edit/{id}', [CollectionController::class, 'edit'])
    ->name('collections.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

// Update an existing collection (optional)
Route::post('/collections/update/{id}', [CollectionController::class, 'update'])
    ->name('collections.update')
    ->middleware('check_user_rights:add_new_sacco_member');

// Delete a collection (optional)
Route::get('/collections/delete/{id}', [CollectionController::class, 'destroy'])
    ->name('collections.delete')
    ->middleware('check_user_rights:delete_fleet');

// Load reconciliation modal content for a collection
Route::get('/collections/{id}/reconcile', [CollectionController::class, 'reconcile'])
    ->name('collections.reconcile')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/collections/{id}/attach-loan', [CollectionController::class, 'attachLoan'])
    ->name('collections.attachLoan')
    ->middleware('check_user_rights:add_new_sacco_member');
    
   Route::get('/routes/create', [MatatuRouteController::class, 'create'])
    ->name('routes.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/routes/store', [MatatuRouteController::class, 'store'])
    ->name('routes.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/routes/edit/{id}', [MatatuRouteController::class, 'edit'])
    ->name('routes.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/routes/update/{id}', [MatatuRouteController::class, 'update'])
    ->name('routes.update')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/routes/delete/{id}', [MatatuRouteController::class, 'destroy'])
    ->name('routes.delete')
    ->middleware('check_user_rights:add_new_sacco_member');


    Route::get('/penalties', [PenaltyController::class, 'index'])
        ->name('penalties')
        ->middleware('check_user_rights:add_new_sacco_member');

        // Penalties Management
Route::get('/penalties', [PenaltyController::class, 'index'])
    ->name('penalties')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/penalties/create', [PenaltyController::class, 'create'])
    ->name('penalties.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/penalties/store', [PenaltyController::class, 'store'])
    ->name('penalties.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/penalties/edit/{id}', [PenaltyController::class, 'edit'])
    ->name('penalties.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/penalties/update/{id}', [PenaltyController::class, 'update'])
    ->name('penalties.update')
    ->middleware('check_user_rights:add_new_sacco_member');


Route::get('/penalties/delete/{id}', [PenaltyController::class, 'destroy'])
    ->name('penalties.delete')
    ->middleware('check_user_rights:delete_fleet');

    Route::get('/targets', [TargetController::class, 'index'])
        ->name('targets')
        ->middleware('check_user_rights:add_new_sacco_member');

   // Maintenance Management
Route::get('/maintenance', [MaintenanceController::class, 'index'])
    ->name('maintenance')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/maintenance/create', [MaintenanceController::class, 'create'])
    ->name('maintenance.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/maintenance/store', [MaintenanceController::class, 'store'])
    ->name('maintenance.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/maintenance/edit/{id}', [MaintenanceController::class, 'edit'])
    ->name('maintenance.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/maintenance/update/{id}', [MaintenanceController::class, 'update'])
    ->name('maintenance.update')
    ->middleware('check_user_rights:add_new_sacco_member');

 
// List all assignments
Route::get('/assignments', [OperatorVehicleAssignmentController::class, 'index'])
    ->name('assignments.index')
    ->middleware('check_user_rights:add_new_sacco_member');

// Show create form
Route::get('/assignments/create', [OperatorVehicleAssignmentController::class, 'create'])
    ->name('assignments.create')
    ->middleware('check_user_rights:add_new_sacco_member');

// Store new assignment
Route::post('/assignments/store', [OperatorVehicleAssignmentController::class, 'store'])
    ->name('assignments.store')
    ->middleware('check_user_rights:add_new_sacco_member');

// Show edit form
Route::get('/assignments/edit/{id}', [OperatorVehicleAssignmentController::class, 'edit'])
    ->name('assignments.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

// Update existing assignment
Route::post('/assignments/update/{id}', [OperatorVehicleAssignmentController::class, 'update'])
    ->name('assignments.update')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/operators/add-from-dashboard', [OperatorDashBoardSlefAddController::class, 'addFromDashboard'])
    ->name('operators.addFromDashboard');


    // ============================
// STAGES MANAGEMENT
// ============================
Route::get('/stages', [MatatuRouteController::class, 'stagesIndex'])
    ->name('stages.index')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stages/create', [MatatuRouteController::class, 'stagesCreate'])
    ->name('stages.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/stages/store', [MatatuRouteController::class, 'stagesStore'])
    ->name('stages.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stages/edit/{id}', [MatatuRouteController::class, 'stagesEdit'])
    ->name('stages.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/stages/update/{id}', [MatatuRouteController::class, 'stagesUpdate'])
    ->name('stages.update')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stages/delete/{id}', [MatatuRouteController::class, 'stagesDelete'])
    ->name('stages.delete')
    ->middleware('check_user_rights:add_new_sacco_member');


// ============================
// STAGE CHAIRS MANAGEMENT
// ============================
Route::get('/stage-chairs', [MatatuRouteController::class, 'chairsIndex'])
    ->name('chairs.index')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stage-chairs/create', [MatatuRouteController::class, 'chairsCreate'])
    ->name('chairs.create')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/stage-chairs/store', [MatatuRouteController::class, 'chairsStore'])
    ->name('chairs.store')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stage-chairs/edit/{id}', [MatatuRouteController::class, 'chairsEdit'])
    ->name('chairs.edit')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::post('/stage-chairs/update/{id}', [MatatuRouteController::class, 'chairsUpdate'])
    ->name('chairs.update')
    ->middleware('check_user_rights:add_new_sacco_member');

Route::get('/stage-chairs/delete/{id}', [MatatuRouteController::class, 'chairsDelete'])
    ->name('chairs.delete')
    ->middleware('check_user_rights:add_new_sacco_member');

