<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasSignOffs;
use App\Exceptions\SignOffException;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowInstance;
use App\Models\ApprovalFlowStep;
use App\Models\DocumentSignoff;
use App\Models\User;
use App\Support\SignatureFormatter;
use RuntimeException;

/**
 * DB-driven approval flow engine. An active ApprovalFlow (ordered ApprovalFlowSteps)
 * drives per-document ApprovalFlowInstance state; document_signoffs rows still hold
 * the signatures. Documents without an active flow fall back to the legacy config
 * steps, so introducing flows never changes behaviour for un-migrated forms.
 */
final class ApprovalFlowService
{
    /**
     * The active flow for a document type, or null when the legacy config rules apply.
     */
    public function resolveFor(string $modelType): ?ApprovalFlow
    {
        return ApprovalFlow::query()
            ->where('model_type', $modelType)
            ->where('is_active', true)
            ->with('steps')
            ->first();
    }

    /**
     * Create (or return) the runtime instance tracking $document against its active flow.
     */
    public function start(HasSignOffs $document): ?ApprovalFlowInstance
    {
        $flow = $this->resolveFor($document->documentType());

        if ($flow === null || $flow->steps->isEmpty()) {
            return null;
        }

        return ApprovalFlowInstance::query()->firstOrCreate(
            ['model_type' => $document->documentType(), 'model_id' => $document->getKey()],
            ['approval_flow_id' => $flow->id, 'current_sequence' => $flow->steps->first()->sequence],
        );
    }

    /**
     * The step $user may sign right now, or null. Throws when the step is
     * already signed, out of order, or not permitted — mirroring SignOffException.
     */
    public function sign(HasSignOffs $document, ?User $actor = null): DocumentSignoff
    {
        $actor ??= auth()->user();

        if (! $actor instanceof User) {
            throw new RuntimeException('A signed-in user is required to record a sign-off.');
        }

        $instance = $this->start($document)
            ?? throw SignOffException::outOfOrder($document->documentType(), 'no active approval flow');

        $instance->loadMissing('flow.steps');

        $step = $instance->currentStep()
            ?? throw SignOffException::alreadySigned('flow already completed');

        if (! $actor->hasAnyRole([$step->role_name, 'Super Admin'])) {
            throw SignOffException::notPermitted($step->label, $step->role_name);
        }

        $signedAt = now('Asia/Jakarta');

        $signoff = $document->signoffs()->create([
            'document_type' => $document->documentType(),
            'step' => (string) $step->sequence,
            'role_required' => $step->role_name,
            'approval_flow_instance_id' => $instance->id,
            'approval_flow_step_id' => $step->id,
            'user_id' => $actor->id,
            'signer_name' => SignatureFormatter::format($actor, $signedAt),
            'signed_at' => $signedAt,
        ]);

        $next = $instance->flow->steps->firstWhere('sequence', '>', $step->sequence);

        $instance->update([
            'current_sequence' => $next?->sequence ?? 0,
            'status' => $next === null ? 'completed' : 'pending',
        ]);

        return $signoff;
    }

    /**
     * Whether $user may sign the current step of $document.
     */
    public function canSignCurrent(HasSignOffs $document, User $user): bool
    {
        $instance = ApprovalFlowInstance::query()
            ->where('model_type', $document->documentType())
            ->where('model_id', $document->getKey())
            ->with('flow.steps')
            ->first();

        if ($instance === null) {
            // Started lazily by sign(); without an instance nothing may be signed yet.
            $flow = $this->resolveFor($document->documentType());

            return $flow !== null
                && $flow->steps->isNotEmpty()
                && $user->hasAnyRole([$flow->steps->first()->role_name, 'Super Admin']);
        }

        $step = $instance->currentStep();

        if ($step === null) {
            return false;
        }

        return $user->hasAnyRole([$step->role_name, 'Super Admin'])
            && ! $document->signoffs()
                ->where('approval_flow_step_id', $step->id)
                ->exists();
    }

    /**
     * The document's current step, or null when no flow governs it or it is complete.
     */
    public function currentStepFor(HasSignOffs $document): ?ApprovalFlowStep
    {
        $instance = ApprovalFlowInstance::query()
            ->where('model_type', $document->documentType())
            ->where('model_id', $document->getKey())
            ->with('flow.steps')
            ->first();

        return $instance?->currentStep();
    }

    public function isComplete(HasSignOffs $document): bool
    {
        $instance = ApprovalFlowInstance::query()
            ->where('model_type', $document->documentType())
            ->where('model_id', $document->getKey())
            ->first();

        return $instance !== null && $instance->isCompleted();
    }
}
