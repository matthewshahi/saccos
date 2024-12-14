@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Complete Your Registration</h1>
    <p>Hello {{ $member->first_name }},</p>
    <p>We need the following additional documents to complete your registration:</p>
    <ul>
        <li>Passport Photo</li>
        <li>Signature</li>
        <li>ID Copies (Front and Back)</li>
        <li>Payslips or Bank Statements</li>
    </ul>
    <form action="{{ route('register.complete.submit') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="member_id" value="{{ $member->id }}">
        <div class="form-group">
            <label for="passport_photo">Passport Photo</label>
            <input type="file" class="form-control" id="passport_photo" name="passport_photo" required>
        </div>
        <div class="form-group">
            <label for="signature">Signature</label>
            <input type="file" class="form-control" id="signature" name="signature" required>
        </div>
        <div class="form-group">
            <label for="id_copy_front">ID Copy (Front)</label>
            <input type="file" class="form-control" id="id_copy_front" name="id_copy_front" required>
        </div>
        <div class="form-group">
            <label for="id_copy_back">ID Copy (Back)</label>
            <input type="file" class="form-control" id="id_copy_back" name="id_copy_back" required>
        </div>
        <div class="form-group">
            <label for="payslips_bank_statements">Payslips or Bank Statements</label>
            <input type="file" class="form-control" id="payslips_bank_statements" name="payslips_bank_statements" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit Documents</button>
    </form>
</div>
@endsection