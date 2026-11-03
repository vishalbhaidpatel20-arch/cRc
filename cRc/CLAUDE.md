# Project: Client Support Desk (cRc)

Internal support desk: a software company tracks its 10 client companies, the client employees it talks to
(contacts), tickets, conversations and follow-ups. Clients never log in. Planning docs live one folder up
(`../project-scope.md`, `../implementation-plan.md`, `../tech-stack.md`). The git repo root is `CDM/`, not `cRc/`.

## Status
- Milestone 1 (login, roles): done.
- Milestone 2 (companies and contacts) and admin-only agent creation: done.
- Next: Milestone 3 (tickets, agent-only visibility, assignment history, admin-only assign). Then interactions
  and templates, dashboard, deploy.

## Stack and conventions
- Laravel 13, PHP 8.3+, plain Blade views (no Filament, no starter kit, no JS build step needed), MariaDB 10.4.
- Primary keys are named, not `id`: `user_id`, `company_id`, `contact_id`, `ticket_id`... Models set `$primaryKey`.
  Tables mostly have only `created_at`, so models use `$timestamps = false`.
- Users: `password_hash` (not `password`), `full_name` (not `name`), no remember token. `User::getAuthPassword()`
  maps the column. `role` is `admin` or `agent`. Use `$user->homeRoute()` for the post-login redirect.
- Never hard-delete users, companies or contacts: deactivate with `is_active`. FKs use RESTRICT for history,
  CASCADE for child rows, SET NULL for optional links (see `database/sql/01_schema.sql`).
- There is NO public registration. Only admins create agents (`Admin\UserController`); role is forced to `agent`
  server-side. Do not add a `/register` route.
- Access control: `role:admin` / `role:agent` middleware plus `active` (logs out deactivated users). Agents can view
  every company and contact; agents will see only their own tickets (milestone 3, via global scope + policy).
- Keep authorization in middleware/policies, not scattered in views. Hiding a button is not security.
- Use the Write/Edit tools to create files; very large inline bash heredocs have failed in this environment.

## Database
- Dev database `crc` (sample data in `database/sql/03_seed.sql`, all sample users use password `password`).
- Schema is managed by Laravel migrations (`database/migrations/2026_11_03_*`), mirrored by hand-written SQL in
  `database/sql/` (schema, trigger, seed, 10 join queries, indexing review, relationship notes). Keep both in sync
  when the schema changes.
- MariaDB-specific pieces live in migrations: the `trg_interactions_after_insert` trigger, the `chk_tc_author`
  CHECK, FULLTEXT indexes. They cannot run on SQLite.
- `00_reset.sql` wipes all tables: run it only when the user asks for a rebuild.

## Testing
- Tests run on a separate MariaDB database `crc_test` (see `phpunit.xml`). **Never run migrate:fresh, db:seed,
  or any write against `crc` from tests or scripts.**
- `php artisan test` (all), `php artisan test tests/Feature/E2E --testdox` (journeys, with names).
- `php artisan test` prints a one-line JSON summary in this environment; add `--testdox` to list test names.
- Test data and the test index are documented in `e2e-test.md`: update it whenever tests or seed data change.
- Add a test with every behaviour change. `SecurityJourneyTest` fails when a route is added without being added to
  its protected-URL list: update that list for new routes.

## Safety
- `.env` is git-ignored: never commit secrets or real client data or real passwords (not in tests, docs or seed).
- Do not kill all php processes (`taskkill /IM php.exe`); stop only a server you started.
- Commit or push only when the user asks. Commits end with the Co-Authored-By line from the session attribution.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
