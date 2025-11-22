@extends('layouts.app')

@section('content')
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

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title text-primary">Register as a Member</h4>
        <p class="text-muted">
        Please fill in the form below to apply for SACCO membership. Ensure all details are accurate. 
        </p>
        <form action="{{ route('register.submit') }}" method="POST" enctype="multipart/form-data" id="registration-form">
    @csrf
        
            
            <!-- Personal Details Section -->
            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">1. Personal Details</h5>
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label for="first_name">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="last_name">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                </div>
                
                <div class="col-md-6 form-group mb-3">
                    <label for="birth_date">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" required>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label for="national_id">National ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="national_id" name="national_id" value="{{ old('national_id') }}" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="kra_pin_no">KRA PIN No. <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="kra_pin_no" name="kra_pin_no" value="{{ old('kra_pin_no') }}" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="marital_status">Marital Status</label>
                    <select class="form-control" id="marital_status" name="marital_status">
                        <option value="">Select</option>
                        <option value="Single" {{ old('marital_status') == 'Single' ? 'selected' : '' }}>Single</option>
                        <option value="Married" {{ old('marital_status') == 'Married' ? 'selected' : '' }}>Married</option>
                        <option value="Divorced" {{ old('marital_status') == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                        <option value="Widowed" {{ old('marital_status') == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                    </select>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="gender">Gender</label>
                    <select class="form-control" id="gender" name="gender">
                        <option value="">Select</option>
                        <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
            </div>

            <!-- Additional Information Section -->
            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">2. Additional Information</h5>
            <div class="row">
                <!-- <div class="col-md-6 form-group mb-3">
                    <label for="monthly_income">Monthly Income (in Ksh) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="monthly_income" name="monthly_income" value="{{ old('monthly_income') }}" required>
                </div> -->
                <div class="col-md-6 form-group mb-3">
                    <label for="occupation">Occupation</label>
                    <input type="text" class="form-control" id="occupation" name="occupation" value="{{ old('occupation') }}">
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="dependents">Number of Dependents</label>
                    <input type="number" class="form-control" id="dependents" name="dependents" value="{{ old('dependents') }}">
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="preferred_monthly_contribution">Preferred Monthly Contribution (in Ksh) <span class="text-danger"></span></label>
                    <input type="number" class="form-control" id="preferred_monthly_contribution" name="preferred_monthly_contribution" value="{{ old('preferred_monthly_contribution') }}">
                </div>
            </div>

            <div class="form-group">
                <label for="reason_for_joining">Reason for Joining the SACCO</label>
                <textarea class="form-control" id="reason_for_joining" name="reason_for_joining" rows="4">{{ old('reason_for_joining') }}</textarea>
            </div>

            <!-- Contact Details Section -->
            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">3. Contact Details</h5>
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="phone">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label for="physical_location">Location <span class="text-danger"></span></label>
                    <input type="text" class="form-control" id="physical_location" name="physical_location" value="{{ old('physical_location') }}">
                </div>
            </div>

            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">4. Next of Kin / Nominees / Emergency Contacts</h5>
<p class="text-muted">
    <strong>Note:</strong> In this SACCO, the terms <em>Next of Kin</em>, <em>Nominee</em>, and <em>Emergency Contact</em> refer to the same person(s).  
    These are the individuals you authorize and trust to be contacted or to receive your SACCO benefits, savings, or shares in the event of your death, illness, or incapacitation.
</p>
<p class="text-muted mb-3">
    You may list up to three (3) people and indicate the percentage share each should receive (totaling 100%).  
    Please provide accurate information and ensure that the individuals named are aware of their designation.
</p>
<p class="text-muted">You may add up to 3 next of kin below.</p>



@for ($i = 0; $i < 3; $i++)
    <div class="row border rounded p-3 mb-3">
        <div class="col-md-3 form-group mb-3">
            <label for="next_of_kin[{{ $i }}][name]">Name</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][name]" value="{{ old('next_of_kin.'.$i.'.name') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label for="next_of_kin[{{ $i }}][relationship]">Relationship</label>
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
            <label for="next_of_kin[{{ $i }}][phone]">Phone</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][phone]" value="{{ old('next_of_kin.'.$i.'.phone') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label for="next_of_kin[{{ $i }}][id_or_cert_no]">ID No / Birth Cert No</label>
            <input type="text" class="form-control" name="next_of_kin[{{ $i }}][id_or_cert_no]" value="{{ old('next_of_kin.'.$i.'.id_or_cert_no') }}">
        </div>

        <div class="col-md-3 form-group mb-3">
            <label for="next_of_kin[{{ $i }}][share_percent]">Share (%)</label>
            <input type="number" class="form-control" name="next_of_kin[{{ $i }}][share_percent]" value="{{ old('next_of_kin.'.$i.'.share_percent') }}" min="0" max="100">
        </div>
    </div>
@endfor

            <div class="col-md-12">
            <div class="row">
                            <!-- File Uploads Section -->
                            <div class="col-md-12">
                            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">5. File Uploads</h5>
                                 
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="passport_photo">Passport Photo <span class="text-danger"></span></label>
                                <input class="form-control" id="passport_photo" type="file" name="passport_photo" accept=".jpeg,.jpg,.pdf" >
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="signature">Signature <span class="text-danger"></span></label>
                                <input class="form-control" id="signature" type="file" name="signature" accept=".jpeg,.jpg,.pdf">
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_front">ID Copy (Front) <span class="text-danger"></span></label>
                                <input class="form-control" id="id_copy_front" type="file" name="id_copy_front" accept=".jpeg,.jpg,.pdf">
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_back">ID Copy (Back) <span class="text-danger"></span></label>
                                <input class="form-control" id="id_copy_back" type="file" name="id_copy_back" accept=".jpeg,.jpg,.pdf">
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="payslips_bank_statements">Payslips or Bank Statements <span class="text-danger"></span></label>
                                <input class="form-control" id="payslips_bank_statements" type="file" name="payslips_bank_statements" accept=".jpeg,.jpg,.pdf">
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <!-- Bank Details Section -->
                            <div class="col-md-12">
                            <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">6. Your Bank Details</h5>
                               
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_name">Bank Name <span class="text-danger"></span></label>
                                <input class="form-control" id="bank_name" type="text" name="bank_name" placeholder="Enter your bank name" value="{{ old('bank_name') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_branch">Bank Branch <span class="text-danger"></span></label>
                                <input class="form-control" id="bank_branch" type="text" name="bank_branch" placeholder="Enter your bank branch" value="{{ old('bank_branch') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_account_number">Bank Account Number <span class="text-danger"></span></label>
                                <input class="form-control" id="bank_account_number" type="text" name="bank_account_number" placeholder="Enter your bank account number" value="{{ old('bank_account_number') }}">
                            </div>

                     
                        </div>

                      <!-- SECTION 7: Complete Registration Instructions -->
<h5 class="text-primary border-bottom pb-2 mt-4 mb-3">7. Complete Your Registration</h5>
<div class="alert alert-info">
    <p>To finalize your SACCO membership registration, kindly send the registration fee using the following details:</p>
    <ul>
        <li><strong>Paybill Number:</strong> {{ $paybillNumber }}</li>
        <li><strong>Account Number:</strong> <span class="text-primary">REG[YOUR NATIONAL ID]</span></li>
        <li><strong>Amount:</strong> Ksh {{ $membershipFee }}</li>
    </ul>
    <p>
        <em>
            Replace <strong>[YOUR NATIONAL ID]</strong> with your actual National ID number.
            Example: If your ID is <strong>12345678</strong>, use <strong>REG12345678</strong> as the account number.
        </em>
    </p>
</div>


            <!-- Terms Section -->
            <div class="col-md-12 form-group mt-4">
                <input type="checkbox" id="certification_statement" name="certification_statement" required>
                <label for="certification_statement">I certify that the information provided is accurate to the best of my knowledge. <span class="text-danger">*</span></label>
            </div>

            <div class="col-md-12 form-group mt-3">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#">terms and conditions</a>. <span class="text-danger">*</span></label>
            </div>
          

            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary w-100">Submit Registration</button>
            </div>
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
        </form>
    </div>
</div>



<script src="https://www.google.com/recaptcha/api.js?render={{ env('RECAPTCHA_SITE_KEY') }}"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'register'}).then(function(token) {
            document.getElementById('recaptcha_token').value = token;
        });
    });

    grecaptcha.ready(function () {
    function refreshToken() {
        grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'register'})
            .then(function (token) {
                document.getElementById('recaptcha_token').value = token;
            });
    }

    refreshToken();
    setInterval(refreshToken, 30000); // every 30 seconds
});

document.addEventListener("DOMContentLoaded", function () {
    const submitBtn = document.querySelector("#registration-form button[type='submit']");
    submitBtn.disabled = true;

    function enableSubmit(token) {
        if (token) {
            submitBtn.disabled = false;
        }
    }

    grecaptcha.ready(function () {
        grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'register'}).then(function (token) {
            document.getElementById('recaptcha_token').value = token;
            enableSubmit(token);
        });
    });
});

</script>



@endsection