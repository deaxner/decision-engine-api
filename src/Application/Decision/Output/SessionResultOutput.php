<?php

namespace App\Application\Decision\Output;

final readonly class SessionResultOutput implements \JsonSerializable
{
    /**
     * @param array<string,mixed> $resultData
     */
    public function __construct(
        public string $sessionId,
        public int $version,
        public ?string $winningOptionId,
        public array $resultData,
        public string $calculatedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'session_id' => $this->sessionId,
            'version' => $this->version,
            'winning_option_id' => $this->winningOptionId,
            'result_data' => $this->resultData,
            'calculated_at' => $this->calculatedAt,
        ];
    }
}
