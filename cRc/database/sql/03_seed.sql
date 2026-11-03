-- =====================================================================
-- 03_seed.sql - sample data (made-up names). Run AFTER 01 and 02.
-- Dates are relative to NOW() so "today / overdue" queries always have rows.
-- =====================================================================
SET NAMES utf8mb4;

-- users: all sample users log in with the password "password".
-- 1 admin, 2 full-access agents (one with no tickets), 1 restricted agent
INSERT INTO users (user_id, full_name, email, phone, password_hash, role, all_companies, is_active, created_by) VALUES
 (1, 'Anita Shah',  'anita@support.example',  '9000000001', '$2y$12$AYFD0GzwF2WRZzaFwG9nWu7qN7Z1EUBqBloCQNimhsi8khlufapwq', 'admin', 1, 1, NULL),
 (2, 'Rahul Mehta', 'rahul@support.example',  '9000000002', '$2y$12$AYFD0GzwF2WRZzaFwG9nWu7qN7Z1EUBqBloCQNimhsi8khlufapwq', 'agent', 1, 1, 1),
 (3, 'Priya Nair',  'priya@support.example',  '9000000003', '$2y$12$AYFD0GzwF2WRZzaFwG9nWu7qN7Z1EUBqBloCQNimhsi8khlufapwq', 'agent', 0, 1, 1),
 (4, 'Karan Joshi', 'karan@support.example',  '9000000004', NULL,                                                              'agent', 1, 1, 1);  -- invite not yet accepted

INSERT INTO companies (company_id, company_code, name, industry, email, phone, city) VALUES
 (1, 'CMP001', 'Acme Traders',      'Trading',   'info@acme.example',     '0791000001', 'Ahmedabad'),
 (2, 'CMP002', 'Bluewave Foods',    'Food',      'info@bluewave.example', '0221000002', 'Mumbai'),
 (3, 'CMP003', 'Crescent Textiles', 'Textile',   'info@crescent.example', '0261000003', 'Surat'),
 (4, 'CMP004', 'Delta Logistics',   'Logistics', 'info@delta.example',    '0111000004', 'Delhi');   -- will have no tickets

INSERT INTO product_modules (module_id, name, description) VALUES
 (1, 'Module 1', 'Placeholder module one'),
 (2, 'Module 2', 'Placeholder module two');

INSERT INTO contacts (contact_id, company_id, full_name, email, phone, whatsapp_no, position, department, business_name, product_role, preferred_channel, is_primary) VALUES
 (1, 1, 'Neha Kapoor',  'neha@acme.example',      '9810000001', '9810000001', 'Director',      'Management', 'Acme Traders',      'decision_maker', 'call',     1),
 (2, 1, 'Vikram Rao',   'vikram@acme.example',    '9810000002', '9810000002', 'Executive',     'Sales',      'Acme Traders',      'end_user',       'whatsapp', 0),
 (3, 1, 'Sana Khan',    'sana@acme.example',      '9810000003', NULL,         'Accountant',    'Finance',    'Acme Traders',      'finance',        'email',    0),
 (4, 2, 'Arjun Desai',  'arjun@bluewave.example', '9820000001', '9820000001', 'IT Manager',    'IT',         'Bluewave Foods',    'admin',          'call',     1),
 (5, 2, 'Meera Iyer',   'meera@bluewave.example', '9820000002', '9820000002', 'Store Keeper',  'Stores',     'Bluewave Foods',    'end_user',       'whatsapp', 0),
 (6, 3, 'Rohan Gupta',  'rohan@crescent.example', '9830000001', '9830000001', 'IT Head',       'IT',         'Crescent Textiles', 'technical',      'email',    1),
 (7, 3, 'Isha Verma',   'isha@crescent.example',  '9830000002', NULL,         'Operator',      'Production', 'Crescent Textiles', 'end_user',       'call',     0),
 (8, 4, 'Tarun Bose',   'tarun@delta.example',    '9840000001', '9840000001', 'Owner',         'Management', 'Delta Logistics',   'decision_maker', 'meeting',  1),
 (9, 4, 'Pooja Sethi',  'pooja@delta.example',    '9840000002', NULL,         'Clerk',         'Dispatch',   'Delta Logistics',   'end_user',       'email',    0);

