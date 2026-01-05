<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CategorizeUnsortedFosaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        /**
         * STEP 1: Load authoritative FOSA types
         * Exclude the default catch-all bucket
         */
        $types = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->where('type_name', '!=', 'FOSA')
            ->orderBy('type_id') // deterministic order
            ->get(['type_id', 'type_name']);

        if ($types->isEmpty()) {
            return;
        }

        /**
         * Pre-normalize tokens ONCE
         */
        $tokens = $types->map(function ($t) {
            return [
                'id'    => $t->type_id,
                'token' => strtoupper(trim($t->type_name)),
            ];
        });

        /**
         * STEP 2: Fetch uncategorised FOSA rows (hard cap = 30)
         */
        $fosas = DB::table('sacco_fosas')
            ->whereNull('fosa_type_id')
            ->orderBy('fosa_id')   // stable batching
            ->limit(30)
            ->get([
                'fosa_id',
                'fosa_description',
                'fosa_doc_no',
            ]);

        if ($fosas->isEmpty()) {
            return;
        }

        /**
         * STEP 3: Deterministic categorisation
         */
        foreach ($fosas as $fosa) {

            $haystack = strtoupper(
                ($fosa->fosa_description ?? '') . ' ' . ($fosa->fosa_doc_no ?? '')
            );

            if ($haystack === '') {
                continue;
            }

            foreach ($tokens as $type) {

                /**
                 * Explicit token match only
                 * No fuzzy guessing
                 */
                if (str_contains($haystack, $type['token'])) {

                    DB::table('sacco_fosas')
                        ->where('fosa_id', $fosa->fosa_id)
                        ->update([
                            'fosa_type_id' => $type['id'],
                        ]);

                    // Stop after first deterministic match
                    break;
                }
            }
        }
    }
}
