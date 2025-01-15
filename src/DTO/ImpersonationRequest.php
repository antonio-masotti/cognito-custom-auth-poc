<?php

declare(strict_types=1);

namespace App\DTO;

class ImpersonationRequest
{
    public function __construct(
        public readonly string $targetUserId,
    ) {
    }
}
