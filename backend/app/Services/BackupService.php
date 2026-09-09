<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    public function __construct(private RuntimeFilesystem $files) {}

    public function create(): Backup
    {
        $backup = Backup::query()->create(['status' => 'building', 'created_by' => Auth::id()]);
        $root = rtrim(config('madlen.release_root'), '/').'/backups/'.$backup->id;
        $this->files->ensureDirectory($root);

        try {
            $dump = new Process([
                'mariadb-dump', '--single-transaction', '--skip-comments',
                '-h', config('database.connections.mysql.host'),
                '-P', (string) config('database.connections.mysql.port'),
                '-u', config('database.connections.mysql.username'),
                config('database.connections.mysql.database'),
            ], env: ['MYSQL_PWD' => config('database.connections.mysql.password')]);
            $dump->setTimeout(180);
            $dump->run();
            if (! $dump->isSuccessful()) throw new RuntimeException('Datenbanksicherung fehlgeschlagen: '.trim($dump->getErrorOutput()));
            $this->files->write($root.'/database.sql', $dump->getOutput());

            $mediaRoot = storage_path('app/private');
            $media = new Process(['tar', '-czf', $root.'/media.tar.gz', '-C', $mediaRoot, 'media']);
            $media->setTimeout(180);
            $media->run();
            if (! $media->isSuccessful()) throw new RuntimeException('Mediensicherung fehlgeschlagen: '.trim($media->getErrorOutput()));

            $this->files->write($root.'/checksums.json', json_encode([
                'database.sql' => hash_file('sha256', $root.'/database.sql'),
                'media.tar.gz' => hash_file('sha256', $root.'/media.tar.gz'),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $archive = dirname($root).'/madlen-'.$backup->id.'.tar.gz';
            $pack = new Process(['tar', '-czf', $archive, '-C', $root, '.']);
            $pack->run();
            if (! $pack->isSuccessful()) throw new RuntimeException('Backup-Archiv konnte nicht erstellt werden.');

            $backup->update(['status' => 'ready', 'archive_path' => $archive, 'checksum' => hash_file('sha256', $archive)]);
        } catch (\Throwable $error) {
            $backup->update(['status' => 'failed', 'error_message' => mb_substr($error->getMessage(), 0, 60000)]);
            throw $error;
        }

        return $backup->refresh();
    }

    public function restoreIntoTest(Backup $backup): string
    {
        if ($backup->status !== 'ready' || ! is_file($backup->archive_path)) throw new RuntimeException('Das Backup ist nicht wiederherstellbar.');
        if (! hash_equals($backup->checksum, hash_file('sha256', $backup->archive_path))) throw new RuntimeException('Die Backup-Prüfsumme stimmt nicht.');

        $database = env('MADLEN_RESTORE_DB_DATABASE', '');
        if (! str_ends_with($database, '_restore_test')) {
            throw new RuntimeException('Sicherheitsstopp: Das Wiederherstellungsziel muss auf _restore_test enden.');
        }
        $target = rtrim(config('madlen.release_root'), '/').'/restore-tests/'.$backup->id.'-'.Str::random(6);
        $this->files->ensureDirectory($target);
        $unpack = new Process(['tar', '-xzf', $backup->archive_path, '-C', $target]);
        $unpack->run();
        if (! $unpack->isSuccessful()) throw new RuntimeException('Backup-Archiv konnte nicht gelesen werden.');

        $checksums = json_decode(file_get_contents($target.'/checksums.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($checksums as $file => $checksum) {
            if (! hash_equals($checksum, hash_file('sha256', $target.'/'.$file))) throw new RuntimeException("Prüfsumme für {$file} stimmt nicht.");
        }

        $restore = new Process([
            'mariadb', '-h', env('MADLEN_RESTORE_DB_HOST', 'db_test'), '-P', '3306',
            '-u', env('MADLEN_RESTORE_DB_USERNAME', 'madlen_restore'), $database,
        ], env: ['MYSQL_PWD' => env('MADLEN_RESTORE_DB_PASSWORD')]);
        $restore->setInput(file_get_contents($target.'/database.sql'));
        $restore->setTimeout(180);
        $restore->run();
        if (! $restore->isSuccessful()) throw new RuntimeException('Test-Wiederherstellung der Datenbank fehlgeschlagen: '.trim($restore->getErrorOutput()));

        $this->files->ensureDirectory($target.'/restored-media');
        $restoreMedia = new Process(['tar', '-xzf', $target.'/media.tar.gz', '-C', $target.'/restored-media']);
        $restoreMedia->run();
        if (! $restoreMedia->isSuccessful() || ! is_dir($target.'/restored-media/media')) {
            throw new RuntimeException('Test-Wiederherstellung der Medien fehlgeschlagen.');
        }

        return $target;
    }
}
