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
         * STEP 2: Fetch uncategorised FOSA rows (hard cap = 500)
         */
        $fosas = DB::table('sacco_fosas')
            ->whereNull('fosa_type_id')
            ->orderBy('fosa_id')   // stable batching
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

    // Break type name into words
    $typeWords = preg_split(
        '/[^A-Z0-9\-]+/',
        $type['token'],
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if (empty($typeWords)) {
        continue;
    }

    // If last word is FEE, drop it (generic descriptor)
    if (end($typeWords) === 'FEE') {
        array_pop($typeWords);
    }

    if (empty($typeWords)) {
        continue;
    }

    // Require remaining words to exist
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
            ->whereNull('fosa_type_id')
            ->update([
                'fosa_type_id' => $type['id'],
            ]);

        break;
    }
}

        }
    }
}
