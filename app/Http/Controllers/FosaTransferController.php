<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FosaTransferController extends Controller
{
    /**
     * Load main page
     */
    public function index()
    {
        $types = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_name')
            ->get();

        return view('fosa.transfers.index', compact('types'));
    }

    /**
     * Search available FOSA transactions
     */
    public function search(Request $request)
{
    $q = trim($request->q);
    $typeId = $request->fosa_type_id;

    $rows = DB::table('sacco_fosas as f')
        ->join('sacco_members as m', 'm.member_id', '=', 'f.fosa_member_id')
        ->leftJoin('sacco_fosa_types as t', 't.type_id', '=', 'f.fosa_type_id')
        ->where('f.fosa_amount_paying', '>', 0)
        ->where('f.fosa_end_month_proc', 'N')
        ->whereNull('f.fosa_ref_id')
        ->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('sacco_fosas as x')
              ->whereColumn('x.fosa_ref_id', 'f.fosa_id')
              ->limit(1); // optional but clean
        });

    if ($typeId) {
        $rows->where('f.fosa_type_id', $typeId);
    }

    if ($q) {
        $rows->where(function ($w) use ($q) {
            $w->where('m.member_name', 'LIKE', "%{$q}%")
              ->orWhere('m.member_phone_no', 'LIKE', "%{$q}%")
              ->orWhere('f.fosa_doc_no', 'LIKE', "%{$q}%")
              ->orWhere('f.fosa_description', 'LIKE', "%{$q}%");
        });
    }

    return response()->json(
        $rows->orderBy('f.fosa_date_paid', 'desc')
             ->limit(200)   // THIS is the real limiter
             ->get([
                 'f.fosa_id',
                 'f.fosa_member_id',
                 'f.fosa_type_id',
                 'f.fosa_amount_paying',
                 'f.fosa_doc_no',
                 'f.fosa_description',
                 'f.fosa_date_paid',
                 'm.member_name',
                 'm.member_phone_no',
                 't.type_name'
             ])
    );
}

    /**
     * AJAX ledger search
     */
    public function ledgerSearch(Request $request)
    {
        $q = trim($request->q);

        return DB::table('sacco_sub_account as s')
            ->join('sacco_main_account as m', 'm.main_account_id', '=', 's.sub_account_main_account')
            ->where('s.sub_account_deleted', 'N')
            ->where(function ($w) use ($q) {
                $w->where('s.sub_account_name', 'LIKE', "%{$q}%")
                  ->orWhere('s.sub_account_code', 'LIKE', "%{$q}%")
                  ->orWhere('m.main_account_name', 'LIKE', "%{$q}%");
            })
            ->orderBy('m.main_account_name')
            ->orderBy('s.sub_account_name')
            ->limit(20)
            ->get([
                's.sub_account_id as id',
                DB::raw("CONCAT(m.main_account_name, ' - ', s.sub_account_name) as text")
            ]);
    }

    /**
     * Post FOSA transfer
     */
    public function postTransfer(Request $request)
{
    $fosaIds = $request->fosa_ids;
    $destSub = $request->destination_sub_account;

    if (!$fosaIds || !is_array($fosaIds)) {
        return back()->with('error', 'No FOSA transactions selected.');
    }

    if (!$destSub) {
        return back()->with('error', 'Destination ledger account is required.');
    }

    // Default FOSA ledger sub-account
    $defaultFosa = DB::table('sacco_defaults')
        ->where('default_name', 'default_fosa_account')
        ->value('default_value');

    if (!$defaultFosa) {
        return back()->with('error', 'Default FOSA account not configured.');
    }

    DB::beginTransaction();

    try {

        // Validate destination ledger
        $destLedger = DB::table('sacco_sub_account')
            ->where('sub_account_id', $destSub)
            ->where('sub_account_deleted', 'N')
            ->first();

        if (!$destLedger) {
            throw new \Exception('Selected destination ledger account is invalid.');
        }

        // Load FOSA records (lock rows)
        $records = DB::table('sacco_fosas')
            ->whereIn('fosa_id', $fosaIds)
            ->lockForUpdate()
            ->get();

        if ($records->isEmpty()) {
            throw new \Exception('No valid FOSA transactions found.');
        }

        // Enforce same FOSA type
        $typeIds = $records->pluck('fosa_type_id')->unique()->filter();
        if ($typeIds->count() !== 1) {
            throw new \Exception('Selected transactions must be of the same FOSA type.');
        }

        // Block end-month processed rows
        if ($records->where('fosa_end_month_proc', 'Y')->count() > 0) {
            throw new \Exception('One or more selected transactions are already end-month processed.');
        }

        // Resolve main accounts
        $defaultFosaMain = DB::table('sacco_sub_account')
            ->where('sub_account_id', $defaultFosa)
            ->value('sub_account_main_account');

        $destMain = $destLedger->sub_account_main_account;

        if (!$defaultFosaMain || !$destMain) {
            throw new \Exception('Ledger account configuration error.');
        }

        // Fetch member names once (no N+1 queries)
        $memberNames = DB::table('sacco_members')
            ->whereIn('member_id', $records->pluck('fosa_member_id')->unique())
            ->pluck('member_name', 'member_id');

        $batchDoc = 'FOSA-XFER-' . now()->format('YmdHis');
        $userId   = Auth::id();
        $ip       = request()->ip();

        foreach ($records as $r) {

            // Prevent double transfer
            $exists = DB::table('sacco_fosas')
                ->where('fosa_ref_id', $r->fosa_id)
                ->exists();

            if ($exists) {
                throw new \Exception("Transaction {$r->fosa_id} has already been transferred.");
            }

            $amt = abs($r->fosa_amount_paying);
            $memberName = $memberNames[$r->fosa_member_id] ?? 'UNKNOWN MEMBER';

            /* 1. Negative FOSA entry */
            DB::table('sacco_fosas')->insert([
                'fosa_member_id'      => $r->fosa_member_id,
                'fosa_type_id'        => $r->fosa_type_id,
                'fosa_amount_paying'  => -$amt,
                'fosa_paid_by'        => 'TRANSFER',
                'fosa_period'         => $r->fosa_period,
                'fosa_description'    => "Transfer out to ledger – {$memberName} | {$batchDoc}",
                'fosa_doc_no'         => $batchDoc,
                'fosa_ref_id'         => $r->fosa_id,
                'fosa_action'         => 'TRANSFER_OUT',
                'fosa_date_paid'      => now(),
                'fosa_by'             => $userId,
                'fosa_ip'             => $ip,
                'fosa_transdate'      => now(),
                'fosa_end_month_proc' => 'N',
            ]);

            /* 2. Ledger transactions (double-entry) */
            DB::table('sacco_accounts_trans')->insert([
                [
                    // Debit default FOSA
                    'accounts_trans_sub_account' => $defaultFosa,
                    'accounts_trans_period'      => $r->fosa_period,
                    'accounts_trans_debit'       => $amt,
                    'accounts_trans_credit'      => 0,
                    'accounts_trans_doc_no'      => $batchDoc,
                    'accounts_trans_decription' => "FOSA transfer out – {$memberName} (FOSA#{$r->fosa_id})",
                    'accounts_trans_source'      => 'fosa_transfer',
                    'accounts_trans_dat_date'    => now(),
                    'accounts_trans_transdate'   => now(),
                    'accounts_trans_user_id'     => $userId,
                    'accounts_trans_member_id'   => $r->fosa_member_id,
                    'accounts_trans_payment_type'=> 'INTERNAL',
                    'accounts_trans_ip'          => $ip,
                ],
                [
                    // Credit destination ledger
                    'accounts_trans_sub_account' => $destSub,
                    'accounts_trans_period'      => $r->fosa_period,
                    'accounts_trans_debit'       => 0,
                    'accounts_trans_credit'      => $amt,
                    'accounts_trans_doc_no'      => $batchDoc,
                    'accounts_trans_decription' => "FOSA transfer in – {$memberName} (FOSA#{$r->fosa_id})",
                    'accounts_trans_source'      => 'fosa_transfer',
                    'accounts_trans_dat_date'    => now(),
                    'accounts_trans_transdate'   => now(),
                    'accounts_trans_user_id'     => $userId,
                    'accounts_trans_member_id'   => $r->fosa_member_id,
                    'accounts_trans_payment_type'=> 'INTERNAL',
                    'accounts_trans_ip'          => $ip,
                ]
            ]);

            /* 3. Update sub-account running totals */
            DB::table('sacco_sub_account')
                ->where('sub_account_id', $defaultFosa)
                ->update([
                    'sub_account_debit' => DB::raw("sub_account_debit + {$amt}")
                ]);

            DB::table('sacco_sub_account')
                ->where('sub_account_id', $destSub)
                ->update([
                    'sub_account_credit' => DB::raw("sub_account_credit + {$amt}")
                ]);

            /* 4. Update main-account running totals */
            DB::table('sacco_main_account')
                ->where('main_account_id', $defaultFosaMain)
                ->update([
                    'main_account_debit' => DB::raw("main_account_debit + {$amt}")
                ]);

            DB::table('sacco_main_account')
                ->where('main_account_id', $destMain)
                ->update([
                    'main_account_credit' => DB::raw("main_account_credit + {$amt}")
                ]);

            /* 5. Reduce member total FOSA */
            DB::table('sacco_members')
                ->where('member_id', $r->fosa_member_id)
                ->decrement('member_total_fosa', $amt);
        }

        DB::commit();
        return back()->with('success', 'FOSA transfer completed successfully.');

    } catch (\Exception $e) {
        DB::rollBack();
        return back()->with('error', $e->getMessage());
    }
}

    // public function postTransfer(Request $request)
    // {
    //     $fosaIds = $request->fosa_ids;
    //     $destSub = $request->destination_sub_account;

    //     if (!$fosaIds || !is_array($fosaIds)) {
    //         return back()->with('error', 'No FOSA transactions selected.');
    //     }

    //     if (!$destSub) {
    //         return back()->with('error', 'Destination ledger account is required.');
    //     }

    //     // Default FOSA ledger sub-account
    //     $defaultFosa = DB::table('sacco_defaults')
    //         ->where('default_name', 'default_fosa_account')
    //         ->value('default_value');

    //     if (!$defaultFosa) {
    //         return back()->with('error', 'Default FOSA account not configured.');
    //     }

    //     DB::beginTransaction();

    //     try {

    //         // Validate destination ledger
    //         $destLedger = DB::table('sacco_sub_account')
    //             ->where('sub_account_id', $destSub)
    //             ->where('sub_account_deleted', 'N')
    //             ->first();

    //         if (!$destLedger) {
    //             throw new \Exception('Selected destination ledger account is invalid.');
    //         }

    //         // Load FOSA records (lock for safety)
    //         $records = DB::table('sacco_fosas')
    //             ->whereIn('fosa_id', $fosaIds)
    //             ->lockForUpdate()
    //             ->get();

    //         if ($records->isEmpty()) {
    //             throw new \Exception('No valid FOSA transactions found.');
    //         }

    //         // Enforce same FOSA type
    //         $typeIds = $records->pluck('fosa_type_id')->unique()->filter();
    //         if ($typeIds->count() !== 1) {
    //             throw new \Exception('Selected transactions must be of the same FOSA type.');
    //         }

    //         // Block end-month processed rows
    //         if ($records->where('fosa_end_month_proc', 'Y')->count() > 0) {
    //             throw new \Exception('One or more selected transactions are already end-month processed.');
    //         }

    //         // Resolve main accounts
    //         $defaultFosaMain = DB::table('sacco_sub_account')
    //             ->where('sub_account_id', $defaultFosa)
    //             ->value('sub_account_main_account');

    //         $destMain = $destLedger->sub_account_main_account;

    //         if (!$defaultFosaMain || !$destMain) {
    //             throw new \Exception('Ledger account configuration error.');
    //         }

    //         $batchDoc = 'FOSA-XFER-' . now()->format('YmdHis');
    //         $userId = Auth::id();
    //         $ip = request()->ip();

    //         foreach ($records as $r) {

    //             // Prevent double transfer
    //             $exists = DB::table('sacco_fosas')
    //                 ->where('fosa_ref_id', $r->fosa_id)
    //                 ->exists();

    //             if ($exists) {
    //                 throw new \Exception("Transaction {$r->fosa_id} has already been transferred.");
    //             }

    //             $amt = abs($r->fosa_amount_paying);

    //             // 1. Negative FOSA entry
    //             DB::table('sacco_fosas')->insert([
    //                 'fosa_member_id'      => $r->fosa_member_id,
    //                 'fosa_type_id'        => $r->fosa_type_id,
    //                 'fosa_amount_paying'  => -$amt,
    //                 'fosa_paid_by'        => 'TRANSFER',
    //                 'fosa_period'         => $r->fosa_period,
    //                 'fosa_description'    => 'Transfer out to ledger | ' . $batchDoc,
    //                 'fosa_doc_no'         => $batchDoc,
    //                 'fosa_ref_id'         => $r->fosa_id,
    //                 'fosa_action'         => 'TRANSFER_OUT',
    //                 'fosa_date_paid'      => now(),
    //                 'fosa_by'             => $userId,
    //                 'fosa_ip'             => $ip,
    //                 'fosa_transdate'      => now(),
    //                 'fosa_end_month_proc' => 'N',
    //             ]);

    //             // 2. Ledger transactions (double-entry)
    //             DB::table('sacco_accounts_trans')->insert([
    //                 [
    //                     'accounts_trans_sub_account' => $defaultFosa,
    //                     'accounts_trans_period'      => $r->fosa_period,
    //                     'accounts_trans_debit'       => $amt,
    //                     'accounts_trans_credit'      => 0,
    //                     'accounts_trans_doc_no'      => $batchDoc,
    //                     'accounts_trans_decription' => 'FOSA transfer out',
    //                     'accounts_trans_source'      => 'fosa_transfer',
    //                     'accounts_trans_dat_date'    => now(),
    //                     'accounts_trans_transdate'   => now(),
    //                     'accounts_trans_user_id'     => $userId,
    //                     'accounts_trans_member_id'   => $r->fosa_member_id,
    //                     'accounts_trans_payment_type'=> 'INTERNAL',
    //                     'accounts_trans_ip'          => $ip,
    //                 ],
    //                 [
    //                     'accounts_trans_sub_account' => $destSub,
    //                     'accounts_trans_period'      => $r->fosa_period,
    //                     'accounts_trans_debit'       => 0,
    //                     'accounts_trans_credit'      => $amt,
    //                     'accounts_trans_doc_no'      => $batchDoc,
    //                     'accounts_trans_decription' => 'FOSA transfer in',
    //                     'accounts_trans_source'      => 'fosa_transfer',
    //                     'accounts_trans_dat_date'    => now(),
    //                     'accounts_trans_transdate'   => now(),
    //                     'accounts_trans_user_id'     => $userId,
    //                     'accounts_trans_member_id'   => $r->fosa_member_id,
    //                     'accounts_trans_payment_type'=> 'INTERNAL',
    //                     'accounts_trans_ip'          => $ip,
    //                 ]
    //             ]);

    //             // 3. Update sub-account running totals
    //             DB::table('sacco_sub_account')
    //                 ->where('sub_account_id', $defaultFosa)
    //                 ->update([
    //                     'sub_account_debit' => DB::raw("sub_account_debit + {$amt}")
    //                 ]);

    //             DB::table('sacco_sub_account')
    //                 ->where('sub_account_id', $destSub)
    //                 ->update([
    //                     'sub_account_credit' => DB::raw("sub_account_credit + {$amt}")
    //                 ]);

    //             // 4. Update main-account running totals
    //             DB::table('sacco_main_account')
    //                 ->where('main_account_id', $defaultFosaMain)
    //                 ->update([
    //                     'main_account_debit' => DB::raw("main_account_debit + {$amt}")
    //                 ]);

    //             DB::table('sacco_main_account')
    //                 ->where('main_account_id', $destMain)
    //                 ->update([
    //                     'main_account_credit' => DB::raw("main_account_credit + {$amt}")
    //                 ]);

    //             // 5. Reduce member total FOSA
    //             DB::table('sacco_members')
    //                 ->where('member_id', $r->fosa_member_id)
    //                 ->decrement('member_total_fosa', $amt);
    //         }

    //         DB::commit();
    //         return back()->with('success', 'FOSA transfer completed successfully.');

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', $e->getMessage());
    //     }
    // }
}
