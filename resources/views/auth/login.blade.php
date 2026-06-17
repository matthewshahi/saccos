@extends('layouts.app')

@php
    $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
    $supportPhone = trim((string) config('app.sacco_support', ''));

    $logoEnabled = (bool) config('app.logo.enabled', false);
    $logoFolder = trim((string) config('app.logo.folder', 'logo'), '/');
    $logoFilename = ltrim((string) config('app.logo.filename', ''), '/');

    $logoRelativePath = null;
    $logoPublicPath = null;
    $logoUrl = null;
    $showLogo = false;

    if ($logoEnabled && $logoFolder !== '' && $logoFilename !== '') {
        $logoRelativePath = $logoFolder . '/' . $logoFilename;
        $logoPublicPath = public_path($logoRelativePath);

        if (file_exists($logoPublicPath)) {
            $logoUrl = asset($logoRelativePath);
            $showLogo = true;
        }
    }

    $words = preg_split('/\s+/', trim($saccoName));
    $initials = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    $initials = $initials !== '' ? $initials : 'S';

    $recaptchaSiteKey = config('services.recaptcha.site_key');
@endphp

@section('seo_title', $saccoName . ' Member Login | SACCO Member Portal')

@section('seo_description', $saccoName . ' member login portal for accessing SACCO savings, loans, statements, contributions and member services.')

@section('robots', 'noindex, follow')

