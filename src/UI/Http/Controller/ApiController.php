<?php

namespace App\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class ApiController extends AbstractController
{
    protected function body(Request $request): array
    {
        $body = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($body)) {
            throw new \DomainException('Request body must be a JSON object.');
        }

        return $body;
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function fail(\Throwable $exception, int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], $status);
    }
}
