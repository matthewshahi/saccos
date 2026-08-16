<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class SyncRouteRightsModules extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'modules:sync-route-rights
                            {--dry-run : Show missing modules without inserting them}';

    /**
     * The console command description.
     */
    protected $description = 'Find check_user_rights permissions used by registered routes and add missing modules to sacco_modules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning registered routes for user-rights modules...');

        try {
            /*
            |--------------------------------------------------------------------------
            | 1. Collect rights from REGISTERED Laravel routes
            |--------------------------------------------------------------------------
            |
            | We intentionally inspect Laravel's registered routes rather than
            | reading routes/web.php as plain text.
            |
            | This means:
            | - commented-out routes are ignored;
            | - inactive PHP code is ignored;
            | - only routes actually loaded by Laravel are considered.
            |
            |--------------------------------------------------------------------------
            */

            $moduleNames = [];

            foreach (Route::getRoutes() as $route) {
                foreach ($route->gatherMiddleware() as $middleware) {
                    if (!is_string($middleware)) {
                        continue;
                    }

                    $prefix = 'check_user_rights:';

                    if (!str_starts_with($middleware, $prefix)) {
                        continue;
                    }

                    $moduleName = trim(substr($middleware, strlen($prefix)));

                    if ($moduleName === '') {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | sacco_modules.module_name is VARCHAR(100)
                    |--------------------------------------------------------------------------
                    */

                    if (strlen($moduleName) > 100) {
                        $this->warn(
                            "Skipped '{$moduleName}' because it exceeds 100 characters."
                        );

                        continue;
                    }

                    $moduleNames[] = $moduleName;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Remove duplicate rights
            |--------------------------------------------------------------------------
            */

            $moduleNames = array_values(array_unique($moduleNames));

            sort($moduleNames, SORT_STRING);

            $totalRouteModules = count($moduleNames);

            $this->line(
                "Unique route rights found: {$totalRouteModules}"
            );

            if ($totalRouteModules === 0) {
                $this->warn('No check_user_rights middleware was found.');

                return self::SUCCESS;
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Determine which modules are missing
            |--------------------------------------------------------------------------
            */

            $missingModules = [];

            foreach ($moduleNames as $moduleName) {
                $exists = DB::table('sacco_modules')
                    ->where('module_name', $moduleName)
                    ->exists();

                if (!$exists) {
                    $missingModules[] = $moduleName;
                }
            }

            $missingCount = count($missingModules);

            /*
            |--------------------------------------------------------------------------
            | Nothing missing
            |--------------------------------------------------------------------------
            */

            if ($missingCount === 0) {
                $this->info(
                    'All route rights already exist in sacco_modules. Nothing to add.'
                );

                return self::SUCCESS;
            }

            $this->warn(
                "Missing modules found: {$missingCount}"
            );

            foreach ($missingModules as $moduleName) {
                $this->line(" - {$moduleName}");
            }

            /*
            |--------------------------------------------------------------------------
            | Dry-run mode
            |--------------------------------------------------------------------------
            |
            | Useful for manual testing:
            |
            | php artisan modules:sync-route-rights --dry-run
            |
            |--------------------------------------------------------------------------
            */

            if ($this->option('dry-run')) {
                $this->info(
                    'Dry run complete. No database records were inserted.'
                );

                return self::SUCCESS;
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Insert missing modules
            |--------------------------------------------------------------------------
            |
            | module_userid is NULL because this command runs automatically and
            | there is no authenticated user.
            |
            | module_ip uses 127.0.0.1 to identify a server-side process.
            |
            | NO sacco_userrights records are created here.
            |
            |--------------------------------------------------------------------------
            */

            $created = 0;

            foreach ($missingModules as $moduleName) {
                DB::transaction(function () use ($moduleName, &$created) {

                    /*
                    |--------------------------------------------------------------------------
                    | Check again inside the transaction
                    |--------------------------------------------------------------------------
                    |
                    | This reduces the possibility of inserting a duplicate if the
                    | module was created between the initial scan and this insert.
                    |
                    |--------------------------------------------------------------------------
                    */

                    $exists = DB::table('sacco_modules')
                        ->where('module_name', $moduleName)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    DB::table('sacco_modules')->insert([
                        'module_name'        => $moduleName,
                        'module_active'      => 'Y',
                        'module_description' => $moduleName,
                        'module_deleted'     => 'N',
                        'module_userid'      => null,
                        'module_ip'          => '127.0.0.1',
                        'module_transdate'   => now(),
                    ]);

                    $created++;

                    $this->info(
                        "Created module: {$moduleName}"
                    );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Final result
            |--------------------------------------------------------------------------
            */

            $this->newLine();

            $this->info('Module synchronization completed successfully.');

            $this->table(
                ['Item', 'Count'],
                [
                    ['Route rights found', $totalRouteModules],
                    ['Missing modules detected', $missingCount],
                    ['Modules created', $created],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Module synchronization failed.');

            $this->error($e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}