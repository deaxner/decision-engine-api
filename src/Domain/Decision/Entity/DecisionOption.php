<?php

namespace App\Domain\Decision\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'options')]
class DecisionOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DecisionSession::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'session_id', nullable: false, onDelete: 'CASCADE')]
    private DecisionSession $session;

    #[ORM\Column]
    private string $title;

    #[ORM\Column]
    private int $position;

    public function __construct(DecisionSession $session, string $title, int $position)
    {
        $this->session = $session;
        $this->title = $title;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}