@section('content')
<style>
    :root {
        --fi-purple: #7b3fb4;
        --fi-purple-dark: #4b1f7a;
        --fi-purple-deep: #2b123f;
        --fi-purple-soft: rgba(123, 63, 180, 0.10);
        --fi-purple-border: rgba(123, 63, 180, 0.20);

        --fi-green: #7ed957;
        --fi-green-dark: #45a82e;
        --fi-green-soft: rgba(126, 217, 87, 0.16);

        --fi-ink: #17102a;
        --fi-ink-2: #2d2442;
        --fi-muted: #6b647a;
        --fi-soft: #f8f6fb;
        --fi-card: #ffffff;
        --fi-border: #e8e1ef;

        --fi-danger: #dc2626;

        --fi-shadow: 0 26px 80px rgba(36, 18, 61, 0.20);
        --fi-shadow-soft: 0 16px 42px rgba(36, 18, 61, 0.10);
    }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100svh;
        background:
            radial-gradient(circle at top left, rgba(123, 63, 180, 0.16), transparent 34%),
            radial-gradient(circle at bottom right, rgba(126, 217, 87, 0.18), transparent 30%),
            linear-gradient(135deg, #fbfaff 0%, #f4eef9 48%, #ffffff 100%);
    }

    .fi-login-page {
        width: 100%;
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px 18px;
    }

    .fi-login-shell {
        width: 100%;
        max-width: 1120px;
        min-height: 660px;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        background: var(--fi-card);
        border-radius: 34px;
        overflow: hidden;
        box-shadow: var(--fi-shadow);
        border: 1px solid rgba(255, 255, 255, 0.85);
    }

    .fi-brand-panel {
        position: relative;
        padding: 46px;
        background:
            radial-gradient(circle at top right, rgba(126, 217, 87, 0.20), transparent 32%),
            radial-gradient(circle at bottom left, rgba(255, 255, 255, 0.14), transparent 34%),
            linear-gradient(145deg, var(--fi-purple-deep) 0%, var(--fi-purple-dark) 48%, var(--fi-purple) 100%);
        color: #ffffff;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .fi-brand-panel::before {
        content: "";
        position: absolute;
        width: 330px;
        height: 330px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        top: -160px;
        right: -130px;
    }

    .fi-brand-panel::after {
        content: "";
        position: absolute;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.18);
        bottom: -120px;
        left: -96px;
    }

    .fi-brand-inner,
    .fi-brand-footer {
        position: relative;
        z-index: 2;
    }

    .fi-brand-logo-row {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 30px;
    }

    .fi-brand-logo-box {
        width: 74px;
        height: 74px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.70);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 18px 38px rgba(0, 0, 0, 0.18);
        overflow: hidden;
    }

    .fi-brand-logo-box img {
        max-width: 60px;
        max-height: 60px;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .fi-brand-fallback {
        width: 74px;
        height: 74px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.13);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .fi-brand-kicker {
        font-size: 11px;
        line-height: 1;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: rgba(255, 255, 255, 0.68);
        margin-bottom: 7px;
    }

    .fi-brand-mini-name {
        font-size: 18px;
        font-weight: 950;
        letter-spacing: -0.04em;
        color: #ffffff;
        line-height: 1.1;
    }

    .fi-mini-label {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        font-size: 10px;
        font-weight: 950;
        letter-spacing: .10em;
        text-transform: uppercase;
        margin-bottom: 18px;
    }

    .fi-brand-title {
        font-size: 45px;
        line-height: 1.02;
        font-weight: 950;
        letter-spacing: -0.055em;
        margin: 0 0 16px;
        color: #ffffff;
    }

    .fi-brand-text {
        max-width: 520px;
        margin: 0;
        font-size: 15px;
        line-height: 1.75;
        color: rgba(255, 255, 255, 0.80);
    }

    .fi-feature-list {
        display: grid;
        gap: 13px;
        margin-top: 30px;
    }

    .fi-feature-item {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        padding: 15px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.11);
        border: 1px solid rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(12px);
    }

    .fi-feature-icon {
        width: 31px;
        height: 31px;
        min-width: 31px;
        border-radius: 50%;
        background: var(--fi-green-soft);
        color: #d8ffc8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 950;
    }

    .fi-feature-item strong {
        display: block;
        font-size: 13px;
        margin-bottom: 3px;
        color: #ffffff;
    }

    .fi-feature-item span {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        color: rgba(255, 255, 255, 0.72);
    }

    .fi-brand-footer {
        margin-top: 34px;
        padding-top: 18px;
        border-top: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 12px;
        color: rgba(255, 255, 255, 0.68);
        line-height: 1.6;
    }

    .fi-form-panel {
        padding: 48px 46px;
        background:
            radial-gradient(circle at top right, rgba(123, 63, 180, 0.07), transparent 34%),
            linear-gradient(180deg, #ffffff 0%, #fbfaff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fi-login-card {
        width: 100%;
        max-width: 430px;
    }

    .fi-login-logo-wrap {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin-bottom: 22px;
    }

    .fi-login-logo-box {
        width: 96px;
        height: 96px;
        border-radius: 26px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--fi-shadow-soft);
        overflow: hidden;
    }

    .fi-login-logo {
        display: block;
        max-width: 78px;
        max-height: 78px;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .fi-login-fallback-logo {
        width: 96px;
        height: 96px;
        border-radius: 26px;
        background:
            radial-gradient(circle at top right, rgba(126, 217, 87, 0.28), transparent 38%),
            linear-gradient(135deg, var(--fi-purple), var(--fi-purple-deep));
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -0.05em;
        box-shadow: var(--fi-shadow-soft);
    }

    .fi-login-top {
        margin-bottom: 24px;
    }

    .fi-login-badge {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        background: var(--fi-purple-soft);
        color: var(--fi-purple-dark);
        font-size: 10px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .10em;
        margin-bottom: 14px;
    }

    .sacco-brand {
        font-size: 31px;
        font-weight: 950;
        color: var(--fi-ink);
        letter-spacing: -0.055em;
        line-height: 1.1;
        margin-bottom: 8px;
    }

    .fi-login-subtitle {
        font-size: 13.5px;
        color: var(--fi-muted);
        line-height: 1.6;
        margin: 0;
    }

    .fi-form-group {
        margin-bottom: 16px;
    }

    .fi-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }

    .fi-form-group label {
        display: block;
        font-size: 13px;
        font-weight: 850;
        color: var(--fi-ink);
        margin: 0;
    }

    .fi-forgot-link {
        color: var(--fi-purple-dark);
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }

    .fi-forgot-link:hover {
        color: var(--fi-purple);
        text-decoration: underline;
    }

    .fi-input-wrap {
        position: relative;
    }

    .fi-input-wrap .form-control {
        width: 100%;
        height: 54px;
        border-radius: 16px;
        border: 1px solid var(--fi-border);
        background: #ffffff;
        color: var(--fi-ink);
        font-size: 14px;
        padding: 13px 15px;
        box-shadow: 0 1px 0 rgba(36, 18, 61, 0.02);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .fi-input-wrap .form-control::placeholder {
        color: #9a92a8;
    }

    .fi-input-wrap .form-control:focus {
        border-color: rgba(123, 63, 180, 0.72);
        box-shadow: 0 0 0 4px rgba(123, 63, 180, 0.11);
        background-color: #ffffff;
        outline: none;
    }

    .fi-submit-btn {
        width: 100%;
        height: 54px;
        border-radius: 16px;
        background:
            radial-gradient(circle at top right, rgba(126, 217, 87, 0.35), transparent 36%),
            linear-gradient(135deg, var(--fi-purple), var(--fi-purple-deep));
        border: none;
        color: #ffffff;
        font-weight: 950;
        font-size: 15px;
        box-shadow: 0 18px 36px rgba(75, 31, 122, 0.28);
        transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease, filter .16s ease;
    }

    .fi-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 22px 44px rgba(75, 31, 122, 0.34);
        color: #ffffff;
        filter: saturate(1.04);
    }

    .fi-submit-btn:disabled,
    .fi-submit-btn.is-processing {
        opacity: .78;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
        filter: none;
    }

    .fi-btn-content {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
    }

    .fi-btn-spinner {
        display: none;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.45);
        border-top-color: #ffffff;
        animation: fi-spin .75s linear infinite;
    }

    .fi-submit-btn.is-processing .fi-btn-spinner {
        display: inline-block;
    }

    @keyframes fi-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .fi-apply-box {
        margin-top: 16px;
        padding: 15px;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        text-align: center;
        font-size: 13px;
        line-height: 1.45;
        color: var(--fi-muted);
        box-shadow: 0 12px 28px rgba(36, 18, 61, 0.06);
    }

    .fi-apply-box a {
        display: inline-block;
        margin-top: 5px;
        color: var(--fi-purple-dark);
        font-weight: 950;
        text-decoration: none;
    }

    .fi-apply-box a:hover {
        color: var(--fi-purple);
        text-decoration: underline;
    }

    .fi-security-note {
        display: flex;
        gap: 11px;
        margin-top: 18px;
        padding: 13px 15px;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        color: var(--fi-muted);
        font-size: 12px;
        line-height: 1.55;
        box-shadow: 0 12px 28px rgba(36, 18, 61, 0.06);
    }

    .fi-security-note strong {
        color: var(--fi-ink);
    }

    .fi-security-dot {
        width: 10px;
        height: 10px;
        min-width: 10px;
        margin-top: 5px;
        border-radius: 50%;
        background: var(--fi-green);
        box-shadow: 0 0 0 4px rgba(126, 217, 87, 0.18);
    }

    .login-footer {
        font-size: 0.86rem;
        text-align: center;
        margin-top: 22px;
        color: var(--fi-muted);
    }

    .login-footer a {
        color: var(--fi-purple-dark);
        text-decoration: none;
        font-weight: 850;
    }

    .login-footer a:hover {
        color: var(--fi-purple);
        text-decoration: underline;
    }

    .provider-credit {
        margin-top: 13px;
        padding-top: 13px;
        border-top: 1px solid var(--fi-border);
        font-size: 0.82rem;
        color: var(--fi-muted);
        line-height: 1.45;
    }

    .provider-credit strong {
        color: var(--fi-purple-dark);
        font-weight: 950;
    }

    .recaptcha-note {
        font-size: 0.85rem;
        color: var(--fi-danger);
        margin-top: 0.75rem;
        text-align: center;
        line-height: 1.45;
    }

    @media (max-width: 991.98px) {
        .fi-login-page {
            align-items: stretch;
            padding: 0;
        }

        .fi-login-shell {
            max-width: none;
            min-height: 100vh;
            min-height: 100svh;
            border-radius: 0;
            grid-template-columns: 1fr;
        }

        .fi-form-panel {
            order: 1;
            padding: 30px 20px 24px;
            align-items: flex-start;
        }

        .fi-brand-panel {
            order: 2;
            padding: 28px 20px 30px;
        }

        .fi-brand-logo-row {
            display: none;
        }

        .fi-brand-title {
            font-size: 30px;
        }

        .fi-brand-text {
            font-size: 13px;
        }

        .fi-feature-list {
            display: none;
        }

        .fi-brand-footer {
            display: none;
        }

        .fi-login-card {
            max-width: 460px;
            margin: 0 auto;
        }

        .fi-login-logo-wrap {
            justify-content: center;
            margin-bottom: 18px;
        }

        .fi-login-top {
            text-align: center;
        }
    }

    @media (max-width: 575.98px) {
        .fi-form-panel {
            padding: 24px 17px 24px;
        }

        .fi-brand-panel {
            padding: 24px 17px 28px;
        }

        .fi-login-logo-box,
        .fi-login-fallback-logo {
            width: 86px;
            height: 86px;
            border-radius: 24px;
        }

        .fi-login-logo {
            max-width: 70px;
            max-height: 70px;
        }

        .sacco-brand {
            font-size: 28px;
        }

        .fi-login-subtitle {
            font-size: 13px;
        }

        .fi-input-wrap .form-control,
        .fi-submit-btn {
            height: 52px;
        }
    }

    @media (max-width: 360px) {
        .fi-form-panel {
            padding: 22px 15px 24px;
        }

        .sacco-brand {
            font-size: 25px;
        }

        .fi-brand-title {
            font-size: 26px;
        }

        .fi-login-logo-box,
        .fi-login-fallback-logo {
            width: 78px;
            height: 78px;
            border-radius: 22px;
        }

        .fi-login-logo {
            max-width: 64px;
            max-height: 64px;
        }
    }
