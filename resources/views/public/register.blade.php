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
        Please fill in the form below to apply for SACCO membership. Ensure all details are accurate. Once submitted, an email will be sent to you with a link to complete your registration. You will be required to upload additional documents, including your passport photo, signature, copies of your ID (front and back), and either payslips or bank statements.
        </p>
        <form action="{{ route('register.submit') }}" method="POST" id="registration-form">
            @csrf
            
            <!-- Personal Details Section -->
            <h5 class="mb-3 text-primary">Personal Details</h5>
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

            <!-- Contact Details Section -->
            <h5 class="mt-4 mb-3 text-primary">Contact Details</h5>
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
                    <label for="physical_location">Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="physical_location" name="physical_location" value="{{ old('physical_location') }}" required>
                </div>
            </div>

            <!-- Next of Kin Section -->
            <h5 class="mt-4 mb-3 text-primary">Next of Kin Details</h5>
            <p class="text-muted">You may add up to 3 next of kin.</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="row border rounded p-3 mb-3">
                    <div class="col-md-3 form-group mb-3">
                        <label for="next_of_kin[{{ $i }}][name]">Name</label>
                        <input type="text" class="form-control" name="next_of_kin[{{ $i }}][name]" value="{{ old('next_of_kin.'.$i.'.name') }}">
                    </div>
                    <div class="col-md-3 form-group mb-3">
                        <label for="next_of_kin[{{ $i }}][relationship]">Relationship</label>
                        <input type="text" class="form-control" name="next_of_kin[{{ $i }}][relationship]" value="{{ old('next_of_kin.'.$i.'.relationship') }}">
                    </div>
                    <div class="col-md-3 form-group mb-3">
                        <label for="next_of_kin[{{ $i }}][phone]">Phone Number</label>
                        <input type="text" class="form-control" name="next_of_kin[{{ $i }}][phone]" value="{{ old('next_of_kin.'.$i.'.phone') }}">
                    </div>
                    <div class="col-md-3 form-group mb-3">
                        <label for="next_of_kin[{{ $i }}][share_percent]">Share (%)</label>
                        <input type="number" class="form-control" name="next_of_kin[{{ $i }}][share_percent]" value="{{ old('next_of_kin.'.$i.'.share_percent') }}" min="0" max="100">
                    </div>
                </div>
            @endfor

            <!-- Bank Details Section -->
            <h5 class="mt-4 mb-3 text-primary">Our Sacco Bank Details</h5>
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label>Bank Name</label>
                    <input type="text" class="form-control" value="{{ $bankDetails['bank_name'] }}" readonly>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Branch Name</label>
                    <input type="text" class="form-control" value="{{ $bankDetails['branch_name'] }}" readonly>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Account Name</label>
                    <input type="text" class="form-control" value="{{ $bankDetails['account_name'] }}" readonly>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Account Number</label>
                    <input type="text" class="form-control" value="{{ $bankDetails['account_number'] }}" readonly>
                </div>
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
            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary w-100">Submit Registration</button>
            </div>
        </form>
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