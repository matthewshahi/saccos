<!-- ADD OPERATOR MODAL -->
<div class="modal fade" id="addOperatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title">Add New Operator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                {{-- ERROR ALERTS --}}
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function(){
                            new bootstrap.Modal(document.getElementById('addOperatorModal')).show();
                        });
                    </script>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function(){
                            new bootstrap.Modal(document.getElementById('addOperatorModal')).show();
                        });
                    </script>
                @endif

                <form action="{{ route('operators.addFromDashboard') }}" method="POST">
                    @csrf

                    <div class="row">

                        <!-- PERSONAL INFORMATION -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-2 mb-2 text-primary border-bottom pb-1">
                                Personal Information
                            </h6>
                        </div>

                        <!-- FULL NAME -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control"
                                   required value="{{ old('full_name') }}">
                        </div>

                        <!-- PHONE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="text" name="phone" class="form-control"
                                   required placeholder="07XXXXXXXX"
                                   value="{{ old('phone') }}">
                        </div>

                        <!-- ID NUMBER -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">National ID Number *</label>
                            <input type="text" name="national_id" class="form-control"
                                   required maxlength="12" value="{{ old('national_id') }}">
                        </div>

                        <!-- KRA PIN -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">KRA PIN *</label>
                            <input type="text" name="kra_pin"
                                   class="form-control text-uppercase"
                                   required maxlength="11" placeholder="A000000000B"
                                   value="{{ old('kra_pin') }}">
                        </div>

                        <!-- DOCUMENTS -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-3 mb-2 text-primary border-bottom pb-1">
                                Operator Licenses & Documents
                            </h6>
                        </div>

                        <!-- NTSA LICENCE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NTSA Licence Number *</label>
                            <input type="text" name="ntsa_number"
                                   class="form-control text-uppercase"
                                   required placeholder="NTSA123456"
                                   value="{{ old('ntsa_number') }}">
                        </div>

                        <!-- DRIVING LICENCE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Driving Licence Number *</label>
                            <input type="text" name="driving_licence"
                                   class="form-control text-uppercase"
                                   required placeholder="DL12345678A"
                                   value="{{ old('driving_licence') }}">
                        </div>

                        <!-- JACKET NUMBER -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jacket Number (Optional)</label>
                            <input type="text" name="jacket_number"
                                   class="form-control text-uppercase"
                                   placeholder="SACCO-001"
                                   value="{{ old('jacket_number') }}">
                        </div>

                        <!-- VEHICLE & STAGE INFO -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-3 mb-2 text-primary border-bottom pb-1">
                                Vehicle & Stage Information
                            </h6>
                        </div>

                        <!-- OPERATOR TYPE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Operator Type *</label>
                            <select name="operator_type" class="form-control" required>
                                <option value="driver" {{ old('operator_type')=='driver'?'selected':'' }}>Driver</option>
                                <option value="conductor" {{ old('operator_type')=='conductor'?'selected':'' }}>Conductor</option>
                                <option value="staff" {{ old('operator_type')=='staff'?'selected':'' }}>Staff</option>
                                <option value="rider" {{ old('operator_type')=='rider'?'selected':'' }}>Boda Rider</option>
                                <option value="cab" {{ old('operator_type')=='cab'?'selected':'' }}>Digital Taxi Driver</option>
                            </select>
                        </div>

                        <!-- VEHICLE REG -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Reg Number *</label>
                            <input type="text" name="vehicle_reg_no"
                                   class="form-control text-uppercase"
                                   required placeholder="KDB 457A"
                                   value="{{ old('vehicle_reg_no') }}">
                        </div>

                        <!-- STAGE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stage Name *</label>
                            <input type="text" name="start_stage" class="form-control"
                                   required placeholder="e.g. Githurai 45 Main Stage"
                                   value="{{ old('start_stage') }}">
                        </div>

                        <!-- STAGE CHAIR -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stage Chair (Optional)</label>
                            <input type="text" name="stage_chair" class="form-control"
                                   placeholder="Chairperson Name" value="{{ old('stage_chair') }}">
                        </div>

                        <!-- STAGE CHAIR PHONE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stage Chair Phone (Optional)</label>
                            <input type="text" name="stage_chair_phone"
                                   class="form-control"
                                   placeholder="07XXXXXXXX"
                                   value="{{ old('stage_chair_phone') }}">
                        </div>

                    </div>

                    <button class="btn btn-primary w-100 mt-3" type="submit">
                        Save Operator
                    </button>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- AUTO-CAPITALIZATION -->
<script>
document.addEventListener('input', function (event) {
    const upperFields = ['vehicle_reg_no','kra_pin','ntsa_number','driving_licence','jacket_number'];
    if (upperFields.includes(event.target.name)) {
        event.target.value = event.target.value.toUpperCase();
    }
});
</script>
