<?php

namespace App\Application\Decision;

use App\Application\Decision\Output\DecisionOptionOutput;
use App\Application\Decision\Output\SessionOutput;
use App\Application\Decision\Output\SessionResultOutput;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Workspace;

final class SessionReadModelQuery
{
    public function __construct(
        private readonly SessionReadRepository $repository,
        private readonly SessionViewFactory $sessions,
        private readonly SessionResultViewFactory $results,
    ) {
    }

    /**
     * @return list<SessionOutput>
     */
    public function listForWorkspace(Workspace $workspace): array
    {
        $sessions = $this->repository->sessionsForWorkspace($workspace);

        return array_map(fn (DecisionSession $session) => $this->sessions->payload($session), $sessions);
    }

    public function session(DecisionSession $session): SessionOutput
    {
        return $this->sessions->payload($session);
    }

    public function option(DecisionOption $option): DecisionOptionOutput
    {
        return $this->sessions->optionPayload($option);
    }

    public function result(DecisionSession $session): ?SessionResultOutput
    {
        $result = $this->repository->resultForSession($session);

        return $result instanceof SessionResult ? $this->results->payload($result) : null;
    }
}
