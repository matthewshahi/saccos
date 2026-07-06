@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>SMS Details</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li><a href="{{ route('bulk_sms.messages') }}">Messages</a></li>
        <li>#{{ $message->sms_id }}</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">
                    SMS #{{ $message->sms_id }}
                </h3>

                <div class="text-end w-50 float-end">
                    <a href="{{ route('bulk_sms.messages') }}" class="btn btn-sm btn-outline-secondary">
                        Back to Messages
                    </a>
                </div>
            </div>

            <div class="card-body">
                <h5 class="mb-3">Message</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th style="width: 230px;">Recipient Name</th>
                                <td>{{ $message->recipient_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Raw Phone</th>
                                <td>{{ $message->recipient_phone_raw ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Normalized Phone</th>
                                <td>{{ $message->recipient_phone_normalized ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Network</th>
                                <td>{{ $message->recipient_network ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Provider</th>
                                <td>{{ $message->provider_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Mode</th>
                                <td>{{ strtoupper($message->sms_mode ?? '-') }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($message->sms_status === 'sent' || $message->sms_status === 'delivered')
                                        <span class="badge bg-success">{{ $message->sms_status }}</span>
                                    @elseif ($message->sms_status === 'queued')
                                        <span class="badge bg-info">{{ $message->sms_status }}</span>
                                    @elseif ($message->sms_status === 'demo')
                                        <span class="badge bg-warning">{{ $message->sms_status }}</span>
                                    @elseif ($message->sms_status === 'skipped')
                                        <span class="badge bg-secondary">{{ $message->sms_status }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ $message->sms_status }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Sender ID</th>
                                <td>{{ $message->sender_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Subject</th>
                                <td>{{ $message->sms_subject ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Message</th>
                                <td style="white-space: pre-wrap;">{{ $message->sms_message }}</td>
                            </tr>
                            <tr>
                                <th>Characters</th>
                                <td>{{ $message->sms_character_count }}</td>
                            </tr>
                            <tr>
                                <th>Segments</th>
                                <td>{{ $message->sms_segments }}</td>
                            </tr>
                            <tr>
                                <th>Request Reference</th>
                                <td>{{ $message->request_reference ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Provider Message ID</th>
                                <td>{{ $message->provider_message_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Attempt Count</th>
                                <td>{{ $message->attempt_count ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th>Error Code</th>
                                <td>{{ $message->error_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Error Message</th>
                                <td>{{ $message->error_message ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($message->sms_status === 'queued')
                    <form method="POST"
                          action="{{ route('bulk_sms.messages.dispatch', $message->sms_id) }}"
                          onsubmit="return confirm('Dispatch this SMS now?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Dispatch SMS
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Timeline</h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr>
                            <th>Queued</th>
                            <td>{{ $message->queued_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Sent</th>
                            <td>{{ $message->sent_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Delivered</th>
                            <td>{{ $message->delivered_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Failed</th>
                            <td>{{ $message->failed_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td>{{ $message->created_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Updated</th>
                            <td>{{ $message->updated_at ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Payloads</h3>
            </div>

            <div class="card-body">
                <h6>Request Payload</h6>
                <pre class="bg-light p-2" style="max-height: 250px; overflow:auto; font-size: 12px;">{{ $message->request_payload ?? '-' }}</pre>

                <h6>Response Payload</h6>
                <pre class="bg-light p-2" style="max-height: 250px; overflow:auto; font-size: 12px;">{{ $message->response_payload ?? '-' }}</pre>

                <h6>Meta</h6>
                <pre class="bg-light p-2" style="max-height: 250px; overflow:auto; font-size: 12px;">{{ $message->sms_meta ?? '-' }}</pre>
            </div>
        </div>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header">
        <h3 class="card-title m-0">Delivery Callbacks</h3>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Provider Message ID</th>
                        <th>Phone</th>
                        <th>Received At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($callbacks as $callback)
                        <tr>
                            <td>{{ $callback->callback_id }}</td>
                            <td>{{ $callback->provider_code ?? '-' }}</td>
                            <td>{{ $callback->callback_status ?? '-' }}</td>
                            <td>{{ $callback->provider_message_id ?? '-' }}</td>
                            <td>{{ $callback->recipient_phone ?? '-' }}</td>
                            <td>{{ $callback->callback_received_at ?? $callback->created_at ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted">No delivery callbacks recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection