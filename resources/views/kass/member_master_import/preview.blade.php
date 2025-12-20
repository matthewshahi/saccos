@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h4>IMPORT PREVIEW ({{ count($rows) }} ROWS)</h4>

    <form method="POST" action="{{ route('kass.member_master_import.confirm') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button class="btn btn-success mb-2">CONFIRM IMPORT</button>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>NAME</th>
                    <th>MEMBERSHIP</th>
                    <th>ID</th>
                    <th>DOB</th>
                    <th>EMPLOYER</th>
                    <th>PHONE</th>
                    <th>EMAIL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i=>$r)
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $r['NAME'] }}</td>
                    <td>{{ $r['MEMBERSHIP NO'] }}</td>
                    <td>{{ $r['ID NUMBER'] }}</td>
                    <td>{{ $r['DATE OF BIRTH'] }}</td>
                    <td>{{ $r['EMPLOYER'] }}</td>
                    <td>{{ $r['PHONE NO'] }}</td>
                    <td>{{ $r['EMAIL'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
