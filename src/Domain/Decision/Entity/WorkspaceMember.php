<?php

namespace App\Domain\Decision\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_members')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_user', columns: ['workspace_id', 'user_id'])]
class WorkspaceMember
{
    public const OWNER = 'OWNER';
    public const MEMBER = 'MEMBER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $role;

    #[ORM\Column(name: 'joined_at')]
    private \DateTimeImmutable $joinedAt;

    public function __construct(Workspace $workspace, User $user, string $role)
    {
        if (!in_array($role, [self::OWNER, self::MEMBER], true)) {
            throw new \InvalidArgumentException('Invalid workspace role.');
        }

        $this->workspace = $workspace;
        $this->user = $user;
        $this->role = $role;
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getRole(): string
    {
        return $this->role;
    }
}

