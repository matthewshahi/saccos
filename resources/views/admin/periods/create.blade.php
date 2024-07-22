@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Add New Period</h1>
        <div class="header-part-right">
        <ul>
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
              
              <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
            
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.periods.store') }}">
        @csrf
        <fieldset class="border p-3 mb-4">
            <legend class="w-auto px-2">New Period</legend>
            <div class="form-group">
                <label for="period_name">Period (YYYYMM)</label>
                <input type="text" name="period_name" id="period_name" class="form-control" value="{{ old('period_name', date('Ym')) }}" maxlength="6">
            </div>
            <div class="form-group form-check">
                <input type="checkbox" name="period_active" id="period_active" class="form-check-input" value="Y">
                <label for="period_active" class="form-check-label">Set as active period</label>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </fieldset>
    </form>
@endsection
