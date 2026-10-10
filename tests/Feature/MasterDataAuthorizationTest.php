<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('denies the products create page to a supply chain officer', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $this->actingAs($officer)->get('/dashboard/products/create')
        ->assertRedirect(route('logout.403'));
});

it('allows the products create page to an admin', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    $this->actingAs($admin)->get('/dashboard/products/create')
        ->assertSuccessful();
});

it('denies the products edit page to a supply chain officer', function () {
    $product = Product::factory()->create();
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $this->actingAs($officer)->get("/dashboard/products/{$product->getKey()}/edit")
        ->assertRedirect(route('logout.403'));
});

it('allows the products edit page to an admin', function () {
    $product = Product::factory()->create();
    $admin = User::factory()->create()->assignRole('Admin');

    $this->actingAs($admin)->get("/dashboard/products/{$product->getKey()}/edit")
        ->assertSuccessful();
});
