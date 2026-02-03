{{-- resources/views/reports/final_accounts/trial_balance_pdf.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Trial Balance</title>
  <style>
    @page { margin: 22px 22px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
    .h1 { font-size: 18px; font-weight: 800; margin: 0 0 6px 0; }
    .meta { font-size: 11px; color: #6b7280; margin: 0 0 10px 0; }
    .meta strong { color:#111827; }
    .notice { background:#fff7ed; border:1px solid #fed7aa; padding:10px; border-radius:8px; margin: 10px 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
    th { background: #f9fafb; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: .2px; }
    td { font-size: 11px; }
    .right { text-align: right; white-space: nowrap; }
    .center { text-align: center; }
    .muted { color:#6b7280; }
    .badge {
      display:inline-block; padding:2px 8px; border-radius:999px;
      font-weight:800; font-size:10px; border:1px solid #e5e7eb; background:#f3f4f6;
    }
    .footrow td { background:#f9fafb; font-weight: 800; }
    .ok { color:#065f46; }
    .bad { color:#b91c1c; }
  </style>
</head>
<body>

@php
  // ✅ timezone passed from controller: 'tz' => self::TZ
  $tz = $tz ?? config('app.timezone', 'UTC');

  $mode = $ctx['mode'] ?? 'period';

  $cutoffLabel = '';
  if ($mode === 'period') {
      $cutoffLabel = $ctx['as_at_period'] ?? '';
  } else {
      $cutoffLabel = isset($ctx['as_at_date']) && $ctx['as_at_date']
          ? $ctx['as_at_date']->copy()->timezone($tz)->format('Y-m-d')
          : '';
  }

  $rows   = $rows ?? collect();
  $totals = $totals ?? ['debit'=>0,'credit'=>0,'diff'=>0];

  $diff = (float) ($totals['diff'] ?? 0);
  $diffOk = (abs($diff) < 0.005);

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

<div class="h1">Trial Balance</div>
<div class="meta">
  <strong>Cutoff:</strong> {{ $cutoffLabel }}
  <span class="muted">•</span>
  <strong>Mode:</strong> {{ strtoupper($mode) }}
  <span class="muted">•</span>
  <strong>Generated:</strong> {{ \Carbon\Carbon::now($tz)->format('Y-m-d H:i') }}
</div>

@if (!empty($notices))
  <div class="notice">
    <strong>Notices</strong>
    <ul style="margin:6px 0 0 18px; padding:0;">
      @foreach ($notices as $n)
        <li>{{ $n }}</li>
      @endforeach
    </ul>
  </div>
@endif

<table>
  <thead>
    <tr>
      <th class="center" style="width:34px;">#</th>
      <th class="center" style="width:92px;">Group</th>
      <th style="width:190px;">Main Account</th>
      <th style="width:210px;">Sub Account</th>
      <th class="right" style="width:110px;">Debit</th>
      <th class="right" style="width:110px;">Credit</th>
    </tr>
  </thead>
  <tbody>
    @php $i=1; @endphp
    @forelse($rows as $r)
      @php $g = $groupOf($r->main_account_type ?? ''); @endphp
      <tr>
        <td class="center">{{ $i++ }}</td>
        <td class="center"><span class="badge">{{ $g }}</span></td>

        <td>
          <div style="font-weight:800;">
            {{ $r->main_account_code ?? '' }} - {{ $r->main_account_name ?? '' }}
          </div>
          <div class="muted" style="font-size:10px;">
            {{ $r->main_account_type ?? '' }}
          </div>
        </td>

        <td>
          <div style="font-weight:800;">
            {{ $r->sub_account_code ?? '' }} - {{ $r->sub_account_name ?? '' }}
          </div>
        </td>

        <td class="right">{{ number_format((float)($r->tb_debit ?? 0), 2) }}</td>
        <td class="right">{{ number_format((float)($r->tb_credit ?? 0), 2) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="6" class="center muted">No records found for the selected cutoff.</td>
      </tr>
    @endforelse
  </tbody>

  @if($rows->count() > 0)
    <tfoot>
      <tr class="footrow">
        <td colspan="4" class="right">GRAND TOTALS</td>
        <td class="right">{{ number_format((float)($totals['debit'] ?? 0), 2) }}</td>
        <td class="right">{{ number_format((float)($totals['credit'] ?? 0), 2) }}</td>
      </tr>
      <tr class="footrow">
        <td colspan="4" class="right">DIFFERENCE (DR - CR)</td>
        <td colspan="2" class="right {{ $diffOk ? 'ok' : 'bad' }}">
          {{ number_format($diff, 2) }}
        </td>
      </tr>
    </tfoot>
  @endif
</table>

</body>
</html>