-- profiles only for contacts 1-6 (7, 8, 9 have none -> LEFT JOIN shows NULLs)
INSERT INTO contact_profiles (contact_id, alt_phone, birthday, language, preferred_time, notes) VALUES
 (1, '9811111111', '1985-03-14', 'English', 'Morning',   'Prefers short calls'),
 (2, NULL,         '1992-07-21', 'Hindi',   'Afternoon', NULL),
 (3, '9812222222', '1988-11-02', 'English', 'Morning',   'Mail only after 5 pm'),
 (4, NULL,         '1980-01-30', 'English', 'Evening',   'Technical, wants details'),
 (5, NULL,         '1995-05-09', 'Gujarati','Morning',   NULL),
 (6, '9833333333', '1983-09-17', 'English', 'Afternoon', 'Escalate critical bugs directly');

INSERT INTO contact_modules (contact_id, module_id) VALUES
 (1,1),(2,1),(2,2),(3,2),(4,1),(4,2),(5,1),(6,2),(7,2),(8,1);

-- restricted agent Priya sees Acme and Bluewave only
INSERT INTO user_companies (user_id, company_id) VALUES (3,1),(3,2);

INSERT INTO tickets (ticket_id, ticket_no, company_id, contact_id, assigned_to, topic, priority, status, title, description, due_at, created_at) VALUES
 (1, 'TKT-0001', 1, 1, 2,    'query',         'high',     'open',        'How to export monthly report',    'Client wants to export the monthly sales report to Excel.',         NOW() + INTERVAL 2 DAY,  NOW() - INTERVAL 5 DAY),
 (2, 'TKT-0002', 1, 2, 2,    'bug',           'critical', 'in_progress', 'Invoice total shows wrong value', 'Invoice total does not include tax after the last update.',      NOW() - INTERVAL 1 DAY,  NOW() - INTERVAL 4 DAY),
 (3, 'TKT-0003', 2, 4, 3,    'customization', 'medium',   'waiting',     'Add custom field to order form',  'Needs an extra field for delivery slot on the order form.',          NOW() + INTERVAL 10 DAY, NOW() - INTERVAL 8 DAY),
 (4, 'TKT-0004', 2, 5, NULL, 'query',         'low',      'open',        'Where to change print layout',    'Asked how to change the print layout of delivery challan.',          NULL,                    NOW() - INTERVAL 1 DAY),
 (5, 'TKT-0005', 3, 6, NULL, 'bug',           'critical', 'open',        'System hangs on stock screen',    'Stock screen freezes when more than 500 rows are loaded.',           NOW() + INTERVAL 1 DAY,  NOW() - INTERVAL 2 DAY),
 (6, 'TKT-0006', 3, 7, 2,    'query',         'medium',   'resolved',    'Reset operator password',         'Operator could not log in, password reset needed.',                  NULL,                    NOW() - INTERVAL 9 DAY),
 (7, 'TKT-0007', 1, 3, 3,    'query',         'medium',   'open',        'Tax report filter question',      'Which filter gives GST-wise report for the quarter?',                NOW() + INTERVAL 4 DAY,  NOW() - INTERVAL 3 DAY),
 (8, 'TKT-0008', 2, 4, NULL, 'customization', 'high',     'open',        'Custom invoice format request',   'Wants company logo and bank details on the invoice format.',         NOW() + INTERVAL 7 DAY,  NOW() - INTERVAL 6 HOUR);

INSERT INTO ticket_assignments (assignment_id, ticket_id, from_user, to_user, assigned_by, reason, assigned_at) VALUES
 (1, 1, NULL, 2, 1, 'First assignment',             NOW() - INTERVAL 5 DAY),
 (2, 2, NULL, 2, 1, 'First assignment',             NOW() - INTERVAL 4 DAY),
 (3, 2, 2,    3, 1, 'Priya knows the invoice code', NOW() - INTERVAL 3 DAY),
 (4, 2, 3,    2, 1, 'Priya on leave, back to Rahul',NOW() - INTERVAL 2 DAY),
 (5, 3, NULL, 3, 1, 'First assignment',             NOW() - INTERVAL 8 DAY),
 (6, 6, NULL, 2, 1, 'First assignment',             NOW() - INTERVAL 9 DAY),
 (7, 7, NULL, 3, 1, 'First assignment',             NOW() - INTERVAL 3 DAY);

