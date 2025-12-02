@extends('layouts.app')

@section('content')
<div class="row">

    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-body">

                @include('transport._nav_links')

                <h4 class="card-title mb-3">Edit Stage Chair</h4>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('chairs.update', $chair->id) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Chair Name *</label>
                        <input type="text" name="chair_name"
                               class="form-control"
                               value="{{ old('chair_name', $chair->chair_name) }}"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chair Phone (Optional)</label>
                        <input type="text" name="chair_phone"
                               class="form-control"
                               value="{{ old('chair_phone', $chair->chair_phone) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Stage *</label>
                        <select name="chair_stage_id" class="form-control" required>
                            @foreach($stages as $s)
                                <option value="{{ $s->id }}"
                                    {{ $chair->chair_stage_id == $s->id ? 'selected':'' }}>
                                    {{ $s->stage_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="btn btn-primary mt-2">Update Chair</button>
                </form>

            </div>
        </div>
    </div>

</div>
@endsection
