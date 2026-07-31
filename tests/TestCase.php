<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base class for tests that need the Laravel application.
 *
 * RefreshDatabase lives here rather than on each test class. This class seeds
 * roles in setUp(), which is a database read, so every subclass must have a
 * migrated schema — and relying on each subclass to remember `use
 * RefreshDatabase` made that a silent trap: a test class that forgot it passed
 * on a developer machine (where the test database still had tables from an
 * earlier run) and failed in CI against an empty database with
 * "Table 'roles' doesn't exist".
 *
 * Pure unit tests that need no application or database should extend
 * PHPUnit\Framework\TestCase directly instead of this class.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // RefreshDatabase runs the migrations from within parent::setUp(),
        // so the schema exists by the time seeding starts.
        parent::setUp();

        // Roles are reference data that every authorization path depends on,
        // and RefreshDatabase truncates them between tests. Seeded through the
        // application's own RoleSeeder so the list of roles is defined once.
        $this->seed(RoleSeeder::class);

        // spatie caches the role/permission map in-process; without this a role
        // created after the first lookup in a test run is invisible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
