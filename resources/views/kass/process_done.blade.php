@extends('layouts.app')

@section('content')
<div class="container py-5 text-center">

    <h3>{{ $message }}</h3>

    <p class="mt-3">
        Remaining: <strong>{{ $remaining }}</strong>
    </p>

    <a href="{{ route('kass.index') }}" class="btn btn-primary mt-4">
        Back to Uploads
    </a>

</div>
@endsection
