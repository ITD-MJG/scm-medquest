<?php

use App\Enums\ProductSource;
use App\Models\Product;
use App\Services\Sap\ProductImportService;

beforeEach(function () {
    config()->set('sap.enabled', true);
    config()->set('sap.rows', [
        ['sap_code' => 'SAP-001', 'internal_code' => 'DIS-T-00003', 'description' => 'DIASPECT CUVETTES', 'uom' => 'PCS', 'principal_code' => 'DIASPECT'],
    ]);
});

it('creates a product from a SAP row', function () {
    $result = app(ProductImportService::class)->import();

    expect($result->created)->toBe(1)
        ->and(Product::where('internal_code', 'DIS-T-00003')->first()->source)->toBe(ProductSource::SAP);
});

it('never overwrites a manually created product', function () {
    Product::factory()->create([
        'internal_code' => 'DIS-T-00003',
        'sap_code' => 'SAP-001',
        'description' => 'LOCAL EDIT',
        'source' => 'manual',
    ]);

    app(ProductImportService::class)->import();

    expect(Product::first()->description)->toBe('LOCAL EDIT');
});

it('keeps a user-corrected uom on re-import', function () {
    app(ProductImportService::class)->import();

    Product::where('internal_code', 'DIS-T-00003')->update(['uom' => 'BOX', 'uom_source' => 'manual']);

    app(ProductImportService::class)->import();

    expect(Product::first()->uom)->toBe('BOX');
});

it('refreshes a sap-sourced uom on re-import', function () {
    app(ProductImportService::class)->import();

    Product::where('internal_code', 'DIS-T-00003')->update(['uom_source' => 'sap']);
    config()->set('sap.rows', [['sap_code' => 'SAP-001', 'internal_code' => 'DIS-T-00003', 'description' => 'DIASPECT CUVETTES', 'uom' => 'EA', 'principal_code' => 'DIASPECT']]);

    app(ProductImportService::class)->import();

    expect(Product::first()->uom)->toBe('EA');
});

it('does nothing when the import is disabled', function () {
    config()->set('sap.enabled', false);

    $result = app(ProductImportService::class)->import();

    expect($result->created)->toBe(0)
        ->and(Product::count())->toBe(0);
});
