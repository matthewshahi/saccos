<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Interest Cutoff Date
    |--------------------------------------------------------------------------
    |
    | Interest cycles ending before this date will not be calculated or posted.
    | This can be configured independently for every SACCO deployment through
    | its own .env file.
    |
    */

    'interest_cutoff_date' => env(
        'SPECIAL_SAVINGS_INTEREST_CUTOFF_DATE',
        '2026-04-01'
    ),

];