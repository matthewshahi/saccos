<?php

return [
    'interest_cutoff_date' => env(
        'SPECIAL_SAVINGS_INTEREST_CUTOFF_DATE',
        '2026-04-01'
    ),

    'vesting_start_date' => env(
        'SPECIAL_SAVINGS_VESTING_START_DATE',
        '2026-01-01'
    ),
];