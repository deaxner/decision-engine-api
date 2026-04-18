<?php

namespace App\Domain\Decision\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'decision_sessions')]
class DecisionSession
{
    public const DRAFT = 'DRAFT';
    public const OPEN = 'OPEN';
    public const CLOSED = 'CLOSED';
    public const MAJORITY = 'MAJORITY';
    public const RANKED_IRV = 'RANKED_IRV';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column]
    private string $title;

    #[ORM\Column(nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 20)]
    private string $status = self::DRAFT;

    #[ORM\Column(name: 'voting_type', length: 20)]
    private string $votingType;

    #[ORM\Column(name: 'starts_at', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(name: 'ends_at', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, DecisionOption> */
    #[ORM\OneToMany(mappedBy: 'session', targetEntity: DecisionOption::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    public function __construct(Workspace $workspace, User $createdBy, string $title, ?string $description, string $votingType)
    {
        if (!in_array($votingType, [self::MAJORITY, self::RANKED_IRV], true)) {
            throw new \InvalidArgumentException('Invalid voting type.');
        }

        $this->workspace = $workspace;
        $this->createdBy = $createdBy;
        $this->title = $title;
        $this->description = $description;
        $this->votingType = $votingType;
        $this->createdAt = new \DateTimeImmutable();
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getVotingType(): string
    {
        return $this->votingType;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, DecisionOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(DecisionOption $option): void
    {
        if ($this->status !== self::DRAFT) {
            throw new \DomainException('Options can only be added while the session is in draft.');
        }

        $this->options->add($option);
    }

    public function open(): void
    {
        if ($this->status !== self::DRAFT) {
            throw new \DomainException('Only draft sessions can be opened.');
        }
        if ($this->options->count() < 2) {
            throw new \DomainException('A session needs at least 2 options before it can be opened.');
        }

        $this->status = self::OPEN;
        $this->startsAt = new \DateTimeImmutable();
    }

    public function close(): void
    {
        if ($this->status !== self::OPEN) {
            throw new \DomainException('Only open sessions can be closed.');
        }

        $this->status = self::CLOSED;
        $this->endsAt = new \DateTimeImmutable();
    }
}
