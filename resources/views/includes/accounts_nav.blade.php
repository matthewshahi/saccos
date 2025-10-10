<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-bold text-primary">
    <i class="bi bi-journal-text me-1"></i> .
  </h5>

  <div class="btn-group btn-group-sm" role="group" aria-label="Accounting Navigation">
    {{-- General Ledger --}}
    <a href="{{ route('reports.accounts.ledger') }}"
       class="btn {{ request()->routeIs('reports.accounts.ledger') ? 'btn-primary' : 'btn-outline-primary' }}">
      <i class="bi bi-journal-bookmark me-1"></i> Ledger
    </a>

    {{-- Trial Balance --}}
    <a href="{{ route('reports.accounts.trial-balance') }}"
       class="btn {{ request()->routeIs('reports.accounts.trial-balance') ? 'btn-primary' : 'btn-outline-primary' }}">
      <i class="bi bi-table me-1"></i> Trial Balance
    </a>

    {{-- Profit & Loss --}}
    <a href="{{ route('reports.accounts.profit-loss') }}"
       class="btn {{ request()->routeIs('reports.accounts.profit-loss') ? 'btn-primary' : 'btn-outline-primary' }}">
      <i class="bi bi-graph-up-arrow me-1"></i> Profit &amp; Loss
    </a>

    {{-- Balance Sheet --}}
    <a href="{{ route('reports.accounts.balance-sheet') }}"
       class="btn {{ request()->routeIs('reports.accounts.balance-sheet') ? 'btn-primary' : 'btn-outline-primary' }}">
      <i class="bi bi-grid-3x3-gap me-1"></i> Balance Sheet
    </a>
  </div>
</div>

<hr class="mt-0 mb-3">