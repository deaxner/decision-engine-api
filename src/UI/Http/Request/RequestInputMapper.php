<?php

namespace App\UI\Http\Request;

use App\Application\Decision\Input\AddOptionInput;
use App\Application\Decision\Input\AddWorkspaceMemberInput;
use App\Application\Decision\Input\CastVoteInput;
use App\Application\Decision\Input\CreateSessionInput;
use App\Application\Decision\Input\CreateWorkspaceInput;
use App\Application\Decision\Input\UpdateSessionStatusInput;

final class RequestInputMapper
{
    public function createWorkspace(array $body): CreateWorkspaceInput
    {
        $name = $this->requiredString($body, 'name');
        $slug = $this->requiredString($body, 'slug');

        return new CreateWorkspaceInput($name, $slug);
    }

    public function addWorkspaceMember(array $body): AddWorkspaceMemberInput
    {
        $email = isset($body['email']) && is_string($body['email']) ? strtolower(trim($body['email'])) : null;
        $userId = isset($body['user_id']) && is_numeric($body['user_id']) ? (int) $body['user_id'] : null;

        if (($email === null || $email === '') && $userId === null) {
            throw new \DomainException('Either email or user_id is required.');
        }

        return new AddWorkspaceMemberInput($email ?: null, $userId);
    }

    public function createSession(array $body): CreateSessionInput
    {
        $title = $this->requiredString($body, 'title');
        $votingType = $this->requiredString($body, 'voting_type');
        $description = isset($body['description']) && is_string($body['description']) ? trim($body['description']) : null;
        $category = isset($body['category']) && is_string($body['category']) ? trim($body['category']) : null;
        $dueAt = null;
        if (isset($body['due_at']) && is_string($body['due_at']) && trim($body['due_at']) !== '') {
            $dueAt = new \DateTimeImmutable($body['due_at']);
        }

        $assigneeIds = $body['assignee_ids'] ?? [];
        if ($assigneeIds === null || $assigneeIds === '') {
            $assigneeIds = [];
        }
        if (!is_array($assigneeIds)) {
            throw new \DomainException('assignee_ids must be an array.');
        }

        return new CreateSessionInput(
            title: $title,
            description: $description ?: null,
            votingType: $votingType,
            category: $category ?: null,
            dueAt: $dueAt,
            assigneeIds: array_values(array_unique(array_map('strval', $assigneeIds))),
        );
    }

    public function addOption(array $body): AddOptionInput
    {
        $title = $this->requiredString($body, 'title');
        $position = isset($body['position']) && is_numeric($body['position']) ? (int) $body['position'] : null;

        return new AddOptionInput($title, $position);
    }

    public function updateSessionStatus(array $body): UpdateSessionStatusInput
    {
        return new UpdateSessionStatusInput($this->requiredString($body, 'status'));
    }

    public function castVote(array $body): CastVoteInput
    {
        return new CastVoteInput($body);
    }

    private function requiredString(array $body, string $field): string
    {
        if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
            throw new \DomainException(sprintf('Missing required field: %s.', $field));
        }

        return trim($body[$field]);
    }
}
