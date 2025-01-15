<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\SecretNotFoundException;
use Aws\Credentials\CredentialProvider;
use Aws\SecretsManager\Exception\SecretsManagerException;
use Aws\SecretsManager\SecretsManagerClient;
use Psr\Log\LoggerInterface;

class SecretsManagerService
{
    private SecretsManagerClient $client;

    public function __construct(
        private readonly string $region,
        private readonly string $awsProfile,
        private readonly LoggerInterface $logger,
    ) {
        $credentials = CredentialProvider::sso($this->awsProfile);

        $this->client = new SecretsManagerClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials,
        ]);
    }

    /**
     * @throws SecretNotFoundException
     */
    public function getSecret(string $secretId): string
    {
        try {
            $result = $this->client->getSecretValue(['SecretId' => $secretId]);

            if (!isset($result['SecretString'])) {
                throw new SecretNotFoundException('Secret value not found');
            }

            $this->logger->debug('Secret retrieved successfully', ['secretId' => $secretId]);

            return $result['SecretString'];
        } catch (SecretsManagerException $e) {
            $this->logger->error('Failed to retrieve secret', [
                'secretId' => $secretId,
                'error' => $e->getMessage()
            ]);
            throw new SecretNotFoundException('Failed to retrieve secret', 0, $e);
        }
    }
}

