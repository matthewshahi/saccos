@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Types</h1>
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

<div class="row">
    <div class="col-md-12">
        <a href="{{ url('/loans/types/add') }}" class="btn btn-primary mb-3">Add New Loan Type</a>

        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">All Loan Types</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" type="button" id="dropdownMenuButton1" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                        <a class="dropdown-item" href="{{ url('/loans/types/add') }}">Add New Loan Type</a>
                        <a class="dropdown-item" href="{{ url('/loans/types') }}">Refresh</a>
                    </div>
                </div>
            </div>

            <div>
                <div class="table-responsive">
                   <table class="table table-striped text-center">
    <thead>
        <tr>
            <th class="text-left">Name</th>
            <th class="text-right">Interest</th>
            <th class="text-left">Type</th>
            <th class="text-right">Duration</th>
            <th class="text-right">Guarantee</th>
            <th class="text-left">Code</th>
            <th class="text-right">Max Amount</th>
            <th class="text-right">Qualification</th>
            <th class="text-left">Loan Account</th>
            <th class="text-left">Interest Account</th>
            <th class="text-left">Commission Account</th>
            <th class="text-center">Insurable</th>
            <th class="text-center">Instant</th>
            <th class="text-center">Action</th>
        </tr>
    </thead>

    <tbody>
        @foreach($loanTypes as $loanType)
        <tr>

            {{-- TEXT LEFT --}}
            <td class="text-left">{{ $loanType->loan_type_name }}</td>

            {{-- NUMBERS RIGHT --}}
            <td class="text-right">{{ number_format($loanType->loan_type_interest, 2) }}%</td>

            {{-- TEXT LEFT --}}
            <td class="text-left">{{ $loanType->loan_type_interest_type }}</td>

            {{-- NUMBERS RIGHT --}}
            <td class="text-right">{{ $loanType->loan_type_duration }}</td>

            <td class="text-right">{{ $loanType->loan_type_guaranteable_percent }}%</td>

            {{-- TEXT LEFT --}}
            <td class="text-left">{{ $loanType->loan_type_code }}</td>

            {{-- MONEY RIGHT --}}
            <td class="text-right">{{ number_format($loanType->loan_type_max_amount, 2) }}</td>

            {{-- NUMBERS RIGHT --}}
            <td class="text-right">{{ $loanType->loan_type_qualification_period }} months</td>

            {{-- LEFT ALIGNED ACCOUNTS --}}
            <td class="text-left">
                @php $acc = $subAccountDetails[$loanType->loan_type_acount] ?? null; @endphp
                @if($acc)
                    {{ $acc->sub_account_name }}
                    ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                @else
                    <span class="text-muted">None</span>
                @endif
            </td>

            <td class="text-left">
                @php $iac = $subAccountDetails[$loanType->loan_type_int_account] ?? null; @endphp
                @if($iac)
                    {{ $iac->sub_account_name }}
                    ({{ $iac->main_account_code }}/{{ $iac->sub_account_code }})
                @else
                    <span class="text-muted">None</span>
                @endif
            </td>

            <td class="text-left">
                @php $cac = $subAccountDetails[$loanType->loan_type_comm_account] ?? null; @endphp
                @if($cac)
                    {{ $cac->sub_account_name }}
                    ({{ $cac->main_account_code }}/{{ $cac->sub_account_code }})
                @else
                    <span class="text-muted">None</span>
                @endif
            </td>

            {{-- BADGE CENTER --}}
            <td class="text-center">
                <span class="badge {{ $loanType->loan_type_insurable == 'Y' ? 'text-bg-success' : 'text-bg-danger' }}">
                    {{ $loanType->loan_type_insurable == 'Y' ? 'Yes' : 'No' }}
                </span>
            </td>

            {{-- INSTANT BADGE CENTER --}}
            <td class="text-center">
                @if($loanType->loan_type_instant_qualification == 1)
                    <span class="badge text-bg-primary">Instant</span>
                @else
                    <span class="badge text-bg-secondary">Normal</span>
                @endif
            </td>

            {{-- ACTION ICONS CENTER --}}
            <td class="text-center">
                <a href="{{ url('/loans/types/edit/' . $loanType->loan_type_id) }}"
                    class="text-success me-2" title="Edit">
                    <i class="nav-icon i-Pen-2 font-weight-bold"></i>
                </a>

                <form action="{{ url('/loans/types/delete/' . $loanType->loan_type_id) }}"
                    method="POST" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-danger border-0 bg-transparent" title="Delete"
                        onclick="return confirm('Delete this loan type?');">
                        <i class="nav-icon i-Close-Window font-weight-bold"></i>
                    </button>
                </form>
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
