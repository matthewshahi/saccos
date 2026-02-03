{{-- resources/views/reports/final_accounts/trial_balance_pdf.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Trial Balance</title>
  <style>
    @page { margin: 22px 22px; }
    body{ font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#111827; }
    .header{ margin-bottom: 10px; }
    .title{ font-size: 16px; font-weight: 800; margin:0; }
    .sub{ font-size: 10px; color:#6b7280; margin-top:4px; }
    .chip{
      display:inline-block; margin-top:8px;
      padding:4px 10px; border-radius:999px;
      border:1px solid #e5e7eb; background:#f6f3ff; color:#5b2aa3;
      font-weight:800; font-size:10px;
    }
    .notices{ margin:10px 0 12px; padding:10px; border:1px solid #f59e0b; background:#fff7ed; border-radius:10px; }
    .notices ul{ margin:0; padding-left:16px; }
    .kpis{ width:100%; border-collapse:collapse; margin:10px 0 12px; }
    .kpis td{
      border:1px solid #e5e7eb; border-radius:10px;
      padding:10px; vertical-align:top;
    }
    .k{ font-size:10px; color:#6b7280; font-weight:800; }
    .v{ font-size:13px; font-weight:900; margin-top:4px; }
    .good{ color:#065f46; }
    .bad{ color:#b91c1c; }

    table{ width:100%; border-collapse:collapse; }
    thead th{
      background:#f3f4f6; border:1px solid #e5e7eb;
      padding:7px 6px; font-size:10px; text-transform:uppercase; letter-spacing:.3px;
      white-space:nowrap;
    }
    tbody td{
      border:1px solid #e5e7eb; padding:6px 6px; vertical-align:top;
    }
    tfoot td{
      border:1px solid #e5e7eb; padding:7px 6px; font-weight:900; background:#f3f4f6;
    }
    .text-right{ text-align:right; }
    .text-center{ text-align:center; }
    .muted{ color:#6b7280; font-size:10px; }
    .mono{ font-family: DejaVu Sans Mono, monospace; }
    .badge{
      display:inline-block; padding:2px 8px; border-radius:999px; font-size:9px; font-weight:900;
      border:1px solid #e5e7eb;
    }
    .b-asset{ background:#e0f2fe; }
    .b-liab{ background:#fef3c7; }
    .b-cap{ background:#e5e7eb; }
    .b-inc{ background:#dcfce7; }
    .b-exp{ background:#fee2e2; }
    .b-oth{ background:#f3f4f6; }
    .nowrap{ white-space:nowrap; }
  </style>
</head>
<body>

@php
  $rows = $rows ?? collect();
  $ctx  = $ctx  ?? [];
  $totals = $totals ?? ['debit'=>0,'credit'=>0,'diff'=>0];

  $cutoffLabel = (($ctx['mode'] ?? '') === 'period')
      ? ($ctx['as_at_period'] ?? '')
      : (isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '');

  $diff = (float) ($totals['diff'] ?? 0);

  $groupOf = function ($t) {
      $t = strtoupper(trim((string) $t));
      if (str_starts_with($t, 'ASSET')) return 'ASSET';
      if (str_starts_with($t, 'LIABILIT')) return 'LIABILITY';
      if (str_starts_with($t, 'CAPITAL')) return 'CAPITAL';
      if (str_starts_with($t, 'INCOME')) return 'INCOME';
      if (str_starts_with($t, 'EXPENSE')) return 'EXPENSE';
      return 'OTHER';
  };

  $badgeClass = function ($g) {
      return match ($g) {
          'ASSET' => 'b-asset',
          'LIABILITY' => 'b-liab',
          'CAPITAL' => 'b-cap',
          'INCOME' => 'b-inc',
          'EXPENSE' => 'b-exp',
          default => 'b-oth',
      };
  };
@endphp

<div class="header">
  <div class="title">Trial Balance</div>
  <div class="sub">
    Generated: {{ now()->format('Y-m-d H:i') }} ({{ \App\Http\Controllers\Reports\FinalAccountsController::TZ ?? 'Africa/Nairobi' }})
  </div>
  <div class="chip">Cutoff: <span class="mono">{{ $cutoffLabel }}</span></div>
</div>

@if (!empty($notices))
  <div class="notices">
    <strong>Notices</strong>
    <ul>
      @foreach ($notices as $n)
        <li>{{ $n }}</li>
      @endforeach
    </ul>
  </div>
@endif

<table class="kpis">
  <tr>
    <td style="width:25%;">
      <div class="k">Total Debit</div>
      <div class="v">{{ number_format($totals['debit'] ?? 0, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Total Credit</div>
      <div class="v">{{ number_format($totals['credit'] ?? 0, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Difference</div>
      <div class="v {{ (abs($diff) < 0.005) ? 'good' : 'bad' }}">{{ number_format($diff, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Mode</div>
      <div class="v">{{ strtoupper($ctx['mode'] ?? '') }}</div>
      <div class="muted">As at {{ $cutoffLabel }}</div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:30px;">#</th>
      <th style="width:90px;">Group</th>
      <th class="text-left">Main</th>
      <th style="width:120px;">Main Type</th>
      <th class="text-left">Sub</th>
      <th style="width:110px;" class="text-right">Debit</th>
      <th style="width:110px;" class="text-right">Credit</th>
    </tr>
  </thead>

  <tbody>
    @php $i = 1; @endphp

    @forelse($rows as $r)
      @php
        $g = $groupOf($r->main_account_type ?? '');
      @endphp
      <tr>
        <td class="text-center">{{ $i++ }}</td>
        <td class="text-center">
          <span class="badge {{ $badgeClass($g) }}">{{ $g }}</span>
        </td>

        <td>
          <div class="nowrap"><strong>{{ $r->main_account_code ?? '' }}</strong> - {{ $r->main_account_name ?? '' }}</div>
        </td>

        <td class="text-center nowrap">{{ $r->main_account_type ?? '' }}</td>

        <td>
          <div class="nowrap"><strong>{{ $r->sub_account_code ?? '' }}</strong> - {{ $r->sub_account_name ?? '' }}</div>
        </td>

        <td class="text-right nowrap">{{ number_format((float)($r->tb_debit ?? 0), 2) }}</td>
        <td class="text-right nowrap">{{ number_format((float)($r->tb_credit ?? 0), 2) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="7" class="text-center muted">No records found for the selected cutoff.</td>
      </tr>
    @endforelse
  </tbody>

  @if($rows instanceof \Illuminate\Support\Collection ? $rows->count() > 0 : (is_countable($rows) && count($rows) > 0))
    <tfoot>
      <tr>
        <td colspan="5" class="text-right">GRAND TOTALS</td>
        <td class="text-right nowrap">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
        <td class="text-right nowrap">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
      </tr>
    </tfoot>
  @endif
</table>

</body>
</html>
