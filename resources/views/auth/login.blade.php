@extends('layouts.app')

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
</style>

<div class="login-wrapper">
    <div class="login-card">
        <div class="sacco-brand">{{ $defaultCompanyName }}</div>
        <h5>Member Login</h5>

        <form method="POST" action="{{ route('login') }}">
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

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>

            <div class="text-center mb-3">
                <a href="{{ url('/register') }}" class="text-decoration-none">Click here to apply for membership</a>
            </div>


            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

<script src="https://www.google.com/recaptcha/api.js?render={{ env('RECAPTCHA_SITE_KEY') }}"></script>
<script>
grecaptcha.ready(function() {
    grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'login'})
        .then(function(token) {
            document.getElementById('recaptcha_token').value = token;
        });
});
</script>


        </form>

        <div class="login-footer">
            <div><strong>Need Help?</strong><br>
                Call or WhatsApp: <a href="tel:{{ env('SACCO_SUPPORT') }}">{{ env('SACCO_SUPPORT') }}</a>
            </div>
            <div class="mt-2">
                ERP provided by: <a href="https://shahi.co.ke" target="_blank">shahi.co.ke</a>
            </div>
        </div>
    </div>
</div>
@endsection