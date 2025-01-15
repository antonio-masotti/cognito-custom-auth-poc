<?php

declare(strict_types=1);

namespace App\Service;

use Aws\CognitoIdentity\CognitoIdentityClient;
use Aws\SecretsManager\SecretsManagerClient;
use Symfony\Component\HttpKernel\Log\Logger;

class CognitoService
{
    private Logger $logger;
    private CognitoIdentityClient $cognitoClient;
    private SecretsManagerClient $secretsClient;

    public function __construct(
        private readonly string $userPoolId,
        private readonly string $clientId,
        private readonly string $region,
    ) {
        $this->logger = new Logger();

        $this->cognitoClient = new CognitoIdentityClient([
            'version' => 'latest',
            'region' => $this->region,
            'profile' => $_ENV['AWS_PROFILE'],
        ]);
        $this->logger->info('CognitoService initialized');
        $this->logger->info('User Pool ID: '.$this->userPoolId);
        $this->logger->info('Client ID: '.$this->clientId);
        $this->logger->info('Region: '.$this->region);

        $this->secretsClient = new SecretsManagerClient([
            'version' => 'latest',
            'region' => $this->region,
            'profile' => $_ENV['AWS_PROFILE'],
        ]);

        $this->logger->info('SecretsManagerClient initialized');
    }

    public function impersonateUser(string $targetUserId): array
    {
        // Get the current secret from Secrets Manager
        $secret = $this->getImpersonationSecret();

        try {
            // Initiate auth with CUSTOM_AUTH flow
            $result = $this->cognitoClient->initiateAuth([
                'AuthFlow' => 'CUSTOM_AUTH',
                'ClientId' => $this->clientId,
                'AuthParameters' => [
                    'USERNAME' => $targetUserId,
                    'CHALLENGE_NAME' => 'CUSTOM_CHALLENGE',
                    'SECRET' => $secret,
                ],
            ]);

            $this->logger->info('Impersonation successful');
            $this->logger->info('Access Token: '.$result['AuthenticationResult']['AccessToken']);
            $this->logger->info('Refresh Token: '.$result['AuthenticationResult']['RefreshToken']);
            $this->logger->info('Id Token: '.$result['AuthenticationResult']['IdToken']);
            $this->logger->info('Expires In: '.$result['AuthenticationResult']['ExpiresIn']);

            return [
                'token' => $result['AuthenticationResult']['AccessToken'],
                'refreshToken' => $result['AuthenticationResult']['RefreshToken'],
                'idToken' => $result['AuthenticationResult']['IdToken'],
                'expiresIn' => $result['AuthenticationResult']['ExpiresIn'],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Impersonation failed: '.$e->getMessage());
        }
    }

    private function getImpersonationSecret(): string
    {
        try {
            $result = $this->secretsClient->getSecretValue([
                'SecretId' => $_ENV['AWS_SECRET_NAME'],
            ]);

            $secret = json_decode($result['SecretString'], true);
            $this->logger->info('Secret retrieved');

            return $secret['value'];
        } catch (\Exception $e) {
            $message = 'Failed to retrieve impersonation secret: '.$e->getMessage();
            $this->logger->error($message);
            throw new \Exception($message);
        }
    }
}
