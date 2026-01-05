<div style="position:fixed; top:10px; right:10px; background:red; color:white; z-index:99999;">
    PERIOD ALERT PARTIAL LOADED
</div>

@if (
    Auth::check()
    && Auth::user()->member_position == 2
    && !empty($isOldPeriod)
    && $isOldPeriod
)
    <div
        id="period-alert"
        class="alert alert-warning alert-dismissible fade show"
        style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            max-width: 420px;
            z-index: 1050;
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
            aria-label="Close"
            onclick="dismissPeriodAlert()"
        ></button>
    </div>

    <script>
        (function () {
            if (sessionStorage.getItem('periodAlertDismissed') === '1') {
                document.getElementById('period-alert')?.remove();
                return;
            }

            window.dismissPeriodAlert = function () {
                document.getElementById('period-alert')?.remove();
                sessionStorage.setItem('periodAlertDismissed', '1');
            };
        })();
    </script>
@endif
