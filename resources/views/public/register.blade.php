@extends('layouts.app')

@section('content')
<div class="container">
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>New Member Registration / Application</h1>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Form Card -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title text-primary mb-4">Register as a Member</h4>
            <p class="text-muted mb-4">
                Fill in the form below with accurate information to apply for SACCO membership.
                Ensure all required documents are uploaded to complete the registration process.
            </p>

            <form action="{{ route('register.submit') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- SECTION 1: Personal Details -->
                <h5 class="mb-3 text-primary border-bottom pb-2">1. Personal Details</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="birth_date" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>National ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="national_id" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>KRA PIN No. <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kra_pin_no" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Gender <span class="text-danger">*</span></label>
                        <select class="form-control" name="gender" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Marital Status</label>
                        <select class="form-control" name="marital_status">
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2: Additional Information -->
                <h5 class="mb-3 text-primary border-bottom pb-2">2. Additional Information</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Monthly Income (in Ksh) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="monthly_income" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Preferred Monthly Contribution (in Ksh) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="preferred_monthly_contribution" required>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label>Reason for Joining the SACCO</label>
                    <textarea class="form-control" name="reason_for_joining" rows="3"></textarea>
                </div>

                <!-- SECTION 3: Contact Details -->
                <h5 class="mb-3 text-primary border-bottom pb-2">3. Contact Details</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Physical Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="physical_location" required>
                    </div>
                </div>

                <!-- SECTION 4: Next of Kin -->
<h5 class="mb-3 text-primary border-bottom pb-2">4. Next of Kin (Up to 3)</h5>
@for ($i = 0; $i < 3; $i++)
    <div class="row border rounded p-3 mb-3">
        <div class="col-md-3 form-group mb-3">
            <label>Name</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][name]" value="{{ old('next_of_kin.'.$i.'.name') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label>Relationship</label>
            <select class="form-control" name="next_of_kin[{{ $i }}][relationship]">
                <option value="">Select Relationship</option>
                @foreach ($kinTypes as $kinType)
                    <option value="{{ $kinType->kin_type_name }}"
                        {{ old('next_of_kin.'.$i.'.relationship') == $kinType->kin_type_name ? 'selected' : '' }}>
                        {{ $kinType->kin_type_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3 form-group mb-3">
            <label>Phone</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][phone]" value="{{ old('next_of_kin.'.$i.'.phone') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label>ID or Cert No.</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][id_or_cert_no]" value="{{ old('next_of_kin.'.$i.'.id_or_cert_no') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label>Share (%)</label>
            <input type="number" class="form-control" name="next_of_kin[{{ $i }}][share_percent]"
                value="{{ old('next_of_kin.'.$i.'.share_percent') }}" min="0" max="100">
        </div>
    </div>
@endfor

                <!-- SECTION 5: File Uploads -->
                <h5 class="mb-3 text-primary border-bottom pb-2">5. File Uploads</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Passport Photo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="passport_photo" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Signature <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="signature" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>ID Copy (Front) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="id_copy_front" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>ID Copy (Back) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="id_copy_back" required>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Payslips or Bank Statements <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="payslips_bank_statements" required>
                    </div>
                </div>

                <!-- SECTION 6: Terms and Conditions -->
                <h5 class="mb-3 text-primary border-bottom pb-2">6. Terms and Conditions</h5>
                <div class="form-group mb-3">
                    <input type="checkbox" name="certification_statement" required>
                    <label>I certify that the information provided is accurate.</label>
                </div>
                <div class="form-group mb-3">
                    <input type="checkbox" name="terms" required>
                    <label>I agree to the <a href="#">terms and conditions</a>.</label>
                </div>
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                <!-- Submit Button -->
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary w-100">Submit Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render={{ env('RECAPTCHA_SITE_KEY') }}"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'submit'}).then(function(token) {
            document.getElementById('g-recaptcha-response').value = token;
        });
    });
</script>
@endsection