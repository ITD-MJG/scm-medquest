<?php

use App\Enums\Disposition;
use App\Enums\StorageRequirement;
use App\Models\Carrier;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Location;
use App\Models\Room;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * The paper's line table, in paper order. Everything after these 17 headers is a
 * sub-block of form 2 (Registration Label / Storage / Physical movement) that the
 * single-table layout appends rather than draws as a second band.
 */
const GRIFOLS_PAPER_HEADERS = [
    'No',
    'Principal',
    'Code',
    'Part No',
    'Lot',
    'Serial No',
    'Expiry Date',
    'No. of Cases',
    'Unit per Case',
    'Qty',
    'UoM',
    'Labeled Correctly (Y/N)',
    'ISN or Packing List (Y/N)',
    'Pallet/ID',
    'Physical Condition',
    'Storage Conditions',
    'Remark',
];

function grifolsCellText(string $html): string
{
    // Layout entities (&ensp; etc.) are visual spacing, not data; treat them as spaces
    // so cell comparisons do not depend on the template's padding conventions.
    $text = str_ireplace(['&ensp;', '&nbsp;', '&#8194;', '&#160;'], ' ', $html);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

    return trim(preg_replace('/\s+/u', ' ', $text));
}

/**
 * @return array{headers: list<string>, rows: list<list<string>>}
 */