</style>

<div class="fi-login-page">
    <div class="fi-login-shell">
        <section class="fi-brand-panel">
            <div class="fi-brand-inner">
                <div class="fi-brand-logo-row">
                    @if($showLogo)
                        <div class="fi-brand-logo-box">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ $saccoName }} Logo"
                                loading="eager"
                                onerror="this.closest('.fi-brand-logo-box').style.display='none';"
                            >
                        </div>
                    @else
                        <div class="fi-brand-fallback">{{ $initials }}</div>
                    @endif

                    <div>
                        <div class="fi-brand-kicker">Member Banking Portal</div>
                        <div class="fi-brand-mini-name">{{ $saccoName }}</div>
                    </div>
                </div>

                <div class="fi-mini-label">Secure Member Access</div>

                <h1 class="fi-brand-title">
                    Banking-grade access for your SACCO account.
                </h1>

                <p class="fi-brand-text">
                    Sign in securely to access savings, deposits, capital, loans, statements,
                    account records and member services from one trusted portal.
                </p>

                <div class="fi-feature-list">
                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Member self-service</strong>
                            <span>View key account services, balances and records from one secure portal.</span>
                        </div>
                    </div>

                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Savings and loans visibility</strong>
                            <span>Access contribution, deposit, loan and statement information with confidence.</span>
                        </div>
                    </div>

                    <div class="fi-feature-item">
                        <div class="fi-feature-icon">✓</div>
                        <div>
                            <strong>Secure sign-in</strong>
                            <span>Your login is protected by account validation and automated security checks.</span>
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
                <div class="fi-login-logo-wrap">
                    @if($showLogo)
                        <div class="fi-login-logo-box">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ $saccoName }} Logo"
                                class="fi-login-logo"
                                loading="eager"
                                onerror="this.closest('.fi-login-logo-wrap').innerHTML='<div class=&quot;fi-login-fallback-logo&quot;>{{ $initials }}</div>';"
                            >
                        </div>
                    @else
                        <div class="fi-login-fallback-logo">{{ $initials }}</div>
                    @endif
                </div>

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

                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">

                    <div class="d-grid mb-3">
                        <button type="submit" class="fi-submit-btn" id="loginBtn">
                            <span class="fi-btn-content">
                                <span class="fi-btn-spinner" aria-hidden="true"></span>
                                <span class="fi-btn-text">Login Securely</span>
                            </span>
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
                        Do not share your password, OTP, PIN or login details with anyone.
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

