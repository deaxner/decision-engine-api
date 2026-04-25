# Decision Engine API

Symfony 7 backend for the real-time decision platform.

## License Status

This repository is source-available for viewing and evaluation only.
All rights are reserved by the author. No permission is granted to use,
modify, redistribute, or commercialize this code without prior written
permission. See [LICENSE](LICENSE).

This repository contains the modular monolith API. It owns the domain model, application use cases, persistence, async result computation, authentication, authorization, and Mercure publishing.

## Responsibilities

- User registration and login.
- Workspace and membership management.
- Decision session lifecycle.
- Option management.
- Vote casting.
- Majority and Ranked IRV result computation.
- Result snapshots.
- Workspace dashboard analytics.
- Product-facing activity event storage.
- Audit-ready immutable vote and activity storage.
- Mercure result update publishing.

## Architecture

```text
src/
  Domain/
    Decision/
  Application/
    Decision/
    DemoData/
  Infrastructure/
    Persistence/
    Messaging/
    Mercure/
    Security/
  UI/
    Http/
      Controller/
    Console/
```

`UI\Console\SeedDemoDataCommand` is intentionally thin. Demo dataset shape and seeding orchestration live in `Application\DemoData`, so local tooling does not become an unreviewed second application layer.

HTTP controllers are now expected to stay thin as well. Command orchestration for workspace, session, and vote writes lives in `Application\Decision\*CommandService`, raw JSON request bodies are mapped into typed inputs before they cross the application boundary, read fetching lives in `Application\Decision\*ReadModelQuery`, and the remaining application services for auth, result computation, and Messenger handlers now also depend on narrow repository/store ports implemented in `Infrastructure\Persistence\Decision` instead of the full ORM surface. Responses are emitted through explicit output DTOs instead of anonymous arrays.

## Stack

- PHP 8.4+; verified locally with PHP 8.5
- Symfony 7
- Doctrine ORM
- PostgreSQL 16
- Redis 7
- Symfony Messenger
- Mercure
- PHPUnit

Local note: the project now requires PHP 8.4 or newer with `pdo_pgsql` enabled. PHP 8.5 is also supported and is installed locally at:

```text
C:\Users\bouke\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
```

PHP 9 is not a stable PHP release branch, so it is not installed.

The Windows machine PATH has been updated so new terminals resolve `php` to PHP 8.5 before XAMPP PHP. Restart existing terminals if they still show PHP 8.2.

## API Surface

- `POST /register`
- `POST /login`
- `GET /workspaces`
- `POST /workspaces`
- `GET /workspaces/{id}`
- `GET /workspaces/{id}/dashboard`
- `GET /workspaces/{id}/members`
- `POST /workspaces/{id}/members`
- `GET /workspaces/{id}/sessions`
- `POST /workspaces/{id}/sessions`
- `GET /sessions/{id}`
- `POST /sessions/{id}/options`
- `PATCH /sessions/{id}`
- `POST /sessions/{id}/votes`
- `GET /sessions/{id}/results`

Authenticated endpoints expect `Authorization: Bearer <token>` using the token returned by `/register` or `/login`.

`GET /workspaces/{id}/dashboard` returns the workspace summary, dashboard metrics, recent activity, and deterministic rule-based insights. Activity is stored in the append-only `activity_events` table and is recorded for workspace, member, session, option, voting, vote, close, and result recompute actions.

`GET /workspaces/{id}/members` returns existing workspace members for assignment UI. `POST /workspaces/{id}/members` accepts either `user_id` for compatibility or `email` for the web MVP. Email membership adds an already registered user; invite emails are not part of this slice.

`POST /workspaces/{id}/sessions` accepts the existing `title`, optional `description`, and `voting_type` fields plus optional metadata:

```json
{
  "category": "Infrastructure",
  "due_at": "2026-04-28T12:00:00+00:00",
  "assignee_ids": ["3", "4"]
}
```

Session list/detail responses include `category`, `due_at`, and `assignees`. Assignees must already be members of the same workspace.

`POST /sessions/{id}/votes` persists the vote and dispatches async result recomputation. The response confirms acceptance and does not include the result snapshot. Clients should read `GET /sessions/{id}/results` or subscribe to Mercure updates.

Mercure result updates are published to:

```text
/sessions/{id}/results
```

## Implemented Rules

- Votes are immutable.
- The latest vote per user is the active vote.
- Sessions move from `DRAFT` to `OPEN` to `CLOSED`.
- Options can only be added while a session is `DRAFT`.
- Votes can only be cast while a session is `OPEN`.
- Results are derived snapshots and can be recomputed from votes.
- Result recomputation runs through Messenger.
- `session_results.version` only increments when the computed snapshot changes.
- Majority and ranked IRV strategies use deterministic option-position tie handling.
- Dashboard metrics are read-model data; rule-based insights can now use real due dates, but comments, notifications, and stakeholder progress remain out of scope.

## Local Commands

```bash
composer install
composer run test:db:up
php bin/console doctrine:migrations:migrate
php bin/console lint:container
php bin/console doctrine:schema:validate --skip-sync
php vendor/bin/phpunit
```

Demo data:

```bash
php bin/console app:seed:demo-data --reset
```

The command delegates to `App\Application\DemoData\DemoDataSeeder`, which owns dataset orchestration and returns an explicit seed report to the console layer.

Functional tests use PostgreSQL 16 through `docker-compose.test.yaml` on port `55432`. Unit tests remain database-free.

The default Messenger transport uses Redis through a Predis-backed transport for `redis://.../queue` DSNs, avoiding a hard dependency on the PHP `ext-redis` extension.
