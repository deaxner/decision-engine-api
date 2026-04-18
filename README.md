# Decision Engine API

Symfony 7 backend for the real-time decision platform.

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

- PHP 8.3
- Symfony 7
- Doctrine ORM
- PostgreSQL 16
- Redis 7
- Symfony Messenger
- Mercure
- PHPUnit

