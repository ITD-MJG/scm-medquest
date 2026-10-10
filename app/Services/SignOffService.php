<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasSignOffs;
use App\Exceptions\SignOffException;
use App\Models\DocumentSignoff;
use App\Models\User;
use App\Support\SignatureFormatter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ordered, role-gated sign-off engine shared by every controlled form.
 *
 * A step may only be signed once, in configured order, by the configured role (or
 * a Super Admin). The ordering rules live here alone so all forms agree.
 */
final class SignOffService
{
    /**
     * The ordered step definitions for a document type.
     *
     * @return array<string, array{label: string, role: string}>
     */
    public function stepsFor(string $documentType): array
    {
        return config("documents.signoff_steps.{$documentType}", []);
    }

    /**
     * Record a sign-off, or refuse with the reason.
     *
     * @throws SignOffException when the step is already signed, out of order, or not permitted
     */
    public function record(HasSignOffs $document, string $step, ?User $actor = null): DocumentSignoff
    {
        $actor ??= auth()->user();

        if (! $actor instanceof User) {
            throw new RuntimeException('A signed-in user is required to record a sign-off.');
        }

        $steps = $this->stepsFor($document->documentType());

        if (! array_key_exists($step, $steps)) {
            throw new InvalidArgumentException("Unknown sign-off step [{$step}] for document type [{$document->documentType()}].");
        }

        $signed = $this->signedSteps($document);

        if (in_array($step, $signed, true)) {
            throw SignOffException::alreadySigned($step);
        }

        $prerequisite = $this->firstUnsignedPrerequisite($steps, $step, $signed);

        if ($prerequisite !== null) {
            throw SignOffException::outOfOrder($step, $prerequisite);
        }

        if (! $this->canSign($document, $step, $actor)) {
            throw SignOffException::notPermitted($step, $steps[$step]['role']);
        }

        $signedAt = now('Asia/Jakarta');

        return $document->signoffs()->create([
            'document_type' => $document->documentType(),
            'step' => $step,
            'role_required' => $steps[$step]['role'],
            'user_id' => $actor->id,
            'signer_name' => SignatureFormatter::format($actor, $signedAt),
            'signed_at' => $signedAt,
        ]);
    }

    /**
     * Whether $user may sign $step right now: the configured role (or Super Admin)
     * and every earlier step already signed.
     */
    public function canSign(HasSignOffs $document, string $step, User $user): bool
    {
        $steps = $this->stepsFor($document->documentType());

        if (! array_key_exists($step, $steps)) {
            return false;
        }

        if (! $user->hasAnyRole([$steps[$step]['role'], 'Super Admin'])) {
            return false;
        }

        return $this->firstUnsignedPrerequisite($steps, $step, $this->signedSteps($document)) === null;
    }

    /**
     * The first configured step with no sign-off row, or null when all are signed.
     */
    public function nextPendingStep(HasSignOffs $document): ?string
    {
        $signed = $this->signedSteps($document);

        foreach (array_keys($this->stepsFor($document->documentType())) as $step) {
            if (! in_array($step, $signed, true)) {
                return $step;
            }
        }

        return null;
    }

    public function isComplete(HasSignOffs $document): bool
    {
        return $this->nextPendingStep($document) === null;
    }

    /**
     * @return list<string>
     */
    private function signedSteps(HasSignOffs $document): array
    {
        return $document->signoffs()->pluck('step')->all();
    }

    /**
     * The earliest configured step before $step that is still unsigned, or null.
     *
     * @param  array<string, array{label: string, role: string}>  $steps
     * @param  list<string>  $signed
     */
    private function firstUnsignedPrerequisite(array $steps, string $step, array $signed): ?string
    {
        foreach (array_keys($steps) as $key) {
            if ($key === $step) {
                return null;
            }

            if (! in_array($key, $signed, true)) {
                return $key;
            }
        }

        return null;
    }
}
