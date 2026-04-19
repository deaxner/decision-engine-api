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
- Audit-ready immutable vote storage.
- Mercure result update publishing.

## Architecture

```text
src/
  Domain/
    Decision/
  Application/
    Decision/
  Infrastructure/
    Persistence/
    Messaging/
    Mercure/
    Security/
  UI/
    Http/
      Controller/
```

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
- `POST /workspaces/{id}/members`
- `GET /workspaces/{id}/sessions`
- `POST /workspaces/{id}/sessions`
- `GET /sessions/{id}`
- `POST /sessions/{id}/options`
- `PATCH /sessions/{id}`
- `POST /sessions/{id}/votes`
- `GET /sessions/{id}/results`

Authenticated endpoints expect `Authorization: Bearer <token>` using the token returned by `/register` or `/login`.

`POST /workspaces/{id}/members` accepts either `user_id` for compatibility or `email` for the web MVP. Email membership adds an already registered user; invite emails are not part of this slice.

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

## Local Commands

```bash
composer install
composer run test:db:up
php bin/console doctrine:migrations:migrate
php bin/console lint:container
php bin/console doctrine:schema:validate --skip-sync
php vendor/bin/phpunit
```

Functional tests use PostgreSQL 16 through `docker-compose.test.yaml` on port `55432`. Unit tests remain database-free.

The default Messenger transport uses Redis through a Predis-backed transport for `redis://.../queue` DSNs, avoiding a hard dependency on the PHP `ext-redis` extension.
