<?php

namespace App\Domain\Decision\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'session_assignees')]
#[ORM\UniqueConstraint(name: 'uniq_session_assignee', columns: ['session_id', 'user_id'])]
#[ORM\Index(name: 'idx_session_assignees_session', columns: ['session_id'])]
#[ORM\Index(name: 'idx_session_assignees_user', columns: ['user_id'])]
class SessionAssignee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DecisionSession::class, inversedBy: 'assignees')]
    #[ORM\JoinColumn(name: 'session_id', nullable: false, onDelete: 'CASCADE')]
    private DecisionSession $session;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'assigned_at')]
    private \DateTimeImmutable $assignedAt;

    public function __construct(DecisionSession $session, User $user)
    {
        $this->session = $session;
        $this->user = $user;
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
