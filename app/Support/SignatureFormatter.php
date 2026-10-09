<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;

final class SignatureFormatter
{
    /**
     * Format a sign-off signature.
     *
     * The signing time is a parameter on purpose: the temperature module's
     * formatPicSignature() stamps the record's created_at, which prints the
     * wrong date on a document signed across several days. See spec §6.
     */
    public static function format(User $user, CarbonInterface $signedAt): string
    {
        if ($user->hasRole('Security')) {
            return $user->name;
        }

        return "{$user->initial} ".strtoupper($signedAt->format('d M Y'));
    }
}
