@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">FOSA Deposit Types</h3>

        <div class="text-end w-50 float-end">
          <a href="{{ route('fosa.create') }}" class="btn btn-primary shadow-sm">
            Add New
          </a>
        </div>
      </div>

      @include('partials.alerts')

      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Type Name</th>
                <th>Prefix</th>
                <th>Default</th>
                <th>Expected</th>
                <th>Status</th>
                <th style="min-width: 180px;">Action</th>
              </tr>
            </thead>

            <tbody>
              @foreach($records as $i => $rec)
              <tr>
                <td>{{ $i + 1 }}</td>

                <td class="text-start">
                  <div class="fw-semibold">{{ $rec->type_name }}</div>
                </td>

                <td>
                  <span class="badge bg-secondary">{{ $rec->type_prefix }}</span>
                </td>

                <td>
                  @if($rec->type_default == 'Y')
                    <span class="badge bg-info">Default</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>

                <td>
                  @php
                    $ea = $rec->expected_amount ?? null;
                    $ep = $rec->expected_period ?? null;
                  @endphp

                  @if(is_null($ea) && is_null($ep))
                    <span class="text-muted">—</span>
                  @else
                    <span class="badge bg-light text-dark">
                      {{ number_format((float)$ea, 2) }}
                      @if($ep)
                        / {{ strtoupper($ep) }}
                      @endif
                    </span>
                  @endif
                </td>

                <td>
                  @if($rec->type_active == 'Y')
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-danger">Inactive</span>
                  @endif
                </td>

                <td>
                  <div class="d-flex justify-content-center gap-2">

                    <a href="{{ route('fosa.edit', ['id' => $rec->type_id]) }}"
                       class="btn btn-sm btn-outline-primary">
                      Edit
                    </a>

                    <form action="{{ route('fosa.toggle', ['id' => $rec->type_id]) }}" method="POST" class="m-0">
                      @csrf
                      @if($rec->type_active == 'Y')
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Deactivate this FOSA Type?');">
                          Deactivate
                        </button>
                      @else
                        <button type="submit" class="btn btn-sm btn-outline-success"
                                onclick="return confirm('Activate this FOSA Type?');">
                          Activate
                        </button>
                      @endif
                    </form>

                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>

          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection