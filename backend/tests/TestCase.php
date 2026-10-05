<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'secondhand_marketplace_test'
            || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'secondhand_marketplace_test') {
            throw new RuntimeException('Refusing database tests outside the dedicated MySQL test database.');
        }

        return $app;
    }
}
