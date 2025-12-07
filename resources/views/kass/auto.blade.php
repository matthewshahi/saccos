@extends('layouts.app')

@section('content')
<div class="container py-5 text-center">

    <h3>{{ $message }}</h3>

    <p class="mt-3">Remaining files: <strong>{{ $remaining }}</strong></p>

    @if($next)
        <p class="text-muted mt-3">Next file will process in <span id="count">2</span> seconds...</p>

        <script>
            let c = 2;
            setInterval(function() {
                document.getElementById('count').innerText = c;
                c--;
            }, 1000);

            setTimeout(function() {
                window.location.href = "{{ route('kass.process.all') }}";
            }, 2000);
        </script>
    @else
        <a class="btn btn-primary mt-4" href="{{ route('kass.index') }}">Back Home</a>
    @endif

</div>
@endsection
