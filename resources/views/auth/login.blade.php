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
    body {
        background:
            radial-gradient(circle at top left, rgba(100, 58, 40, 0.12), transparent 35%),
            linear-gradient(135deg, #f8f4f1 0%, #f3ede8 45%, #ffffff 100%);
        min-height: 100vh;
    }

    .bank-login-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 34px 18px;
    }

    .bank-login-shell {
        width: 100%;
        max-width: 1080px;
        min-height: 620px;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        background: #ffffff;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(45, 27, 20, 0.18);
        border: 1px solid rgba(100, 58, 40, 0.08);
    }

    .bank-login-brand-panel {
        position: relative;
        padding: 42px;
        background:
            linear-gradient(135deg, rgba(100, 58, 40, 0.98), rgba(79, 46, 32, 0.99)),
            radial-gradient(circle at top right, rgba(255,255,255,0.22), transparent 38%);
        color: #ffffff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }

    .bank-login-brand-panel::before {
        content: "";
        position: absolute;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        top: -120px;
        right: -110px;
    }

    .bank-login-brand-panel::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        border: 1px solid rgba(255,255,255,0.14);
        bottom: -100px;
        left: -80px;
    }

    .bank-brand-inner {
        position: relative;
        z-index: 2;
    }

    .bank-mini-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 999px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.18);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 22px;
    }

    .bank-brand-title {
        font-size: 38px;
        line-height: 1.08;
        font-weight: 900;
        margin-bottom: 14px;
        letter-spacing: -0.03em;
    }

    .bank-brand-text {
        font-size: 15px;
        line-height: 1.75;
        color: rgba(255,255,255,0.84);
        max-width: 430px;
        margin-bottom: 28px;
    }

    .bank-feature-list {
        display: grid;
        gap: 12px;
        margin-top: 22px;
    }

    .bank-feature-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 13px 14px;
        border-radius: 16px;
        background: rgba(255,255,255,0.10);
        border: 1px solid rgba(255,255,255,0.14);
        backdrop-filter: blur(8px);
    }

    .bank-feature-icon {
        width: 28px;
        height: 28px;
        min-width: 28px;
        border-radius: 50%;
        background: rgba(255,255,255,0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 900;
    }

    .bank-feature-item strong {
        display: block;
        font-size: 13px;
        margin-bottom: 2px;
        color: #ffffff;
    }

    .bank-feature-item span {
        display: block;
        font-size: 12px;
        line-height: 1.45;
        color: rgba(255,255,255,0.78);
    }

    .bank-brand-footer {
        position: relative;
        z-index: 2;
        font-size: 12px;
        color: rgba(255,255,255,0.72);
        line-height: 1.6;
    }

    .bank-login-form-panel {
        padding: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(180deg, #ffffff 0%, #fcf8f5 100%);
    }

    .bank-login-card {
        width: 100%;
        max-width: 420px;
    }

    .bank-login-top {
        text-align: left;
        margin-bottom: 28px;
    }

    .bank-login-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(100, 58, 40, 0.08);
        color: #643A28;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 14px;
    }

    .sacco-brand {
        font-size: 28px;
        font-weight: 900;
        color: #241913;
        letter-spacing: -0.03em;
        line-height: 1.15;
        margin-bottom: 6px;
    }

    .bank-login-subtitle {
        font-size: 14px;
        color: #756760;
        line-height: 1.6;
        margin: 0;
    }

    .bank-form-group {
        margin-bottom: 16px;
    }

    .bank-form-group label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        color: #3d2b23;
        margin-bottom: 7px;
    }

    .bank-input-wrap {
        position: relative;
    }

    .bank-input-wrap .form-control {
        height: 50px;
        border-radius: 14px;
        border: 1px solid rgba(100, 58, 40, 0.16);
        background: #ffffff;
        color: #2d211c;
        font-size: 14px;
        padding: 12px 14px;
        box-shadow: 0 1px 0 rgba(0,0,0,0.02);
        transition: all .18s ease;
    }

    .bank-input-wrap .form-control:focus {
        border-color: rgba(100, 58, 40, 0.72);
        box-shadow: 0 0 0 4px rgba(100, 58, 40, 0.10);
    }

    .bank-submit-btn {
        width: 100%;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, #643A28, #4f2e20);
        border: none;
        color: #ffffff;
        font-weight: 900;
        font-size: 15px;
        box-shadow: 0 12px 28px rgba(100, 58, 40, 0.26);
        transition: transform .16s ease, box-shadow .16s ease;
    }

    .bank-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(100, 58, 40, 0.32);
        color: #ffffff;
    }

    .bank-apply-box {
        margin-top: 16px;
        padding: 14px;
        border-radius: 16px;
        background: rgba(100, 58, 40, 0.06);
        border: 1px solid rgba(100, 58, 40, 0.10);
        text-align: center;
        font-size: 13px;
        color: #675951;
    }

    .bank-apply-box a {
        display: inline-block;
        margin-top: 4px;
        color: #643A28;
        font-weight: 900;
        text-decoration: none;
    }

    .bank-apply-box a:hover {
        text-decoration: underline;
    }

    .bank-security-note {
        display: flex;
        gap: 10px;
        margin-top: 18px;
        padding: 12px 14px;
        border-radius: 16px;
        background: #faf6f3;
        border: 1px solid rgba(0,0,0,0.05);
        color: #756760;
        font-size: 12px;
        line-height: 1.55;
    }

    .bank-security-note strong {
        color: #3d2b23;
    }

    .security-dot {
        width: 9px;
        height: 9px;
        min-width: 9px;
        margin-top: 5px;
        border-radius: 50%;
        background: #2fb344;
        box-shadow: 0 0 0 4px rgba(47,179,68,0.12);
    }

    .login-footer {
        font-size: 0.86rem;
        text-align: center;
        margin-top: 24px;
        color: #675951;
    }

    .login-footer a {
        color: #643A28;
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
        color: #643A28;
        font-weight: 900;
    }

    .recaptcha-note {
        font-size: 0.85rem;
        color: #666;
        margin-top: 0.75rem;
        text-align: center;
    }

    @media (max-width: 900px) {
        .bank-login-shell {
            grid-template-columns: 1fr;
            max-width: 520px;
        }

        .bank-login-brand-panel {
            padding: 32px 26px;
            min-height: auto;
        }

        .bank-brand-title {
            font-size: 30px;
        }

        .bank-feature-list,
        .bank-brand-footer {
            display: none;
        }

        .bank-login-form-panel {
            padding: 30px 22px;
        }
    }

    @media (max-width: 480px) {
        .bank-login-page {
            padding: 0;
            align-items: stretch;
        }

        .bank-login-shell {
            border-radius: 0;
            min-height: 100vh;
            box-shadow: none;
        }

        .bank-login-brand-panel {
            padding: 28px 20px;
        }

        .bank-login-form-panel {
            padding: 28px 18px 36px;
        }

        .sacco-brand {
            font-size: 24px;
        }
    }
