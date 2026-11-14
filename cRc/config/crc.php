<?php

return [
    /*
     | Rows the server sends per list page. The data grid (resources/js/grid.jsx) pages through them on screen, so this is
     | large on purpose: a list only falls back to the server's Previous/Next bar when it has more rows than this.
     | Tests set it to 20 (tests/TestCase.php) so paging behaviour stays easy to exercise.
     */
    'per_page' => (int) env('CRC_PER_PAGE', 200),

    /*
     | Database backups (`php artisan crc:backup-db`): a gzipped mysqldump of the app database, written to `path` and
     | pruned after `keep_days`. On the server the scheduler runs it daily (production only, see routes/console.php);
     | on a Windows dev PC a Task Scheduler task runs it (deploy/windows/install-tasks.ps1).
     */
    'backup' => [
        // `?:` so an empty `CRC_BACKUP_PATH=` line (as in .env.example) still falls back to the default.
        'path' => env('CRC_BACKUP_PATH') ?: storage_path('backups'),
        'keep_days' => (int) env('CRC_BACKUP_KEEP_DAYS', 14),
        'mysqldump' => env('CRC_MYSQLDUMP') ?: 'mysqldump',
    ],
];
