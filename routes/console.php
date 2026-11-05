<?php

use App\Models\AuditLog;
use App\Services\BackupService;
use App\Services\ContractService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('contracts:monitor', function (ContractService $contracts) {
    $changed = $contracts->refreshStatuses();
    $sent = $contracts->sendReminders();
    $this->info("Statuses updated: {$changed}. Reminders generated: {$sent}.");
})->purpose('Update contract expiry statuses and send due expiry reminders to Managers');

Artisan::command('hrms:backup', function (BackupService $backups) {
    $file = $backups->create();
    AuditLog::record('system.backup', null, 'Database backup created: '.basename($file));
    $this->info('Backup written to '.$file);
})->purpose('Create a gzipped database backup in storage/app/backups');

Artisan::command('hrms:restore {file : Backup file name, e.g. hrms-20261105-010000.sql.gz} {--force}', function (BackupService $backups) {
    $file = $this->argument('file');
    if (! $this->option('force') && ! $this->confirm("Restore {$file}? This replaces ALL current data.")) {
        return;
    }
    $backups->restore($file);
    AuditLog::record('system.restore', null, 'Database restored from '.$file);
    $this->info('Database restored from '.$file);
})->purpose('Restore the database from a backup file');

Schedule::command('contracts:monitor')->dailyAt('07:00');
Schedule::command('hrms:backup')->dailyAt('01:00');