</style>

<div class="bank-login-page">
    <div class="bank-login-shell">
        <section class="bank-login-brand-panel">
            <div class="bank-brand-inner">
                <div class="bank-mini-label">Secure Member Access</div>

                <div class="bank-brand-title">
                    Welcome to<br>{{ $saccoName }}
                </div>

                <p class="bank-brand-text">
                    Access your SACCO member services securely, including savings, contributions,
                    loan information, statements and account updates.
                </p>

                <div class="bank-feature-list">
    <div class="bank-feature-item">
        <div class="bank-feature-icon">✓</div>
        <div>
            <strong>Member self-service</strong>
            <span>View key SACCO account services from one secure portal.</span>
        </div>
    </div>

    <div class="bank-feature-item">
        <div class="bank-feature-icon">✓</div>
        <div>
            <strong>Savings and loans access</strong>
            <span>Check contributions, loan records and member account information.</span>
        </div>
    </div>

    <div class="bank-feature-item">
        <div class="bank-feature-icon">✓</div>
        <div>
            <strong>Protected sign-in</strong>
            <span>Your login is protected by account validation and security checks.</span>
        </div>
    </div>
</div>
            </div>

            <div class="bank-brand-footer">
                Use this portal only if you are a registered member or applicant of {{ $saccoName }}.
            </div>
        </section>

        <section class="bank-login-form-panel">
            <div class="bank-login-card">
                <div class="bank-login-top">
                    <div class="bank-login-badge">Member Portal</div>
                    <div class="sacco-brand">{{ $saccoName }}</div>
                    <p class="bank-login-subtitle">
                        Sign in using your registered email address or phone number.
                    </p>
                </div>

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="bank-form-group">
                        <label for="login">Email or Phone Number</label>
                        <div class="bank-input-wrap">
                            <input type="text" name="login" id="login" class="form-control" placeholder="Enter email or phone" value="{{ old('login') }}" required>
                        </div>
                        @if ($errors->has('login'))
                            <small class="text-danger">{{ $errors->first('login') }}</small>
                        @endif
                    </div>

                    <div class="bank-form-group">
                        <label for="password">Password</label>
                        <div class="bank-input-wrap">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                        </div>
                        @if ($errors->has('password'))
                            <small class="text-danger">{{ $errors->first('password') }}</small>
                        @endif
                    </div>

                    {{-- reCAPTCHA v3 token --}}
                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">

                    <div class="d-grid mb-3">
                        <button type="submit" class="bank-submit-btn" id="loginBtn">Login Securely</button>
                    </div>

                    <div class="bank-apply-box">
                        Not yet a member?
                        <br>
                        <a href="{{ url('/register') }}">Apply for SACCO membership</a>
                    </div>

                    <div class="recaptcha-note" id="recaptchaNote" style="display:none;">
                        reCAPTCHA is blocked or slow. If login fails, disable any ad-blocker for this site and try again.
                    </div>
                </form>

                <div class="bank-security-note">
                    <span class="security-dot"></span>
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