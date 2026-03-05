<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\SyncRolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteClass;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('permissions')) {
            $this->seed(SyncRolesAndPermissionsSeeder::class);
        }

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

    /**
     * @param  Authenticatable  $user
     */
    public function actingAs($user, $guard = null)
    {
        if ($user instanceof User && ! $user->roles()->exists()) {
            $adminRole = Role::query()
                ->where('name', 'Admin')
                ->where('guard_name', 'web')
                ->first();

            if ($adminRole !== null) {
                $user->assignRole($adminRole);
            }
        }

        return parent::actingAs($user, $guard);
    }
}
