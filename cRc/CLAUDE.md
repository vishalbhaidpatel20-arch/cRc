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
- Laravel 13, PHP 8.3+, plain Blade views (no Filament, no starter kit), MariaDB 10.4. `public/js/partial.js` needs no build; the data grid
  does (Vite + React + AG Grid, run `npm run build`; `public/build` is git-ignored, so build on deploy).
- Primary keys are named, not `id`: `user_id`, `company_id`, `contact_id`, `ticket_id`... Models set `$primaryKey`.
  Tables mostly have only `created_at`, so models use `$timestamps = false`.
- Users: `password_hash` (not `password`), `full_name` (not `name`), no remember token. `User::getAuthPassword()`
  maps the column. `role` is `admin` or `agent`. Use `$user->homeRoute()` for the post-login redirect: login ALWAYS lands on the dashboard (no `intended()`, the remembered page is dropped).
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
- Dashboard design: both dashboards share one look: a `.dash-hero` header (greeting and date are filled in by a tiny inline script from the
  browser's clock, because the server runs in UTC), `<x-stat>` icon cards (tone: brand/info/good/warn/bad), `.panel` blocks in a 2/3 + 1/3
  `.dash-grid`, `partials.dash-bars` for the breakdown bars. `main:has(.dash-hero)` makes them full width (and `main:has(.contact-grid)` the contact page). Both dashboards' headings are
  "<First name>'s dashboard" (the `<title>` stays "Admin dashboard" / "Agent dashboard"). Keep the strings the tests pin ("Welcome, <name>", "Unassigned queue",
  "Workload per agent", "assigned by <admin>", the quick-link URLs). Panels that change in place carry `data-region` names (admin: `cards`,
  `queue`, `workload`; agent: `agent-stats`, `agent-next`, `agent-followups`, `agent-assigned`, `agent-priority`).
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
- Partial refresh (no full-page reloads): `public/js/partial.js` (plain JS, loaded by the layout) intercepts forms and
  same-page links inside a region, sends them with `fetch()`, and swaps only the `data-region="name"` areas whose HTML
  changed. If the reply has a different set of areas it swaps `<main>`; on an error status it shows the error page; on a
  network failure it submits normally. Every page that changes in place must expose the same named areas on every request
  (an always-present wrapper, never an `@if`-ed one). Flash messages use `partials/flash` (the `flash` area); lists put
  table + pager in a `results` area; dashboards use `cards`/`queue`/`workload`. `<x-modal>` sets `data-open` when the
  pop-up must be open; the `+ New` / `Edit` links are opened by the script's click handler (never attach per-link handlers
  in a modal). Scripts inside an area re-run when it is swapped, so keep them idempotent. Opt out with `data-no-ajax`
  (logout and login forms). Navigation is in place too: any same-site link fetches the page and swaps only `<main>`
  (and the title, history and scroll position; Back/Forward work the same way), while the header and the script stay.
  Everything keeps working without JavaScript as ordinary links and forms.
- Auto search: GET filter forms (`form.filters`) have no Search/Filter button (it is kept inside `<noscript>`). `partial.js` submits
  them 300 ms after typing and straight away when a select, checkbox or date changes; the newest request wins and the
  history entry is replaced, not added. The search box is 250px (`.filters input[name=q]`); its hint is in `title`.
- All data tables are grids. Full-size (fit the window, widen the page): the list pages, a company's contacts, Modules. Embedded
  (`<x-grid-skeleton :total="..." :page-size="5" />` + `<table ... data-grid data-page-size="5">`): dashboard cards and a ticket's
  assignment history; fixed rows per page, as tall as its rows, no page widening. Every grid table needs a header `<tr>` of `<th>`
  (`data-flex="3"` wider column, `data-nosort` not sortable). A row's inputs/buttons that need a form use `form="id"` pointing to a
  `<form id>` outside the table (see Modules). A key/value details table is a two-column grid too (`Detail` / `Value` header, one row per field, `data-nosort`; see the ticket page); an overdue date keeps its red styling by wrapping the value in a `<span class="overdue">` (the grid keeps only the cell content, not the `<td>` class).
- Grid without flash: the server still sends the plain `<table class="list" data-grid>` (no-JavaScript fallback), but with JS on it must
  never be painted. Put `<x-grid-skeleton :total="$rows->count()" />` directly before every `data-grid` table and
  `@push('assets') @include('partials.grid-assets') @endpush` on the page (requests the grid chunk from `<head>`, not after `app.js`).
  `public/js/grid-size.js` (sync, in `<head>`) sets `<html class="js">` (CSS hides the table next to a placeholder) and owns the
  sizing rule `crcGridLayout`, shared by the placeholder and `grid.jsx`, so the placeholder and the grid have the same height.
  The grid mounts inside the placeholder. An empty list has no placeholder (its "No ... found" table shows). If the grid cannot
  load, `app.js` (or an 8 s timer) removes `js` so the plain table shows. `partial.js` compares an area with its server HTML
  (`area._srcHtml`) so an unchanged grid is not rebuilt.
