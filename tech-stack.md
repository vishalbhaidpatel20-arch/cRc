# Tech Stack: Client Support Desk

| Layer | Choice | Why |
|-------|--------|-----|
| Language | PHP 8.2+ | Required by current Laravel |
| Framework | **Laravel** (latest) | Existing skill and plan; migrations, policies, notifications built in |
| Admin UI | **Filament** (panel `admin`) | Login, user management, tables and forms without hand-building them |
| Database | **MySQL** | Supports `FIELD()` priority ordering and the `idx_assignee_status (assigned_to, status)` index |
| Authorization | Laravel global scope + Policy (`TicketPolicy`) | Agent-only visibility enforced in one place, including search and reports |
| Notifications | Laravel database notifications, plus mail as an option | "New assignment" alert |
| Local dev | Laravel Herd (PHP, Composer, server) + MySQL (Herd or XAMPP) | Fast setup on Windows |
| Package manager | Composer | |
| Hosting | **AWS EC2** (Nginx, PHP-FPM, MySQL or RDS) | Planned deployment target |
| Backups | Scheduled `mysqldump` to S3, or RDS snapshots | Protect client data |
| Testing | Pest or PHPUnit | Test the visibility rule and policy |
| Version control | Git + GitHub | |

## Key design rules
- Role stored on `users.role` (`admin` or `agent`), plus `is_active`.
- Visibility rule lives only in the `Ticket` global scope and `TicketPolicy`.
- Ticket queue is ordered by priority, then `created_at`.
- Secrets live in `.env`, never in git.

## Core tables
`users`, `companies`, `contacts`, `tickets`, `ticket_history`, `interactions`, `message_templates`, `user_companies`, `notifications`.
