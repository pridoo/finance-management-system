<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\DatabaseBackup;

/**
 * Backup page of the MSWDO side.
 *
 * Lists the .sql backups saved in storage/app/backup and lets the user make a
 * new one, download one, or delete one. The automatic backup runs every day at
 * 5:00 PM (Asia/Manila) — see App\Console\Kernel and RunDailyBackup.
 *
 * Routes:
 *   GET    /backup               -> index()
 *   POST   /backup               -> store()     "Back up now" button
 *   GET    /backup/{file}        -> download()
 *   DELETE /backup/{file}        -> destroy()
 */
class BackupController extends Controller
{
    public function __construct(private DatabaseBackup $backup)
    {
    }

    public function index()
    {
        $backups = $this->backup->all();
        $folder = DatabaseBackup::directory();

        return view('mswdo.backup.index', compact('backups', 'folder'));
    }

    public function store()
    {
        try {
            $name = $this->backup->create('manual');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }

        AuditLog::record("Created database backup: {$name}", 'Backup');

        return back()->with('success', "Backup saved as {$name}.");
    }

    public function download(string $file)
    {
        $path = $this->backup->path($file);
        abort_unless($path, 404);

        AuditLog::record("Downloaded database backup: {$file}", 'Backup');

        return response()->download($path, $file, ['Content-Type' => 'application/sql']);
    }

    public function destroy(string $file)
    {
        $path = $this->backup->path($file);
        abort_unless($path, 404);

        unlink($path);
        AuditLog::record("Deleted database backup: {$file}", 'Backup');

        return back()->with('success', "Deleted {$file}.");
    }
}
