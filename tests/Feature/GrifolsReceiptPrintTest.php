<?php

use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RoleSeeder;

it('renders the controlled document metadata and the 7 sign-off labels', function () {
    $receipt = IncomingReceipt::factory()->create(['variant' => 'grifols']);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = view('print.incoming-grifols', ['receipt' => $receipt->load('lines', 'signoffs', 'location', 'supplier', 'carrier')])->render();

    expect($html)->toContain('MJG-FOR-SCM.02-002-08')
        ->toContain('06')
        ->toContain('INCOMING RECEIPT INSPECTION FOR GRIFOLS DIAGNOSTICS PRODUCT')
        ->toContain('Received by')
        ->toContain('Inspected by')
        ->toContain('Verified by')
        ->toContain('Checked by')
        ->toContain('Disposition (by Grifols SCM)')
        ->toContain('Entry data to the SAP System (performed by)')
        ->toContain('Warehouse Manager');
});

it('renders grifols line fields without the fallback', function () {
    $receipt = IncomingReceipt::factory()->create(['variant' => 'grifols']);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create([
        'part_no' => 'PN-12345',
        'serial_no' => 'SN-0001',
        'no_of_cases' => 3,
        'packaging_observation' => 'Sealed kit, ea',
    ]);

    $html = view('print.incoming-grifols', ['receipt' => $receipt->load('lines', 'signoffs')])->render();

    foreach (['PN-12345', 'SN-0001', '3', 'Sealed kit, ea'] as $value) {
        $row = substr($html, (int) strpos($html, $value) - 60, 140);

        expect($row)->toContain($value)
            ->and($row)->not->toContain('&ensp;-');
    }
});

it('prints N/A when disposition is null and the value when set', function () {
    $receipt = IncomingReceipt::factory()->create(['variant' => 'grifols', 'disposition' => null]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = view('print.incoming-grifols', ['receipt' => $receipt->load('lines', 'signoffs')])->render();

    expect($html)->toContain('N/A');

    $receipt->update(['disposition' => 'Released']);
    $html = view('print.incoming-grifols', ['receipt' => $receipt->load('lines', 'signoffs')])->render();

    expect($html)->toContain('Released');
});

it('blocks a print route from another location', function () {
    $this->seed(RoleSeeder::class);

    $location = Location::factory()->create();
    $other = Location::factory()->create();
    $receipt = IncomingReceipt::factory()->create(['location_id' => $location->id]);
    $officer = User::factory()->create(['location_id' => $other->id])->assignRole('Supply Chain Officer');

    $this->actingAs($officer)
        ->get("/incoming-receipts-grifols/{$receipt->getKey()}/print")
        ->assertRedirect(route('logout.403'));
});
it('renders the print route for an officer of the same location', function () {
    $this->seed(RoleSeeder::class);
    $this->mock('alias:Spatie\Browsershot\Browsershot', function ($mock) {
        $mock->shouldReceive('html')->andReturnSelf();
        $mock->shouldReceive('format')->andReturnSelf();
        $mock->shouldReceive('margins')->andReturnSelf();
        $mock->shouldReceive('landscape')->andReturnSelf();
        $mock->shouldReceive('showBackground')->andReturnSelf();
        $mock->shouldReceive('pdf')->andReturn('%PDF-fake');
    });

    $location = Location::factory()->create();
    $receipt = IncomingReceipt::factory()->create(['location_id' => $location->id, 'variant' => 'grifols']);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();
    $officer = User::factory()->create(['location_id' => $location->id])->assignRole('Supply Chain Officer');

    $this->actingAs($officer)
        ->get("/incoming-receipts-grifols/{$receipt->getKey()}/print")
        ->assertSuccessful();
});
