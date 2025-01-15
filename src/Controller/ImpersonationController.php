<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ImpersonationRequest;
use App\Exceptions\ImpersonationException;
use App\Exceptions\InvalidRequestException;
use App\Exceptions\SecretNotFoundException;
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
            $impersonationRequest = $this->validateAndCreateRequest($request);

            $result = $this->authService->impersonateUser(
                targetUserId: $impersonationRequest->targetUserId,
                providedSecret: $impersonationRequest->secretCode
            );

            return $this->json($result);
        } catch (InvalidRequestException $e) {
            $this->logger->warning('Invalid impersonation request', [
                'error' => $e->getMessage(),
                'violations' => $e->getViolations(),
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => $e->getViolations(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (ImpersonationException|SecretNotFoundException $e) {
            $this->logger->error('Impersonation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            $this->logger->critical('Unexpected error during impersonation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => 'An unexpected error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws InvalidRequestException
     */
    private function validateAndCreateRequest(Request $request): ImpersonationRequest
    {
        try {
            $content = $this->decodeRequestContent($request);

            $impersonationRequest = new ImpersonationRequest(
                targetUserId: $this->sanitizeInput($content['targetUserId'] ?? ''),
                secretCode: $content['secretCode'] ?? ''
            );

            $violations = $this->validator->validate($impersonationRequest);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
                }
                throw new InvalidRequestException('Invalid request data', $errors);
            }

            return $impersonationRequest;
        } catch (\JsonException $e) {
            throw new InvalidRequestException('Invalid JSON payload');
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws \JsonException
     */
    private function decodeRequestContent(Request $request): array
    {
        $content = $request->getContent();

        if (empty($content)) {
            throw new InvalidRequestException('Empty request body');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new InvalidRequestException('Invalid request format');
        }

        return $decoded;
    }

    private function sanitizeInput(string $input): string
    {
        // Remove any non-alphanumeric characters except dash and underscore
        $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '', $input);
        $this->logger->info('Sanitized input', ['original' => $input, 'sanitized' => $sanitized]);

        // Ensure the string isn't empty after sanitization
        if (empty($sanitized)) {
            throw new InvalidRequestException('Invalid input after sanitization');
        }

        return $sanitized;
    }
}
