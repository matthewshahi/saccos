@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Send Bulk SMS</h1>

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

        <li>Send Bulk SMS</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@include('bulk_sms.partials.nav')

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
                <div class="card-title mb-3">
                    New Bulk SMS Message
                </div>

                <form
                    method="POST"
                    action="{{ route('bulk_sms.compose.queue') }}"
                    id="bulkSmsComposeForm"
                >
                    @csrf

                    <div class="row">
                        <div class="col-md-12 form-group mb-4">
                            <label class="d-block mb-2">
                                <strong>Select Recipients</strong>
                            </label>

                            <div class="card mb-3">
                                <div class="card-body py-3">
                                    <div class="form-check">
                                        <input
                                            type="radio"
                                            name="audience"
                                            id="audience_members"
                                            value="members"
                                            class="form-check-input"
                                            {{ old('audience', 'members') === 'members' ? 'checked' : '' }}
                                            required
                                        >

                                        <label
                                            class="form-check-label"
                                            for="audience_members"
                                        >
                                            <strong>All Active Members</strong>

                                            <span class="badge bg-primary ms-2">
                                                {{ number_format($activeMembersCount) }}
                                            </span>

                                            <div class="text-muted small mt-1">
                                                Includes all active members,
                                                including officials.
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body py-3">
                                    <div class="form-check">
                                        <input
                                            type="radio"
                                            name="audience"
                                            id="audience_officials"
                                            value="officials"
                                            class="form-check-input"
                                            {{ old('audience') === 'officials' ? 'checked' : '' }}
                                            required
                                        >

                                        <label
                                            class="form-check-label"
                                            for="audience_officials"
                                        >
                                            <strong>Officials Only</strong>

                                            <span class="badge bg-info ms-2">
                                                {{ number_format($activeOfficialsCount) }}
                                            </span>

                                            <div class="text-muted small mt-1">
                                                Sends only to active officials
                                                whose member position is 2.
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="message">
                                <strong>Message</strong>
                            </label>

                            <textarea
                                name="message"
                                id="message"
                                class="form-control"
                                rows="7"
                                maxlength="1000"
                                placeholder="Enter the message to send to the selected recipients"
                                required
                            >{{ old('message') }}</textarea>

                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    The system will automatically add:
                                    <strong>Hello Firstname,</strong>
                                    before this message.
                                </small>

                                <small class="text-muted">
                                    <span id="messageCharacterCount">0</span>
                                    / 1000 characters
                                </small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-info">
                                Clicking the button below will only load the
                                messages into the Bulk SMS outbox. The messages
                                will not be sent immediately.
                            </div>
                        </div>

                        <div class="col-md-12">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                id="queueMessagesButton"
                            >
                                Load Messages to Outbox
                            </button>

                            <a
                                href="{{ route('bulk_sms.messages') }}"
                                class="btn btn-outline-secondary"
                            >
                                View Outbox
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
                <h3 class="card-title m-0">
                    Recipient Summary
                </h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <th>Valid Active Members</th>
                            <td>
                                {{ number_format($activeMembersCount) }}
                            </td>
                        </tr>

                        <tr>
                            <th>Valid Officials</th>
                            <td>
                                {{ number_format($activeOfficialsCount) }}
                            </td>
                        </tr>

                        <tr>
                            <th>Invalid Member Numbers</th>
                            <td>
                                {{ number_format($invalidMembersPhoneCount) }}
                            </td>
                        </tr>

                        <tr>
                            <th>Duplicate Member Numbers</th>
                            <td>
                                {{ number_format($duplicateMembersPhoneCount) }}
                            </td>
                        </tr>

                        <tr>
                            <th>Invalid Official Numbers</th>
                            <td>
                                {{ number_format($invalidOfficialsPhoneCount) }}
                            </td>
                        </tr>

                        <tr>
                            <th>Duplicate Official Numbers</th>
                            <td>
                                {{ number_format($duplicateOfficialsPhoneCount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">
                    Message Preview
                </h3>
            </div>

            <div class="card-body">
                <div
                    id="messagePreview"
                    class="border rounded p-3 bg-light"
                    style="white-space: pre-wrap; min-height: 140px;"
                >Hello Member,

Your message will appear here.</div>

                <p class="text-muted small mt-3 mb-0">
                    The greeting will use each recipient's first name.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const messageField = document.getElementById('message');
    const characterCount = document.getElementById(
        'messageCharacterCount'
    );
    const messagePreview = document.getElementById(
        'messagePreview'
    );
    const form = document.getElementById(
        'bulkSmsComposeForm'
    );
    const submitButton = document.getElementById(
        'queueMessagesButton'
    );

    function updateMessagePreview() {
        const message = messageField.value.trim();

        characterCount.textContent = messageField.value.length;

        messagePreview.textContent = message
            ? 'Hello Member,\n\n' + message
            : 'Hello Member,\n\nYour message will appear here.';
    }

    updateMessagePreview();

    messageField.addEventListener(
        'input',
        updateMessagePreview
    );

    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        submitButton.textContent =
            'Loading Messages to Outbox...';
    });
});
</script>
@endsection
