<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Database backups using mysqldump, stored gzipped in storage/app/backups.
 */
class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function list(): array
    {
        return collect(File::files($this->directory()))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])
            ->values()->all();
    }

    public function create(): string
    {
        $db = config('database.connections.mysql');
        $file = $this->directory().DIRECTORY_SEPARATOR.'hrms-'.now()->format('Ymd-His').'.sql.gz';

        $cmd = [config('hrms.mysqldump'), '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', $db['database']];
        $env = $db['password'] !== '' && $db['password'] !== null ? ['MYSQL_PWD' => $db['password']] : [];

        $process = new Process($cmd, null, $env, null, 600);
        $gz = gzopen($file, 'wb6');
        $process->run(function ($type, $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });
        gzclose($gz);

        if (! $process->isSuccessful()) {
            @unlink($file);
            throw new RuntimeException('Backup failed: '.trim($process->getErrorOutput()));
        }

        $this->prune();

        return $file;
    }

    public function restore(string $name): void
    {
        $path = $this->path($name);
        $db = config('database.connections.mysql');
        $cmd = [config('hrms.mysql'), '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--default-character-set=utf8mb4', $db['database']];
        $env = $db['password'] !== '' && $db['password'] !== null ? ['MYSQL_PWD' => $db['password']] : [];

        $process = new Process($cmd, null, $env, null, 1200);
        $process->setInput(gzdecode(file_get_contents($path)));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore failed: '.trim($process->getErrorOutput()));
        }
    }

    public function path(string $name): string
    {
        $name = basename($name);
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! preg_match('/^hrms-\d{8}-\d{6}\.sql\.gz$/', $name) || ! is_file($path)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $path;
    }

    /** Keep the most recent N backups. */
    private function prune(): void
    {
        foreach (array_slice($this->list(), (int) config('hrms.backup_keep')) as $old) {
            @unlink($this->directory().DIRECTORY_SEPARATOR.$old['name']);
        }
    }
}
