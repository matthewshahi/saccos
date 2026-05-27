@extends('layouts.app')

@php
    $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
    $supportPhone = trim((string) env('SACCO_SUPPORT'));
@endphp

@section('seo_title', $saccoName . ' Member Login | SACCO Member Portal')

@section('seo_description', $saccoName . ' member login portal for accessing SACCO savings, loans, statements, contributions and member services.')

@section('robots', 'noindex, follow')

@section('content')
<style>
    :root {
        --fi-primary: #643A28;
        --fi-primary-dark: #4f2e20;
        --fi-primary-soft: rgba(100, 58, 40, 0.08);
        --fi-primary-border: rgba(100, 58, 40, 0.16);
        --fi-text: #241913;
        --fi-muted: #756760;
        --fi-panel: #ffffff;
        --fi-soft-bg: #fcf8f5;
    }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100svh;
        background:
            radial-gradient(circle at top left, rgba(100, 58, 40, 0.12), transparent 34%),
            linear-gradient(135deg, #f8f4f1 0%, #f3ede8 46%, #ffffff 100%);
    }

    .fi-login-page {
        width: 100%;
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        align-items: stretch;
        justify-content: center;
        padding: 0;
    }

    .fi-login-shell {
        width: 100%;
        min-height: 100vh;
        min-height: 100svh;
        background: var(--fi-panel);
        display: grid;
        grid-template-columns: 1fr;
        overflow: hidden;
    }

    .fi-brand-panel {
        position: relative;
        padding: 24px 20px;
        background:
            linear-gradient(135deg, rgba(100, 58, 40, 0.98), rgba(79, 46, 32, 0.99)),
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 36%);
        color: #ffffff;
        overflow: hidden;
    }

    .fi-brand-panel::before {
        content: "";
        position: absolute;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        top: -110px;
        right: -96px;
    }

    .fi-brand-panel::after {
        content: "";
        position: absolute;
        width: 190px;
        height: 190px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.14);
        bottom: -105px;
        left: -86px;
    }

    .fi-brand-inner {
        position: relative;
        z-index: 2;
    }

    .fi-mini-label {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .fi-brand-title {
        font-size: 28px;
        line-height: 1.08;
        font-weight: 900;
        letter-spacing: -0.035em;
        margin: 0 0 10px;
    }

    .fi-brand-text {
        max-width: 480px;
        margin: 0;
        font-size: 13px;
        line-height: 1.65;
        color: rgba(255, 255, 255, 0.84);
    }

    .fi-feature-list {
        display: none;
    }

    .fi-brand-footer {
        display: none;
    }

    .fi-form-panel {
        padding: 26px 18px 34px;
        background: linear-gradient(180deg, #ffffff 0%, var(--fi-soft-bg) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fi-login-card {
        width: 100%;
        max-width: 430px;
    }

    .fi-login-top {
        margin-bottom: 24px;
    }

    .fi-login-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--fi-primary-soft);
        color: var(--fi-primary);
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 14px;
    }

    .sacco-brand {
        font-size: 25px;
        font-weight: 900;
        color: var(--fi-text);
        letter-spacing: -0.035em;
        line-height: 1.15;
        margin-bottom: 6px;
    }

    .fi-login-subtitle {
        font-size: 13px;
        color: var(--fi-muted);
        line-height: 1.6;
        margin: 0;
    }

    .fi-form-group {
        margin-bottom: 15px;
    }

    .fi-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 7px;
    }

    .fi-form-group label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        color: #3d2b23;
        margin: 0;
    }

    .fi-forgot-link {
        color: var(--fi-primary);
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }

    .fi-forgot-link:hover {
        text-decoration: underline;
    }

    .fi-input-wrap {
        position: relative;
    }

    .fi-input-wrap .form-control {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        border: 1px solid var(--fi-primary-border);
        background: #ffffff;
        color: #2d211c;
        font-size: 14px;
        padding: 12px 14px;
        box-shadow: 0 1px 0 rgba(0, 0, 0, 0.02);
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .fi-input-wrap .form-control:focus {
        border-color: rgba(100, 58, 40, 0.72);
        box-shadow: 0 0 0 4px rgba(100, 58, 40, 0.10);
        outline: none;
    }

    .fi-submit-btn {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--fi-primary), var(--fi-primary-dark));
        border: none;
        color: #ffffff;
        font-weight: 900;
        font-size: 15px;
        box-shadow: 0 12px 28px rgba(100, 58, 40, 0.24);
        transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
    }

    .fi-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(100, 58, 40, 0.30);
        color: #ffffff;
    }

    .fi-submit-btn:disabled {
        opacity: .75;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .fi-apply-box {
        margin-top: 16px;
        padding: 14px;
        border-radius: 16px;
        background: rgba(100, 58, 40, 0.06);
        border: 1px solid rgba(100, 58, 40, 0.10);
        text-align: center;
        font-size: 13px;
        line-height: 1.45;
        color: #675951;
    }

    .fi-apply-box a {
        display: inline-block;
        margin-top: 4px;
        color: var(--fi-primary);
        font-weight: 900;
        text-decoration: none;
    }

    .fi-apply-box a:hover {
        text-decoration: underline;
    }

    .fi-security-note {
        display: flex;
        gap: 10px;
        margin-top: 18px;
        padding: 12px 14px;
        border-radius: 16px;
        background: #faf6f3;
        border: 1px solid rgba(0, 0, 0, 0.05);
        color: var(--fi-muted);
        font-size: 12px;
        line-height: 1.55;
    }

    .fi-security-note strong {
        color: #3d2b23;
    }

    .fi-security-dot {
        width: 9px;
        height: 9px;
        min-width: 9px;
        margin-top: 5px;
        border-radius: 50%;
        background: #2fb344;
        box-shadow: 0 0 0 4px rgba(47, 179, 68, 0.12);
    }

    .login-footer {
        font-size: 0.86rem;
        text-align: center;
        margin-top: 22px;
        color: #675951;
    }

    .login-footer a {
        color: var(--fi-primary);
        text-decoration: none;
        font-weight: 800;
    }

    .login-footer a:hover {
        text-decoration: underline;
    }

    .provider-credit {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(100, 58, 40, 0.10);
        font-size: 0.82rem;
        color: #7a6c64;
        line-height: 1.45;
    }

    .provider-credit strong {
        color: var(--fi-primary);
        font-weight: 900;
    }

    .recaptcha-note {
        font-size: 0.85rem;
        color: #666;
        margin-top: 0.75rem;
        text-align: center;
    }

    @media (min-width: 576px) {
        .fi-login-page {
            align-items: center;
            padding: 24px 18px;
        }

        .fi-login-shell {
            min-height: auto;
            max-width: 540px;
            border-radius: 26px;
            box-shadow: 0 22px 60px rgba(45, 27, 20, 0.16);
            border: 1px solid rgba(100, 58, 40, 0.08);
        }

        .fi-brand-panel {
            padding: 32px 28px;
        }

        .fi-form-panel {
            padding: 34px 28px 38px;
        }

        .sacco-brand {
            font-size: 28px;
        }
    }

    @media (min-width: 960px) {
        .fi-login-page {
            padding: 34px 18px;
        }

        .fi-login-shell {
            max-width: 1080px;
            min-height: 620px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(45, 27, 20, 0.18);
        }

        .fi-brand-panel {
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .fi-brand-title {
            font-size: 38px;
        }

        .fi-brand-text {
            font-size: 15px;
            line-height: 1.75;
        }

        .fi-feature-list {
            display: grid;
            gap: 12px;
            margin-top: 24px;
        }

        .fi-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
        }

        .fi-feature-icon {
            width: 28px;
            height: 28px;
            min-width: 28px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
        }

        .fi-feature-item strong {
            display: block;
            font-size: 13px;
            margin-bottom: 2px;
            color: #ffffff;
        }

        .fi-feature-item span {
            display: block;
            font-size: 12px;
            line-height: 1.45;
            color: rgba(255, 255, 255, 0.78);
        }

        .fi-brand-footer {
            display: block;
            position: relative;
            z-index: 2;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.72);
            line-height: 1.6;
        }

        .fi-form-panel {
            padding: 44px;
        }
    }

    @media (max-width: 360px) {
        .fi-brand-panel {
            padding: 22px 16px;
        }

        .fi-form-panel {
            padding: 24px 16px 32px;
        }

        .sacco-brand {
            font-size: 23px;
        }

        .fi-brand-title {
            font-size: 25px;
        }

        .fi-submit-btn,
        .fi-input-wrap .form-control {
            height: 48px;
        }
    }
