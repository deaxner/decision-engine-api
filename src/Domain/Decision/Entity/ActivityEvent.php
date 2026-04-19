<?php

namespace App\Domain\Decision\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_events')]
#[ORM\Index(name: 'idx_activity_events_workspace_created', columns: ['workspace_id', 'created_at'])]
#[ORM\Index(name: 'idx_activity_events_session_created', columns: ['session_id', 'created_at'])]
#[ORM\Index(name: 'idx_activity_events_type', columns: ['event_type'])]
class ActivityEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: DecisionSession::class)]
    #[ORM\JoinColumn(name: 'session_id', nullable: true, onDelete: 'CASCADE')]
    private ?DecisionSession $session;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $actor;

    #[ORM\Column(name: 'event_type', length: 50)]
    private string $type;

    #[ORM\Column]
    private string $summary;

    #[ORM\Column(name: 'metadata_json', type: 'json')]
    private array $metadata;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Workspace $workspace,
        string $type,
        string $summary,
        ?User $actor = null,
        ?DecisionSession $session = null,
        array $metadata = [],
    ) {
        $this->workspace = $workspace;
        $this->type = $type;
        $this->summary = $summary;
        $this->actor = $actor;
        $this->session = $session;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getSession(): ?DecisionSession
    {
        return $this->session;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
