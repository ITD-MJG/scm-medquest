<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('shows the products list to an admin', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    $this->actingAs($admin)->get('/dashboard/products')->assertSuccessful();
});

it('hides the products list from a supply chain officer', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $this->actingAs($officer)->get('/dashboard/products')->assertRedirect(route('logout.403'));
});