</style>

<div class="fi-login-page">
    <div class="fi-login-shell">
        <section class="fi-brand-panel">
            <div class="fi-brand-inner">
                <div class="fi-mini-label">Secure Member Access</div>

                <h1 class="fi-brand-title">
                    Welcome to<br>{{ $saccoName }}
                </h1>

                <p class="fi-brand-text">
                    Access your SACCO member services securely, including savings, contributions,
                    loan information, statements and account updates.
                </p>

                <div class="fi-feature-list">
                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Member self-service</strong>
                            <span>View key SACCO account services from one secure portal.</span>
                        </div>
                    </div>

                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Savings and loans access</strong>
                            <span>Check contributions, loan records and member account information.</span>
                        </div>
                    </div>

                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Protected sign-in</strong>
                            <span>Your login is protected by account validation and security checks.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fi-brand-footer">
                Use this portal only if you are a registered member or applicant of {{ $saccoName }}.
            </div>
        </section>

        <section class="fi-form-panel">
            <div class="fi-login-card">
                <div class="fi-login-top">
                    <div class="fi-login-badge">Member Portal</div>
                    <div class="sacco-brand">{{ $saccoName }}</div>
                    <p class="fi-login-subtitle">
                        Sign in using your registered email address or phone number.
                    </p>
                </div>

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="fi-form-group">
                        <label for="login">Email or Phone Number</label>
                        <div class="fi-input-wrap">
                            <input type="text"
                                   name="login"
                                   id="login"
                                   class="form-control"
                                   placeholder="Enter email or phone"
                                   value="{{ old('login') }}"
                                   autocomplete="username"
                                   inputmode="email"
                                   required>
                        </div>
                        @if ($errors->has('login'))
                            <small class="text-danger">{{ $errors->first('login') }}</small>
                        @endif
                    </div>

                    <div class="fi-form-group">
                        <div class="fi-label-row">
                            <label for="password">Password</label>
                            <a href="{{ route('member.password.request') }}" class="fi-forgot-link">
                                Forgot password?
                            </a>
                        </div>

                        <div class="fi-input-wrap">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="form-control"
                                   placeholder="Enter password"
                                   autocomplete="current-password"
                                   required>
                        </div>
                        @if ($errors->has('password'))
                            <small class="text-danger">{{ $errors->first('password') }}</small>
                        @endif
                    </div>

                    {{-- reCAPTCHA v3 token --}}
                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">

                    <div class="d-grid mb-3">
                        <button type="submit" class="fi-submit-btn" id="loginBtn">
                            Login Securely
                        </button>
                    </div>

                    <div class="fi-apply-box">
                        Not yet a member?
                        <br>
                        <a href="{{ url('/register') }}">Apply for SACCO membership</a>
                    </div>

                    <div class="recaptcha-note" id="recaptchaNote" style="display:none;">
                        reCAPTCHA is blocked or slow. If login fails, disable any ad-blocker for this site and try again.
                    </div>
                </form>

                <div class="fi-security-note">
                    <span class="fi-security-dot"></span>
                    <div>
                        <strong>Security reminder:</strong>
                        Do not share your password or login details with anyone.
                    </div>
                </div>

                <div class="login-footer">
                    @if($supportPhone !== '')
                        <div>
                            <strong>Need Help?</strong><br>
                            Call or WhatsApp:
                            <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}">
                                {{ $supportPhone }}
                            </a>
                        </div>
                    @endif

                    <div class="provider-credit">
                        <div>
                            <strong>iSacco</strong> system by
                            <a href="https://shahi.co.ke" target="_blank" rel="noopener">
                                Shahi Services
                            </a>
                        </div>
                        <div>
                            <a href="https://shahi.co.ke" target="_blank" rel="noopener">
                                shahi.co.ke
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

