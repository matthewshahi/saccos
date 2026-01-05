@if (
    Auth::check()
    && Auth::user()->member_position == 2
    && !empty($isOldPeriod)
    && $isOldPeriod
)
    <div
        id="period-alert"
        class="alert alert-warning alert-dismissible fade show"
        role="alert"
        style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            max-width: 420px;
            z-index: 99999;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        "
    >
        <strong>Old Accounting Period</strong><br>
        You are currently working in period
        <strong>{{ $currentPeriod->period_no }}</strong>.
        The system period is
        <strong>{{ $systemPeriod }}</strong>.

        <div class="mt-2">
            <a href="{{ url('admin/periods') }}" class="btn btn-sm btn-outline-dark">
                Switch Period
            </a>
        </div>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>

    <script>
        (function () {
            const alertEl = document.getElementById('period-alert');

            if (!alertEl) return;

            // Do not re-show if dismissed in this session
            if (sessionStorage.getItem('periodAlertDismissed') === '1') {
                alertEl.remove();
                return;
            }

            alertEl.addEventListener('closed.bs.alert', function () {
                sessionStorage.setItem('periodAlertDismissed', '1');
            });
        })();
    </script>
@endif
