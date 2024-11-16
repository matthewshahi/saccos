@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4>Member Login</h4>
            <div class="card mb-5">
                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="form-group row">
                            <label for="login" class="col-sm-3 col-form-label">Email/Phone</label>
                            <div class="col-sm-9">
                                <input type="text" name="login" id="login" class="form-control" placeholder="Email or Phone Number" value="{{ old('login') }}" required>
                                @if ($errors->has('login'))
                                    <span class="text-danger">{{ $errors->first('login') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="password" class="col-sm-3 col-form-label">Password</label>
                            <div class="col-sm-9">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                                @if ($errors->has('password'))
                                    <span class="text-danger">{{ $errors->first('password') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">Login</button>
                                <a href="{{ url('/register') }}" class="btn btn-link">Or click here to apply for membership</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection