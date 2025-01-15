<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\ImpersonationException;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Aws\Credentials\CredentialProvider;
use Psr\Log\LoggerInterface;

class CognitoService
{
    private readonly CognitoIdentityProviderClient $cognitoClient;
    private readonly SecretsManagerService $secretManager;

    public function __construct(
        private readonly string $userPoolId,
        private readonly string $clientId,
        private readonly string $region,
        private readonly string $awsProfile,
        private readonly LoggerInterface $logger,
    ) {
        $this->cognitoClient = new CognitoIdentityProviderClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => CredentialProvider::sso($this->awsProfile),
        ]);

        $this->secretManager = new SecretsManagerService(
            $this->region,
            $this->awsProfile,
            $this->logger
        );

        $this->logger->info('Authentication service initialized', [
            'userPoolId' => $this->userPoolId,
            'clientId' => $this->clientId,
            'region' => $this->region,
        ]);
    }

    /**
     * @throws ImpersonationException
     * @return array{accessToken: string, refreshToken: string, idToken: string, expiresIn: int}
     */
    public function impersonateUser(string $targetUserId, string $providedSecret): array
    {
        try {
            $this->validateImpersonationSecret($providedSecret);
            $this->verifyUserExists($targetUserId);

            $challenge = $this->startAuthChallenge($targetUserId);

            return $this->respondToChallenge($targetUserId, $providedSecret, $challenge);
        } catch (ImpersonationException $e) {
            $this->logger->error('Authentication failed', [
                'error' => $e->getMessage(),
                'userId' => $targetUserId,
            ]);
            throw $e;
        }
    }

    private function verifyUserExists(string $targetUserId): void
    {
        try {
            $this->cognitoClient->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $targetUserId,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            if ('UserNotFoundException' === $e->getAwsErrorCode()) {
                throw new ImpersonationException('User not found');
            }
            throw $e;
        }
    }

    private function startAuthChallenge(string $targetUserId): array
    {
        $result = $this->cognitoClient->adminInitiateAuth([
            'UserPoolId' => $this->userPoolId,
            'ClientId' => $this->clientId,
            'AuthFlow' => 'CUSTOM_AUTH',
            'AuthParameters' => [
                'USERNAME' => $targetUserId,
            ],
        ]);

        $this->logger->debug('Challenge initiated', [
            'challengeName' => $result['ChallengeName'] ?? 'none',
            'session' => $result['Session'] ?? 'none',
        ]);

        if (!isset($result['ChallengeName'], $result['Session'])) {
            throw new ImpersonationException('Invalid challenge response');
        }

        return [
            'challengeName' => $result['ChallengeName'],
            'session' => $result['Session'],
        ];
    }

    /**
     * @param array{challengeName: string, session: string} $challenge
     *
     * @return array{accessToken: string, refreshToken: string, idToken: string, expiresIn: int}
     *
     * @throws ImpersonationException
     */
    private function respondToChallenge(string $targetUserId, string $secret, array $challenge): array
    {
        $response = $this->cognitoClient->adminRespondToAuthChallenge([
            'UserPoolId' => $this->userPoolId,
            'ClientId' => $this->clientId,
            'ChallengeName' => $challenge['challengeName'],
            'ChallengeResponses' => [
                'USERNAME' => $targetUserId,
                'ANSWER' => $secret,
            ],
            'Session' => $challenge['session'],
        ]);

        if (!isset($response['AuthenticationResult'])) {
            throw new ImpersonationException('Challenge response failed: No authentication result returned');
        }

        $this->logger->info('Impersonation successful', [
            'targetUserId' => $targetUserId,
            'expiresIn' => $response['AuthenticationResult']['ExpiresIn'],
        ]);

        return [
            'accessToken' => $response['AuthenticationResult']['AccessToken'],
            'refreshToken' => $response['AuthenticationResult']['RefreshToken'],
            'idToken' => $response['AuthenticationResult']['IdToken'],
            'expiresIn' => $response['AuthenticationResult']['ExpiresIn'],
        ];
    }

    private function validateImpersonationSecret(string $providedSecret): void
    {
        $storedSecret = $this->secretManager->getSecret(
            $_ENV['AWS_SECRET_NAME'] ?? throw new ImpersonationException('AWS_SECRET_NAME not configured')
        );

        if ($providedSecret !== $storedSecret) {
            throw new ImpersonationException('Invalid impersonation secret');
        }
    }
}
