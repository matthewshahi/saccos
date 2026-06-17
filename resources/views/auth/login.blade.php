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
        --fi-gold: #b88a2b;
        --fi-gold-dark: #8a641e;
        --fi-gold-soft: rgba(184, 138, 43, 0.10);
        --fi-gold-border: rgba(184, 138, 43, 0.22);

        --fi-ink: #111827;
        --fi-ink-soft: #263142;
        --fi-muted: #6b7280;
        --fi-muted-strong: #4b5563;

        --fi-bg: #f8f7f3;
        --fi-panel: #ffffff;
        --fi-soft: #fbfaf7;
        --fi-border: #e7e2d7;

        --fi-success: #3fa34d;
        --fi-danger: #dc2626;

        --fi-shadow: 0 24px 70px rgba(17, 24, 39, 0.13);
        --fi-shadow-soft: 0 14px 34px rgba(17, 24, 39, 0.08);
    }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100svh;
        background:
            radial-gradient(circle at top left, rgba(184, 138, 43, 0.12), transparent 32%),
            radial-gradient(circle at bottom right, rgba(17, 24, 39, 0.06), transparent 34%),
            linear-gradient(135deg, #ffffff 0%, #faf8f2 48%, #f3f0e8 100%);
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
        min-height: 650px;
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        background: var(--fi-panel);
        border-radius: 32px;
        overflow: hidden;
        box-shadow: var(--fi-shadow);
        border: 1px solid rgba(255, 255, 255, 0.9);
    }

    .fi-brand-panel {
        position: relative;
        padding: 46px;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.16), transparent 34%),
            radial-gradient(circle at bottom left, rgba(255, 255, 255, 0.9), transparent 36%),
            linear-gradient(145deg, #ffffff 0%, #fbfaf7 44%, #f0eadf 100%);
        color: var(--fi-ink);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-right: 1px solid var(--fi-border);
    }

    .fi-brand-panel::before {
        content: "";
        position: absolute;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: rgba(184, 138, 43, 0.08);
        top: -160px;
        right: -120px;
    }

    .fi-brand-panel::after {
        content: "";
        position: absolute;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        border: 1px solid rgba(184, 138, 43, 0.18);
        bottom: -120px;
        left: -95px;
    }

    .fi-brand-inner,
    .fi-brand-footer {
        position: relative;
        z-index: 2;
    }

    .fi-brand-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        box-shadow: 0 10px 24px rgba(17, 24, 39, 0.04);
        color: var(--fi-gold-dark);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .11em;
        text-transform: uppercase;
        margin-bottom: 22px;
    }

    .fi-brand-kicker::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--fi-success);
        box-shadow: 0 0 0 4px rgba(63, 163, 77, 0.12);
    }

    .fi-brand-title {
        font-size: 39px;
        line-height: 1.04;
        font-weight: 950;
        letter-spacing: -0.055em;
        margin: 0 0 16px;
        color: var(--fi-ink);
    }

    .fi-brand-title span {
        color: var(--fi-gold-dark);
    }

    .fi-brand-text {
        max-width: 520px;
        margin: 0;
        font-size: 15px;
        line-height: 1.75;
        color: var(--fi-muted-strong);
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
        background: rgba(255, 255, 255, 0.78);
        border: 1px solid var(--fi-border);
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.045);
    }

    .fi-feature-icon {
        width: 31px;
        height: 31px;
        min-width: 31px;
        border-radius: 50%;
        background: var(--fi-gold-soft);
        color: var(--fi-gold-dark);
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
        color: var(--fi-ink);
    }

    .fi-feature-item span {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        color: var(--fi-muted);
    }

    .fi-brand-footer {
        margin-top: 34px;
        padding-top: 18px;
        border-top: 1px solid var(--fi-border);
        font-size: 12px;
        color: var(--fi-muted);
        line-height: 1.6;
    }

    .fi-form-panel {
        padding: 48px 46px;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.07), transparent 34%),
            linear-gradient(180deg, #ffffff 0%, #fbfaf7 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fi-login-card {
        width: 100%;
        max-width: 450px;
    }

    .fi-login-logo-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 22px;
    }

    .fi-login-logo-box {
        width: 112px;
        height: 112px;
        border-radius: 28px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--fi-shadow-soft);
        overflow: hidden;
        padding: 12px;
    }

    .fi-login-logo {
        display: block;
        max-width: 92px;
        max-height: 92px;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .fi-login-fallback-logo {
        width: 112px;
        height: 112px;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.32), transparent 38%),
            linear-gradient(135deg, #2f3746, #111827);
        color: #ffffff;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        font-weight: 950;
        letter-spacing: -0.05em;
        box-shadow: var(--fi-shadow-soft);
    }

    .fi-login-top {
        margin-bottom: 24px;
        text-align: center;
    }

    .fi-login-badge {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        background: var(--fi-gold-soft);
        color: var(--fi-gold-dark);
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
        color: var(--fi-gold-dark);
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
        height: 54px;
        border-radius: 16px;
        border: 1px solid var(--fi-border);
        background: #ffffff;
        color: var(--fi-ink);
        font-size: 14px;
        padding: 13px 15px;
        box-shadow: 0 1px 0 rgba(17, 24, 39, 0.02);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .fi-input-wrap .form-control::placeholder {
        color: #9ca3af;
    }

    .fi-input-wrap .form-control:focus {
        border-color: rgba(184, 138, 43, 0.72);
        box-shadow: 0 0 0 4px rgba(184, 138, 43, 0.12);
        background-color: #ffffff;
        outline: none;
    }

    .fi-submit-btn {
        width: 100%;
        height: 54px;
        border-radius: 16px;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.38), transparent 36%),
            linear-gradient(135deg, #2f3746, #111827);
        border: none;
        color: #ffffff;
        font-weight: 950;
        font-size: 15px;
        box-shadow: 0 18px 36px rgba(17, 24, 39, 0.24);
        transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease, filter .16s ease;
    }

    .fi-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 22px 44px rgba(17, 24, 39, 0.30);
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
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.045);
    }

    .fi-apply-box a {
        display: inline-block;
        margin-top: 5px;
        color: var(--fi-gold-dark);
        font-weight: 950;
        text-decoration: none;
    }

    .fi-apply-box a:hover {
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
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.045);
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
        background: var(--fi-success);
        box-shadow: 0 0 0 4px rgba(63, 163, 77, 0.14);
    }

    .login-footer {
        font-size: 0.86rem;
        text-align: center;
        margin-top: 22px;
        color: var(--fi-muted);
    }

    .login-footer a {
        color: var(--fi-gold-dark);
        text-decoration: none;
        font-weight: 850;
    }

    .login-footer a:hover {
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
        color: var(--fi-ink);
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
            box-shadow: none;
        }

        .fi-form-panel {
            order: 1;
            padding: 30px 20px 24px;
            align-items: flex-start;
        }

        .fi-brand-panel {
            order: 2;
            padding: 28px 20px 30px;
            border-right: 0;
            border-top: 1px solid var(--fi-border);
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
            width: 92px;
            height: 92px;
            border-radius: 24px;
        }

        .fi-login-logo {
            max-width: 76px;
            max-height: 76px;
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
            width: 82px;
            height: 82px;
            border-radius: 22px;
        }

        .fi-login-logo {
            max-width: 68px;
            max-height: 68px;
        }
    }
</style>

<div class="fi-login-page">
    <div class="fi-login-shell">
        <section class="fi-brand-panel">
            <div class="fi-brand-inner">
                <div class="fi-brand-kicker">
                    Secure member access
                </div>

                <h1 class="fi-brand-title">
                    Welcome to<br>
                    <span>{{ $saccoName }}</span>
                </h1>

                <p class="fi-brand-text">
                    Access your SACCO account securely. View savings, deposits, capital,
                    loans, statements and member services from one trusted portal.
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
                        <div class="fi-login-logo-box" id="loginLogoBox">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ $saccoName }} Logo"
                                class="fi-login-logo"
                                loading="eager"
                                onerror="document.getElementById('loginLogoBox').style.display='none'; document.getElementById('loginLogoFallback').style.display='flex';"
                            >
                        </div>

                        <div class="fi-login-fallback-logo" id="loginLogoFallback" style="display:none;">
                            {{ $initials }}
                        </div>
                    @else
                        <div class="fi-login-fallback-logo" style="display:flex;">
                            {{ $initials }}
                        </div>
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