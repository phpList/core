# AGENT.md — Code Review Knowledge Base for phpList/core

## 🧭 Repository Overview

This repository is the **core package** of **phpList 4**, a modular and extensible email campaign management system.

- **Purpose:** Provides the reusable foundation and framework for phpList applications and modules.
- **Consumers:** `phpList/web-frontend`, `phpList/rest-api`, and `phpList/base-distribution`.
- **Core responsibilities:**
    - Application bootstrapping and service configuration
    - Database ORM (Doctrine) integration
    - Command-line utilities (Symfony Console)
    - Async email sending via Symfony Messenger
    - Logging, configuration, routing, dependency injection
    - Schema definition and updates

> **Note:** This repository does *not* contain UI or REST endpoints. Those are part of other phpList packages.

---

## ⚙️ Tech Stack

| Category | Technology |
|-----------|-------------|
| Language | PHP ≥ 8.1 |
| Framework | Symfony 6.x components |
| ORM | Doctrine ORM 3.x |
| Async / Queue | Symfony Messenger |
| Tests | PHPUnit |
| Static analysis | PHPStan |
| Docs | PHPDocumentor |
| License | AGPL-3.0 |

---

## 📁 Project Structure
- .github/ CI/CD and PR templates
- bin/ Symfony console entrypoints
- config/ Application & environment configs
- docs/ Developer documentation and generated API docs
- public/ Public entrypoint for local web server
- resources/Database/ Canonical SQL schema
- src/ Core PHP source code
- tests/ PHPUnit test suites
- composer.json Package metadata and dependencies
- phpunit.xml.dist Test configuration
- phpstan.neon Static analysis configuration

---

## 💡 Code Design Principles

1. **Modularity:**  
   The core remains framework-like — decoupled from frontend or API layers.

2. **Dependency Injection:**  
   Use Symfony’s service container; avoid static/global dependencies.

3. **Strict Typing:**  
   Always use `declare(strict_types=1);` and explicit type declarations.

4. **Doctrine Entities:**
    - Keep entities simple (no business logic).
    - Mirror schema changes in `resources/Database/Schema.sql`.
    - Maintain backward compatibility with phpList 3.

5. **Symfony Best Practices:**  
   Follow Symfony structure and naming conventions. Use annotations or attributes for routing.

6. **Error Handling & Logging:**
    - Prefer structured logging via Graylog.
    - Catch and handle exceptions at service or command boundaries.

7. **Async Email:**
    - Uses Symfony Messenger.
    - Handlers must be idempotent and retry-safe.
    - Avoid blocking or synchronous email sending.

---

## 🧪 Testing Guidelines

- **Framework:** PHPUnit
- **Database:** SQLite or mocks for unit tests; MySQL for integration tests.
- **Coverage target:** ≥85% for core logic.
- **Naming:** Mirror source structure (e.g., `Mailer.php` → `MailerTest.php`).


## 🧱 Code Style

- Follow PSR-12 and Symfony coding conventions.
- Match the current codebase’s formatting and spacing.
- Use meaningful, consistent naming.
- Apply a single responsibility per class.


## 🔄 Pull Request Review Guidelines
### 🔐 Security Review Notes

- Do not log sensitive data (passwords, tokens, SMTP credentials).
- Sanitize all user and external inputs.
- Always use parameterized Doctrine queries.
- Async jobs must be retry-safe and idempotent.
