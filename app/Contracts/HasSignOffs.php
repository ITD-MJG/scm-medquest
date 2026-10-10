<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A controlled document that carries ordered sign-offs.
 *
 * The document set is closed and known, so the link is a string type plus an id
 * rather than a morphTo. See spec §5.
 */
interface HasSignOffs
{
    /**
     * The key used in `config('documents.signoff_steps')` and in `document_signoffs.document_type`.
     */
    public function documentType(): string;

    /**
     * The sign-off rows already recorded for this document, in any order.
     */
    public function signoffs(): HasMany;
}