- Data grid: list tables (companies, contacts, tickets, users, templates, conversations) are `<table class="list" data-grid>` inside the
  `results` area. `resources/js/grid.jsx` (AG Grid + React, loaded by `resources/js/app.js` through `@vite`) replaces each with a
  full-width sortable grid built from the table's cells (cell HTML is kept, so links, tags and forms still work); `main` goes
  full width via `main:has(.grid-host)`; the admin and agent dashboards are full width too (`main:has(.dash-hero)`). Paging and filtering stay server-side: each grid fills the window height (`fit()` in grid.jsx; the `footer.site` is below the fold and only appears when you scroll down) and pages on screen with AG Grid's own pager (`layout()` in grid.jsx picks whole rows that fit, stretches them a little to fill the box, or shrinks the grid when the list is short; 56px base rows, long text clipped to 2 lines with a hover tip), so nothing scrolls. The server sends up to `config('crc.per_page')` rows (200, env `CRC_PER_PAGE`; `tests/TestCase.php` sets 20) and `partials.pager` is only a fallback bar for bigger lists or no JavaScript (inside the `results` area; 20 per page; after a create, redirect to the page where the new row sits). The plain table is the no-JavaScript fallback,
  and empty lists keep their "No ... found" row. `partial.js` fires `partial:updated` after a swap so grids re-mount. New list
  tables: add `data-grid` (`<th data-flex="3">` = wider column; header cells = columns, one `<td>` per column, no colspan rows except the empty message).
  Cell text wraps to 2 lines (the cell span in grid.jsx needs `minWidth: 0` and `whiteSpace: 'normal'`: AG cells are nowrap flex containers,
  and without these long text is cut off on one line); give a column with long text or pills enough `data-flex` (see the Conversations table).
- Login page (`auth/login.blade.php`): full-bleed split screen (`main:has(.login-shell)` removes the page padding): indigo brand panel on the left,
  "Welcome back" form on the right (icon fields, show/hide password toggle, red invalid state, gradient button, "no public sign-up" note). The
  form keeps `data-no-ajax`. Its page CSS is pushed to `<head>` with `@push('assets')`; the small-screen `@media` rule must stay LAST so it
  overrides the base rules (the brand panel hides under 900px).
- Calendar (`CalendarController`, `/calendar`, a calendar icon beside the bell in the header, for everyone): a month grid of tickets by due date
  (overdue red; agents only their own via the Ticket scope), the user's own follow-ups and PRIVATE plans (`calendar_events`: title, notes, `start_at`
  in UTC, all-day, `remind_minutes`, `reminded_at`, status; every query and change goes through the signed-in user, others get 404, admins included).
  Regions: `cal-stats`, `cal-main` (grid), `cal-agenda` (selected day, one edit pop-up per plan), `cal-overdue`. The server runs in UTC:
  `App\Support\UserTime` reads the browser's zone from the `crc_tz` cookie or the hidden `tz` field (written by `public/js/notifications.js`; the cookie
  is excluded from encryption in `bootstrap/app.php`; aliases like `Asia/Calcutta` are accepted, an unknown zone falls back to UTC and never blocks saving).
  Reminders: `App\Services\EventReminders::sendDue()` sends one `EventReminder` notification per plan when start minus `remind_minutes` has passed
  (claimed with `UPDATE ... WHERE reminded_at IS NULL`, so never twice; stale ones are marked quietly). It runs from the bell poll
  (`GET /notifications/feed`, every 20 s) and `/notifications`, and from `php artisan crc:send-reminders` (scheduled every minute in
  `routes/console.php`; on the server add the cron line `* * * * * php artisan schedule:run` so people who are not in the app are reminded too). The feed gives
  the toast its kind, url and age: a reminder toast is amber, stays 15 s, is clickable, and also shows on the first poll when it just came due. Schema:
  migration `2026_11_04_000015`, mirrored in `database/sql/01_schema.sql`, `00_reset.sql` and `03_seed.sql` (20 sample plans).
- Follow-ups page (`FollowUpController@index`, `GET /follow-ups`, menu link for everyone): agents see only their own follow-ups, admins see all and can
  filter by agent; filters pending (default) / overdue / due / today / upcoming / done / all, plus search; "Mark done" uses `follow-ups.done`. The admin
  "Follow-ups overdue" and agent "Follow-ups due" dashboard cards link to it with the matching filter, so a dashboard number always has a list behind it.
- Notifications (database channel, in-app only): `TicketAssigned` (admin assigns: the agent is told) and `TicketCompleted` (an agent resolves
  the ticket they hold: the admin who made the latest assignment to them is told, else every active admin; see
  `TicketController::notifyCompleted`). Header bell (`partials/notification-bell`, `public/js/notifications.js`): unread badge, dropdown of the
  latest 8, a toast for a new one, polls `GET /notifications/feed` every 20 s and after every in-place page update (`partial:updated`).
  `/notifications` is the full list (a grid), `GET /notifications/{id}/open` marks one read and goes to its ticket, `POST /notifications/read`
  marks all read. Everything goes through `$request->user()->notifications()`, so a user only ever reaches their own. Text comes from
  `App\Support\NotificationPresenter`; agent-only visibility applies there too: if the agent no longer has the ticket, show only its number,
  never the title, and opening goes to the ticket list. New type: add a class, a case in the presenter and a test; new routes go in
  `SecurityJourneyTest`.
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
