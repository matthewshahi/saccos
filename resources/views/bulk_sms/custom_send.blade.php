```blade
{{-- resources/views/bulk_sms/custom_send.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Custom Bulk SMS</h1>

    <ul>
        <li>
            <a href="{{ url('/dashboard') }}">
                Dashboard
            </a>
        </li>

        <li>
            <a href="{{ route('bulk_sms.index') }}">
                Bulk SMS
            </a>
        </li>

        <li>Custom Send</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@include('bulk_sms.partials.nav')

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>
            The Custom SMS batch was not queued.
        </strong>

        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">
                    Send One Message to Custom Phone Numbers
                </div>

                <p class="text-muted mb-4">
                    Enter multiple phone numbers separated by commas.
                    The same SMS message will be queued independently for every
                    valid phone number.
                </p>

                <form
                    method="POST"
                    action="{{ route('bulk_sms.custom_send.queue') }}"
                    id="customBulkSmsForm"
                >
                    @csrf

                    <div class="row">
                        <div class="col-md-12 form-group mb-3">
                            <label for="phone_numbers">
                                Phone Numbers
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                name="phone_numbers"
                                id="phone_numbers"
                                class="form-control @error('phone_numbers') is-invalid @enderror"
                                rows="8"
                                maxlength="50000"
                                placeholder="0722400737, 0712345678, 254 722 400737, +254 733 123456"
                                required
                            >{{ old('phone_numbers') }}</textarea>

                            @error('phone_numbers')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <small class="form-text text-muted">
                                Separate phone numbers using commas only.
                                Spaces inside an individual phone number are allowed.
                            </small>

                            <div class="mt-2">
                                <span class="badge badge-light border">
                                    Entries detected:
                                    <strong id="recipientCount">0</strong>
                                </span>

                                <span
                                    class="badge badge-danger ml-1"
                                    id="emptyEntryWarning"
                                    style="display: none;"
                                >
                                    Empty comma-separated entry detected
                                </span>
                            </div>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="subject">
                                Subject
                            </label>

                            <input
                                type="text"
                                name="subject"
                                id="subject"
                                class="form-control @error('subject') is-invalid @enderror"
                                value="{{ old('subject') }}"
                                maxlength="180"
                                placeholder="For example: Event Attendance Appreciation"
                            >

                            @error('subject')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <small class="form-text text-muted">
                                This is an internal reference for identifying the
                                SMS batch. It is not added to the SMS message.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="message">
                                SMS Message
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                name="message"
                                id="message"
                                class="form-control @error('message') is-invalid @enderror"
                                rows="7"
                                maxlength="1000"
                                placeholder="Thank you for attending our event. We appreciate your participation and support."
                                required
                            >{{ old('message') }}</textarea>

                            @error('message')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    The same message will be queued for every recipient.
                                </small>

                                <small class="text-muted">
                                    Characters:
                                    <strong id="messageCharacterCount">0</strong>/1000
                                </small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <i class="i-Warning-Window text-warning"></i>
                                    </div>

                                    <div>
                                        <strong>All-or-nothing validation</strong>

                                        <div class="mt-1">
                                            Every supplied phone number must be valid and
                                            unique. If one number is blank, duplicated or
                                            cannot be normalized, the complete batch will
                                            be rejected and no SMS records will be saved.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                id="queueCustomSmsButton"
                            >
                                <i class="i-Mail-Send mr-1"></i>
                                Queue for Automatic Dispatch
                            </button>

                            <a
                                href="{{ route('bulk_sms.messages') }}"
                                class="btn btn-outline-secondary"
                            >
                                View Messages
                            </a>

                            <a
                                href="{{ route('bulk_sms.index') }}"
                                class="btn btn-outline-info"
                            >
                                Bulk SMS Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">
                    Automatic Dispatch
                </h3>
            </div>

            <div class="card-body">
                @php
                    $readyToSend = (bool) (
                        $readiness['ready_to_send'] ?? false
                    );

                    $readinessIssues = $readiness['issues'] ?? [];
                @endphp

                @if ($readyToSend)
                    <div class="alert alert-success">
                        <strong>Bulk SMS is ready.</strong>

                        <div class="mt-1">
                            Messages saved from this form will be immediately
                            eligible for the scheduled outbox dispatcher.
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger">
                        <strong>Bulk SMS is not ready.</strong>

                        @if (!empty($readinessIssues))
                            <ul class="mb-0 mt-2 pl-3">
                                @foreach ($readinessIssues as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <p>
                    Each valid phone number creates a separate record in the
                    Bulk SMS outbox.
                </p>

                <ul class="pl-3">
                    <li>Each recipient gets an independent SMS record.</li>
                    <li>All records share one batch reference.</li>
                    <li>Messages are saved with queued status.</li>
                    <li>No manual Dispatch button is required.</li>
                    <li>The existing scheduler sends them automatically.</li>
                </ul>

                <hr>

                <h6>Correct format</h6>

                <div class="bg-light border rounded p-2 mb-3">
                    <code>
                        0722400737, 0712345678, 254 722 400737
                    </code>
                </div>

                <h6>Incorrect format</h6>

                <div class="bg-light border rounded p-2 mb-3">
                    <code>
                        0722400737 0712345678
                    </code>
                </div>

                <p class="mb-0 text-muted">
                    A space cannot separate recipients because spaces may form
                    part of a formatted phone number. Use commas between all
                    recipients.
                </p>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">
                    Example Use
                </h3>
            </div>

            <div class="card-body">
                <p class="mb-2">
                    Use Custom Send for recipients who may not form one of the
                    standard SACCO member groups.
                </p>

                <p class="mb-0 text-muted">
                    Example: sending a thank-you message to everyone who
                    attended a particular meeting, training or SACCO event.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const phoneNumbersInput = document.getElementById(
            'phone_numbers'
        );

        const messageInput = document.getElementById(
            'message'
        );

        const recipientCount = document.getElementById(
            'recipientCount'
        );

        const emptyEntryWarning = document.getElementById(
            'emptyEntryWarning'
        );

        const messageCharacterCount = document.getElementById(
            'messageCharacterCount'
        );

        const form = document.getElementById(
            'customBulkSmsForm'
        );

        const submitButton = document.getElementById(
            'queueCustomSmsButton'
        );

        function updateRecipientCount() {
            const rawValue = phoneNumbersInput.value;

            if (rawValue.trim() === '') {
                recipientCount.textContent = '0';
                emptyEntryWarning.style.display = 'none';

                return;
            }

            /*
             * Comma is intentionally the only separator.
             */
            const entries = rawValue.split(',');

            const nonEmptyEntries = entries.filter(function (entry) {
                return entry.trim() !== '';
            });

            const hasEmptyEntry = entries.some(function (entry) {
                return entry.trim() === '';
            });

            recipientCount.textContent = String(
                nonEmptyEntries.length
            );

            emptyEntryWarning.style.display = hasEmptyEntry
                ? 'inline-block'
                : 'none';
        }

        function updateMessageCharacterCount() {
            messageCharacterCount.textContent = String(
                messageInput.value.length
            );
        }

        phoneNumbersInput.addEventListener(
            'input',
            updateRecipientCount
        );

        messageInput.addEventListener(
            'input',
            updateMessageCharacterCount
        );

        form.addEventListener('submit', function () {
            submitButton.disabled = true;

            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm mr-1" ' +
                'role="status" aria-hidden="true"></span>' +
                'Validating and Queueing...';
        });

        updateRecipientCount();
        updateMessageCharacterCount();
    });
</script>
@endsection