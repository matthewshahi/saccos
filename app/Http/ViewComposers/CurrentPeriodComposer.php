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

        $view->with('currentPeriod', $currentPeriod);
    }
}