INSERT INTO ticket_comments (comment_id, ticket_id, user_id, contact_id, message, is_internal, created_at) VALUES
 (1, 1, 2,    NULL, 'Looking into the export option.',                    0, NOW() - INTERVAL 4 DAY),
 (2, 1, NULL, 1,    'Thanks, please share steps by tomorrow.',            0, NOW() - INTERVAL 4 DAY + INTERVAL 1 HOUR),
 (3, 2, 2,    NULL, 'Reproduced the issue, tax rule missing.',            1, NOW() - INTERVAL 3 DAY),
 (4, 2, NULL, 2,    'Any update? We are sending wrong invoices.',         0, NOW() - INTERVAL 1 DAY),
 (5, 3, 3,    NULL, 'Quoted 2 days of work, waiting for approval.',       0, NOW() - INTERVAL 6 DAY),
 (6, 5, NULL, 6,    'Happens every morning after the data import.',       0, NOW() - INTERVAL 2 DAY);

-- interactions: inserted one by one so the trigger sets contacts.last_contacted_at
INSERT INTO interactions (interaction_id, contact_id, user_id, ticket_id, topic, channel, direction, summary, interaction_at) VALUES
 (1,  1, 2, 1,    'query',         'call',     'inbound',  'Neha asked how to export the monthly report; explained the steps.',    NOW() - INTERVAL 5 DAY),
 (2,  1, 2, 1,    'follow_up',     'whatsapp', 'outbound', 'Sent screenshots of the export screen.',                               NOW() - INTERVAL 4 DAY),
 (3,  2, 2, 2,    'bug',           'call',     'inbound',  'Vikram reported invoice total mismatch.',                              NOW() - INTERVAL 4 DAY),
 (4,  2, 2, 2,    'bug',           'email',    'outbound', 'Confirmed the bug and shared the expected fix time.',                  NOW() - INTERVAL 2 DAY),
 (5,  4, 3, 3,    'customization', 'meeting',  'outbound', 'Met Arjun and noted requirements for the delivery slot field.',        NOW() - INTERVAL 7 DAY),
 (6,  5, 3, 4,    'query',         'whatsapp', 'inbound',  'Meera asked about the print layout for challans.',                     NOW() - INTERVAL 1 DAY),
 (7,  6, 2, 5,    'bug',           'call',     'inbound',  'Rohan reported the stock screen freezing issue.',                      NOW() - INTERVAL 2 DAY),
 (8,  7, 2, 6,    'query',         'call',     'inbound',  'Isha could not log in; password reset done.',                          NOW() - INTERVAL 9 DAY),
 (9,  3, 3, 7,    'query',         'email',    'inbound',  'Sana asked which filter gives the GST-wise report.',                   NOW() - INTERVAL 3 DAY),
 (10, 8, 2, NULL, 'general',       'meeting',  'outbound', 'Intro meeting with Tarun, no open issues.',                            NOW() - INTERVAL 12 DAY);

-- follow-ups: overdue + due today + future + done + cancelled
INSERT INTO follow_ups (follow_up_id, contact_id, interaction_id, assigned_to, due_date, description, status, completed_at) VALUES
 (1, 2, 4,    2, CURDATE() - INTERVAL 3 DAY, 'Confirm invoice fix is working',      'pending',   NULL),
 (2, 1, 2,    2, CURDATE(),                  'Check whether export steps worked',   'pending',   NULL),
 (3, 6, 7,    2, CURDATE() + INTERVAL 2 DAY, 'Call back after patch is ready',      'pending',   NULL),
 (4, 7, 8,    2, CURDATE() - INTERVAL 8 DAY, 'Confirm password works',              'done',      NOW() - INTERVAL 8 DAY),
 (5, 4, 5,    3, CURDATE(),                  'Share quote for custom field',        'pending',   NULL),
 (6, 5, 6,    3, CURDATE() - INTERVAL 1 DAY, 'Send print layout guide',             'pending',   NULL),
 (7, 8, 10,   2, CURDATE() - INTERVAL 5 DAY, 'Quarterly check-in',                  'cancelled', NULL);

INSERT INTO message_templates (template_id, name, category, channel, subject, body) VALUES
 (1, 'Ticket received',   'query',     'email',    'We received your request {{ticket_no}}', 'Hello {{name}}, we have received your request and will update you shortly.'),
 (2, 'Ticket received',   'query',     'whatsapp', NULL,                                      'Hi {{name}}, your request {{ticket_no}} is received. We will update you soon.'),
 (3, 'Bug acknowledged',  'bug',       'email',    'Bug {{ticket_no}} acknowledged',          'Hello {{name}}, we have reproduced the issue and are working on a fix.'),
 (4, 'Follow-up reminder','follow_up', 'whatsapp', NULL,                                      'Hi {{name}}, just checking whether the issue is now resolved.');
