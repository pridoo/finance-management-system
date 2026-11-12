<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Writes the whole database out as a plain .sql file in storage/app/backup.
 *
 * Done in PHP rather than by calling mysqldump.exe so it works the same on
 * XAMPP and on shared hosting, where shelling out is usually disabled.
 *
 * File names carry the date and time of the backup (Philippine time) and
 * whether it was the automatic 5 PM run or a manual click:
 *     backup_2026-11-12_17-00-00_auto.sql
 *     backup_2026-11-12_09-31-44_manual.sql
 *
 * The .sql can be restored with phpMyAdmin → Import.
 */
class DatabaseBackup
{
    public const TIMEZONE = 'Asia/Manila';

    /** Only names this class produces are ever served or deleted. */
    public const NAME_PATTERN = '/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(auto|manual)\.sql$/';

    public static function directory(): string
    {
        return storage_path('app/backup');
    }

    /**
     * Creates a backup and returns its file name.
     */
    public function create(string $type = 'manual'): string
    {
        $type = $type === 'auto' ? 'auto' : 'manual';
        $dir = $this->ensureDirectory();

        $now = Carbon::now(self::TIMEZONE);
        $name = 'backup_'.$now->format('Y-m-d_H-i-s')."_{$type}.sql";
        $path = $dir.DIRECTORY_SEPARATOR.$name;

        // Written to a .part file first so a half-finished backup never shows
        // up in the list as if it were a good one.
        $tmp = $path.'.part';
        $fh = fopen($tmp, 'w');

        try {
            $this->dump($fh, $now);
        } catch (\Throwable $e) {
            fclose($fh);
            @unlink($tmp);
            throw $e;
        }

        fclose($fh);
        rename($tmp, $path);

        return $name;
    }

    /**
     * Has the automatic backup already run today (Philippine date)?
     */
    public function hasAutoBackupToday(): bool
    {
        $today = Carbon::now(self::TIMEZONE)->format('Y-m-d');

        return File::exists(self::directory())
            && count(glob(self::directory()."/backup_{$today}_*_auto.sql")) > 0;
    }

    /**
     * Every backup on disk, newest first.
     *
     * @return array<int, array{name:string, size:int, created_at:Carbon, type:string}>
     */
    public function all(): array
    {
        if (! File::exists(self::directory())) {
            return [];
        }

        return collect(File::files(self::directory()))
            ->filter(fn ($f) => preg_match(self::NAME_PATTERN, $f->getFilename()))
            ->map(function ($f) {
                preg_match('/^backup_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})_(auto|manual)/', $f->getFilename(), $m);

                return [
                    'name'       => $f->getFilename(),
                    'size'       => $f->getSize(),
                    'created_at' => Carbon::parse("{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}", self::TIMEZONE),
                    'type'       => $m[5],
                ];
            })
            ->sortByDesc('name')
            ->values()
            ->all();
    }

    /**
     * Full path of a backup, or null if the name is not one of ours.
     */
    public function path(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $path = self::directory().DIRECTORY_SEPARATOR.$name;

        return File::exists($path) ? $path : null;
    }

    private function ensureDirectory(): string
    {
        $dir = self::directory();
        File::ensureDirectoryExists($dir);

        // The project sits inside htdocs, so without this Apache would hand
        // the .sql files to anyone who guessed the URL.
        $htaccess = $dir.DIRECTORY_SEPARATOR.'.htaccess';
        if (! File::exists($htaccess)) {
            File::put($htaccess, "Require all denied\nDeny from all\n");
        }

        return $dir;
    }

    /**
     * @param  resource  $fh
     */
    private function dump($fh, Carbon $now): void
    {
        $pdo = DB::connection()->getPdo();
        $database = DB::connection()->getDatabaseName();

        fwrite($fh, "-- MSWDO San Agustin database backup\n");
        fwrite($fh, "-- Database: `{$database}`\n");
        fwrite($fh, '-- Created: '.$now->format('F d, Y h:i:s A')." (Asia/Manila)\n\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = [];
        $views = [];
        foreach (DB::select('SHOW FULL TABLES') as $row) {
            [$name, $kind] = array_values((array) $row);
            $kind === 'VIEW' ? $views[] = $name : $tables[] = $name;
        }

        foreach ($tables as $table) {
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");

            fwrite($fh, "-- --------------------------------------------------------\n");
            fwrite($fh, "-- Table `{$table}`\n\n");
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fh, $create['Create Table'].";\n\n");

            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $batch = [];

            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                $batch[] = '('.implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    $row
                )).')';

                // 100 rows per INSERT keeps each statement well under
                // MySQL's max_allowed_packet when the file is imported.
                if (count($batch) === 100) {
                    fwrite($fh, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch) {
                fwrite($fh, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
            }

            fwrite($fh, "\n");
        }

        foreach ($views as $view) {
            $create = (array) DB::selectOne("SHOW CREATE VIEW `{$view}`");
            fwrite($fh, "DROP VIEW IF EXISTS `{$view}`;\n");
            fwrite($fh, $create['Create View'].";\n\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    }
}
