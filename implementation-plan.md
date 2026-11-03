# Implementation Plan: Client Support Desk

Do each milestone only after the previous one works.

## Milestone 1: Project, login, roles (about 1 hour)
1. Check tools: `php -v` (8.2+), `composer -V`. Install Laravel Herd if missing. Have MySQL running.
2. `composer create-project laravel/laravel support-desk`, then create the `support_desk` DB and set `.env` (`DB_*`).
3. `php artisan migrate`.
4. `composer require filament/filament` and `php artisan filament:install --panels` (panel: `admin`).
5. Migration `add_role_to_users_table`: `role` enum(admin, agent) default agent, `is_active` boolean default true. Add both to `$fillable` in `User`.
6. `php artisan make:filament-user`, then set your role to `admin` via tinker.
7. `php artisan serve` and log in at `/admin`.
8. `php artisan make:filament-resource User --generate`. Restrict `canViewAny()` to admins and make role a dropdown.

**Done when:** you can sign in, create an agent as admin, and the agent (private window) does not see the Users screen.

## Milestone 2: Companies and Contacts
- Migrations: `companies` (name, etc.), `contacts` (company_id, name, email, phone, position).
- Optional `user_companies` pivot (M-M).
- Filament resources for both. Enter the 10 clients and their 3-4 people each.

**Done when:** all 10 companies and their employees are entered and searchable.

## Milestone 3: Tickets and agent-only visibility
- Migrations: `tickets` (ticket_no, title, description, type, priority, status, company_id, contact_id, assigned_to, created_at) with index `idx_assignee_status (assigned_to, status)`, and `ticket_history`.
- `Ticket` global scope restricting agents to `assigned_to = their id`. Admins are unfiltered.
- `TicketPolicy::view`: admin, or `assigned_to` equals the user.
- "Assign ticket" action, **admin only** (enforced in `TicketPolicy::assign` and hidden from agents), that writes history and sends the "new assignment" database notification.
- Agent "my open tickets" screen using the `FIELD(priority,...)` ordering.
- Tests: an agent cannot open another agent's ticket, and an admin sees all.

**Done when:** an admin assigns a ticket, the agent sees only theirs, and URL guessing is denied.

## Milestone 4: Interactions and templates
- Migrations: `interactions` (ticket_id, contact_id, user_id, channel, notes, occurred_at) and `message_templates`.
- Log a conversation from the ticket screen, and insert a template into a reply.

**Done when:** daily conversations are logged against tickets and contacts.

## Milestone 5: Dashboard
- Unassigned queue widget.
- Workload per agent (open tickets, by priority).
- Admin only.

**Done when:** the admin can see and balance the team's load.

## Milestone 6: Deploy
- Provision EC2 (Nginx, PHP-FPM, MySQL or RDS), set production `.env`, run migrations, enable HTTPS.
- Scheduled DB backups (mysqldump to S3 or RDS snapshots) with a tested restore.
- Queue worker and scheduler via Supervisor and cron.

**Done when:** the team uses it in production and a backup restore has been verified.

## Open decisions
- After reassignment, the old agent loses access and history keeps the record. Confirm.
- Agents see all contacts and can converse with any of them (decided). Tickets stay visible only to the assigned agent. Optionally restrict contacts per agent later via `user_companies`.
- In-app notification only, or email as well.
