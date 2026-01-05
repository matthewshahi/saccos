<?php

namespace App\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CurrentPeriodComposer
{
    public function compose(View $view)
    {
        // System period (authoritative YYYYMM)
        $systemPeriod = (int) date('Ym');

        // 1. Try to fetch the single active, non-deleted period
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        /**
         * 2. If NO active period exists, bootstrap or reactivate current system period
         */
        if (!$currentPeriod) {

            // Check if current system period already exists
            $existing = DB::table('sacco_period')
                ->where('period_name', $systemPeriod)
                ->where('period_deleted', '<>', 'Y')
                ->first();

            if ($existing) {
                // Reactivate existing system period
                DB::table('sacco_period')
                    ->where('period_id', $existing->period_id)
                    ->update([
                        'period_active'    => 'Y',
                        'period_transdate' => now(),
                        'period_user_id'   => auth()->id() ?? 0,
                        'period_ip'        => request()->ip() ?? 'SYSTEM',
                    ]);

                $currentPeriod = $existing;
            } else {
                // Create new system period and mark active
                $periodId = DB::table('sacco_period')->insertGetId([
                    'period_name'      => $systemPeriod,
                    'period_active'    => 'Y',
                    'period_deleted'   => 'N',
                    'period_user_id'   => auth()->id() ?? 0,
                    'period_transdate' => now(),
                    'period_ip'        => request()->ip() ?? 'SYSTEM',
                ]);

                $currentPeriod = DB::table('sacco_period')
                    ->where('period_id', $periodId)
                    ->first();
            }
        }

        // Normalize active accounting period
        $activePeriod = $currentPeriod
            ? (int) $currentPeriod->period_name
            : null;

        // Determine relationship to system period
        $isOldPeriod     = false;
        $isCurrentPeriod = false;
        $isFuturePeriod  = false;

        if ($activePeriod !== null) {
            if ($activePeriod < $systemPeriod) {
                $isOldPeriod = true;
            } elseif ($activePeriod === $systemPeriod) {
                $isCurrentPeriod = true;
            } else {
                $isFuturePeriod = true;
            }
        }

        // Share semantic state with all views
        $view->with([
            'currentPeriod'   => $currentPeriod,  // raw DB row (if needed)
            'activePeriod'    => $activePeriod,   // YYYYMM
            'systemPeriod'    => $systemPeriod,   // YYYYMM
            'isOldPeriod'     => $isOldPeriod,
            'isCurrentPeriod' => $isCurrentPeriod,
            'isFuturePeriod'  => $isFuturePeriod,
        ]);
    }
}
