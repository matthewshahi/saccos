@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-6">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Edit FOSA Type</h3>

        <div class="text-end w-50 float-end">
          <a href="{{ route('fosa.index') }}" class="btn btn-primary shadow-sm">
            Back to List
          </a>
        </div>
      </div>

      @include('partials.alerts')

      <div class="card-body">
        @php
          $isRF = (strtoupper($rec->type_prefix) === 'RF');
        @endphp

        <form action="{{ route('fosa.update', ['id' => $rec->type_id]) }}" method="POST">
          @csrf

          <!-- Type Name -->
          <div class="form-group mb-3">
            <label for="type_name">Type Name</label>
            <input type="text"
                   name="type_name"
                   id="type_name"
                   value="{{ old('type_name', $rec->type_name) }}"
                   class="form-control @error('type_name') is-invalid @enderror"
                   placeholder="e.g. Welfare"
                   {{ $isRF ? 'disabled' : '' }}
                   required>
            @error('type_name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            @if($isRF)
              <small class="text-muted">Registration Fee is system-protected. Name cannot be edited.</small>
            @endif
          </div>

          <!-- Prefix (Disabled Always) -->
          <div class="form-group mb-3">
            <label for="type_prefix">Prefix (2 Letters)</label>

            <!-- show it but prevent edits -->
            <input type="text"
                   id="type_prefix"
                   value="{{ old('type_prefix', $rec->type_prefix) }}"
                   class="form-control text-uppercase"
                   disabled>

            <!-- keep value posted (because disabled fields are not submitted) -->
            <input type="hidden" name="type_prefix" value="{{ old('type_prefix', $rec->type_prefix) }}">

            <small class="text-muted">
              Prefix is locked and cannot be edited after creation.
            </small>

            @error('type_prefix')
              <div class="text-danger mt-1">{{ $message }}</div>
            @enderror
          </div>

          <hr class="my-3">

          <!-- Expected Contribution (Optional) -->
          <div class="form-group mb-3">
            <label for="expected_amount">Expected Amount (Optional)</label>
            <input type="number"
                   name="expected_amount"
                   id="expected_amount"
                   value="{{ old('expected_amount', $rec->expected_amount) }}"
                   class="form-control @error('expected_amount') is-invalid @enderror"
                   step="0.01" min="0"
                   placeholder="e.g. 100">
            <small class="text-muted">
              Leave blank if there is no fixed expected contribution.
            </small>
            @error('expected_amount')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-group mb-3">
            <label for="expected_period">Expected Period (Optional)</label>
            <select name="expected_period"
                    id="expected_period"
                    class="form-control @error('expected_period') is-invalid @enderror">
              <option value="">-- Select period (leave blank if none) --</option>
              <option value="daily"   {{ old('expected_period', $rec->expected_period)=='daily' ? 'selected' : '' }}>Daily</option>
              <option value="weekly"  {{ old('expected_period', $rec->expected_period)=='weekly' ? 'selected' : '' }}>Weekly</option>
              <option value="monthly" {{ old('expected_period', $rec->expected_period)=='monthly' ? 'selected' : '' }}>Monthly</option>
              <option value="yearly"  {{ old('expected_period', $rec->expected_period)=='yearly' ? 'selected' : '' }}>Yearly</option>
              <option value="one_time" {{ old('expected_period', $rec->expected_period ?? '')=='one_time' ? 'selected' : '' }}>
  One-time (once)
</option>
            </select>
            <small class="text-muted">
              If you set a period, also enter an amount (and vice versa).
            </small>
            @error('expected_period')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>

      </div>
    </div>
  </div>
</div>
@endsection