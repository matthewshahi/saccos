@extends('layouts.app')

@section('content')
    @include('member_name')

    <div class="container">

        {{-- Success --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Global errors (covers withErrors + validator errors) --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
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
                    and approved by the SACCO.<br>
                    <strong>Important:</strong> The junior email must be <em>unique</em> (not used by any other member).
                </p>

                <form method="POST" action="{{ route('members.juniors.store') }}">
                    @csrf

                    <div class="row">
                        {{-- Full Name --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="member_name"
                                   class="form-control @error('member_name') is-invalid @enderror"
                                   value="{{ old('member_name') }}"
                                   required>
                            @error('member_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- National ID / Birth Cert --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">National ID / Birth Certificate No <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="member_national_id"
                                   class="form-control @error('member_national_id') is-invalid @enderror"
                                   value="{{ old('member_national_id') }}"
                                   required>
                            @error('member_national_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- DOB --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="member_dob"
                                   class="form-control @error('member_dob') is-invalid @enderror"
                                   value="{{ old('member_dob') }}"
                                   required>
                            @error('member_dob')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Gender --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="member_gender"
                                    class="form-select @error('member_gender') is-invalid @enderror"
                                    required>
                                <option value="">-- Select Gender --</option>
                                <option value="M" {{ old('member_gender') === 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ old('member_gender') === 'F' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('member_gender')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Phone (optional) --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number (optional)</label>
                            <input type="text"
                                   name="member_phone_no"
                                   class="form-control @error('member_phone_no') is-invalid @enderror"
                                   value="{{ old('member_phone_no') }}"
                                   placeholder="e.g. 2547XXXXXXXX">
                            @error('member_phone_no')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Email (REQUIRED + UNIQUE) --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email"
                                   name="member_email"
                                   class="form-control @error('member_email') is-invalid @enderror"
                                   value="{{ old('member_email') }}"
                                   required
                                   placeholder="e.g. juniorname@example.com">
                            <small class="text-muted">Must be unique (not used by any other member).</small>
                            @error('member_email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            Save Junior Member
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Existing juniors --}}
        @if ($juniors->count())
            <div class="card">
                <div class="card-header">Your Registered Juniors</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>DOB</th>
                                    <th>Gender</th>
                                    <th>ID/Birth Cert</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-center">Account</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($juniors as $junior)
                                    @php
                                        // retain current page query params, but never duplicate jaccount
                                        $qs = request()->except('jaccount');
                                        $qs['jaccount'] = $junior->member_id;
                                        $viewUrl = url('/dashboard') . '?' . http_build_query($qs);

                                        $dob = '';
                                        if (!empty($junior->member_dob)) {
                                            try { $dob = \Carbon\Carbon::parse($junior->member_dob)->format('Y-m-d'); }
                                            catch (\Throwable $e) { $dob = (string) $junior->member_dob; }
                                        }
                                    @endphp

                                    <tr>
                                        <td>{{ $junior->member_name }}</td>
                                        <td>{{ $dob }}</td>
                                        <td>
                                            @if($junior->member_gender === 'M') Male
                                            @elseif($junior->member_gender === 'F') Female
                                            @else <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>{{ $junior->member_national_id }}</td>
                                        <td>{{ $junior->member_email }}</td>
                                        <td>
                                            @if ($junior->member_active === 'Y')
                                                <span class="badge bg-success text-white px-3 py-2">Active</span>
                                            @else
                                                <span class="badge bg-warning text-dark px-3 py-2">Pending</span>
                                            @endif
                                        </td>

                                        <td class="text-center">
                                            @if ($junior->member_active === 'Y')
                                                <a href="{{ $viewUrl }}"
                                                   target="_blank" rel="noopener"
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            @else
                                                <span class="text-muted small">N/A</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info">
                You have not registered any junior members yet.
            </div>
        @endif

    </div>
@endsection