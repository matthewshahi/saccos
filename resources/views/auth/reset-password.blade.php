@extends('layouts.app')

@php
    $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
@endphp

@section('seo_title', 'Create New Password | ' . $saccoName . ' Member Portal')
@section('seo_description', 'Create a new password for your ' . $saccoName . ' SACCO member portal account.')
@section('robots', 'noindex, nofollow')

@section('content')
<style>
    body {
        background:
            radial-gradient(circle at top left, rgba(100, 58, 40, 0.12), transparent 35%),
            linear-gradient(135deg, #f8f4f1 0%, #f3ede8 45%, #ffffff 100%);
        min-height: 100vh;
        min-height: 100svh;
        margin: 0;
    }

    .reset-page {
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .reset-card {
        width: 100%;
        max-width: 480px;
        background: #ffffff;
        border-radius: 22px;
        box-shadow: 0 18px 48px rgba(45, 27, 20, 0.14);
        border: 1px solid rgba(100, 58, 40, 0.08);
        padding: 26px 20px;
    }

    .reset-badge {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(100, 58, 40, 0.08);
        color: #643A28;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .reset-title {
        font-size: 24px;
        font-weight: 900;
        color: #241913;
        letter-spacing: -0.03em;
        margin-bottom: 8px;
        line-height: 1.15;
    }

    .reset-text {
        color: #756760;
        font-size: 13px;
        line-height: 1.65;
        margin-bottom: 22px;
    }

    .reset-form-group {
        margin-bottom: 15px;
    }

    .reset-form-group label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        color: #3d2b23;
        margin-bottom: 7px;
    }

    .reset-form-group .form-control {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        border: 1px solid rgba(100, 58, 40, 0.16);
        background: #ffffff;
        color: #2d211c;
        font-size: 14px;
        padding: 12px 14px;
    }

    .reset-form-group .form-control:focus {
        border-color: rgba(100, 58, 40, 0.72);
        box-shadow: 0 0 0 4px rgba(100, 58, 40, 0.10);
        outline: none;
    }

    .reset-help {
        margin-top: 6px;
        font-size: 12px;
        line-height: 1.45;
        color: #85746b;
    }

    .reset-btn {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, #643A28, #4f2e20);
        border: none;
        color: #ffffff;
        font-weight: 900;
        font-size: 15px;
        box-shadow: 0 12px 28px rgba(100, 58, 40, 0.24);
    }

    .reset-btn:hover {
        color: #ffffff;
        box-shadow: 0 16px 34px rgba(100, 58, 40, 0.30);
    }

    .reset-btn:disabled {
        opacity: .75;
        cursor: not-allowed;
        box-shadow: none;
    }

    .security-note {
        margin-top: 16px;
        padding: 12px 14px;
        border-radius: 14px;
        background: #faf6f3;
        border: 1px solid rgba(0, 0, 0, 0.05);
        color: #756760;
        font-size: 12px;
        line-height: 1.55;
    }

    .reset-footer {
        margin-top: 18px;
        text-align: center;
        font-size: 13px;
    }

    .reset-footer a {
        color: #643A28;
        font-weight: 800;
        text-decoration: none;
    }

    .reset-footer a:hover {
        text-decoration: underline;
    }

    @media (min-width: 576px) {
        .reset-page {
            padding: 34px 18px;
        }

        .reset-card {
            padding: 34px;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(45, 27, 20, 0.16);
        }

        .reset-title {
            font-size: 28px;
        }

        .reset-text {
            font-size: 14px;
        }
    }

    @media (max-width: 360px) {
        .reset-page {
            padding: 10px;
        }

        .reset-card {
            padding: 22px 16px;
            border-radius: 18px;
        }

        .reset-title {
            font-size: 22px;
        }

        .reset-btn,
        .reset-form-group .form-control {
            height: 48px;
        }
    }
</style>

<div class="reset-page">
    <div class="reset-card">
        <div class="reset-badge">Secure Password Reset</div>

        <div class="reset-title">Create new password</div>

        <p class="reset-text">
            Enter and confirm your new password for your {{ $saccoName }} member portal account.
        </p>

        @if($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('member.password.update') }}" id="resetPasswordForm">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

            <div class="reset-form-group">
                <label for="password">New Password</label>
                <input type="password"
                       name="password"
                       id="password"
                       class="form-control"
                       placeholder="Enter new password"
                       autocomplete="new-password"
                       required>
                <div class="reset-help">
                    Use at least 8 characters. Avoid using a password you share with other accounts.
                </div>
            </div>

            <div class="reset-form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password"
                       name="password_confirmation"
                       id="password_confirmation"
                       class="form-control"
                       placeholder="Confirm new password"
                       autocomplete="new-password"
                       required>
            </div>

            <button type="submit" class="reset-btn" id="resetBtn">
                Save New Password
            </button>
        </form>

        <div class="security-note">
            After saving your new password, return to the member login page and sign in using your updated details.
        </div>

        <div class="reset-footer">
            <a href="{{ route('login') }}">Back to member login</a>
        </div>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<script>
(function() {
    const SITE_KEY = "{{ config('services.recaptcha.site_key') }}";
    const form = document.getElementById('resetPasswordForm');
    const tokenInput = document.getElementById('recaptcha_token');
    const submitBtn = document.getElementById('resetBtn');

    form.addEventListener('submit', function(e) {
        if (typeof grecaptcha === 'undefined') {
            return;
        }

        if (tokenInput.value) {
            return;
        }

        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerText = 'Checking security...';

        grecaptcha.ready(function() {
            grecaptcha.execute(SITE_KEY, { action: 'password_reset' }).then(function(token) {
                tokenInput.value = token;
                form.submit();
            }).catch(function() {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Save New Password';
            });
        });
    });
})();
</script>
@endsection