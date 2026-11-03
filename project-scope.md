# Project Scope: Client Support Desk

## 1. Background
Our company sells a software product to **10 client companies**. Each client has multiple businesses and 3-4 employees who use the product daily. I talk to those employees every day about queries, bugs and customization requests. Today this is untracked. This project builds an internal support desk to record and manage it all.

## 2. Goals
- Keep one record of every client company and only the employees I talk to (contacts), not its whole staff.
- Log every query, bug and customization request as a ticket.
- Assign tickets to support agents. An agent sees **only** their own tickets.
- Log daily conversations and reuse message templates.
- Give the admin a view of the unassigned queue and each agent's workload.

## 3. Users and roles
| Role | Can do |
|------|--------|
| **Admin** | Sees everything. Creates users and agents, manages companies and contacts, assigns and reassigns tickets, views the dashboard. |
| **Agent** | Sees only tickets assigned to them. Can view the details of every company contact (name, email, phone, company, position) and can converse with any contact, logging each conversation as an interaction. Uses templates. **Cannot assign or reassign tickets** (admin only). |

Clients do not log in. They are records only.

## 4. In scope
1. **Authentication and roles** (admin/agent, active flag). Only admins create users.
2. **Companies and Contacts**
   - Company 1-M Contacts (name, email, phone, position, company).
   - All agents can view every contact and converse with them. The ticket-only restriction applies to tickets, not contacts.
   - Optional M-M `user_companies` to limit an agent to certain companies later, if needed.
3. **Tickets**: ticket_no, title, description, type (query/bug/customization), priority (critical/high/medium/low), status, company, raised-by contact, `assigned_to`.
   - Agent-only visibility through a global scope and a policy.
   - Reassignment: the old agent loses access, and a history table records who handled it.
4. **Interactions**: a conversation log per ticket and contact (date, channel, notes).
5. **Message templates**: reusable replies.
6. **Notifications**: in-app "new assignment" alert, with email as an option.
7. **Dashboard**: unassigned queue and workload per agent.
8. **Deployment** to AWS EC2 with database backups.

## 5. Data relationships
- Company **1-M** Contacts
- Company **1-M** Tickets
- Contact **1-M** Tickets (raised by)
- User (agent) **1-M** Tickets (assigned_to)
- Ticket **1-M** Interactions and Ticket **1-M** Ticket history
- User **M-M** Company through `user_companies` (optional access grant)
- User **1-1** profile (optional)

## 6. Out of scope (for now)
- Client-facing portal or login
- Email-to-ticket ingestion, WhatsApp integration
- SLA timers, billing, mobile app
- Multi-language support

## 7. Assumptions to confirm
- Agent visibility is limited to their own tickets. Contact details and conversations with contacts are open to all agents.
- Team size is small (a few agents), with thousands of tickets over time.
- Notifications are in-app first, with email added later.

## 8. Success criteria
- An admin can create agents, and agents cannot reach the Users screen.
- An agent opening another agent's ticket by URL or ID is denied.
- Every client conversation is logged and searchable.
- The team uses it in production on EC2, with backups running.