@if(!empty($recaptchaSiteKey))
<script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
@endif

<script>
(function() {
    const SITE_KEY = "{{ $recaptchaSiteKey }}";
    const form = document.getElementById('loginForm');
    const tokenInput = document.getElementById('recaptcha_token');
    const note = document.getElementById('recaptchaNote');
    const loginBtn = document.getElementById('loginBtn');
    const loginBtnText = loginBtn ? loginBtn.querySelector('.fi-btn-text') : null;

    if (!form) return;

    let submitLocked = false;
    let unlockTimer = null;

    function lockSubmitButton() {
        if (!loginBtn) return;

        submitLocked = true;
        loginBtn.disabled = true;
        loginBtn.classList.add('is-processing');

        if (loginBtnText) {
            loginBtnText.textContent = 'Signing in...';
        }

        clearTimeout(unlockTimer);

        unlockTimer = setTimeout(function() {
            unlockSubmitButton();
        }, 30000);
    }

    function unlockSubmitButton() {
        if (!loginBtn) return;

        submitLocked = false;
        loginBtn.disabled = false;
        loginBtn.classList.remove('is-processing');

        if (loginBtnText) {
            loginBtnText.textContent = 'Login Securely';
        }
    }

    function showRecaptchaNote() {
        if (note) {
            note.style.display = 'block';
        }
    }

    function submitNativeForm() {
        HTMLFormElement.prototype.submit.call(form);
    }

    function clearToken() {
        if (tokenInput) {
            tokenInput.value = '';
        }
    }

    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');

    if (loginInput) {
        loginInput.addEventListener('input', clearToken);
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', clearToken);
    }

    form.addEventListener('submit', function(e) {
        if (submitLocked) {
            e.preventDefault();
            return;
        }

        lockSubmitButton();

        if (!SITE_KEY || typeof grecaptcha === 'undefined') {
            if (SITE_KEY) {
                showRecaptchaNote();
            }
            return;
        }

        if (tokenInput && tokenInput.value) {
            return;
        }

        e.preventDefault();

        grecaptcha.ready(function() {
            grecaptcha.execute(SITE_KEY, { action: 'login' }).then(function(token) {
                if (tokenInput) {
                    tokenInput.value = token;
                }

                submitNativeForm();
            }).catch(function() {
                showRecaptchaNote();
                submitNativeForm();
            });
        });
    });

    setTimeout(function() {
        if (SITE_KEY && typeof grecaptcha === 'undefined') {
            showRecaptchaNote();
        }
    }, 2500);
})();
</script>
@endsection