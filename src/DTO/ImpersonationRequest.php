<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ImpersonationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Target user ID is required')]
        #[Assert\Length(
            min: 1,
            max: 128,
            minMessage: 'Target user ID must be at least {{ limit }} characters long',
            maxMessage: 'Target user ID cannot be longer than {{ limit }} characters'
        )]
        #[Assert\Regex(
            pattern: '/^[a-zA-Z0-9\-_]+$/',
            message: 'Target user ID can only contain letters, numbers, dashes, and underscores'
        )]
        public readonly string $targetUserId,

        #[Assert\NotBlank(message: 'Secret code is required')]
        #[Assert\Length(
            min: 10,
            max: 1024,
            minMessage: 'Secret code must be at least {{ limit }} characters long',
            maxMessage: 'Secret code cannot be longer than {{ limit }} characters'
        )]
        public readonly string $secretCode,
    ) {
    }
}
