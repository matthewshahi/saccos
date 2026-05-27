@extends('layouts.app')

@php
    $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
@endphp

@section('seo_title', $saccoName . ' Member Login | SACCO Member Portal')

@section('seo_description', $saccoName . ' member login portal for accessing SACCO savings, loans, statements, contributions and member services.')

@section('robots', 'noindex, follow')
@section('content')
<style>
    body {
        background: linear-gradient(to right, #f7f7fc, #f3f3f9);
    }

    .login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .login-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        padding: 2.5rem 2rem;
        width: 100%;
        max-width: 420px;
    }

    .sacco-brand {
        font-size: 2rem;
        font-weight: 700;
        color: #5a189a;
        text-align: center;
        margin-bottom: 0.5rem;
    }

    .login-card h5 {
        font-weight: 500;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .btn-primary {
        background-color: #5a189a;
        border-color: #5a189a;
    }

    .btn-primary:hover {
        background-color: #4c1380;
        border-color: #4c1380;
    }

    .form-control:focus {
        border-color: #5a189a;
        box-shadow: 0 0 0 0.15rem rgba(90, 24, 154, 0.25);
    }

    .login-footer {
        font-size: 0.9rem;
        text-align: center;
        margin-top: 2rem;
        color: #555;
    }

    .login-footer a {
        color: #5a189a;
        text-decoration: none;
    }

    .login-footer a:hover {
        text-decoration: underline;
    }

    .recaptcha-note {
        font-size: 0.85rem;
        color: #666;
        margin-top: 0.75rem;
        text-align: center;
    }
</style>

<div class="login-wrapper">
    <div class="login-card">
        <div class="sacco-brand">{{ $defaultCompanyName }}</div>
        <h5>Member Login</h5>

        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf

            <div class="form-group mb-3">
                <label for="login">Email or Phone Number</label>
                <input type="text" name="login" id="login" class="form-control" placeholder="Enter email or phone" value="{{ old('login') }}" required>
                @if ($errors->has('login'))
                    <small class="text-danger">{{ $errors->first('login') }}</small>
                @endif
            </div>

            <div class="form-group mb-3">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                @if ($errors->has('password'))
                    <small class="text-danger">{{ $errors->first('password') }}</small>
                @endif
            </div>

            {{-- reCAPTCHA v3 token --}}
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-block" id="loginBtn">Login</button>
            </div>

            <div class="text-center mb-3">
                <a href="{{ url('/register') }}" class="text-decoration-none">Click here to apply for membership</a>
            </div>

            <div class="recaptcha-note" id="recaptchaNote" style="display:none;">
                reCAPTCHA is blocked or slow. If login fails, disable any ad-blocker for this site and try again.
            </div>
        </form>

        <div class="login-footer">
    @if(!empty(env('SACCO_SUPPORT')))
        <div>
            <strong>Need Help?</strong><br>
            Call or WhatsApp:
            <a href="tel:{{ preg_replace('/\s+/', '', env('SACCO_SUPPORT')) }}">
                {{ env('SACCO_SUPPORT') }}
            </a>
        </div>
    @endif

    <div class="mt-2">
        <span>iSacco system by</span>
        <a href="https://shahi.co.ke" target="_blank" rel="noopener">
            Shahi Services
        </a>
    </div>

    <div style="font-size: 0.82rem; margin-top: 2px;">
        <a href="https://shahi.co.ke" target="_blank" rel="noopener">
            shahi.co.ke
        </a>
    </div>
</div>
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
