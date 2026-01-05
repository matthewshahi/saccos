<?php

namespace App\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CurrentPeriodComposer
{
    public function compose(View $view)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        // Compute system "current" YYYYMM
        $systemPeriod = (int) date('Ym');

        $isOldPeriod = false;

        if ($currentPeriod && isset($currentPeriod->period_no)) {
            $isOldPeriod = ((int) $currentPeriod->period_no < $systemPeriod);
        }

        $view->with([
            'currentPeriod' => $currentPeriod,
            'systemPeriod'  => $systemPeriod,
            'isOldPeriod'   => $isOldPeriod,
        ]);
    }
}
