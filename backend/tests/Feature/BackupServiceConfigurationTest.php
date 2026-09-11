<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupServiceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/backup-binary-test-'.Str::random(10));
        Storage::fake('local');
        Storage::disk('local')->put('media/keep.txt', 'published media');
        config(['madlen.release_root' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_backup_uses_the_explicitly_configured_mysqldump_binary(): void
    {
        $binary = $this->root.'/mysqldump-test';
        File::ensureDirectoryExists($this->root);
        file_put_contents($binary, "#!/bin/sh\nprintf '%s\\n' '-- isolated mysqldump output'\n");
        chmod($binary, 0700);
        config([
            'madlen.backup.dump_binary' => $binary,
            'madlen.backup.client_binary' => '/usr/bin/mysql',
        ]);
        $this->actingAs(User::factory()->create());

        $backup = app(BackupService::class)->create();

        $this->assertSame('ready', $backup->status);
        $this->assertFileExists($backup->archive_path);
        $this->assertNotEmpty($backup->checksum);
    }

    public function test_netcup_default_is_the_available_absolute_mysqldump_path(): void
    {
        $this->assertSame('/usr/bin/mysqldump', config('madlen.backup.dump_binary'));
        $this->assertSame('/usr/bin/mysql', config('madlen.backup.client_binary'));
    }
}
