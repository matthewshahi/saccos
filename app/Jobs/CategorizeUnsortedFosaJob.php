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
         * - Active only
         * - Exclude generic FOSA bucket
         * - Deterministic ordering
         */
        $types = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->where('type_name', '!=', 'FOSA')
            ->orderBy('type_id')
            ->get(['type_id', 'type_name']);

        if ($types->isEmpty()) {
            return;
        }

        /**
         * STEP 2: Normalize type tokens once
         */
        $tokens = $types->map(function ($t) {
            return [
                'id'    => $t->type_id,
                'token' => strtoupper(trim($t->type_name)),
            ];
        })->values();

        /**
         * STEP 3: Fetch uncategorised FOSA rows (bounded batch)
         */
        $fosas = DB::table('sacco_fosas')
            ->whereNull('fosa_type_id')
            ->orderBy('fosa_id')
            ->limit(500)
            ->get([
                'fosa_id',
                'fosa_description',
                'fosa_doc_no',
            ]);

        if ($fosas->isEmpty()) {
            return;
        }

        /**
         * STEP 4: Deterministic categorisation
         */
        foreach ($fosas as $fosa) {

            // Build normalized haystack
            $haystack = trim(strtoupper(
                ($fosa->fosa_description ?? '') . ' ' . ($fosa->fosa_doc_no ?? '')
            ));

            if ($haystack === '') {
                continue;
            }

            // Tokenize haystack once per row
            $hayWords = preg_split(
                '/[^A-Z0-9\-]+/',
                $haystack,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if (empty($hayWords)) {
                continue;
            }

            $haySet = array_fill_keys($hayWords, true);

            foreach ($tokens as $type) {

                /**
                 * Break type name into words
                 */
                $typeWords = preg_split(
                    '/[^A-Z0-9\-]+/',
                    $type['token'],
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );

                if (empty($typeWords)) {
                    continue;
                }

                /**
                 * Drop generic trailing descriptor:
                 *   REGISTRATION FEE → REGISTRATION
                 *   NTSA FEE         → NTSA
                 */
                if (end($typeWords) === 'FEE') {
                    array_pop($typeWords);
                }

                if (empty($typeWords)) {
                    continue;
                }

                /**
                 * Require ALL remaining words to exist
                 */
                $allMatch = true;
                foreach ($typeWords as $word) {
                    if (!isset($haySet[$word])) {
                        $allMatch = false;
                        break;
                    }
                }

                if ($allMatch) {
                    DB::table('sacco_fosas')
                        ->where('fosa_id', $fosa->fosa_id)
                        ->whereNull('fosa_type_id') // safety against race conditions
                        ->update([
                            'fosa_type_id' => $type['id'],
                        ]);

                    // Deterministic first match only
                    break;
                }
            }
        }
    }
}
