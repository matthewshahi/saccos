@extends('layouts.app')

@section('content')
    @include('member_name')
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Add a Junior Member
            </div>
            <div class="card-body">
                <p class="mb-4">
                    <strong>Dear {{ $guardian->member_name }},</strong><br>
                    This form allows you to register a junior member under your guardianship.<br>
                    Some fields like address and bank will be inherited automatically.<br>
                    <strong>Note:</strong> The junior member will remain <em>inactive</em> until their account is reviewed
                    and approved by the SACCO.
                </p>

                <form method="POST" action="{{ route('members.juniors.store') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="member_name"
                                    class="form-control @error('member_name') is-invalid @enderror"
                                    value="{{ old('member_name') }}" required>
                                @error('member_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>National ID / Birth Certificate No <span class="text-danger">*</span></label>
                                <input type="text" name="member_national_id"
                                    class="form-control @error('member_national_id') is-invalid @enderror"
                                    value="{{ old('member_national_id') }}" required>
                                @error('member_national_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="member_dob"
                                    class="form-control @error('member_dob') is-invalid @enderror"
                                    value="{{ old('member_dob') }}" required>
                                @error('member_dob')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender <span class="text-danger">*</span></label>
                                <select name="member_gender"
                                    class="form-control @error('member_gender') is-invalid @enderror" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="M" {{ old('member_gender') == 'M' ? 'selected' : '' }}>Male</option>
                                    <option value="F" {{ old('member_gender') == 'F' ? 'selected' : '' }}>Female
                                    </option>
                                </select>
                                @error('member_gender')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number (optional)</label>
                                <input type="text" name="member_phone_no"
                                    class="form-control @error('member_phone_no') is-invalid @enderror"
                                    value="{{ old('member_phone_no') }}">
                                @error('member_phone_no')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email (optional)</label>
                                <input type="email" name="member_email"
                                    class="form-control @error('member_email') is-invalid @enderror"
                                    value="{{ old('member_email') }}">
                                @error('member_email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="member_sacco_id" value="{{ $guardian->member_sacco_id }}">

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Junior Member</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($juniors->count())
    <div class="card">
        <div class="card-header">Your Registered Juniors</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>DOB</th>
                        <th>Gender</th>
                        <th>ID/Birth Cert</th>
                        <th>Status</th>
                        <th class="text-center">Account</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($juniors as $junior)
                        @php
                            // ✅ retain current page query params, but never duplicate jaccount
                            $qs = request()->except('jaccount');
                            $qs['jaccount'] = $junior->member_id;

                            $viewUrl = url('/dashboard') . '?' . http_build_query($qs);
                        @endphp

                        <tr>
                            <td>{{ $junior->member_name }}</td>
                            <td>{{ $junior->member_dob }}</td>
                            <td>{{ $junior->member_gender == 'M' ? 'Male' : 'Female' }}</td>
                            <td>{{ $junior->member_national_id }}</td>
                            <td>
                                @if ($junior->member_active === 'Y')
                                    <span class="badge bg-success text-white px-3 py-2">Active</span>
                                @else
                                    <span class="badge bg-warning text-dark px-3 py-2">Pending</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {{-- ✅ keep current page URL (open dashboard in new tab) --}}
                                <a href="{{ $viewUrl }}"
                                   target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="alert alert-info">
        You have not registered any junior members yet.
    </div>
@endif
    </div>
@endsection
