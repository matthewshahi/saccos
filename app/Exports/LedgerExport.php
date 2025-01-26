<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class LedgerExport implements FromView
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.ledger', ['transactions' => $this->data['transactions'], 'totals' => $this->data['totals'], 'openingBalance' => $this->data['openingBalance']]);
    }
}