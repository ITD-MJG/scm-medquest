<?php

use App\Filament\Resources\IncomingReceipts\Pages\ViewIncomingReceipt;
use App\Models\IncomingReceipt;
use App\Models\User;
use App\Services\SignOffService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('lets the correct role sign the next step', function () {
    $receipt = IncomingReceipt::factory()->create();
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');
    $manager = User::factory()->create(['initial' => 'SUP'])->assignRole('Supply Chain Manager');

    // step 1 recorded directly through the service (not the UI action under test)
    app(SignOffService::class)->record($receipt, 'received_and_processed', $officer);

    $this->actingAs($manager);
    Livewire::test(ViewIncomingReceipt::class, ['record' => $receipt->getKey()])
        ->callAction('sign_checked_by');

    expect($receipt->fresh()->signoffs()->where('step', 'checked_by')->exists())->toBeTrue();
});

it('hides the sign action from the wrong role', function () {
    $receipt = IncomingReceipt::factory()->create();
    $staff = User::factory()->create()->assignRole('QA Staff');
    $this->actingAs($staff);

    Livewire::test(ViewIncomingReceipt::class, ['record' => $receipt->getKey()])
        ->assertActionHidden('sign_checked_by');
});
