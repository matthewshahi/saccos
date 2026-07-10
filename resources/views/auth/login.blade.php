@extends('layouts.app')

@php
    $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
    $supportPhone = trim((string) config('app.sacco_support', ''));

    $logoEnabled = (bool) config('app.logo.enabled', false);
    $logoFolder = trim((string) config('app.logo.folder', 'logo'), '/');
    $logoFilename = ltrim((string) config('app.logo.filename', ''), '/');

    $logoUrl = null;
    $showLogo = false;

    if ($logoEnabled && $logoFolder !== '' && $logoFilename !== '') {
        $logoRelativePath = $logoFolder . '/' . $logoFilename;
        $logoPublicPath = public_path($logoRelativePath);

        if (is_file($logoPublicPath)) {
            $logoUrl = asset($logoRelativePath);
            $showLogo = true;
        }
    }

    $words = preg_split('/\s+/', $saccoName, -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';

    foreach ($words as $word) {
        $initials .= strtoupper(mb_substr($word, 0, 1));

        if (mb_strlen($initials) >= 2) {
            break;
        }
    }

    $initials = $initials !== '' ? $initials : 'S';

    $recaptchaSiteKey = trim((string) config('services.recaptcha.site_key', ''));

   $legalPageUrl = asset('terms_privacy.html');

$termsUrl = $legalPageUrl . '#terms';
$privacyUrl = $legalPageUrl . '#privacy';
$dataProtectionUrl = $legalPageUrl . '#privacy';

    $forgotPasswordUrl = Route::has('member.password.request')
        ? route('member.password.request')
        : (Route::has('password.request') ? route('password.request') : null);

    $registrationUrl = Route::has('register')
        ? route('register')
        : url('/register');

    $supportDialNumber = preg_replace('/[^0-9+]/', '', $supportPhone);
@endphp

@section('seo_title', $saccoName . ' Member Login')
@section('seo_description', 'Secure member login for ' . $saccoName . '.')
@section('robots', 'noindex, nofollow')

@section('content')
<style>
    :root {
        --login-accent: #9a7423;
        --login-accent-dark: #745416;
        --login-accent-soft: rgba(154, 116, 35, 0.10);
        --login-ink: #111827;
        --login-muted: #667085;
        --login-border: #e5e7eb;
        --login-surface: #ffffff;
        --login-background: #f7f5ef;
        --login-success: #238636;
        --login-danger: #b42318;
        --login-focus: rgba(154, 116, 35, 0.16);
        --login-shadow: 0 18px 55px rgba(17, 24, 39, 0.12);
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-width: 320px;
        min-height: 100vh;
        min-height: 100svh;
        color: var(--login-ink);
        background:
            radial-gradient(circle at 10% 0%, rgba(154, 116, 35, 0.12), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, var(--login-background) 100%);
    }

    .member-login-page {
        width: 100%;
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 18px 14px;
    }

    .member-login-shell {
        width: 100%;
        max-width: 480px;
    }

    .member-login-brand-panel {
        display: none;
    }

    .member-login-form-panel {
        width: 100%;
        padding: 22px 18px;
        border: 1px solid rgba(229, 231, 235, 0.92);
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: var(--login-shadow);
    }

    .member-login-card {
        width: 100%;
        max-width: 430px;
        margin: 0 auto;
    }

    .member-login-logo-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 14px;
    }

    .member-login-logo-box,
    .member-login-logo-fallback {
        width: 78px;
        height: 78px;
        border-radius: 20px;
        border: 1px solid var(--login-border);
        box-shadow: 0 10px 28px rgba(17, 24, 39, 0.08);
    }

    .member-login-logo-box {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 9px;
        overflow: hidden;
        background: #ffffff;
    }

    .member-login-logo {
        display: block;
        max-width: 62px;
        max-height: 62px;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .member-login-logo-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.42), transparent 38%),
            linear-gradient(135deg, #323b4a, #111827);
        font-size: 23px;
        font-weight: 900;
        letter-spacing: -0.05em;
    }

    .member-login-header {
        margin-bottom: 21px;
        text-align: center;
    }

    .member-login-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 9px;
        color: var(--login-accent-dark);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.10em;
        text-transform: uppercase;
    }

    .member-login-eyebrow::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--login-success);
        box-shadow: 0 0 0 4px rgba(35, 134, 54, 0.11);
    }

    .member-login-title {
        margin: 0;
        color: var(--login-ink);
        font-size: clamp(25px, 8vw, 31px);
        font-weight: 900;
        line-height: 1.12;
        letter-spacing: -0.045em;
        overflow-wrap: anywhere;
    }

    .member-login-subtitle {
        margin: 7px 0 0;
        color: var(--login-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .member-login-alert {
        margin-bottom: 16px;
        padding: 11px 13px;
        border: 1px solid transparent;
        border-radius: 13px;
        font-size: 13px;
        line-height: 1.45;
    }

    .member-login-alert-success {
        border-color: rgba(35, 134, 54, 0.20);
        color: #166534;
        background: #f0fdf4;
    }

    .member-login-alert-danger {
        border-color: rgba(180, 35, 24, 0.18);
        color: var(--login-danger);
        background: #fff5f4;
    }

    .member-login-group {
        margin-bottom: 16px;
    }

    .member-login-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 7px;
    }

    .member-login-label {
        display: block;
        margin: 0 0 7px;
        color: var(--login-ink);
        font-size: 13px;
        font-weight: 800;
    }

    .member-login-label-row .member-login-label {
        margin-bottom: 0;
    }

    .member-login-forgot {
        color: var(--login-accent-dark);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .member-login-forgot:hover,
    .member-login-forgot:focus-visible {
        text-decoration: underline;
    }

    .member-login-input-wrap {
        position: relative;
    }

    .member-login-input {
        display: block;
        width: 100%;
        min-height: 52px;
        padding: 13px 14px;
        border: 1px solid var(--login-border);
        border-radius: 14px;
        outline: none;
        background: #ffffff;
        color: var(--login-ink);
        font: inherit;
        font-size: 16px;
        transition:
            border-color 0.18s ease,
            box-shadow 0.18s ease,
            background-color 0.18s ease;
    }

    .member-login-input::placeholder {
        color: #98a2b3;
    }

    .member-login-input:focus {
        border-color: rgba(154, 116, 35, 0.72);
        box-shadow: 0 0 0 4px var(--login-focus);
    }

    .member-login-input.is-invalid {
        border-color: rgba(180, 35, 24, 0.62);
    }

    .member-login-input.is-invalid:focus {
        box-shadow: 0 0 0 4px rgba(180, 35, 24, 0.10);
    }

    .member-login-password-input {
        padding-right: 52px;
    }

    .member-login-password-toggle {
        position: absolute;
        top: 50%;
        right: 7px;
        width: 40px;
        height: 40px;
        transform: translateY(-50%);
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: var(--login-muted);
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
    }

    .member-login-password-toggle:hover,
    .member-login-password-toggle:focus-visible {
        color: var(--login-ink);
        background: #f3f4f6;
        outline: none;
    }

    .member-login-help,
    .member-login-error {
        display: block;
        margin-top: 6px;
        font-size: 11.5px;
        line-height: 1.4;
    }

    .member-login-help {
        color: var(--login-muted);
    }

    .member-login-error {
        color: var(--login-danger);
        font-weight: 600;
    }

    .member-login-submit {
        width: 100%;
        min-height: 52px;
        margin-top: 2px;
        border: 0;
        border-radius: 14px;
        color: #ffffff;
        background:
            radial-gradient(circle at top right, rgba(184, 138, 43, 0.42), transparent 35%),
            linear-gradient(135deg, #303947, #111827);
        box-shadow: 0 14px 30px rgba(17, 24, 39, 0.22);
        cursor: pointer;
        font-size: 14px;
        font-weight: 900;
        transition:
            transform 0.16s ease,
            box-shadow 0.16s ease,
            opacity 0.16s ease;
    }

    .member-login-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 36px rgba(17, 24, 39, 0.28);
    }

    .member-login-submit:focus-visible {
        outline: 3px solid rgba(154, 116, 35, 0.30);
        outline-offset: 3px;
    }

    .member-login-submit:disabled,
    .member-login-submit.is-processing {
        opacity: 0.72;
        cursor: wait;
        transform: none;
        box-shadow: none;
    }

    .member-login-submit-content {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
    }

    .member-login-spinner {
        display: none;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.42);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: member-login-spin 0.75s linear infinite;
    }

    .member-login-submit.is-processing .member-login-spinner {
        display: inline-block;
    }

    @keyframes member-login-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .member-login-legal {
        margin: 11px 2px 0;
        color: var(--login-muted);
        font-size: 10.5px;
        line-height: 1.55;
        text-align: center;
    }

    .member-login-legal a {
        color: var(--login-accent-dark);
        font-weight: 700;
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-underline-offset: 2px;
    }

    .member-login-security {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-top: 15px;
        padding: 11px 12px;
        border: 1px solid var(--login-border);
        border-radius: 14px;
        color: var(--login-muted);
        background: #fafafa;
        font-size: 11.5px;
        line-height: 1.45;
    }

    .member-login-security-dot {
        width: 8px;
        height: 8px;
        min-width: 8px;
        margin-top: 4px;
        border-radius: 50%;
        background: var(--login-success);
        box-shadow: 0 0 0 3px rgba(35, 134, 54, 0.11);
    }

    .member-login-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 6px 11px;
        margin-top: 16px;
        color: var(--login-muted);
        font-size: 12px;
        line-height: 1.45;
        text-align: center;
    }

    .member-login-actions a {
        color: var(--login-accent-dark);
        font-weight: 800;
        text-decoration: none;
    }

    .member-login-actions a:hover,
    .member-login-actions a:focus-visible {
        text-decoration: underline;
    }

    .member-login-divider {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: #c4c8cf;
    }

    .member-login-credit {
        margin-top: 13px;
        padding-top: 12px;
        border-top: 1px solid var(--login-border);
        color: var(--login-muted);
        font-size: 10.5px;
        line-height: 1.45;
        text-align: center;
    }

    .member-login-credit a {
        color: var(--login-ink);
        font-weight: 800;
        text-decoration: none;
    }

    .member-login-recaptcha-note {
        display: none;
        margin-top: 11px;
        color: var(--login-danger);
        font-size: 11.5px;
        line-height: 1.45;
        text-align: center;
    }

    @media (min-width: 576px) {
        .member-login-page {
            align-items: center;
            padding: 28px;
        }

        .member-login-form-panel {
            padding: 32px;
        }
    }

    @media (min-width: 992px) {
        .member-login-shell {
            display: grid;
            grid-template-columns: minmax(0, 0.88fr) minmax(0, 1.12fr);
            max-width: 1020px;
            min-height: 610px;
            overflow: hidden;
            border: 1px solid rgba(229, 231, 235, 0.90);
            border-radius: 30px;
            background: #ffffff;
            box-shadow: var(--login-shadow);
        }

        .member-login-brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 44px;
            overflow: hidden;
            border-right: 1px solid var(--login-border);
            background:
                radial-gradient(circle at top right, rgba(154, 116, 35, 0.18), transparent 34%),
                linear-gradient(145deg, #ffffff 0%, #f5f1e7 100%);
        }

        .member-login-brand-panel::before,
        .member-login-brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .member-login-brand-panel::before {
            width: 280px;
            height: 280px;
            top: -150px;
            right: -110px;
            background: rgba(154, 116, 35, 0.08);
        }

        .member-login-brand-panel::after {
            width: 210px;
            height: 210px;
            left: -100px;
            bottom: -110px;
            border: 1px solid rgba(154, 116, 35, 0.18);
        }

        .member-login-brand-content,
        .member-login-brand-footer {
            position: relative;
            z-index: 1;
        }

        .member-login-brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            padding: 8px 11px;
            border: 1px solid var(--login-border);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            color: var(--login-accent-dark);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.10em;
            text-transform: uppercase;
        }

        .member-login-brand-badge::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--login-success);
        }

        .member-login-brand-title {
            margin: 0;
            color: var(--login-ink);
            font-size: 39px;
            font-weight: 900;
            line-height: 1.06;
            letter-spacing: -0.05em;
        }

        .member-login-brand-title span {
            display: block;
            margin-top: 7px;
            color: var(--login-accent-dark);
            overflow-wrap: anywhere;
        }

        .member-login-brand-text {
            max-width: 390px;
            margin: 16px 0 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.65;
        }

        .member-login-brand-points {
            display: grid;
            gap: 11px;
            margin-top: 28px;
        }

        .member-login-brand-point {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
        }

        .member-login-brand-check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            min-width: 24px;
            border-radius: 50%;
            color: var(--login-accent-dark);
            background: var(--login-accent-soft);
            font-size: 12px;
            font-weight: 900;
        }

        .member-login-brand-footer {
            color: var(--login-muted);
            font-size: 11.5px;
            line-height: 1.55;
        }

        .member-login-form-panel {
            display: flex;
            align-items: center;
            padding: 44px 48px;
            border: 0;
            border-radius: 0;
            background:
                radial-gradient(circle at top right, rgba(154, 116, 35, 0.06), transparent 32%),
                #ffffff;
            box-shadow: none;
        }

        .member-login-logo-box,
        .member-login-logo-fallback {
            width: 92px;
            height: 92px;
            border-radius: 24px;
        }

        .member-login-logo {
            max-width: 74px;
            max-height: 74px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<main class="member-login-page">
    <div class="member-login-shell">
        <aside class="member-login-brand-panel" aria-label="{{ $saccoName }} member portal">
            <div class="member-login-brand-content">
                <div class="member-login-brand-badge">Secure member access</div>

                <h1 class="member-login-brand-title">
                    Welcome to
                    <span>{{ $saccoName }}</span>
                </h1>

                <p class="member-login-brand-text">
                    Access your savings, loans and statements securely.
                </p>

                <div class="member-login-brand-points" aria-label="Portal services">
                    <div class="member-login-brand-point">
                        <span class="member-login-brand-check" aria-hidden="true">✓</span>
                        <span>Savings and deposits</span>
                    </div>

                    <div class="member-login-brand-point">
                        <span class="member-login-brand-check" aria-hidden="true">✓</span>
                        <span>Loans and statements</span>
                    </div>

                    <div class="member-login-brand-point">
                        <span class="member-login-brand-check" aria-hidden="true">✓</span>
                        <span>Secure account access</span>
                    </div>
                </div>
            </div>

            <div class="member-login-brand-footer">
                Authorised members only.
            </div>
        </aside>

        <section class="member-login-form-panel" aria-labelledby="memberLoginTitle">
            <div class="member-login-card">
                <div class="member-login-logo-wrap">
                    @if($showLogo)
                        <div class="member-login-logo-box" id="memberLoginLogoBox">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ $saccoName }} logo"
                                class="member-login-logo"
                                id="memberLoginLogo"
                                loading="eager"
                                decoding="async"
                            >
                        </div>

                        <div
                            class="member-login-logo-fallback"
                            id="memberLoginLogoFallback"
                            style="display:none;"
                            aria-hidden="true"
                        >
                            {{ $initials }}
                        </div>
                    @else
                        <div class="member-login-logo-fallback" aria-hidden="true">
                            {{ $initials }}
                        </div>
                    @endif
                </div>

                <header class="member-login-header">
                    <div class="member-login-eyebrow">Member Portal</div>
                    <h2 class="member-login-title" id="memberLoginTitle">{{ $saccoName }}</h2>
                    <p class="member-login-subtitle">Sign in to your account.</p>
                </header>

                @if(session('status'))
                    <div class="member-login-alert member-login-alert-success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="member-login-alert member-login-alert-danger" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="memberLoginForm">
                    @csrf

                    <div class="member-login-group">
                        <label class="member-login-label" for="login">Login ID</label>

                        <div class="member-login-input-wrap">
                            <input
                                type="text"
                                name="login"
                                id="login"
                                class="member-login-input @error('login') is-invalid @enderror"
                                value="{{ old('login') }}"
                                placeholder="Email, phone, ID or SACCO number"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                inputmode="text"
                                aria-describedby="loginHelp @error('login') loginError @enderror"
                                aria-invalid="@error('login') true @else false @enderror"
                                required
                                autofocus
                            >
                        </div>

                        <small class="member-login-help" id="loginHelp">
                            Use your registered member details.
                        </small>

                        @error('login')
                            <small class="member-login-error" id="loginError" role="alert">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    <div class="member-login-group">
                        <div class="member-login-label-row">
                            <label class="member-login-label" for="password">Password</label>

                            @if($forgotPasswordUrl)
                                <a href="{{ $forgotPasswordUrl }}" class="member-login-forgot">
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <div class="member-login-input-wrap">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="member-login-input member-login-password-input @error('password') is-invalid @enderror"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                aria-describedby="@error('password') passwordError @enderror"
                                aria-invalid="@error('password') true @else false @enderror"
                                required
                            >

                            <button
                                type="button"
                                class="member-login-password-toggle"
                                id="memberLoginPasswordToggle"
                                aria-controls="password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>
                        </div>

                        @error('password')
                            <small class="member-login-error" id="passwordError" role="alert">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">

                    <button type="submit" class="member-login-submit" id="memberLoginButton">
                        <span class="member-login-submit-content">
                            <span class="member-login-spinner" aria-hidden="true"></span>
                            <span class="member-login-button-text">Sign In</span>
                        </span>
                    </button>

                    <p class="member-login-legal">
                        By signing in, you agree to the
                        <a href="../terms_privacy.html" target="_blank" rel="noopener">Terms of Use</a>
                        and acknowledge the
                        <a href="../terms_privacy.html" target="_blank" rel="noopener">Privacy Policy</a>
                        and
                        <a href="../terms_privacy.html" target="_blank" rel="noopener">Data Protection Notice</a>.
                    </p>

                    <div
                        class="member-login-recaptcha-note"
                        id="memberLoginRecaptchaNote"
                        role="alert"
                    >
                        Security verification is unavailable. Refresh the page and try again.
                    </div>
                </form>

                <div class="member-login-security">
                    <span class="member-login-security-dot" aria-hidden="true"></span>
                    <span>Never share your password, PIN or OTP.</span>
                </div>

                <div class="member-login-actions">
                    <span>Not a member?</span>
                    <a href="{{ $registrationUrl }}">Apply now</a>

                    @if($supportPhone !== '')
                        <span class="member-login-divider" aria-hidden="true"></span>
                        <a href="tel:{{ $supportDialNumber }}">Get help</a>
                    @endif
                </div>

                <footer class="member-login-credit">
                    <strong>iSacco</strong> by
                    <a href="https://shahi.co.ke" target="_blank" rel="noopener noreferrer">
                        Shahi Services
                    </a>
                </footer>
            </div>
        </section>
    </div>
