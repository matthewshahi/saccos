@extends('layouts.app')

@section('content')
<div class="container">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Complete Your Registration</div>
                <p>Hello, <strong>{{ $member->first_name }}</strong>.</p>

                @if ($isUpdated)
                    <!-- Message if files are already updated -->
                    <p class="text-success">Your documents and bank details have already been uploaded and saved in our system. No further action is required.</p>
                @else
                    <!-- Form if files are not updated -->
                    <p>We are excited to have you join our SACCO! To complete your registration, kindly provide the additional documents and bank details below:</p>
                    
                    <form action="{{ route('register.complete.submit') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="member_id" value="{{ $member->id }}">
                        <div class="row">
                            <!-- File Uploads Section -->
                            <div class="col-md-12">
                                <h5 class="text-primary">File Uploads</h5>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="passport_photo">Passport Photo <span class="text-danger">*</span></label>
                                <input class="form-control" id="passport_photo" type="file" name="passport_photo" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="signature">Signature <span class="text-danger">*</span></label>
                                <input class="form-control" id="signature" type="file" name="signature" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_front">ID Copy (Front) <span class="text-danger">*</span></label>
                                <input class="form-control" id="id_copy_front" type="file" name="id_copy_front" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_back">ID Copy (Back) <span class="text-danger">*</span></label>
                                <input class="form-control" id="id_copy_back" type="file" name="id_copy_back" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="payslips_bank_statements">Payslips or Bank Statements <span class="text-danger">*</span></label>
                                <input class="form-control" id="payslips_bank_statements" type="file" name="payslips_bank_statements" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>

                            <!-- Bank Details Section -->
                            <div class="col-md-12">
                                <h5 class="text-primary">Your Bank Details</h5>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_name">Bank Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="bank_name" type="text" name="bank_name" placeholder="Enter your bank name" value="{{ old('bank_name') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_branch">Bank Branch <span class="text-danger">*</span></label>
                                <input class="form-control" id="bank_branch" type="text" name="bank_branch" placeholder="Enter your bank branch" value="{{ old('bank_branch') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="bank_account_number">Bank Account Number <span class="text-danger">*</span></label>
                                <input class="form-control" id="bank_account_number" type="text" name="bank_account_number" placeholder="Enter your bank account number" value="{{ old('bank_account_number') }}" required>
                            </div>

                            <!-- Submit Button -->
                            <div class="col-md-12">
                                <button class="btn btn-primary w-100" type="submit">Submit Documents and Bank Details</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection