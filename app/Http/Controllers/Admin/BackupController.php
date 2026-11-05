<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\BackupService;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        return view('admin.backups', ['backups' => $this->backups->list()]);
    }

    public function store()
    {
        try {
            $file = $this->backups->create();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLog::record('system.backup', null, 'Manual database backup created: '.basename($file));

        return back()->with('success', 'Backup created: '.basename($file));
    }

    public function download(string $name)
    {
        try {
            $path = $this->backups->path($name);
        } catch (RuntimeException) {
            abort(404);
        }
        AuditLog::record('system.backup_download', null, 'Backup downloaded: '.$name);

        return response()->download($path);
    }
}
