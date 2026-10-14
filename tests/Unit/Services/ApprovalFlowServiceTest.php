<?php

use App\Exceptions\SignOffException;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowInstance;
use App\Models\ApprovalFlowStep;
use App\Models\User;
use App\Services\ApprovalFlowService;
use App\Services\SignOffService;
use Tests\Fixtures\SignableDocument;

beforeEach(function () {
    $this->document = SignableDocument::create(['id' => 1, 'document_type' => 'incoming_receipt']);
    $this->flows = app(ApprovalFlowService::class);
    $this->service = app(SignOffService::class);

    $this->flow = ApprovalFlow::create(['name' => 'Receipt Flow', 'model_type' => 'incoming_receipt', 'is_active' => true]);
    ApprovalFlowStep::create(['approval_flow_id' => $this->flow->id, 'sequence' => 1, 'label' => 'Received by', 'role_name' => 'Supply Chain Officer']);
    ApprovalFlowStep::create(['approval_flow_id' => $this->flow->id, 'sequence' => 2, 'label' => 'Checked by', 'role_name' => 'Supply Chain Manager']);
    ApprovalFlowStep::create(['approval_flow_id' => $this->flow->id, 'sequence' => 3, 'label' => 'Verified by', 'role_name' => 'QA Manager']);

    $this->officer = User::factory()->create(['initial' => 'IAN'])->assignRole('Supply Chain Officer');
    $this->manager = User::factory()->create(['initial' => 'MGR'])->assignRole('Supply Chain Manager');
    $this->qaManager = User::factory()->create(['initial' => 'QAM'])->assignRole('QA Manager');
});

it('resolves the active flow for a document type', function () {
    expect($this->flows->resolveFor('incoming_receipt')->id)->toBe($this->flow->id)
        ->and($this->flows->resolveFor('unknown_type'))->toBeNull();
});

it('activating a flow retires other flows of the same document type', function () {
    $other = ApprovalFlow::create(['name' => 'Alt Flow', 'model_type' => 'incoming_receipt']);

    $other->activate();

    expect($other->refresh()->is_active)->toBeTrue()
        ->and($this->flow->refresh()->is_active)->toBeFalse();
});

it('starts an instance lazily at the first step', function () {
    $instance = $this->flows->start($this->document);

    expect($instance)->toBeInstanceOf(ApprovalFlowInstance::class)
        ->and($instance->current_sequence)->toBe(1)
        ->and($instance->status)->toBe('pending')
        // second call returns the same row, not a duplicate
        ->and($this->flows->start($this->document)->id)->toBe($instance->id);
});

it('signs steps strictly in sequence and completes the flow', function () {
    $this->flows->sign($this->document, $this->officer);

    expect(fn () => $this->flows->sign($this->document, $this->qaManager))
        ->toThrow(SignOffException::class);

    $this->flows->sign($this->document, $this->manager);
    $this->flows->sign($this->document, $this->qaManager);

    $instance = ApprovalFlowInstance::query()
        ->where('model_type', 'incoming_receipt')->where('model_id', 1)->first();

    expect($instance->status)->toBe('completed')
        ->and($this->flows->isComplete($this->document))->toBeTrue()
        ->and(fn () => $this->flows->sign($this->document, $this->officer))
        ->toThrow(SignOffException::class);
});

it('refuses the wrong role for the current step', function () {
    expect(fn () => $this->flows->sign($this->document, $this->qaManager))
        ->toThrow(SignOffException::class);
});

it('lets a super admin sign any step', function () {
    $admin = User::factory()->create()->assignRole('Super Admin');

    $this->flows->sign($this->document, $admin);

    $signoff = $this->document->signoffs()->first();

    expect($signoff->role_required)->toBe('Supply Chain Officer')
        ->and($signoff->approval_flow_step_id)->not->toBeNull();
});

it('links the signoff row to the flow instance and step', function () {
    $signoff = $this->flows->sign($this->document, $this->officer);

    $instance = $this->flows->start($this->document);

    expect($signoff->approval_flow_instance_id)->toBe($instance->id)
        ->and($signoff->approval_flow_step_id)->toBe($this->flow->steps->firstWhere('sequence', 1)->id);
});

it('falls back to config steps when no flow is active', function () {
    // Deactivate the flow: no DB rules apply, config governs again.
    $this->flow->update(['is_active' => false]);

    expect($this->flows->resolveFor('incoming_receipt'))->toBeNull()
        ->and($this->flows->start($this->document))->toBeNull()
        ->and($this->service->stepsFor('incoming_receipt'))
        ->toHaveKey('received_and_processed')
        ->and($this->service->nextPendingStep($this->document))->toBe('received_and_processed');
});

it('reports the next pending step by sequence under a flow', function () {
    expect($this->service->nextPendingStep($this->document))->toBe('1');

    $this->flows->sign($this->document, $this->officer);

    expect($this->service->nextPendingStep($this->document))->toBe('2');
});

it('stepsFor exposes flow labels and roles', function () {
    $steps = $this->service->stepsFor('incoming_receipt');

    expect($steps)->toBe([
        '1' => ['label' => 'Received by', 'role' => 'Supply Chain Officer'],
        '2' => ['label' => 'Checked by', 'role' => 'Supply Chain Manager'],
        '3' => ['label' => 'Verified by', 'role' => 'QA Manager'],
    ]);
});
