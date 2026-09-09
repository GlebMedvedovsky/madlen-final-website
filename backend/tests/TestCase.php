<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();

        $driver = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (app()->environment() !== 'testing' || config('database.default') !== 'sqlite' || $driver !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Sicherheitsstopp: CMS-Tests dürfen nur SQLite :memory: verwenden.');
        }
    }
}
