<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteClass;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! ViteClass::hasMacro('fake')) {
            ViteClass::macro('fake', function (?string $devServerUrl = null) {
                $hotFile = storage_path('framework/testing-vite.hot');

                if (! is_dir(dirname($hotFile))) {
                    mkdir(dirname($hotFile), 0775, true);
                }

                file_put_contents($hotFile, rtrim($devServerUrl ?? 'http://127.0.0.1:5173', '/'));

                return $this->useHotFile($hotFile);
            });
        }

        Vite::fake();
    }
}
