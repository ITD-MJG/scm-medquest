<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(RefreshDatabase::class)->in('Unit/Models', 'Unit/Services', 'Unit/Mcp');

// One shared role set for tests. Scope is Unit/Services only: a Feature-scope hook runs
// before each file's own beforeEach, so the pre-existing inline Role::create() calls in
// tests/Feature/TemperatureHumidityNotificationTest.php would throw RoleAlreadyExists.
pest()->in('Unit/Services')->beforeEach(function () {
    $this->seed(RoleSeeder::class);
});
