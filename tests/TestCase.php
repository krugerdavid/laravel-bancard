<?php

namespace KrugerDavid\Bancard\Tests;

use KrugerDavid\Bancard\BancardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BancardServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('bancard.private_key', 'test_private_key_40_chars_xxxxxxxxxxxx');
        config()->set('bancard.public_key', 'test_public_key_32_chars_xxxx');
        config()->set('bancard.staging', true);
    }
}
