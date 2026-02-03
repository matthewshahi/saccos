@extends('layouts.app')

@section('content')
    <div class="row">

        {{-- FILTER CARD --}}
        <div class="col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Trial Balance</h3>

                    <div class="dropdown dropleft text-end w-50 float-end">
                        <button class="btn bg-gray-100" id="dropdownMenuButton_tb" type="button" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="nav-icon i-Gear-2"></i>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb">
                            <a class="dropdown-item"
                                href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download
                                PDF</a>
                            <a class="dropdown-item"
                                href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download
                                Excel</a>
                        </div>
                    </div>
                </div>

                <div class="card-body">

                    @if (!empty($notices))
                        <div class="alert alert-warning">
                            <ul class="mb-0">
                                @foreach ($notices as $n)
                                    <li>{{ $n }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="GET" action="{{ route('reports.final_accounts.trial_balance') }}">
                        <div class="row g-3 align-items-end">

                            {{-- MODE --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold mb-1">Mode</label>
                                <select name="mode" class="form-control">
                                    <option value="period"
                                        {{ request('mode', $ctx['mode'] ?? 'period') == 'period' ? 'selected' : '' }}>
                                        As at Period (YYYYMM)
                                    </option>
                                    <option value="date"
                                        {{ request('mode', $ctx['mode'] ?? '') == 'date' ? 'selected' : '' }}>
                                        As at Date
                                    </option>
                                </select>
                                <small class="text-muted d-block mt-1">Period recommended for SACCO month-end.</small>
                            </div>

                            {{-- PERIOD --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold mb-1">As at Period</label>
                                <div class="input-group">
                                    <span class="input-group-text">YYYYMM</span>
                                    <input type="text" name="as_at_period"
                                        value="{{ request('as_at_period', $ctx['as_at_period'] ?? '') }}"
                                        class="form-control" inputmode="numeric" maxlength="6" pattern="\d{6}"
                                        placeholder="202602"
                                        oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)">
                                </div>
                                <small class="text-muted d-block mt-1">Max 6 digits. Used when mode=period.</small>
                            </div>

                            {{-- DATE --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold mb-1">As at Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="nav-icon i-Calendar-4"></i></span>
                                    <input type="date" name="as_at_date"
                                        value="{{ request('as_at_date', isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '') }}"
                                        class="form-control">
                                </div>
                                <small class="text-muted d-block mt-1">End-of-day applied (23:59:59).</small>
                            </div>

                            {{-- RUN --}}
                            <div class="col-12 col-md-3">
                                <button class="btn btn-primary w-100 py-3 fw-bold" type="submit"
                                    style="border-radius:12px;">
                                    <i class="nav-icon i-Search-People me-2"></i> Run Report
                                </button>
                                <small class="text-muted d-block mt-1 text-center">Generates totals + lines
                                    instantly.</small>
                            </div>

                        </div>
                    </form>

                    <hr class="my-4">


                    {{-- SUMMARY (Grand totals: standard TB) --}}
                    <div class="row">
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted">Total Debit</div>
                                <div class="fw-bold">{{ number_format($totals['debit'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted">Total Credit</div>
                                <div class="fw-bold">{{ number_format($totals['credit'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted">Diff (Dr - Cr)</div>
                                <div class="fw-bold {{ ($totals['diff'] ?? 0) == 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($totals['diff'] ?? 0, 2) }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted">Cutoff</div>
                                <div class="fw-bold">
                                    @if (($ctx['mode'] ?? '') === 'period')
                                        {{ $ctx['as_at_period'] ?? '' }}
                                    @else
                                        {{ isset($ctx['as_at_date']) ? $ctx['as_at_date']->format('Y-m-d') : '' }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- TB TABLE CARD --}}
        <div class="col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Trial Balance Lines</h3>
                    <div class="dropdown dropleft text-end w-50 float-end">
                        <button class="btn bg-gray-100" id="dropdownMenuButton_tb2" type="button" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="nav-icon i-Gear-2"></i>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb2">
                            <a class="dropdown-item"
                                href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download
                                PDF</a>
                            <a class="dropdown-item"
                                href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download
                                Excel</a>
                        </div>
                    </div>
                </div>

                @php
                    $groupOf = function ($t) {
                        $t = strtoupper(trim((string) $t));
                        if (str_starts_with($t, 'ASSET')) {
                            return 'ASSET';
                        }
                        if (str_starts_with($t, 'LIABILIT')) {
                            return 'LIABILITY';
                        }
                        if (str_starts_with($t, 'CAPITAL')) {
                            return 'CAPITAL';
                        }
                        if (str_starts_with($t, 'INCOME')) {
                            return 'INCOME';
                        }
                        if (str_starts_with($t, 'EXPENSE')) {
                            return 'EXPENSE';
                        }
                        return 'OTHER';
                    };

                    $badgeClass = function ($g) {
                        return match ($g) {
                            'ASSET' => 'bg-info',
                            'LIABILITY' => 'bg-warning',
                            'CAPITAL' => 'bg-dark',
                            'INCOME' => 'bg-success',
                            'EXPENSE' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                    };

                    // Standard controller now passes: $rows (not opening/movement)
                    $rows = $rows ?? collect();
                @endphp

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-center table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Group</th>
                                    <th class="text-start">Main</th>
                                    <th>Main Type</th>
                                    <th class="text-start">Sub</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>

                            <tbody>
                                @php $i=1; @endphp

                                @forelse($rows as $r)
                                    @php $g = $groupOf($r->main_account_type); @endphp
                                    <tr>
                                        <td>{{ $i++ }}</td>
                                        <td><span class="badge {{ $badgeClass($g) }}">{{ $g }}</span></td>

                                        <td class="text-start">
                                            <div class="fw-bold">{{ $r->main_account_code }} -
                                                {{ $r->main_account_name }}</div>
                                        </td>

                                        <td>{{ $r->main_account_type }}</td>

                                        <td class="text-start">
                                            <div class="fw-bold">{{ $r->sub_account_code }} - {{ $r->sub_account_name }}
                                            </div>
                                        </td>

                                        <td class="text-end">{{ number_format($r->tb_debit ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($r->tb_credit ?? 0, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-muted">No records found for the selected cutoff.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            {{-- GRAND TOTALS --}}
                            @if ($rows->count() > 0)
                                <tfoot>
                                    <tr class="fw-bold table-secondary">
                                        <td colspan="5" class="text-end">GRAND TOTALS</td>
                                        <td class="text-end">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif

                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
