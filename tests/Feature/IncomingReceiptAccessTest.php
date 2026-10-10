<?php

use App\Models\IncomingReceipt;
use App\Models\Location;
use App\Models\User;
use App\Services\SignOffService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('shows an officer only their own location receipts', function () {
    $mine = Location::factory()->create();
    $theirs = Location::factory()->create();
    $officer = User::factory()->create(['location_id' => $mine->id])->assignRole('Supply Chain Officer');

    IncomingReceipt::factory()->create(['location_id' => $mine->id, 'receipt_no' => 'MINE/1']);
    IncomingReceipt::factory()->create(['location_id' => $theirs->id, 'receipt_no' => 'THEIRS/1']);

    $this->actingAs($officer)->get('/dashboard/incoming-receipts')
        ->assertSuccessful()
        ->assertSee('MINE/1')
        ->assertDontSee('THEIRS/1');
});

it('blocks editing a signed receipt for a non super admin', function () {
    $location = Location::factory()->create();
    $receipt = IncomingReceipt::factory()->create(['location_id' => $location->id]);
    $officer = User::factory()->create(['location_id' => $location->id])->assignRole('Supply Chain Officer');

    app(SignOffService::class)->record($receipt, 'received_and_processed', $officer);

    $this->actingAs($officer)
        ->get("/dashboard/incoming-receipts/{$receipt->getKey()}/edit")
        ->assertRedirect(route('logout.403'));
});

it('lets a super admin edit a signed receipt', function () {
    $receipt = IncomingReceipt::factory()->create();
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');
    app(SignOffService::class)->record($receipt, 'received_and_processed', $officer);

    $admin = User::factory()->create()->assignRole('Super Admin');

    $this->actingAs($admin)
        ->get("/dashboard/incoming-receipts/{$receipt->getKey()}/edit")
        ->assertSuccessful();
});

it('shows a manager every location', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();
    $manager = User::factory()->create()->assignRole('Supply Chain Manager');

    IncomingReceipt::factory()->create(['location_id' => $a->id, 'receipt_no' => 'A/1']);
    IncomingReceipt::factory()->create(['location_id' => $b->id, 'receipt_no' => 'B/1']);

    $this->actingAs($manager)->get('/dashboard/incoming-receipts')
        ->assertSuccessful()
        ->assertSee('A/1')
        ->assertSee('B/1');
});
