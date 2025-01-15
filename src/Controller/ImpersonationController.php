<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ImpersonationRequest;
use App\Exceptions\InvalidRequestException;
use App\Service\CognitoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ImpersonationController extends AbstractController
{
    public function __construct(
        private readonly CognitoService $authService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/impersonate', name: 'api_impersonate', methods: ['POST'])]
    public function impersonate(Request $request): JsonResponse
    {
        try {
            $content = $this->parseRequest($request);

            $impersonationRequest = new ImpersonationRequest(
                targetUserId: preg_replace('/[^a-zA-Z0-9\-]/', '', $content['targetUserId'] ?? ''),
                secretCode: $content['secretCode'] ?? ''
            );

            $violations = $this->validator->validate($impersonationRequest);
            if (count($violations) > 0) {
                return $this->json(['error' => iterator_to_array($violations)], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->authService->impersonateUser(
                targetUserId: $impersonationRequest->targetUserId,
                secret: $impersonationRequest->secretCode
            );

            return $this->json($result);
        } catch (\Throwable $e) {
            $this->logger->error('Impersonation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json(
                ['error' => $e->getMessage()],
                $e instanceof \InvalidArgumentException ? Response::HTTP_BAD_REQUEST : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws \JsonException
     */
    public function parseRequest(Request $request): array
    {
        $content = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($content)) {
            throw new InvalidRequestException('Invalid request format');
        }

        return $content;
    }
}
