<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Roles are reference data every authorization path depends on, and
        // RefreshDatabase truncates them between tests. Seeding here rather
        // than in each test keeps the tests about behaviour.
        foreach (['student', 'lecturer', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // spatie caches the role/permission map in-process; without this a role
        // created after the first lookup in a test run is invisible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
