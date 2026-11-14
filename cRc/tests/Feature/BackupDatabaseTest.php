<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** crc:backup-db: mysqldump is faked, so nothing touches a real database or the real backup folder. */
class BackupDatabaseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config(['crc.backup.path' => $this->dir, 'crc.backup.keep_days' => 14, 'crc.backup.mysqldump' => 'mysqldump']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Fakes mysqldump: writes $sql to the --result-file it was given and exits with $exit. */
    private function fakeDump(string $sql = "CREATE TABLE t (x int);\n", int $exit = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($sql, $exit) {
            foreach ((array) $process->command as $arg) {
                if ($exit === 0 && str_starts_with($arg, '--result-file=')) {
                    file_put_contents(substr($arg, strlen('--result-file=')), $sql);
                }
            }

            return Process::result(errorOutput: $exit ? 'Access denied' : '', exitCode: $exit);
        });
    }

    public function test_it_writes_a_gzipped_dump_and_removes_the_plain_file(): void
    {
        $this->fakeDump("CREATE TABLE tickets (ticket_id int);\n");

        $this->artisan('crc:backup-db')->assertSuccessful();

        $files = File::files($this->dir);
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^crc-\d{8}-\d{6}\.sql\.gz$/', $files[0]->getFilename());
        $this->assertSame("CREATE TABLE tickets (ticket_id int);\n", gzdecode(file_get_contents($files[0]->getPathname())));
    }

    public function test_the_password_goes_in_the_environment_not_the_command_line(): void
    {
        config(['database.connections.'.config('database.default').'.password' => 's3cret-pw']);
        $this->fakeDump();

        $this->artisan('crc:backup-db')->assertSuccessful();

        Process::assertRan(function (PendingProcess $process) {
            return ! str_contains(implode(' ', (array) $process->command), 's3cret-pw')
                && ($process->environment['MYSQL_PWD'] ?? null) === 's3cret-pw'
                && in_array('--single-transaction', (array) $process->command, true)
                && in_array('--triggers', (array) $process->command, true);
        });
    }

    public function test_a_failed_dump_leaves_no_file_and_fails(): void
    {
        $this->fakeDump(exit: 2);

        $this->artisan('crc:backup-db')->assertFailed();

        $this->assertSame([], File::files($this->dir));
    }

    public function test_old_backups_are_pruned_but_recent_and_foreign_files_are_kept(): void
    {
        File::ensureDirectoryExists($this->dir);
        $old = $this->dir.'/crc-20200101-020000.sql.gz';
        $recent = $this->dir.'/crc-20200102-020000.sql.gz';
        $foreign = $this->dir.'/notes.sql.gz';
        foreach ([$old, $recent, $foreign] as $file) {
            file_put_contents($file, 'x');
        }
        touch($old, now()->subDays(15)->getTimestamp());
        touch($recent, now()->subDays(3)->getTimestamp());
        touch($foreign, now()->subDays(30)->getTimestamp());
        $this->fakeDump();

        $this->artisan('crc:backup-db')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($foreign);
        $this->assertCount(3, File::files($this->dir)); // recent + foreign + the new backup
    }

    public function test_the_daily_backup_is_scheduled_for_production_only(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'crc:backup-db'));

        $this->assertNotNull($event, 'crc:backup-db is not scheduled');
        $this->assertSame('0 13 * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertSame(['production'], $event->environments);
    }
}
