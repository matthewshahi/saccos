@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Test Bulk SMS</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Test SMS</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Send Test SMS</div>

                <div class="alert alert-warning">
                    <strong>Safety:</strong>
                    This form logs a test SMS into the outbox. With the current settings, it should not send because Bulk SMS is disabled and demo mode is enabled.
                </div>

                <form method="POST" action="{{ route('bulk_sms.test.send') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Recipient Name</label>
                            <input type="text"
                                   name="recipient_name"
                                   class="form-control"
                                   value="{{ old('recipient_name', 'Test Member') }}"
                                   placeholder="Test Member">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Phone Number</label>
                            <input type="text"
                                   name="phone"
                                   class="form-control"
                                   value="{{ old('phone', '0733400737') }}"
                                   placeholder="0733400737"
                                   required>
                            <small class="text-muted">
                                Accepts 07..., 01..., 2547..., or +2547... format.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Subject</label>
                            <input type="text"
                                   name="subject"
                                   class="form-control"
                                   value="{{ old('subject', 'Bulk SMS Test') }}"
                                   placeholder="Bulk SMS Test">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Network</label>
                            <select name="network" class="form-control">
                                <option value="">Auto Detect</option>
                                <option value="safaricom" {{ old('network') === 'safaricom' ? 'selected' : '' }}>Safaricom</option>
                                <option value="airtel" {{ old('network') === 'airtel' ? 'selected' : '' }}>Airtel</option>
                                <option value="telkom" {{ old('network') === 'telkom' ? 'selected' : '' }}>Telkom</option>
                                <option value="equitel" {{ old('network') === 'equitel' ? 'selected' : '' }}>Equitel</option>
                                <option value="unknown" {{ old('network') === 'unknown' ? 'selected' : '' }}>Unknown</option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Sender ID</label>
                            <input type="text"
                                   name="sender_id"
                                   class="form-control"
                                   value="{{ old('sender_id') }}"
                                   placeholder="Leave blank to use default">
                            <small class="text-muted">
                                Leave blank unless ADTEL has approved a sender ID.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Message</label>
                            <textarea name="message"
                                      class="form-control"
                                      rows="5"
                                      maxlength="1000"
                                      required>{{ old('message', 'This is a Bulk SMS outbox test. It should be logged but not sent.') }}</textarea>
                            <small class="text-muted">
                                The system will estimate SMS segments automatically.
                            </small>
                        </div>

                        <div class="col-md-12">
                            <button type="submit"
                                    class="btn btn-primary"
                                    onclick="return confirm('Log this test SMS into the outbox?');">
                                Log Test SMS
                            </button>

                            <a href="{{ route('bulk_sms.messages') }}" class="btn btn-outline-secondary">
                                View Messages
                            </a>

                            <a href="{{ route('bulk_sms.index') }}" class="btn btn-outline-info">
                                Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Current Readiness</h3>
            </div>

            <div class="card-body">
                @if (!empty($readiness['ready_to_send']))
                    <div class="alert alert-success">
                        Bulk SMS is ready to send.
                    </div>
                @else
                    <div class="alert alert-warning">
                        <strong>Not ready to send.</strong>

                        @if (!empty($readiness['issues']))
                            <ul class="mb-0 mt-2">
                                @foreach ($readiness['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr>
                            <th>Enabled</th>
                            <td>
                                @if (!empty($readiness['enabled']))
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-danger">No</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Demo Mode</th>
                            <td>
                                @if (!empty($readiness['demo_mode']))
                                    <span class="badge bg-warning">Yes</span>
                                @else
                                    <span class="badge bg-success">No</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Provider</th>
                            <td>{{ $readiness['provider_code'] ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Default Network</th>
                            <td>{{ $readiness['default_network'] ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Sender ID</th>
                            <td>{{ $readiness['default_sender_id'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Expected Result</h3>
            </div>

            <div class="card-body">
                <p>
                    With the current safe settings, this test should create a new row in:
                </p>

                <code>sacco_bulk_sms_messages</code>

                <p class="mt-3 mb-1">Expected status:</p>

                <ul class="mb-0">
                    <li><strong>sms_mode:</strong> demo</li>
                    <li><strong>sms_status:</strong> skipped</li>
                    <li><strong>error_code:</strong> BULK_SMS_DISABLED</li>
                    <li><strong>sent:</strong> false</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection