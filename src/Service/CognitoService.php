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

        $this->logger->info('Cognito service initialized', [
            'userPoolId' => $this->userPoolId,
            'clientId' => $this->clientId,
            'region' => $this->region,
        ]);
    }

    /**
     * @return array{accessToken: string, refreshToken: string, idToken: string, expiresIn: int}
     *
     * @throws ImpersonationException
     */
    public function impersonateUser(string $targetUserId, string $secret): array
    {
        try {
            $this->logger->info('Starting impersonation process', ['targetUserId' => $targetUserId]);

            $this->verifyUserExists($targetUserId); // If the user was not found, why continue?

            // --------- CORE FUNCTIONALITY ---------
            $challenge = $this->startAuthChallenge($targetUserId);
            $tokens = $this->completeChallenge($targetUserId, $secret, $challenge);
            // -------------------------------------

            $this->logger->info('Impersonation successful', [
                'targetUserId' => $targetUserId,
                'tokenExpiry' => $tokens['expiresIn'],
            ]);

            return $tokens;
        } catch (ImpersonationException $e) {
            $this->logger->error('Impersonation failed', [
                'error' => $e->getMessage(),
                'targetUserId' => $targetUserId,
            ]);
            throw $e;
        }
    }

    /**
     * The adminGetUser call will throw an exception if the user is not found or any other
     * error occurs. If it exists, a simple 200 response is returned with the user details.
     */
    private function verifyUserExists(string $targetUserId): void
    {
        $this->logger->info('Verifying user exists in Cognito', ['targetUserId' => $targetUserId]);

        try {
            $user = $this->cognitoClient->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $targetUserId,
            ]);

            $this->logger->info('User found in Cognito', ['targetUserId' => $targetUserId, 'user' => $user]);
        } catch (CognitoIdentityProviderException $e) {
            if ('UserNotFoundException' === $e->getAwsErrorCode()) {
                $this->logger->warning('User not found in Cognito', ['targetUserId' => $targetUserId]);
                throw new ImpersonationException('User not found');
            }
            throw $e;
        }
    }

    private function startAuthChallenge(string $targetUserId): array
    {
        $this->logger->info('Initiating Cognito custom auth challenge', ['targetUserId' => $targetUserId]);

        // No need to pass any secret here, since the DefineAuthChallenge Lambda will handle the challenge
        // and understand which follow-up challenge to send back.
        $result = $this->cognitoClient->adminInitiateAuth([
            'UserPoolId' => $this->userPoolId,
            'ClientId' => $this->clientId,
            'AuthFlow' => 'CUSTOM_AUTH',
            'AuthParameters' => ['USERNAME' => $targetUserId],
        ]);

        if (!isset($result['ChallengeName'], $result['Session'])) {
            $this->logger->error('Invalid challenge response from Cognito', [
                'targetUserId' => $targetUserId,
                'response' => $result,
            ]);
            throw new ImpersonationException('Invalid challenge response');
        }

        $this->logger->info('Custom auth challenge initiated', [
            'targetUserId' => $targetUserId,
            'challengeName' => $result['ChallengeName'],
        ]);

        return [
            'challengeName' => $result['ChallengeName'],
            'session' => $result['Session'],
        ];
    }

    private function completeChallenge(string $targetUserId, string $secret, array $challenge): array
    {
        $this->logger->info('Responding to auth challenge', [
            'targetUserId' => $targetUserId,
            'challengeName' => $challenge['challengeName'],
        ]);

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
            $this->logger->error('Challenge verification failed', [
                'targetUserId' => $targetUserId,
                'response' => $response,
            ]);
            throw new ImpersonationException('Challenge verification failed');
        }

        $this->logger->info('Challenge completed successfully', [
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
}
