# Project: Client Support Desk (cRc)

Internal support desk: a software company tracks its 10 client companies, the client employees it talks to
(contacts), tickets, conversations and follow-ups. Clients never log in. Planning docs live one folder up
(`../project-scope.md`, `../implementation-plan.md`, `../tech-stack.md`). The git repo root is `CDM/`, not `cRc/`.

## Status
- Milestone 1 (login, roles): done.
- Milestone 2 (companies and contacts) and admin-only agent creation: done.
- Milestone 3 (tickets, agent-only visibility, assignment history, admin-only assign, assignment alerts): done.
- Milestone 4 (conversation log and message templates): done.
- Milestone 5 (admin dashboard: summary cards, unassigned queue with inline assign, workload per agent): done.
- Next: Milestone 6 (deploy: EC2, backups, queue worker/scheduler).

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
  every company and contact, but only their own tickets (see Tickets below).
- Tickets: agent-only visibility lives in ONE place, the `Ticket` global scope (`agent_visibility`), backed by
  `TicketPolicy`. Agents therefore get a 404 (not 403) for tickets that are not theirs, including unassigned ones. Admins
  are unfiltered. Use `Ticket::withoutGlobalScopes()` only for admin/system code, never for agent requests. Only admins
  assign or reassign (`POST /tickets/{id}/assign`, `TicketPolicy::assign`); every assignment writes a
  `ticket_assignments` row and sends the in-app `TicketAssigned` notification. A ticket's company always comes from
  its contact. Agent-created tickets are auto-assigned to the agent. Tickets are never deleted.
- Conversations (`interactions`): append-only log, no edit/delete routes. Any agent can log with any active contact and
  read the whole log (project scope: conversations are open to all agents). Linking a conversation to a ticket must go
  through the `Ticket` model so the visibility scope applies (never `Rule::exists('tickets')`, which bypasses it). The
  author (`user_id`) always comes from the session. The DB trigger keeps `contacts.last_contacted_at` current.
- Contact page (`contacts/show`): two columns (details, module tags, "Prefers" on the left; log form above the
  history on the right). The log form can save a "Follow up on" date, which creates a pending `follow_ups` row for the
  author; the agent dashboard lists follow-ups due today or overdue with "Mark done" (owner or admin only). Product
  modules (`product_modules`) are managed by admins under `/admin/modules` and ticked per contact in the contact form;
  the contact's best time to reach is stored in `contact_profiles.preferred_time`. The template picker fills the
  message box with a few lines of JavaScript (all templates are rendered server-side into `data-` attributes; a
  `<noscript>` form falls back to `?template=ID`). Channel display names come from `Interaction::channelLabel()`.
- Admin dashboard (`Admin\DashboardController`, `/admin`): admin only. Cards (open, unassigned, critical, overdue,
  conversations today, follow-ups overdue), the unassigned queue (priority, then oldest) with an inline assign form
  that posts to `tickets.assign` with `from=dashboard` (the only redirect value honoured), and workload per agent
  (LEFT JOIN with the status condition in ON so zero-load agents appear; a deactivated agent still holding open
  tickets is shown and flagged). The navbar has no Tickets/Templates/Modules links: admins reach Templates, Modules
  and Users from the dashboard quick links. `tickets.index` accepts `overdue=1`.
- Pop-up forms: every "create" (company, agent, contact, ticket, template) is a native `<dialog>` on the list page
  via the `<x-modal>` component (`resources/views/components/modal.blade.php`), and "Edit" is a pop-up on the detail
  page (company, contact, ticket) or per row (templates). The trigger is a normal link to the stand-alone
  create/edit page (`data-modal="<dialog id>"`), so everything still works without JavaScript. Field markup lives in
  `_fields.blade.php` partials shared by the pop-up and the stand-alone page. After a successful create the controller
  redirects to the LIST (page computed so the new row is visible) with `->with('highlight', $id)` and a flash status;
  lists mark that row `row-new`. A failed submit redirects back and the pop-up reopens because `old('_modal')` equals
  its marker; pages with several pop-ups must scope old input and errors per pop-up (see `templates/_fields`).
  `?new=1` (plus `company_id` / `contact_id`) opens the create pop-up pre-filled, which is how other pages link to it.
  New create/edit forms should follow this pattern, not a separate page plus redirect.
- Message templates: admins manage, agents only use active ones. Placeholders are a fixed whitelist
  (`MessageTemplate::PLACEHOLDERS`: name, company, ticket_no, ticket_title, agent_name), filled by `TemplateRenderer`
  with plain text substitution (nothing evaluated). Nothing is sent. In Blade never write a literal double-brace pair
  inside an echo: use `MessageTemplate::token('name')`.
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
