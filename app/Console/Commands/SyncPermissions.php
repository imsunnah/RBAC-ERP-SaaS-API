<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RBAC\RoutePermissionSyncer;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync {--force : Force execution even in production environments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan routes for permission middleware and synchronize missing ones with the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force) {
            $this->warn('Force mode active. Running permission synchronization on production/live environment.');
        }

        $this->info('Scanning routes for permission-protected URLs...');

        try {
            RoutePermissionSyncer::sync($force);
            $this->info('Permissions successfully synchronized to the database!');
        } catch (\Throwable $e) {
            $this->error('An error occurred during synchronization: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
