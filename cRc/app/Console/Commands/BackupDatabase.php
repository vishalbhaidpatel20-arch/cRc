<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Dumps the app database to `crc-YYYYMMDD-HHMMSS.sql.gz` in config('crc.backup.path') and deletes backups older than
 * config('crc.backup.keep_days'). Read-only on the database (mysqldump with --single-transaction).
 *
 * The password is passed in the MYSQL_PWD environment variable, never on the command line, so it does not show up in
 * the process list or in logs. Restore: `gunzip -c crc-....sql.gz | mysql -u USER -p DBNAME`.
 */
class BackupDatabase extends Command
{
    protected $signature = 'crc:backup-db {--path= : Folder to write to (default: config crc.backup.path)}';

    protected $description = 'Write a gzipped mysqldump of the app database and prune old backups';

    /** Only files this command wrote are ever pruned. */
    public const FILE_PATTERN = '/^crc-\d{8}-\d{6}\.sql\.gz$/';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('crc:backup-db only supports MySQL/MariaDB.');

            return self::FAILURE;
        }

        $dir = rtrim($this->option('path') ?: config('crc.backup.path'), '\\/');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd-His');
        $sqlFile = $dir.DIRECTORY_SEPARATOR."crc-{$stamp}.sql";
        $gzFile = $sqlFile.'.gz';

        $result = Process::env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->timeout(900)
            ->run([
                config('crc.backup.mysqldump'),
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.$db['username'],
                '--single-transaction',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file='.$sqlFile,
                $db['database'],
            ]);

        if ($result->failed() || ! is_file($sqlFile) || filesize($sqlFile) === 0) {
            File::delete($sqlFile);
            $this->error('Backup failed: '.trim($result->errorOutput() ?: 'mysqldump wrote no output.'));

            return self::FAILURE;
        }

        try {
            $this->gzip($sqlFile, $gzFile);
        } catch (Throwable $e) {
            File::delete([$sqlFile, $gzFile]);
            $this->error('Backup failed while compressing: '.$e->getMessage());

            return self::FAILURE;
        }
        File::delete($sqlFile);

        $pruned = $this->prune($dir, (int) config('crc.backup.keep_days'));

        $this->info(sprintf('Backup written: %s (%s KB). %d old backup(s) removed.', $gzFile, number_format(filesize($gzFile) / 1024, 1), $pruned));

        return self::SUCCESS;
    }

    private function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        if (! $in || ! $out) {
            throw new \RuntimeException('cannot open the dump or the .gz file');
        }

        try {
            while (! feof($in)) {
                gzwrite($out, fread($in, 1024 * 512));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    /** Deletes this command's backups older than $keepDays days; 0 or less keeps everything. */
    private function prune(string $dir, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::files($dir) as $file) {
            if (preg_match(self::FILE_PATTERN, $file->getFilename()) && $file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        return $removed;
    }
}
