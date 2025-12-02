@extends('layouts.app')

@section('content')
<div class="row">

    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-body">

                @include('transport._nav_links')

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title">Edit Stage</h4>
                    <a href="{{ route('stages.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="i-Arrow-Left"></i> Back
                    </a>
                </div>

                {{-- Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('stages.update', $stage->id) }}" method="POST">
                    @csrf

                    {{-- Stage Name --}}
                    <div class="mb-3">
                        <label class="form-label">Stage Name *</label>
                        <input type="text" name="stage_name" class="form-control"
                               required
                               value="{{ old('stage_name', $stage->stage_name) }}">
                    </div>

                    <hr class="my-3">

                    {{-- Linked Chair Section --}}
                    <h5 class="mb-2">Stage Chair</h5>

                    <div class="mb-3">
                        <label class="form-label">Chair Name</label>
                        <input type="text" name="chair_name" class="form-control"
                               placeholder="Chairperson Name"
                               value="{{ old('chair_name', $chair->chair_name ?? '') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chair Phone</label>
                        <input type="text" name="chair_phone" class="form-control"
                               placeholder="07XXXXXXXX"
                               value="{{ old('chair_phone', $chair->chair_phone ?? '') }}">
                    </div>

                    <button class="btn btn-primary mt-2">
                        <i class="i-Save"></i> Update Stage & Chair
                    </button>

                </form>

            </div>
        </div>
    </div>

</div>
@endsection
