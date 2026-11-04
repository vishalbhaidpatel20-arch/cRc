-- OPTIONAL: wipes all 14 tables. Run manually only when you want a clean rebuild.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS calendar_events, notifications, follow_ups, interactions, ticket_comments, ticket_assignments,
  tickets, user_companies, contact_modules, contact_profiles, contacts,
  message_templates, product_modules, companies, users;
SET FOREIGN_KEY_CHECKS = 1;

