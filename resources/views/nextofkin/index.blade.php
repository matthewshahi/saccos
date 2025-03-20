@extends('layouts.app')

@section('content')
<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Next of Kin</h3>
        <div class="text-end w-50 float-end">
            <a href="{{ route('nextofkin.add') }}" class="btn btn-primary">Add Next of Kin</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>National ID</th>
                        <th>Percent</th>
                        <th>Relationship</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nextOfKin as $kin)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $kin->kin_names }}</td>
                        <td>{{ $kin->kin_address }}</td>
                        <td>{{ $kin->kin_national_id }}</td>
                        <td>{{ $kin->kin_percent }}%</td>
                        <td>{{ $kin->kin_relationship }}</td>
                        <td>
                            <a href="{{ route('nextofkin.edit', $kin->kin_id) }}" class="text-success me-2"><i class="nav-icon i-Pen-2 fw-bold"></i></a>
                            <a href="#" class="text-danger me-2"><i class="nav-icon i-Close-Window fw-bold"></i></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection