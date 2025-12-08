@extends('layouts.app')

@section('content')
<div class="container py-5 text-center">

    <h3>{{ $message }}</h3>

    <p class="mt-3">
        Remaining: <strong>{{ $remaining }}</strong>
    </p>

    <p class="text-muted mt-3">
        Next step in <span id="count">1</span> second...
    </p>

    <script>
        let c = 1;
        let interval = setInterval(() => {
            document.getElementById('count').innerText = c;
            c--;
            if (c < 0) clearInterval(interval);
        }, 1000);

        setTimeout(() => {
            window.location.href = "{{ route('kass.process.all') }}";
        }, 1000);
    </script>

</div>
@endsection
