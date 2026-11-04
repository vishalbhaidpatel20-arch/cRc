-- =====================================================================
-- 01_schema.sql  -  14 tables, in dependency order (MariaDB 10.4 / MySQL 8)
-- Delete rules:  RESTRICT = protect company/user history (no hard deletes;
--                use is_active instead)
--                CASCADE  = child records die with their parent
--                SET NULL = optional links
-- =====================================================================
SET NAMES utf8mb4;
-- (Run 00_reset.sql first only if you need to rebuild over existing tables.)

-- 1. users -------------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  phone          VARCHAR(30)  NULL,
  password_hash  VARCHAR(255) NULL COMMENT 'NULL until the invite is accepted',
  role           ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  all_companies  TINYINT(1)   NOT NULL DEFAULT 1,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_by     INT UNSIGNED NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. companies ---------------------------------------------------------
CREATE TABLE companies (
  company_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_code  VARCHAR(20)  NOT NULL,
  name          VARCHAR(150) NOT NULL,
  industry      VARCHAR(100) NULL,
  email         VARCHAR(190) NULL,
  phone         VARCHAR(30)  NULL,
  city          VARCHAR(80)  NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (company_id),
  UNIQUE KEY uq_companies_code (company_code),
  UNIQUE KEY uq_companies_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. product_modules (names are entered by the admin, never hard-coded) -
CREATE TABLE product_modules (
  module_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(255) NULL,
  PRIMARY KEY (module_id),
  UNIQUE KEY uq_modules_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. contacts (client employees) --------------------------------------
CREATE TABLE contacts (
  contact_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id         INT UNSIGNED NOT NULL,
  full_name          VARCHAR(120) NOT NULL,
  email              VARCHAR(190) NOT NULL,
  phone              VARCHAR(30)  NULL,
  whatsapp_no        VARCHAR(30)  NULL,
  position           VARCHAR(100) NULL,
  department         VARCHAR(100) NULL,
  business_name      VARCHAR(150) NULL,
  product_role       ENUM('end_user','admin','decision_maker','technical','finance') NOT NULL DEFAULT 'end_user',
  preferred_channel  ENUM('call','whatsapp','email','meeting') NOT NULL DEFAULT 'email',
  is_primary         TINYINT(1)   NOT NULL DEFAULT 0,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  last_contacted_at  DATETIME     NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (contact_id),
  UNIQUE KEY uq_contacts_email (email),
  KEY idx_contacts_company_active (company_id, is_active),
  KEY idx_contacts_full_name (full_name),
  KEY idx_contacts_phone (phone),
  KEY idx_contacts_last_contacted (last_contacted_at),
  CONSTRAINT fk_contacts_company FOREIGN KEY (company_id)
    REFERENCES companies (company_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. contact_profiles (1-1 with contacts) -----------------------------
CREATE TABLE contact_profiles (
  contact_id      INT UNSIGNED NOT NULL,
  alt_phone       VARCHAR(30)  NULL,
  birthday        DATE         NULL,
  language        VARCHAR(40)  NULL,
  preferred_time  VARCHAR(40)  NULL,
  notes           TEXT         NULL,
  PRIMARY KEY (contact_id),
  CONSTRAINT fk_profiles_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. contact_modules (M-M contacts <-> product_modules) ----------------
CREATE TABLE contact_modules (
  contact_id  INT UNSIGNED NOT NULL,
  module_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (contact_id, module_id),
  KEY idx_cm_module (module_id),
  CONSTRAINT fk_cm_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_module FOREIGN KEY (module_id)
    REFERENCES product_modules (module_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. user_companies (M-M users <-> companies) --------------------------
CREATE TABLE user_companies (
  user_id     INT UNSIGNED NOT NULL,
  company_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, company_id),
  KEY idx_uc_company (company_id),
  CONSTRAINT fk_uc_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_uc_company FOREIGN KEY (company_id)
    REFERENCES companies (company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. tickets -----------------------------------------------------------
CREATE TABLE tickets (
  ticket_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_no    VARCHAR(20)  NOT NULL,
  company_id   INT UNSIGNED NOT NULL,
  contact_id   INT UNSIGNED NOT NULL,
  assigned_to  INT UNSIGNED NULL COMMENT 'NULL = unassigned',
  topic        ENUM('query','bug','customization') NOT NULL DEFAULT 'query',
  priority     ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status       ENUM('open','in_progress','waiting','resolved') NOT NULL DEFAULT 'open',
  title        VARCHAR(200) NOT NULL,
  description  TEXT         NULL,
  due_at       DATETIME     NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id),
  UNIQUE KEY uq_tickets_no (ticket_no),
  KEY idx_tickets_assignee_status (assigned_to, status),
  KEY idx_tickets_status_priority (status, priority),
  KEY idx_tickets_company_status (company_id, status),
  KEY idx_tickets_contact (contact_id),
  KEY idx_tickets_status_due (status, due_at),
  KEY idx_tickets_created (created_at),
  FULLTEXT KEY ft_tickets_text (title, description),
  CONSTRAINT fk_tickets_company FOREIGN KEY (company_id)
    REFERENCES companies (company_id) ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_to)
    REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. ticket_assignments (reassignment history) -------------------------
CREATE TABLE ticket_assignments (
  assignment_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id      INT UNSIGNED NOT NULL,
  from_user      INT UNSIGNED NULL COMMENT 'NULL on first assignment',
  to_user        INT UNSIGNED NULL,
  assigned_by    INT UNSIGNED NOT NULL,
  reason         VARCHAR(255) NULL,
  assigned_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (assignment_id),
  KEY idx_ta_ticket_time (ticket_id, assigned_at),
  KEY idx_ta_from (from_user),
  KEY idx_ta_to (to_user),
  KEY idx_ta_by (assigned_by),
  CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets (ticket_id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_from FOREIGN KEY (from_user)
    REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_ta_to FOREIGN KEY (to_user)
    REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_ta_by FOREIGN KEY (assigned_by)
    REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. ticket_comments --------------------------------------------------
-- user_id / contact_id use RESTRICT (not SET NULL): SET NULL could null both
-- columns and break the CHECK; and we never hard-delete users/contacts anyway.
CREATE TABLE ticket_comments (
  comment_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NULL,
  contact_id   INT UNSIGNED NULL,
  message      TEXT         NOT NULL,
  is_internal  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comment_id),
  KEY idx_tc_ticket_time (ticket_id, created_at),
  KEY idx_tc_user (user_id),
  KEY idx_tc_contact (contact_id),
  CONSTRAINT chk_tc_author CHECK (user_id IS NOT NULL OR contact_id IS NOT NULL),
  CONSTRAINT fk_tc_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets (ticket_id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_tc_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. interactions (daily conversation log) ----------------------------
CREATE TABLE interactions (
  interaction_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  ticket_id       INT UNSIGNED NULL,
  topic           ENUM('query','bug','customization','follow_up','general') NOT NULL DEFAULT 'general',
  channel         ENUM('call','whatsapp','email','meeting') NOT NULL,
  direction       ENUM('inbound','outbound') NOT NULL,
  summary         TEXT         NOT NULL,
  interaction_at  DATETIME     NOT NULL,
  PRIMARY KEY (interaction_id),
  KEY idx_int_contact_time (contact_id, interaction_at),
  KEY idx_int_user_time (user_id, interaction_at),
  KEY idx_int_ticket (ticket_id),
  KEY idx_int_time (interaction_at),
  FULLTEXT KEY ft_int_summary (summary),
  CONSTRAINT fk_int_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE RESTRICT,
  CONSTRAINT fk_int_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_int_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets (ticket_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. follow_ups -------------------------------------------------------
CREATE TABLE follow_ups (
  follow_up_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id      INT UNSIGNED NOT NULL,
  interaction_id  INT UNSIGNED NULL,
  assigned_to     INT UNSIGNED NOT NULL,
  due_date        DATE         NOT NULL,
  description     VARCHAR(255) NOT NULL,
  status          ENUM('pending','done','cancelled') NOT NULL DEFAULT 'pending',
  completed_at    DATETIME     NULL,
  PRIMARY KEY (follow_up_id),
  KEY idx_fu_assignee_status_due (assigned_to, status, due_date),
  KEY idx_fu_contact (contact_id),
  KEY idx_fu_interaction (interaction_id),
  CONSTRAINT fk_fu_contact FOREIGN KEY (contact_id)
    REFERENCES contacts (contact_id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_interaction FOREIGN KEY (interaction_id)
    REFERENCES interactions (interaction_id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_assignee FOREIGN KEY (assigned_to)
    REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. message_templates ------------------------------------------------
CREATE TABLE message_templates (
  template_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  category     ENUM('query','bug','customization','follow_up','general') NOT NULL DEFAULT 'general',
  channel      ENUM('email','whatsapp') NOT NULL,
  subject      VARCHAR(200) NULL,
  body         TEXT         NOT NULL COMMENT 'placeholders like {{name}}',
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (template_id),
  UNIQUE KEY uq_templates_name_channel (name, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. notifications (Laravel database notifications: "new assignment" alerts) -
CREATE TABLE notifications (
  id               CHAR(36)        NOT NULL,
  type             VARCHAR(255)    NOT NULL,
  notifiable_type  VARCHAR(255)    NOT NULL,
  notifiable_id    BIGINT UNSIGNED NOT NULL,
  data             TEXT            NOT NULL,
  read_at          TIMESTAMP       NULL,
  created_at       TIMESTAMP       NULL,
  updated_at       TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. calendar_events (each user's own plans on the calendar; start_at is UTC) ------------------
CREATE TABLE calendar_events (
  event_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  title           VARCHAR(150) NOT NULL,
  notes           TEXT         NULL,
  start_at        DATETIME     NOT NULL COMMENT 'UTC',
  all_day         TINYINT(1)   NOT NULL DEFAULT 0,
  remind_minutes  SMALLINT UNSIGNED NULL COMMENT 'minutes before start_at; NULL = no reminder',
  reminded_at     DATETIME     NULL COMMENT 'set once the reminder notification was sent',
  status          ENUM('planned','done') NOT NULL DEFAULT 'planned',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_ce_user_start (user_id, start_at),
  KEY idx_ce_reminders (status, reminded_at, start_at),
  CONSTRAINT fk_ce_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