function grifolsLineTable(string $html): array
{
    if (! preg_match('/<table class="table table-bordered">\s*<thead>(.*?)<\/thead>\s*<tbody>(.*?)<\/tbody>/s', $html, $table)) {
        throw new RuntimeException('Line items table not found in print view.');
    }

    preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s', $table[1], $thMatches);
    $headers = array_map('grifolsCellText', $thMatches[1]);

    preg_match_all('/<tr>(.*?)<\/tr>/s', $table[2], $trMatches);
    $rows = [];
    foreach ($trMatches[1] as $tr) {
        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $tr, $tdMatches);
        $rows[] = array_map('grifolsCellText', $tdMatches[1]);
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/**
 * The header grid is two parallel columns of `<p>` cells: labels on the left,
 * values on the right. Pairing by index binds each value to its own label.
 * There are two such blocks (identity fields, then inspection fields).
 *
 * @return array<string, string>
 */
function grifolsHeaderGrid(string $html): array
{
    if (! preg_match_all('/<div class="col-4">(.*?)<\/div>\s*<div class="col-8">(.*?)<\/div>/s', $html, $grids, PREG_SET_ORDER)) {
        throw new RuntimeException('Header field grid not found in print view.');
    }

    $cells = function (string $column): array {
        preg_match_all('/<p\b[^>]*>(.*?)<\/p>/s', $column, $matches);

        return array_map('grifolsCellText', $matches[1]);
    };

    $pairs = [];
    foreach ($grids as [, $labelColumn, $valueColumn]) {
        $labels = $cells($labelColumn);
        $values = $cells($valueColumn);

        expect($values)->toHaveCount(count($labels));

        foreach ($labels as $index => $label) {
            // Values are rendered as ":&ensp;value"; drop the leading marker.
            $pairs[$label] = trim(preg_replace('/^:\s*/', '', $values[$index]));
        }
    }

    return $pairs;
}

function grifolsReceipt(array $attributes = []): IncomingReceipt
{
    return IncomingReceipt::factory()->create(array_merge(['variant' => 'grifols'], $attributes));
}

function grifolsPrint(IncomingReceipt $receipt): string
{
    return view('print.incoming-grifols', [
        'receipt' => $receipt->load(
            'lines.product.principal',
            'lines.labeledBy',
            'lines.room',
            'signoffs',
            'location',
            'supplier',
            'carrier',
        ),
    ])->render();
}

it('renders the controlled document metadata and the 7 sign-off labels', function () {
    $receipt = grifolsReceipt();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = grifolsPrint($receipt);

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

it('prints the 17 paper headers in paper order', function () {
    $receipt = grifolsReceipt();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $headers = grifolsLineTable(grifolsPrint($receipt))['headers'];

    expect($headers)->toHaveCount(22)
        ->and(array_slice($headers, 0, 17))->toBe(GRIFOLS_PAPER_HEADERS);
});

it('renders each line value under its own header', function () {
    $room = Room::factory()->create(['room_name' => 'D.13.E.3']);
    $labeler = User::factory()->create(['name' => 'Siti Labeler']);

    $receipt = grifolsReceipt(['storage_requirement' => StorageRequirement::MINUS_35_MINUS_15]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create([
        'line_no' => 4,
        'part_no' => 'PN-12345',
        'serial_no' => 'SN-0001',
        'lot_no' => '710333',
        'no_of_cases' => 7,
        'unit_per_case' => 3,
        'quantity' => 21,
        'uom' => 'EA',
        'labeled_correctly' => true,
        'isn_or_packing_list' => true,
        'pallet_id' => 'CF77408-CF77412',
        'physical_packing_condition' => 'BAIK',
        'registration_label_no' => 'AKL 30305813603',
        'labeled_by' => $labeler->id,
        'room_id' => $room->id,
        'physical_movement_by' => 'Agus',
        'packaging_observation' => 'Sealed kit, ea',
    ]);

    $table = grifolsLineTable(grifolsPrint($receipt));

    expect($table['rows'])->toHaveCount(1)
        ->and($table['rows'][0])->toHaveCount(count($table['headers']));

    // Bound by header name, not by substring position: a shifted cell fails here.
    $row = array_combine($table['headers'], $table['rows'][0]);

    expect($row['No'])->toBe('4')
        ->and($row['Part No'])->toBe('PN-12345')
        ->and($row['Lot'])->toBe('710333')
        ->and($row['Serial No'])->toBe('SN-0001')
        ->and($row['No. of Cases'])->toBe('7')
        ->and($row['Unit per Case'])->toBe('3')
        ->and($row['Qty'])->toBe('21.00')
        ->and($row['UoM'])->toBe('EA')
        ->and($row['Labeled Correctly (Y/N)'])->toBe('Y')
        ->and($row['ISN or Packing List (Y/N)'])->toBe('Y')
        ->and($row['Pallet/ID'])->toBe('CF77408-CF77412')
        ->and($row['Physical Condition'])->toBe('BAIK')
        ->and($row['Storage Conditions'])->toBe('-35°C to -15°C')
        ->and($row['Remark'])->toBe('-')
        ->and($row['Packaging Observation'])->toBe('Sealed kit, ea')
        ->and($row['Registration Label No.'])->toBe('AKL 30305813603')
        ->and($row['Labeled by'])->toBe('Siti Labeler')
        ->and($row['Storage Location'])->toBe('D.13.E.3')
        ->and($row['Physical Movement by'])->toBe('Agus');
});

it('renders the nullable line booleans as Y, N or a dash', function () {
    $receipt = grifolsReceipt();

    $states = [
        ['labeled_correctly' => null, 'isn_or_packing_list' => null, 'expected' => '-'],
        ['labeled_correctly' => true, 'isn_or_packing_list' => false, 'expected' => 'Y'],
        ['labeled_correctly' => false, 'isn_or_packing_list' => true, 'expected' => 'N'],
    ];

    foreach ($states as $index => $state) {
        IncomingReceiptLine::factory()->for($receipt, 'receipt')->create([
            'line_no' => $index + 1,
            'labeled_correctly' => $state['labeled_correctly'],
            'isn_or_packing_list' => $state['isn_or_packing_list'],
        ]);
    }

    $table = grifolsLineTable(grifolsPrint($receipt));
    $rows = array_map(
        fn (array $cells) => array_combine($table['headers'], $cells),
        $table['rows'],
    );

    expect($rows[0]['Labeled Correctly (Y/N)'])->toBe('-')
        ->and($rows[0]['ISN or Packing List (Y/N)'])->toBe('-')
        ->and($rows[1]['Labeled Correctly (Y/N)'])->toBe('Y')
        ->and($rows[1]['ISN or Packing List (Y/N)'])->toBe('N')
        ->and($rows[2]['Labeled Correctly (Y/N)'])->toBe('N')
        ->and($rows[2]['ISN or Packing List (Y/N)'])->toBe('Y');
});

it('prints the carrier, shipper name and address in the header grid', function () {
    $carrier = Carrier::factory()->create(['name' => 'IOL LOGISTICS']);
    $supplier = Supplier::factory()->create(['name' => 'GRIFOLS']);

    $receipt = grifolsReceipt([
        'carrier_id' => $carrier->id,
        'supplier_id' => $supplier->id,
        'shipper_address' => 'SINGAPORE',
    ]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $grid = grifolsHeaderGrid(grifolsPrint($receipt));

    expect($grid['Carrier'])->toBe('IOL LOGISTICS')
        ->and($grid['Shipper Name and Address'])->toBe('GRIFOLS, SINGAPORE');
});

it('renders the registration label attached flag as Y, N or a dash', function () {
    foreach ([[null, '-'], [true, 'Y'], [false, 'N']] as [$value, $expected]) {
        $receipt = grifolsReceipt(['registration_label_attached' => $value]);
        IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

        expect(grifolsHeaderGrid(grifolsPrint($receipt))['Registration Label attached (Y/N)'])
            ->toBe($expected);
    }
});

it('prints N/A when disposition is null and its label when set', function () {
    $receipt = grifolsReceipt(['disposition' => null]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = grifolsPrint($receipt);

    expect($html)->toContain('N/A');

    $receipt->update(['disposition' => Disposition::RELEASED]);

    expect(grifolsPrint($receipt))->toContain('Released');
});

it('prints the duration of transportation inside the disposition cell', function () {
    $receipt = grifolsReceipt([
        'disposition' => Disposition::RELEASED,
        'duration_of_transportation_hours' => 2.5,
    ]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    expect(grifolsPrint($receipt))->toContain('Duration of transportation:')
        ->toContain('2.50 hour');
});

it('does not print product_status under a Package Conditions label', function () {
    $receipt = grifolsReceipt();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = grifolsPrint($receipt);

    expect($html)->not->toContain('Package Conditions')
        ->and($html)->not->toContain($receipt->product_status->value);
});

it('keeps the packaging abbreviation as a legend, not as a column', function () {
    $receipt = grifolsReceipt();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = grifolsPrint($receipt);

    expect(grifolsLineTable($html)['headers'])->not->toContain('Abbreviation of Packaging Product')
        ->and($html)->toContain('Abbreviation of Packaging Product:&ensp;ea = each,&ensp;unit = unit,&ensp;kit = kit');
});

it('spans the empty-state row across every header', function () {
    $receipt = grifolsReceipt();

    $html = grifolsPrint($receipt);

    $headerCount = count(grifolsLineTable($html)['headers']);

    expect($html)->toContain('colspan="'.$headerCount.'"');
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
        $mock->shouldReceive('setNodeBinary')->andReturnSelf();
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
