<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CognitoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\Routing\Annotation\Route;

class ImpersonationController extends AbstractController
{
    private Logger $logger;

    public function __construct(
        private readonly CognitoService $cognitoService,
    ) {
        $this->logger = new Logger();
    }

    #[Route('/api/impersonate', name: 'impersonation', methods: ['POST'])]
    public function impersonate(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $this->logger->info('Impersonation request: '.json_encode($content));

        $targetUserId = $content['targetUserId'] ?? null;

        if (!$targetUserId || !is_string($targetUserId)) {
            $this->logger->warning('Target user ID is required');

            return new JsonResponse(['error' => 'Target user ID is required'], 400);
        }

        $secret = $content['secretCode'] ?? null;
        if (!$secret || !is_string($secret)) {
            $this->logger->warning('Secret code is required');

            return new JsonResponse(['error' => 'Secret code is required'], 400);
        }

        try {
            $result = $this->cognitoService->impersonateUser($targetUserId, $secret);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
