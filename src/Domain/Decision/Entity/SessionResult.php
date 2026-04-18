<?php

namespace App\Domain\Decision\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'session_results')]
class SessionResult
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: DecisionSession::class)]
    #[ORM\JoinColumn(name: 'session_id', nullable: false, onDelete: 'CASCADE')]
    private DecisionSession $session;

    #[ORM\Column]
    private int $version = 0;

    #[ORM\ManyToOne(targetEntity: DecisionOption::class)]
    #[ORM\JoinColumn(name: 'winning_option_id', nullable: true, onDelete: 'SET NULL')]
    private ?DecisionOption $winningOption = null;

    #[ORM\Column(name: 'result_data_json', type: 'json')]
    private array $resultData = [];

    #[ORM\Column(name: 'calculated_at')]
    private \DateTimeImmutable $calculatedAt;

    public function __construct(DecisionSession $session)
    {
        $this->session = $session;
        $this->calculatedAt = new \DateTimeImmutable();
    }

    public function update(?DecisionOption $winningOption, array $resultData): void
    {
        ++$this->version;
        $this->winningOption = $winningOption;
        $this->resultData = $resultData;
        $this->calculatedAt = new \DateTimeImmutable();
    }

    public function matches(?int $winningOptionId, array $resultData): bool
    {
        $currentWinningOptionId = $this->winningOption?->getId();
        $currentResultData = $this->resultData;
        unset($currentResultData['computed_at'], $resultData['computed_at']);

        return $currentWinningOptionId === $winningOptionId && $currentResultData == $resultData;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function toArray(): array
    {
        return [
            'session_id' => (string) $this->session->getId(),
            'version' => $this->version,
            'winning_option_id' => $this->winningOption ? (string) $this->winningOption->getId() : null,
            'result_data' => $this->resultData,
            'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
