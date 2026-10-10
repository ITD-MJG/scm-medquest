<?php

use App\Exceptions\SignOffException;
use App\Models\User;
use App\Services\SignOffService;
use Tests\Fixtures\SignableDocument;

beforeEach(function () {
    $this->document = SignableDocument::create(['id' => 1, 'document_type' => 'incoming_receipt']);
    $this->service = app(SignOffService::class);
});

it('records the first step for the correct role', function () {
    $officer = User::factory()->create(['initial' => 'IAN'])->assignRole('Supply Chain Officer');

    $signoff = $this->service->record($this->document, 'received_and_processed', $officer);

    expect($signoff->step)->toBe('received_and_processed')
        ->and($signoff->signer_name)->toBe('IAN '.strtoupper(now('Asia/Jakarta')->format('d M Y')))
        ->and($signoff->user_id)->toBe($officer->id);
});

it('refuses a step signed by the wrong role', function () {
    $staff = User::factory()->create()->assignRole('QA Staff');

    expect(fn () => $this->service->record($this->document, 'received_and_processed', $staff))
        ->toThrow(SignOffException::class);
});

it('refuses a step whose prerequisite is unsigned', function () {
    $manager = User::factory()->create()->assignRole('Supply Chain Manager');

    // checked_by is step 2; received_and_processed (step 1) is unsigned.
    expect(fn () => $this->service->record($this->document, 'checked_by', $manager))
        ->toThrow(SignOffException::class);
});

it('refuses a double sign of the same step', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');
    $this->service->record($this->document, 'received_and_processed', $officer);

    expect(fn () => $this->service->record($this->document, 'received_and_processed', $officer))
        ->toThrow(SignOffException::class);
});

it('lets a super admin bypass the role but not the order', function () {
    $admin = User::factory()->create()->assignRole('Super Admin');

    expect($this->service->canSign($this->document, 'received_and_processed', $admin))->toBeTrue()
        ->and(fn () => $this->service->record($this->document, 'checked_by', $admin))
        ->toThrow(SignOffException::class);
});

it('reports the next pending step', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    expect($this->service->nextPendingStep($this->document))->toBe('received_and_processed');

    $this->service->record($this->document, 'received_and_processed', $officer);

    expect($this->service->nextPendingStep($this->document))->toBe('checked_by');
});

it('is complete only when every step is signed', function () {
    expect($this->service->isComplete($this->document))->toBeFalse();

    foreach (['received_and_processed', 'checked_by', 'inspected_by', 'verified_by', 'status_defined_by', 'acknowledged_by', 'sap_entry_by'] as $step) {
        $admin = User::factory()->create()->assignRole('Super Admin');
        $this->service->record($this->document, $step, $admin);
    }

    expect($this->service->isComplete($this->document))->toBeTrue()
        ->and($this->service->nextPendingStep($this->document))->toBeNull();
});

it('stores the role required at signing time', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $signoff = $this->service->record($this->document, 'received_and_processed', $officer);

    expect($signoff->role_required)->toBe('Supply Chain Officer');
});
