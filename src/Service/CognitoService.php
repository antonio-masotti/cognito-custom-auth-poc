<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\ImpersonationException;
use App\Exceptions\SecretNotFoundException;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Aws\Credentials\CredentialProvider;
use Aws\SecretsManager\Exception\SecretsManagerException;
use Aws\SecretsManager\SecretsManagerClient;
use Psr\Log\LoggerInterface;

class CognitoService
{
    private CognitoIdentityProviderClient $cognitoClient;
    private SecretsManagerClient $secretsClient;

    public function __construct(
        private readonly string $userPoolId,
        private readonly string $clientId,
        private readonly string $region,
        private readonly string $awsProfile,
        private readonly LoggerInterface $logger,
    ) {
        // https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/sso-provider.html
        $credentials = CredentialProvider::sso($this->awsProfile);

        $sdkConfig = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials,
        ];

        $this->cognitoClient = new CognitoIdentityProviderClient($sdkConfig);
        $this->secretsClient = new SecretsManagerClient($sdkConfig);

        $this->logger->info('CognitoService initialized', [
            'userPoolId' => $this->userPoolId,
            'clientId' => $this->clientId,
            'region' => $this->region,
        ]);
    }

    /**
     * @return array{accessToken: string, refreshToken: string, idToken: string, expiresIn: int}
     *
     * @throws ImpersonationException
     * @throws SecretNotFoundException
     */
    public function impersonateUser(string $targetUserId, string $providedSecret): array
    {
        $storedSecret = $this->getImpersonationSecret();

        if ($providedSecret !== $storedSecret) {
            throw new ImpersonationException('Invalid impersonation secret');
        }

        try {
            // First, use adminInitiateAuth to start the auth flow
            $result = $this->cognitoClient->adminInitiateAuth([
                'UserPoolId' => $this->userPoolId,
                'ClientId' => $this->clientId,
                'AuthFlow' => 'CUSTOM_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $targetUserId,
                ],
            ]);

            if (!isset($result['AuthenticationResult'])) {
                throw new ImpersonationException('Authentication failed: No authentication result returned');
            }

            $this->logger->info('Impersonation successful', [
                'targetUserId' => $targetUserId,
                'expiresIn' => $result['AuthenticationResult']['ExpiresIn'],
            ]);

            return [
                'accessToken' => $result['AuthenticationResult']['AccessToken'],
                'refreshToken' => $result['AuthenticationResult']['RefreshToken'],
                'idToken' => $result['AuthenticationResult']['IdToken'],
                'expiresIn' => $result['AuthenticationResult']['ExpiresIn'],
            ];
        } catch (CognitoIdentityProviderException $e) {
            $this->logger->error('Cognito authentication failed', [
                'error' => $e->getMessage(),
                'targetUserId' => $targetUserId,
            ]);
            throw new ImpersonationException('Authentication failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws SecretNotFoundException
     */
    private function getImpersonationSecret(): string
    {
        try {
            $result = $this->secretsClient->getSecretValue([
                'SecretId' => $_ENV['AWS_SECRET_NAME'] ?? throw new SecretNotFoundException('AWS_SECRET_NAME not configured'),
            ]);

            if (!isset($result['SecretString'])) {
                throw new SecretNotFoundException('Secret value not found');
            }
            $this->logger->debug('Secret retrieved'.$result);

            $this->logger->debug('Secret retrieved successfully');

            return $result['SecretString'];
        } catch (SecretsManagerException $e) {
            $this->logger->error('Failed to retrieve secret. Error: '.$e->getMessage());
            throw new SecretNotFoundException('Failed to retrieve impersonation secret', 0, $e);
        }
    }
}
