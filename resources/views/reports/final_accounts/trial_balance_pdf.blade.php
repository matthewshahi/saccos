{{-- resources/views/reports/final_accounts/trial_balance_pdf.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Trial Balance</title>

  <style>
    /* DomPDF page */
    @page { size: A4 landscape; margin: 18px 18px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#111827; }

    .muted{ color:#6b7280; }
    .title{ font-size: 14px; font-weight: 700; margin:0; }
    .sub{ font-size: 10px; margin:4px 0 0 0; }
    .meta{ margin: 10px 0 12px 0; }

    .notice{
      border:1px solid #f59e0b;
      background:#fffbeb;
      padding:8px 10px;
      border-radius:6px;
      margin: 10px 0 12px 0;
      font-size: 9.5px;
    }
    .notice ul{ margin: 0; padding-left: 18px; }

    table{ width:100%; border-collapse: collapse; }
    th, td{ border:1px solid #e5e7eb; padding:6px 6px; vertical-align: top; }
    thead th{
      background:#f9fafb;
      font-weight:700;
      font-size:10px;
      white-space: nowrap;
    }

    .num{ text-align:right; white-space: nowrap; }
    .center{ text-align:center; white-space: nowrap; }
    .wrap{ white-space: normal; word-break: break-word; }
    .small{ font-size: 9px; }

    /* column widths (A4 landscape) */
    .col-no{ width: 3.5%; }
    .col-group{ width: 8%; }
    .col-main{ width: 28%; }
    .col-type{ width: 12%; }
    .col-sub{ width: 30%; }
    .col-dr{ width: 9%; }
    .col-cr{ width: 9%; }

    tfoot td{
      font-weight:700;
      background:#f3f4f6;
    }

    .footer{
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      font-size: 9px;
      color:#6b7280;
    }
  </style>
</head>
<body>

@php
  $rows = $rows ?? collect();
  $totals = $totals ?? [];
  $ctx = $ctx ?? [];
  $notices = $notices ?? ($ctx['notices'] ?? []);
@endphp

  <div>
    <p class="title">Trial Balance</p>

    @php
      $cutoffLabel = (($ctx['mode'] ?? '') === 'period')
        ? ($ctx['as_at_period'] ?? '')
        : (isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '');
    @endphp

    <p class="sub muted">
      Cutoff:
      <strong>{{ $cutoffLabel }}</strong>
      <span class="muted">({{ strtoupper($ctx['mode'] ?? 'period') }})</span>
    </p>

    <div class="meta muted small">
      Generated: {{ now()->format('Y-m-d H:i') }}
    </div>

    @if(!empty($notices))
      <div class="notice">
        <strong>Notes</strong>
        <ul>
          @foreach($notices as $n)
            <li>{{ $n }}</li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>

  @php
    $groupOf = function ($t) {
      $t = strtoupper(trim((string) $t));
      if (str_starts_with($t, 'ASSET')) return 'ASSET';
      if (str_starts_with($t, 'LIABILIT')) return 'LIABILITY';
      if (str_starts_with($t, 'CAPITAL')) return 'CAPITAL';
      if (str_starts_with($t, 'INCOME')) return 'INCOME';
      if (str_starts_with($t, 'EXPENSE')) return 'EXPENSE';
      return 'OTHER';
    };
  @endphp

  <table>
    <thead>
      <tr>
        <th class="col-no">#</th>
        <th class="col-group">Group</th>
        <th class="col-main">Main</th>
        <th class="col-type">Main Type</th>
        <th class="col-sub">Sub</th>
        <th class="col-dr num">Debit</th>
        <th class="col-cr num">Credit</th>
      </tr>
    </thead>

    <tbody>
      @php $i = 1; @endphp

      @forelse($rows as $r)
        @php $g = $groupOf($r->main_account_type ?? ''); @endphp
        <tr>
          <td class="center">{{ $i++ }}</td>
          <td class="center">{{ $g }}</td>

          <td class="wrap">
            <strong>{{ $r->main_account_code ?? '' }}</strong>
            @if(!empty($r->main_account_name))
              - {{ $r->main_account_name }}
            @endif
          </td>

          <td class="wrap small">{{ $r->main_account_type ?? '' }}</td>

          <td class="wrap">
            <strong>{{ $r->sub_account_code ?? '' }}</strong>
            @if(!empty($r->sub_account_name))
              - {{ $r->sub_account_name }}
            @endif
          </td>

          <td class="num">{{ number_format((float)($r->tb_debit ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float)($r->tb_credit ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="center muted">No records found for the selected cutoff.</td>
        </tr>
      @endforelse
    </tbody>

    @if($rows instanceof \Illuminate\Support\Collection ? $rows->count() > 0 : (is_countable($rows) && count($rows) > 0))
      <tfoot>
        <tr>
          <td colspan="5" class="num">GRAND TOTALS</td>
          <td class="num">{{ number_format((float)($totals['debit'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float)($totals['credit'] ?? 0), 2) }}</td>
        </tr>
        <tr>
          <td colspan="5" class="num">DIFFERENCE (DR - CR)</td>
          <td class="num" colspan="2">{{ number_format((float)(($totals['diff'] ?? 0)), 2) }}</td>
        </tr>
      </tfoot>
    @endif
  </table>

  <div class="footer">
    <span>Trial Balance</span>
    <span style="float:right;">Page {PAGE_NUM} of {PAGE_COUNT}</span>
  </div>

</body>
</html>