</main>

@if($recaptchaSiteKey !== '')
    <script
        src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}"
        async
        defer
    ></script>
@endif

<script>
(function () {
    'use strict';

    const siteKey = @json($recaptchaSiteKey);
    const form = document.getElementById('memberLoginForm');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('memberLoginPasswordToggle');
    const tokenInput = document.getElementById('recaptcha_token');
    const submitButton = document.getElementById('memberLoginButton');
    const submitText = submitButton
        ? submitButton.querySelector('.member-login-button-text')
        : null;
    const recaptchaNote = document.getElementById('memberLoginRecaptchaNote');
    const logo = document.getElementById('memberLoginLogo');
    const logoBox = document.getElementById('memberLoginLogoBox');
    const logoFallback = document.getElementById('memberLoginLogoFallback');

    if (logo && logoBox && logoFallback) {
        logo.addEventListener('error', function () {
            logoBox.style.display = 'none';
            logoFallback.style.display = 'flex';
        });
    }

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', function () {
            const shouldShow = passwordInput.type === 'password';

            passwordInput.type = shouldShow ? 'text' : 'password';
            passwordToggle.textContent = shouldShow ? 'Hide' : 'Show';
            passwordToggle.setAttribute(
                'aria-label',
                shouldShow ? 'Hide password' : 'Show password'
            );
            passwordToggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    }

    if (!form || !submitButton) {
        return;
    }

    let submitting = false;

    function setSubmittingState(isSubmitting) {
        submitting = isSubmitting;
        submitButton.disabled = isSubmitting;
        submitButton.classList.toggle('is-processing', isSubmitting);

        if (submitText) {
            submitText.textContent = isSubmitting ? 'Signing in...' : 'Sign In';
        }
    }

    function clearRecaptchaToken() {
        if (tokenInput) {
            tokenInput.value = '';
        }

        if (recaptchaNote) {
            recaptchaNote.style.display = 'none';
        }
    }

    function showRecaptchaError() {
        if (recaptchaNote) {
            recaptchaNote.style.display = 'block';
        }
    }

    if (loginInput) {
        loginInput.addEventListener('input', clearRecaptchaToken);
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', clearRecaptchaToken);
    }

    form.addEventListener('submit', function (event) {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        setSubmittingState(true);

        /*
         * When reCAPTCHA is not configured, submit normally.
         */
        if (!siteKey) {
            return;
        }

        event.preventDefault();

        if (typeof window.grecaptcha === 'undefined') {
            setSubmittingState(false);
            showRecaptchaError();
            return;
        }

        window.grecaptcha.ready(function () {
            window.grecaptcha
                .execute(siteKey, { action: 'login' })
                .then(function (token) {
                    if (!token) {
                        throw new Error('Empty reCAPTCHA token.');
                    }

                    if (tokenInput) {
                        tokenInput.value = token;
                    }

                    HTMLFormElement.prototype.submit.call(form);
                })
                .catch(function () {
                    setSubmittingState(false);
                    clearRecaptchaToken();
                    showRecaptchaError();
                });
        });
    });
})();
</script>
@endsection
