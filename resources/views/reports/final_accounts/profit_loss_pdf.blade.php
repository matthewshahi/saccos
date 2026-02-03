{{-- resources/views/reports/final_accounts/profit_loss_pdf.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profit &amp; Loss</title>
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
      border:1px solid #e5e7eb;
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
    .b-inc{ background:#dcfce7; }
    .b-exp{ background:#fee2e2; }
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

  // Range label (match your web chip logic)
  if (($ctx['mode'] ?? '') === 'period') {
      $rangeLabel = ($ctx['period_from'] ?? '') . ' → ' . ($ctx['period_to'] ?? '');
  } else {
      $rangeLabel =
          (isset($ctx['date_from']) && $ctx['date_from'] ? $ctx['date_from']->format('Y-m-d') : '') .
          ' → ' .
          (isset($ctx['date_to']) && $ctx['date_to'] ? $ctx['date_to']->format('Y-m-d') : '');
  }

  // IMPORTANT: derive totals from rows (same logic as your web Blade)
  $incomeTotal  = 0.0;
  $expenseTotal = 0.0;
  $otherCount   = 0;

  foreach ($rows as $rr) {
      $g = strtoupper((string) ($rr->main_group ?? ''));
      $g = $g ?: 'OTHER';

      $net = (float) ($rr->net_effect ?? 0); // SIGNED (Cr - Dr)

      if ($g === 'INCOME') {
          $incomeTotal += $net;           // reversals reduce income
      } elseif ($g === 'EXPENSE') {
          $expenseTotal += (-1 * $net);   // flip sign => positive expenses
      } else {
          $otherCount++;
      }
  }

  $incomeTotal  = round($incomeTotal, 2);
  $expenseTotal = round($expenseTotal, 2);
  $netSurplus   = round($incomeTotal - $expenseTotal, 2);

  $badgeClass = function ($g) {
      $g = strtoupper(trim((string) $g));
      return match ($g) {
          'INCOME'  => 'b-inc',
          'EXPENSE' => 'b-exp',
          default   => 'b-oth',
      };
  };
@endphp

<div class="header">
  <div class="title">Profit &amp; Loss</div>
  <div class="sub">Generated: {{ now()->format('Y-m-d H:i') }} (Africa/Nairobi)</div>
  <div class="chip">Range: <span class="mono">{{ $rangeLabel }}</span></div>
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

@if($otherCount > 0)
  <div class="notices" style="border-color:#ef4444; background:#fef2f2;">
    <strong>Classification warning</strong>
    <div class="muted" style="margin-top:4px;">
      {{ $otherCount }} line(s) are classified as <span class="mono">OTHER</span> and are excluded from Income/Expense totals.
      Fix <span class="mono">main_account_type</span> mapping so P&amp;L rows are only INCOME/EXPENSE.
    </div>
  </div>
@endif

<table class="kpis">
  <tr>
    <td style="width:33.33%;">
      <div class="k">Total Income</div>
      <div class="v good">{{ number_format($incomeTotal, 2) }}</div>
    </td>
    <td style="width:33.33%;">
      <div class="k">Total Expenses</div>
      <div class="v bad">{{ number_format($expenseTotal, 2) }}</div>
    </td>
    <td style="width:33.33%;">
      <div class="k">Net Surplus / (Deficit)</div>
      <div class="v {{ $netSurplus >= 0 ? 'good' : 'bad' }}">{{ number_format($netSurplus, 2) }}</div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:30px;">#</th>
      <th style="width:90px;">Type</th>
      <th class="text-left">Sub Account</th>
      <th style="width:110px;" class="text-right">Debit</th>
      <th style="width:110px;" class="text-right">Credit</th>
      <th style="width:130px;" class="text-right">Net (Cr - Dr)</th>
    </tr>
  </thead>

  <tbody>
    @php $i = 1; @endphp

    @forelse($rows as $r)
      @php
        $g = strtoupper((string) ($r->main_group ?? ''));
        $g = $g ?: 'OTHER';
        $net = (float) ($r->net_effect ?? 0);

        // labels (match web: show sub then main)
        $subLabel  = trim(($r->sub_account_code ?? '').' '.$r->sub_account_name);
        $mainLabel = trim(($r->main_account_code ?? '').' '.$r->main_account_name);
      @endphp

      <tr>
        <td class="text-center">{{ $i++ }}</td>

        <td class="text-center">
          <span class="badge {{ $badgeClass($g) }}">{{ $g }}</span>
        </td>

        <td>
          <div class="acc-main nowrap">{{ $subLabel }}</div>
          <div class="acc-sub muted nowrap">{{ $mainLabel }}</div>
        </td>

        <td class="text-right nowrap">{{ number_format((float)($r->debit ?? 0), 2) }}</td>
        <td class="text-right nowrap">{{ number_format((float)($r->credit ?? 0), 2) }}</td>

        <td class="text-right nowrap {{ $net >= 0 ? 'good' : 'bad' }}">
          {{ number_format($net, 2) }}
        </td>
      </tr>

    @empty
      <tr>
        <td colspan="6" class="text-center muted">No records found for the selected range.</td>
      </tr>
    @endforelse
  </tbody>

  @if($rows instanceof \Illuminate\Support\Collection ? $rows->count() > 0 : (is_countable($rows) && count($rows) > 0))
    <tfoot>
      <tr>
        <td colspan="5" class="text-right">TOTAL INCOME</td>
        <td class="text-right nowrap good">{{ number_format($incomeTotal, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">TOTAL EXPENSES</td>
        <td class="text-right nowrap bad">{{ number_format($expenseTotal, 2) }}</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right">NET SURPLUS / (DEFICIT)</td>
        <td class="text-right nowrap {{ $netSurplus >= 0 ? 'good' : 'bad' }}">{{ number_format($netSurplus, 2) }}</td>
      </tr>
    </tfoot>
  @endif
</table>

</body>
</html>
