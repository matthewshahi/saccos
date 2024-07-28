@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Edit Main Account</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod))
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card text-start">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('accounts.main.update', $mainAccount->main_account_id) }}" method="POST">
                    @csrf
                    @method('POST')

                    <div class="form-group row">
                        <label for="main_account_name" class="col-sm-2 col-form-label">Account Name*</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="main_account_name" name="main_account_name" value="{{ old('main_account_name', $mainAccount->main_account_name) }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="main_account_type" class="col-sm-2 col-form-label">Account Type*</label>
                        <div class="col-sm-10">
                            <select class="form-control" id="main_account_type" name="main_account_type" required>
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type }}" {{ old('main_account_type', $mainAccount->main_account_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-sm-10 offset-sm-2">
                            <button type="submit" class="btn btn-primary">Update Account</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
