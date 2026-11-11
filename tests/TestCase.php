<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Se ejecuta antes de RefreshDatabase: nunca migrar una base real desde las pruebas.
        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || filled($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Pruebas detenidas: se requiere testing con SQLite :memory: y sin DB_URL.');
        }

        return $app;
    }
}
