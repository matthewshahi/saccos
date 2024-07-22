@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Change Guarantors for Loan ID: {{ $loan->loan_id }}</h1>
        <div class="header-part-right">
        <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif

              
              <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
            
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-4">
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form action="{{ route('updateGuarantors', ['member_id' => $member_id, 'guarantor_id' => $guarantor_id]) }}" method="POST">
                        @csrf
                        <div class="table-responsive">
                            <table class="display table table-striped table-bordered" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>Existing Guarantors</th>
                                        <th>Sacco ID</th>
                                        <th>Amount Guaranteed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($existingGuarantors as $guarantor)
                                        <tr>
                                            <td>{{ $guarantor->member_name }}</td>
                                            <td>{{ $guarantor->member_sacco_id }}</td>
                                            <td>{{ number_format($guarantor->loan_guar_amount_guaranteed, 2) }}</td>
                                            <td>
                                                <form action="{{ route('deleteGuarantor', ['member_id' => $member_id, 'guarantor_id' => $guarantor->loan_guar_id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this guarantor?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <h4>Add New Guarantor</h4>
                        <div class="form-group">
                            <label for="new_guarantor">Select New Guarantor:</label>
                            <select name="new_guarantor" id="new_guarantor" class="form-control">
                                @foreach($potentialGuarantors as $potentialGuarantor)
                                    <option value="{{ $potentialGuarantor->member_id }}">{{ $potentialGuarantor->member_name }} ({{ $potentialGuarantor->member_sacco_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="guaranteed_amount">Amount Guaranteed:</label>
                            <input type="number" name="guaranteed_amount" id="guaranteed_amount" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Guarantor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
