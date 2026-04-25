<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\WorkspaceAccess;
use App\Application\Decision\WorkspaceReadModelQuery;
use App\Domain\Decision\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceDashboardController extends ApiController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
        private readonly WorkspaceReadModelQuery $query,
    ) {
    }

    #[Route('/workspaces/{id}/dashboard', methods: ['GET'])]
    public function dashboard(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $member = $this->access->requireMember($user, $workspace);
            return $this->ok($this->query->dashboard($workspace, $member->getRole()));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
