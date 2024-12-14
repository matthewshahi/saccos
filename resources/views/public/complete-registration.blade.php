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
                    <p class="text-success">Your documents have already been uploaded and saved in our system. No further action is required.</p>
                @else
                    <!-- Form if files are not updated -->
                    <p>We are excited to have you join our SACCO! To complete your registration, kindly provide the additional documents listed below:</p>
                    <ul>
                        <li>Passport Photo (JPEG/JPG or PDF only, max size: 300KB)</li>
                        <li>Signature (JPEG/JPG or PDF only, max size: 300KB)</li>
                        <li>ID Copies (Front and Back, JPEG/JPG or PDF only, max size: 300KB each)</li>
                        <li>Payslips or Bank Statements (JPEG/JPG or PDF only, max size: 300KB)</li>
                    </ul>
                    <form action="{{ route('register.complete.submit') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="member_id" value="{{ $member->id }}">
                        <div class="row">
                            <!-- Passport Photo -->
                            <div class="col-md-6 form-group mb-3">
                                <label for="passport_photo">Passport Photo <span class="text-danger">*</span></label>
                                <input class="form-control" id="passport_photo" type="file" name="passport_photo" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>
                            <!-- Signature -->
                            <div class="col-md-6 form-group mb-3">
                                <label for="signature">Signature <span class="text-danger">*</span></label>
                                <input class="form-control" id="signature" type="file" name="signature" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>
                            <!-- ID Copy Front -->
                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_front">ID Copy (Front) <span class="text-danger">*</span></label>
                                <input class="form-control" id="id_copy_front" type="file" name="id_copy_front" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>
                            <!-- ID Copy Back -->
                            <div class="col-md-6 form-group mb-3">
                                <label for="id_copy_back">ID Copy (Back) <span class="text-danger">*</span></label>
                                <input class="form-control" id="id_copy_back" type="file" name="id_copy_back" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>
                            <!-- Payslips or Bank Statements -->
                            <div class="col-md-6 form-group mb-3">
                                <label for="payslips_bank_statements">Payslips or Bank Statements <span class="text-danger">*</span></label>
                                <input class="form-control" id="payslips_bank_statements" type="file" name="payslips_bank_statements" accept=".jpeg,.jpg,.pdf" required>
                                <small class="text-muted">JPEG/JPG or PDF only, max size: 300KB</small>
                            </div>
                            <!-- Submit Button -->
                            <div class="col-md-12">
                                <button class="btn btn-primary w-100" type="submit">Submit Documents</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection