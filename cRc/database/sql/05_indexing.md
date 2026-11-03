# 05 - Indexing review

Checked on MariaDB 10.4 with the sample data (tiny tables). Every primary key, UNIQUE key and
foreign key also has an index; the table below covers the extra/composite ones.

**Rule used for composite order:** equality columns first, then the range or sort column last.
MySQL/MariaDB can use an index left-to-right and stops at the first range column.

| Index | Serves | Why this column order |
|---|---|---|
| `contacts (company_id, is_active)` | Q2, Q4 (employee lists of a company) | `company_id` is always `=`; `is_active` is a second `=` filter. Leading column also covers the FK `company_id` alone. |
| `contacts (full_name)`, `(phone)` | Search box / caller lookup | Single column: lookups are by one value or prefix. |
| `contacts (last_contacted_at)` | "Not contacted for N days" lists | Range/sort on one column. |
| `tickets (assigned_to, status)` | Q1, Q3, Q6, agent dashboard | `assigned_to` is `=` (or `IS NULL` for the unassigned queue); `status` narrows after. Reversed order would not help Q6, which has no useful status equality. |
| `tickets (status, priority)` | Queue screens "open + critical" | `status` has few values, but the pair matches filter then sort by priority. |
| `tickets (company_id, status)` | Company page "open tickets"; Q8 join | `company_id` equality first (high selectivity), `status` second. |
| `tickets (contact_id)` | Contact page ticket list; FK | Single equality. |
| `tickets (status, due_at)` | Overdue tickets: `status <> 'resolved' AND due_at < NOW()` | `status` equality/filter first, `due_at` range last. |
| `tickets (created_at)` | Newest-first lists, date reports | Sort/range on one column. |
| `FULLTEXT tickets (title, description)` | Ticket search with `MATCH ... AGAINST` | Both columns searched together. |
| `ticket_assignments (ticket_id, assigned_at)` | Q7 history, already time-ordered | `ticket_id` equality, `assigned_at` is the `ORDER BY`, so no filesort. |
| `ticket_comments (ticket_id, created_at)` | Comment thread of a ticket, oldest first | Same pattern as above. |
| `interactions (contact_id, interaction_at)` | Q2, contact timeline, `MAX(interaction_at)` | Equality on contact, then sorted/maxed by time. |
| `interactions (user_id, interaction_at)` | "What did this agent do today" | Same pattern per agent. |
| `interactions (ticket_id)` | Conversations of a ticket; FK | Single equality. |
| `interactions (interaction_at)` | Daily log across all agents | Range on time only. |
| `FULLTEXT interactions (summary)` | Search conversation text | One text column. |
| `follow_ups (assigned_to, status, due_date)` | Q10 | Two equalities (`assigned_to`, `status`) then the range `due_date <= today`. Putting `due_date` earlier would stop the index at the range. |
| `contact_modules (module_id)` | Q4 "who uses module X" | The PK `(contact_id, module_id)` serves contact -> modules; this reverse index serves module -> contacts. |
| `user_companies (company_id)` | "Which agents can see company X" | The PK `(user_id, company_id)` serves user -> companies; this serves the reverse. |

## EXPLAIN results (sample data)

| Query | Result |
|---|---|
| Q1 agent's open queries | `tickets`: `type=ALL` (8 rows), candidates `idx_tickets_assignee_status`, `idx_tickets_status_*`. The optimizer scans because the table is tiny; with `FORCE INDEX` or real volume it uses `idx_tickets_assignee_status`. `companies` and `contacts` use `eq_ref` on PRIMARY. |
| Q2 contact conversation counts | `contacts`: `ref` on `idx_contacts_company_active` (Using index); `interactions`: `ref` on `idx_int_contact_time` (Using index, no table read). |
| Q3 workload | `users` scanned (4 rows); `tickets` `ref` via `idx_tickets_assignee_status`. |
| Q4 module users | `product_modules`: `const` on `uq_modules_name`; `contact_modules`: `ref` on `idx_cm_module` (Using index); `contacts`: `eq_ref` PRIMARY. |
| Q6 unassigned queue | Default plan: `ALL` + `Using filesort` (8 rows). With `FORCE INDEX (idx_tickets_assignee_status)`: `type=ref`, key used, still `Using filesort` because `FIELD(priority,...)` is an expression that no index can order. Fine for a small queue; if it grows, store priority as a sortable number. |
| Q7 assignment history | `ticket_assignments`: `ref` on `idx_ta_ticket_time`, Using index, no filesort. |
| Q8 companies without tickets | `companies` index scan; `tickets`: `ref` on `idx_tickets_company_status`, `Not exists` (anti-join optimisation). |
| Q10 follow-ups | `follow_ups`: `type=range` on `idx_fu_assignee_status_due`, `Using index`, no filesort. |
| Ticket search | `type=fulltext`, key `ft_tickets_text`. |

**Honest caveat:** with 8 ticket rows the optimizer prefers a full scan (cheaper than an index
lookup), so Q1 and Q6 show `ALL` by default. Re-run the `EXPLAIN` on realistic volume
(thousands of tickets) to see the indexes chosen automatically.

## Optional improvements (not applied)
- `tickets (assigned_to, topic, status)` if Q1/Q3 become hot: covers all three conditions.
- `contacts (company_id, is_primary)` if "primary contact of a company" is queried a lot.
