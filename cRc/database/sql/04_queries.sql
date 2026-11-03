-- =====================================================================
-- 04_queries.sql - the 10 join queries. Run after 01, 02, 03.
-- Parameters are set as session variables at the top of each query.
-- "Open" = status is not 'resolved' (open, in_progress, waiting).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Q1. An agent's open queries with company and contact name  (INNER JOIN)
-- Shows every unresolved ticket of topic 'query' assigned to one agent, with who raised it.
-- Result: one row per ticket -> ticket_no, title, priority, status, company, contact.
SET @agent_id = 2;
SELECT t.ticket_no, t.title, t.priority, t.status,
       c.name      AS company,
       ct.full_name AS contact
FROM tickets t
JOIN companies c  ON c.company_id  = t.company_id
JOIN contacts  ct ON ct.contact_id = t.contact_id
WHERE t.assigned_to = @agent_id
  AND t.topic = 'query'
  AND t.status <> 'resolved'
ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at;

-- ---------------------------------------------------------------------
-- Q2. Employees of one company with conversation count and last conversation (LEFT JOIN + GROUP BY)
-- LEFT JOIN keeps employees who were never contacted (count 0, last NULL).
-- Result: one row per contact -> name, position, conversations, last_conversation.
SET @company_id = 1;
SELECT ct.contact_id, ct.full_name, ct.position,
       COUNT(i.interaction_id)  AS conversations,
       MAX(i.interaction_at)    AS last_conversation
FROM contacts ct
LEFT JOIN interactions i ON i.contact_id = ct.contact_id
WHERE ct.company_id = @company_id
  AND ct.is_active = 1
GROUP BY ct.contact_id, ct.full_name, ct.position
ORDER BY last_conversation DESC;

-- ---------------------------------------------------------------------
-- Q3. Workload per agent, including agents with zero open queries
--     (LEFT JOIN with the condition in ON)
-- The status/topic filter is in ON, not WHERE; in WHERE it would remove agents
-- with no matching tickets and turn the LEFT JOIN into an INNER JOIN.
-- Result: one row per active agent -> name, open_queries (0 allowed).
SELECT u.user_id, u.full_name,
       COUNT(t.ticket_id) AS open_queries
FROM users u
LEFT JOIN tickets t
       ON t.assigned_to = u.user_id
      AND t.topic = 'query'
      AND t.status <> 'resolved'
WHERE u.role = 'agent' AND u.is_active = 1
GROUP BY u.user_id, u.full_name
ORDER BY open_queries DESC, u.full_name;

-- ---------------------------------------------------------------------
-- Q4. Which employees use a given module, per company  (M-M, three joins)
-- contacts -> contact_modules -> product_modules, plus companies for grouping.
-- Result: one row per (company, employee) using that module.
SET @module_name = 'Module 1';
SELECT c.name AS company, ct.full_name AS employee, ct.position, pm.name AS module
FROM product_modules pm
JOIN contact_modules cm ON cm.module_id  = pm.module_id
JOIN contacts ct        ON ct.contact_id = cm.contact_id
JOIN companies c        ON c.company_id  = ct.company_id
WHERE pm.name = @module_name
  AND ct.is_active = 1
ORDER BY c.name, ct.full_name;

-- ---------------------------------------------------------------------
-- Q5. Full employee details including the optional profile  (1-1, LEFT JOIN)
-- Contacts without a profile row still appear, with NULL profile columns.
-- Result: one row per contact -> contact columns + alt_phone, birthday, language, ...
SELECT ct.contact_id, ct.full_name, c.name AS company, ct.position, ct.phone,
       p.alt_phone, p.birthday, p.language, p.preferred_time, p.notes
FROM contacts ct
JOIN companies c              ON c.company_id = ct.company_id
LEFT JOIN contact_profiles p  ON p.contact_id = ct.contact_id
ORDER BY c.name, ct.full_name;

-- ---------------------------------------------------------------------
-- Q6. The unassigned queue sorted by priority
-- assigned_to IS NULL means nobody owns the ticket yet; oldest first within a priority.
-- Result: one row per unassigned, unresolved ticket, most urgent first.
SELECT t.ticket_no, t.title, t.priority, t.status, c.name AS company, ct.full_name AS contact, t.created_at
FROM tickets t
JOIN companies c  ON c.company_id  = t.company_id
JOIN contacts  ct ON ct.contact_id = t.contact_id
WHERE t.assigned_to IS NULL
  AND t.status <> 'resolved'
ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at;

-- ---------------------------------------------------------------------
-- Q7. Assignment history of one ticket: from-agent, to-agent, assigned-by
--     (users joined three times, under three aliases)
-- LEFT JOIN for from/to because the first assignment has no from_user and
-- SET NULL may blank a user; assigned_by is NOT NULL so it can be INNER.
-- Result: one row per assignment, oldest first.
SET @ticket_no = 'TKT-0002';
SELECT t.ticket_no, ta.assigned_at,
       fu.full_name AS from_agent,
       tu.full_name AS to_agent,
       bu.full_name AS assigned_by,
       ta.reason
FROM ticket_assignments ta
JOIN tickets t        ON t.ticket_id = ta.ticket_id
LEFT JOIN users fu    ON fu.user_id  = ta.from_user
LEFT JOIN users tu    ON tu.user_id  = ta.to_user
JOIN users bu         ON bu.user_id  = ta.assigned_by
WHERE t.ticket_no = @ticket_no
ORDER BY ta.assigned_at;

-- ---------------------------------------------------------------------
-- Q8. Companies that have no tickets  (LEFT JOIN + IS NULL)
-- Result: one row per company with zero tickets (Delta Logistics in the sample).
SELECT c.company_id, c.company_code, c.name, c.city
FROM companies c
LEFT JOIN tickets t ON t.company_id = c.company_id
WHERE t.ticket_id IS NULL
  AND c.is_active = 1
ORDER BY c.name;

-- ---------------------------------------------------------------------
-- Q9. Which companies a restricted agent can see  (M-M via user_companies)
-- If all_companies = 1 the agent sees every company; otherwise only the rows in user_companies.
-- Result: one row per visible company (user 3 = Acme + Bluewave; user 2 = all four).
SET @user_id = 3;
SELECT c.company_id, c.company_code, c.name
FROM users u
JOIN companies c ON c.is_active = 1
LEFT JOIN user_companies uc
       ON uc.user_id = u.user_id AND uc.company_id = c.company_id
WHERE u.user_id = @user_id
  AND (u.all_companies = 1 OR uc.user_id IS NOT NULL)
ORDER BY c.name;

-- ---------------------------------------------------------------------
-- Q10. Today's and overdue follow-ups for one agent
-- due_date <= today with status 'pending' covers both; days_overdue > 0 marks the late ones.
-- Result: one row per pending follow-up -> contact, company, due_date, days_overdue.
SET @agent_id = 2;
SELECT f.follow_up_id, f.due_date, DATEDIFF(CURDATE(), f.due_date) AS days_overdue,
       f.description, ct.full_name AS contact, ct.phone, c.name AS company
FROM follow_ups f
JOIN contacts  ct ON ct.contact_id = f.contact_id
JOIN companies c  ON c.company_id  = ct.company_id
WHERE f.assigned_to = @agent_id
  AND f.status = 'pending'
  AND f.due_date <= CURDATE()
ORDER BY f.due_date;
