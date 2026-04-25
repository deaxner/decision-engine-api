<?php

namespace App\Application\Decision;

use App\Application\Decision\Output\SessionResultOutput;
use App\Domain\Decision\Entity\SessionResult;

final class SessionResultViewFactory
{
    public function payload(SessionResult $result): SessionResultOutput
    {
        $data = $result->toArray();

        return new SessionResultOutput(
            sessionId: (string) $data['session_id'],
            version: (int) $data['version'],
            winningOptionId: is_string($data['winning_option_id']) ? $data['winning_option_id'] : null,
            resultData: is_array($data['result_data']) ? $data['result_data'] : [],
            calculatedAt: (string) $data['calculated_at'],
        );
    }
}
