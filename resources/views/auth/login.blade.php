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
@endphp

@section('seo_title', $saccoName . ' Member Login | SACCO Member Portal')

@section('seo_description', $saccoName . ' member login portal for accessing SACCO savings, loans, statements, contributions and member services.')

@section('robots', 'noindex, follow')

@section('content')
<style>
    :root {
        --fi-accent: #334155;
        --fi-accent-dark: #0f172a;
        --fi-accent-soft: rgba(51, 65, 85, 0.07);
        --fi-accent-border: rgba(51, 65, 85, 0.14);

        --fi-text: #0f172a;
        --fi-muted: #64748b;
        --fi-muted-strong: #475569;

        --fi-bg: #f6f8fb;
        --fi-bg-soft: #f8fafc;
        --fi-panel: #ffffff;
        --fi-border: #e2e8f0;

        --fi-success: #16a34a;
        --fi-danger: #dc2626;

        --fi-shadow-soft: 0 18px 50px rgba(15, 23, 42, 0.08);
        --fi-shadow-strong: 0 28px 80px rgba(15, 23, 42, 0.13);
    }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100svh;
        background:
            radial-gradient(circle at top left, rgba(148, 163, 184, 0.20), transparent 32%),
            radial-gradient(circle at bottom right, rgba(203, 213, 225, 0.28), transparent 34%),
            linear-gradient(135deg, #ffffff 0%, #f8fafc 46%, #eef2f7 100%);
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
            radial-gradient(circle at top right, rgba(148, 163, 184, 0.24), transparent 36%),
            linear-gradient(135deg, #ffffff 0%, #f8fafc 48%, #eef2f7 100%);
        color: var(--fi-text);
        overflow: hidden;
        border-bottom: 1px solid var(--fi-border);
    }

    .fi-brand-panel::before {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(148, 163, 184, 0.14);
        top: -130px;
        right: -112px;
    }

    .fi-brand-panel::after {
        content: "";
        position: absolute;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        border: 1px solid rgba(148, 163, 184, 0.28);
        bottom: -118px;
        left: -96px;
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
        background: #ffffff;
        border: 1px solid var(--fi-border);
        color: var(--fi-muted-strong);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }

    .fi-brand-title {
        font-size: 28px;
        line-height: 1.08;
        font-weight: 900;
        letter-spacing: -0.035em;
        margin: 0 0 10px;
        color: var(--fi-text);
    }

    .fi-brand-text {
        max-width: 480px;
        margin: 0;
        font-size: 13px;
        line-height: 1.65;
        color: var(--fi-muted-strong);
    }

    .fi-feature-list {
        display: none;
    }

    .fi-brand-footer {
        display: none;
    }

    .fi-form-panel {
        padding: 26px 18px 34px;
        background: #ffffff;
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
        margin-bottom: 18px;
    }

    .fi-login-logo-box {
        width: auto;
        max-width: 190px;
        min-height: 62px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 12px;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    }

    .fi-login-logo {
        display: block;
        max-width: 160px;
        max-height: 68px;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .fi-login-top {
        margin-bottom: 24px;
    }

    .fi-login-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--fi-accent-soft);
        color: var(--fi-accent);
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
        color: var(--fi-text);
        margin: 0;
    }

    .fi-forgot-link {
        color: var(--fi-accent);
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
        border: 1px solid var(--fi-border);
        background: #ffffff;
        color: var(--fi-text);
        font-size: 14px;
        padding: 12px 14px;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .fi-input-wrap .form-control::placeholder {
        color: #94a3b8;
    }

    .fi-input-wrap .form-control:focus {
        border-color: rgba(51, 65, 85, 0.58);
        box-shadow: 0 0 0 4px rgba(51, 65, 85, 0.09);
        background-color: #ffffff;
        outline: none;
    }

    .fi-submit-btn {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, #1f2937, #0f172a);
        border: none;
        color: #ffffff;
        font-weight: 900;
        font-size: 15px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
        transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease, filter .16s ease;
    }

    .fi-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 38px rgba(15, 23, 42, 0.24);
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
        padding: 14px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid var(--fi-border);
        text-align: center;
        font-size: 13px;
        line-height: 1.45;
        color: var(--fi-muted-strong);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .fi-apply-box a {
        display: inline-block;
        margin-top: 4px;
        color: var(--fi-accent-dark);
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
        background: #ffffff;
        border: 1px solid var(--fi-border);
        color: var(--fi-muted);
        font-size: 12px;
        line-height: 1.55;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .fi-security-note strong {
        color: var(--fi-text);
    }

    .fi-security-dot {
        width: 9px;
        height: 9px;
        min-width: 9px;
        margin-top: 5px;
        border-radius: 50%;
        background: var(--fi-success);
        box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.12);
    }

    .login-footer {
        font-size: 0.86rem;
        text-align: center;
        margin-top: 22px;
        color: var(--fi-muted);
    }

    .login-footer a {
        color: var(--fi-accent-dark);
        text-decoration: none;
        font-weight: 800;
    }

    .login-footer a:hover {
        text-decoration: underline;
    }

    .provider-credit {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--fi-border);
        font-size: 0.82rem;
        color: var(--fi-muted);
        line-height: 1.45;
    }

    .provider-credit strong {
        color: var(--fi-text);
        font-weight: 900;
    }

    .recaptcha-note {
        font-size: 0.85rem;
        color: var(--fi-danger);
        margin-top: 0.75rem;
        text-align: center;
        line-height: 1.45;
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
            box-shadow: var(--fi-shadow-soft);
            border: 1px solid rgba(226, 232, 240, 0.88);
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
            box-shadow: var(--fi-shadow-strong);
        }

        .fi-brand-panel {
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-bottom: 0;
            border-right: 1px solid var(--fi-border);
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
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--fi-border);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
        }

        .fi-feature-icon {
            width: 28px;
            height: 28px;
            min-width: 28px;
            border-radius: 50%;
            background: var(--fi-accent-soft);
            color: var(--fi-accent-dark);
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
            color: var(--fi-text);
        }

        .fi-feature-item span {
            display: block;
            font-size: 12px;
            line-height: 1.45;
            color: var(--fi-muted);
        }

        .fi-brand-footer {
            display: block;
            position: relative;
            z-index: 2;
            font-size: 12px;
            color: var(--fi-muted);
            line-height: 1.6;
        }

        .fi-form-panel {
            padding: 44px;
        }
    }

    @media (max-width: 575.98px) {
        .fi-login-shell {
            min-height: 100vh;
            min-height: 100svh;
        }

        .fi-form-panel {
            order: 1;
            align-items: flex-start;
            padding: 24px 18px 28px;
        }

        .fi-brand-panel {
            order: 2;
            padding: 22px 18px 24px;
        }

        .fi-brand-title {
            font-size: 24px;
        }

        .fi-brand-text {
            font-size: 12.5px;
            line-height: 1.6;
        }

        .fi-login-logo-wrap {
            justify-content: center;
            margin-bottom: 16px;
        }

        .fi-login-top {
            text-align: center;
            margin-bottom: 22px;
        }

        .fi-label-row {
            align-items: flex-end;
        }

        .fi-apply-box,
        .fi-security-note {
            border-radius: 15px;
        }

        .login-footer {
            margin-bottom: 10px;
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
            font-size: 23px;
        }

        .fi-submit-btn,
        .fi-input-wrap .form-control {
            height: 48px;
        }

        .fi-login-logo {
            max-width: 132px;
            max-height: 56px;
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
                @if($showLogo)
                    <div class="fi-login-logo-wrap">
                        <div class="fi-login-logo-box">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ $saccoName }} Logo"
                                class="fi-login-logo"
                                loading="eager"
                                onerror="this.closest('.fi-login-logo-wrap').style.display='none';"
                            >
                        </div>
                    </div>
                @endif

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

<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<script>
(function() {
    const SITE_KEY = "{{ config('services.recaptcha.site_key') }}";
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

        if (typeof grecaptcha === 'undefined') {
            showRecaptchaNote();
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
        if (typeof grecaptcha === 'undefined') {
            showRecaptchaNote();
        }
    }, 2500);
})();
</script>
@endsection