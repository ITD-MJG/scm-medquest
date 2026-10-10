<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class SignOffException extends RuntimeException
{
    public static function alreadySigned(string $step): self
    {
        return new self("Step [{$step}] has already been signed.");
    }

    public static function outOfOrder(string $step, string $prerequisite): self
    {
        return new self("Step [{$step}] cannot be signed before [{$prerequisite}].");
    }

    public static function notPermitted(string $step, string $role): self
    {
        return new self("Signing step [{$step}] requires the [{$role}] role.");
    }
}
