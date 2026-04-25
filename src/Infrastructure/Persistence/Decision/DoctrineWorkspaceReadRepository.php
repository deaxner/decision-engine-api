<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\WorkspaceReadRepository;
use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWorkspaceReadRepository implements WorkspaceReadRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function membershipsForUser(User $user): array
    {
        return $this->entityManager->getRepository(WorkspaceMember::class)->findBy(['user' => $user]);
    }

    public function memberCount(Workspace $workspace): int
    {
        return $this->entityManager->getRepository(WorkspaceMember::class)->count(['workspace' => $workspace]);
    }

    public function sessionsForWorkspace(Workspace $workspace, ?string $status = null, array $orderBy = []): array
    {
        $criteria = ['workspace' => $workspace];
        if ($status !== null) {
            $criteria['status'] = $status;
        }

        return $this->entityManager->getRepository(DecisionSession::class)->findBy($criteria, $orderBy);
    }

    public function resultForSession(DecisionSession $session): ?SessionResult
    {
        $result = $this->entityManager->getRepository(SessionResult::class)->find($session);

        return $result instanceof SessionResult ? $result : null;
    }

    public function membersForWorkspace(Workspace $workspace): array
    {
        return $this->entityManager->getRepository(WorkspaceMember::class)->findBy(['workspace' => $workspace]);
    }

    public function recentActivityForWorkspace(Workspace $workspace, int $limit): array
    {
        return $this->entityManager->getRepository(ActivityEvent::class)->findBy(
            ['workspace' => $workspace],
            ['createdAt' => 'DESC'],
            $limit,
        );
    }

    public function distinctVoterCountForWorkspace(Workspace $workspace, array $statuses): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(v.user))')
            ->from(Vote::class, 'v')
            ->join('v.session', 's')
            ->where('s.workspace = :workspace')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('workspace', $workspace)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