{{-- reCAPTCHA v3 script --}}
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<script>
(function() {
  const SITE_KEY = "{{ config('services.recaptcha.site_key') }}";
  const form = document.getElementById('loginForm');
  const tokenInput = document.getElementById('recaptcha_token');
  const note = document.getElementById('recaptchaNote');

  // If user edits fields after token was set, clear it so we always generate a fresh one on submit.
  const clearToken = () => { tokenInput.value = ''; };
  document.getElementById('login').addEventListener('input', clearToken);
  document.getElementById('password').addEventListener('input', clearToken);

  form.addEventListener('submit', function(e) {
    // If script blocked, allow submit (backend should reject with clear message)
    if (typeof grecaptcha === 'undefined') {
      if (note) note.style.display = 'block';
      return;
    }

    // If token already set, allow normal submit
    if (tokenInput.value) return;

    e.preventDefault();

    grecaptcha.ready(function() {
      grecaptcha.execute(SITE_KEY, { action: 'login' }).then(function(token) {
        tokenInput.value = token;
        form.submit();
      }).catch(function() {
        if (note) note.style.display = 'block';
        // allow submit anyway; backend should handle missing token
        form.submit();
      });
    });
  });

  // Optional: show a hint if token is never generated (slow/blocked)
  setTimeout(function() {
    if (typeof grecaptcha === 'undefined') {
      if (note) note.style.display = 'block';
    }
  }, 2500);
})();
</script>
@endsection