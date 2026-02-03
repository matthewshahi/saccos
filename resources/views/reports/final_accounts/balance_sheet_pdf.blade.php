{{-- resources/views/reports/final_accounts/balance_sheet_pdf.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Balance Sheet</title>
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
    .b-oth{ background:#f3f4f6; }
    .nowrap{ white-space:nowrap; }
    .acc-main{ font-weight:800; }
    .acc-sub{ margin-top:2px; }
  </style>
</head>
<body>

@php
  $rows = $rows ?? collect();
  $ctx  = $ctx  ?? [];
  $totals = $totals ?? [];

  $cutoffLabel = (($ctx['mode'] ?? '') === 'period')
      ? ($ctx['as_at_period'] ?? '')
      : (isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '');

  $diff = (float) ($totals['diff'] ?? 0);
  $diffOk = (abs($diff) < 0.005);

  $badgeClass = function ($g) {
      $g = strtoupper(trim((string) $g));
      return match ($g) {
          'ASSET' => 'b-asset',
          'LIABILITY' => 'b-liab',
          'CAPITAL' => 'b-cap',
          default => 'b-oth',
      };
  };
@endphp

<div class="header">
  <div class="title">Balance Sheet</div>
  <div class="sub">
    Generated: {{ now()->format('Y-m-d H:i') }} (Africa/Nairobi)
  </div>
  <div class="chip">As at: <span class="mono">{{ $cutoffLabel }}</span></div>
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
      <div class="k">Total Assets</div>
      <div class="v">{{ number_format($totals['total_assets'] ?? 0, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Total Liabilities</div>
      <div class="v">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Total Capital</div>
      <div class="v">{{ number_format($totals['total_capital'] ?? 0, 2) }}</div>
    </td>
    <td style="width:25%;">
      <div class="k">Balance Check</div>
      <div class="v {{ $diffOk ? 'good' : 'bad' }}">{{ number_format($diff, 2) }}</div>
      <div class="muted">Assets − (Liabilities + Capital)</div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:30px;">#</th>
      <th style="width:90px;">Group</th>
      <th class="text-left">Account</th>
      <th style="width:110px;" class="text-right">Debit</th>
      <th style="width:110px;" class="text-right">Credit</th>
      <th style="width:130px;" class="text-right">Balance</th>
    </tr>
  </thead>

  <tbody>
    @php $i = 1; @endphp

    @forelse($rows as $r)
      @php
        $g = strtoupper((string) ($r->main_group ?? ''));
        $g = $g ?: 'OTHER';

        $bal = (float) ($r->balance ?? 0); // (Dr - Cr)
        $side = $bal >= 0 ? 'Dr' : 'Cr';

        // Display label: prefer sub account when available
        $labelTop = '';
        $labelSub = '';

        if (!empty($r->sub_account_name)) {
            $labelTop = trim(($r->sub_account_code ?? '').' '.$r->sub_account_name);
            $labelSub = trim(($r->main_account_code ?? '').' '.$r->main_account_name);
        } else {
            $labelTop = trim(($r->main_account_code ?? '').' '.$r->main_account_name);
        }
      @endphp

      <tr>
        <td class="text-center">{{ $i++ }}</td>

        <td class="text-center">
          <span class="badge {{ $badgeClass($g) }}">{{ $g }}</span>
        </td>

        <td>
          <div class="acc-main nowrap">{{ $labelTop }}</div>
          @if($labelSub)
            <div class="acc-sub muted nowrap">{{ $labelSub }}</div>
          @endif
        </td>

        <td class="text-right nowrap">{{ number_format((float)($r->debit ?? 0), 2) }}</td>
        <td class="text-right nowrap">{{ number_format((float)($r->credit ?? 0), 2) }}</td>

        <td class="text-right nowrap">
          {{ number_format(abs($bal), 2) }} <span class="muted">{{ $side }}</span>
        </td>
      </tr>

    @empty
      <tr>
        <td colspan="6" class="text-center muted">No records found for the selected cutoff.</td>
      </tr>
    @endforelse
  </tbody>

  @if($rows instanceof \Illuminate\Support\Collection ? $rows->count() > 0 : (is_countable($rows) && count($rows) > 0))
    <tfoot>
      <tr>
        <td colspan="5" class="text-right">TOTAL ASSETS</td>
        <td class="text-right nowrap">{{ number_format($totals['total_assets'] ?? 0, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">TOTAL LIABILITIES</td>
        <td class="text-right nowrap">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">TOTAL CAPITAL</td>
        <td class="text-right nowrap">{{ number_format($totals['total_capital'] ?? 0, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">LIABILITIES + CAPITAL</td>
        <td class="text-right nowrap">{{ number_format($totals['liabilities_plus_capital'] ?? 0, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">BALANCE CHECK</td>
        <td class="text-right nowrap {{ $diffOk ? 'good' : 'bad' }}">{{ number_format($diff, 2) }}</td>
      </tr>
    </tfoot>
  @endif
</table>

</body>
</html>
