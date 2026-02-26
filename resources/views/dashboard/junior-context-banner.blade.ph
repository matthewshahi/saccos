{{-- resources/views/partials/junior-context-banner.blade.php --}}

@php
  $isJuniorView = data_get($data ?? [], 'is_viewing_junior', false);
  $junior       = data_get($data ?? [], 'junior', null);

  $juniorName   = is_array($junior) ? ($junior['member_name'] ?? '') : '';
  $juniorSacco  = is_array($junior) ? ($junior['member_sacco_id'] ?? '') : '';
  $juniorId     = is_array($junior) ? ($junior['member_id'] ?? '') : '';

  // Keep current query, but remove jaccount to "exit" junior view
  $exitUrl = request()->fullUrlWithQuery(['jaccount' => null]);
@endphp

@if($isJuniorView && !empty($juniorName))
  <div class="card o-hidden mb-4" style="border-left: 5px solid #f6c23e;">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
      <div class="d-flex align-items-start">
        <div class="me-3" style="
            width: 44px; height: 44px; border-radius: 10px;
            background: rgba(246, 194, 62, 0.18);
            display:flex; align-items:center; justify-content:center;">
          <i class="i-User text-20" style="color:#856404;"></i>
        </div>

        <div>
          <div class="fw-bold text-dark" style="font-size: 1.05rem;">
            Viewing Junior Account (belongs to you)
          </div>

          <div class="text-muted" style="font-size: 0.95rem;">
            <span class="fw-semibold text-dark">{{ strtoupper($juniorName) }}</span>
            @if(!empty($juniorSacco))
              <span class="mx-2">•</span>
              <span class="text-muted">SACCO ID:</span> <span class="fw-semibold">{{ $juniorSacco }}</span>
            @endif
            @if(!empty($juniorId))
              <span class="mx-2">•</span>
              <span class="text-muted">Member ID:</span> <span class="fw-semibold">{{ $juniorId }}</span>
            @endif
          </div>

          <div class="small text-muted mt-1">
            All reports and balances shown on this page are for this junior account.
          </div>
        </div>
      </div>

      <div class="mt-3 mt-md-0">
        <a href="{{ $exitUrl }}" class="btn btn-sm btn-outline-secondary">
          <i class="i-Back-2 me-1"></i> Exit Junior View
        </a>
      </div>
    </div>
  </div>
@endif