@extends('layouts.app')

@section('content')
<div class="row">

    <div class="col-md-8 mx-auto">
        <div class="card text-start">
            <div class="card-body">

                @include('transport._nav_links')

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title">Add New Stage</h4>
                </div>

                {{-- Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('stages.store') }}" method="POST">
                    @csrf

                    {{-- Stage Name --}}
                    <div class="mb-3">
                        <label class="form-label">Stage Name *</label>
                        <input type="text"
                               name="stage_name"
                               class="form-control"
                               required
                               placeholder="e.g. Githurai 45 Main Stage"
                               value="{{ old('stage_name') }}">
                    </div>

                    {{-- Chair Name --}}
                    <div class="mb-3">
                        <label class="form-label">Stage Chair Name *</label>
                        <input type="text"
                               name="chair_name"
                               class="form-control"
                               required
                               placeholder="Chairperson Name"
                               value="{{ old('chair_name') }}">
                    </div>

                    {{-- Chair Phone --}}
                    <div class="mb-3">
                        <label class="form-label">Stage Chair Phone *</label>
                        <input type="text"
                               name="chair_phone"
                               class="form-control"
                               required
                               placeholder="07XXXXXXXX"
                               value="{{ old('chair_phone') }}">
                    </div>

                    <button class="btn btn-primary mt-2">Save Stage</button>

                </form>

            </div>
        </div>
    </div>

</div>
@endsection
