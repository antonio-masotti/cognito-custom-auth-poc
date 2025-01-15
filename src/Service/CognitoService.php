<?php

declare(strict_types=1);

namespace App\Service;

use Aws\CognitoIdentity\CognitoIdentityClient;
use Aws\SecretsManager\SecretsManagerClient;

class CognitoService
{
    private CognitoIdentityClient $cognitoClient;
    private SecretsManagerClient $secretsClient;

    public function __construct(
        private readonly string $userPoolId,
        private readonly string $clientId,
        private readonly string $region
    ) {
        $this->cognitoClient = new CognitoIdentityClient([
            'version' => 'latest',
            'region'  => $this->region
        ]);

        $this->secretsClient = new SecretsManagerClient([
            'version' => 'latest',
            'region'  => $this->region
        ]);
    }

}
