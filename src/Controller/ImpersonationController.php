<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CognitoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ImpersonationController extends AbstractController
{

    public function __construct(
        private readonly CognitoService $cognitoService
    ) {
    }

    #[Route('/api/impersonate', name:'impersonation', methods: ['POST', 'GET'])]
    public function impersonate(): JsonResponse
    {
        throw new \Exception('Not implemented');
    }

}
