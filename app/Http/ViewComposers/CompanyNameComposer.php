<?php
namespace App\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CompanyNameComposer
{
     
    public function compose(View $view)
    {
         
        $defaultCompanyName = DB::table('sacco_defaults')
            ->where('default_name', 'company_name')
            ->value('default_value') ?? 'iSacco';

    
        $view->with('defaultCompanyName', $defaultCompanyName);
    }
}
