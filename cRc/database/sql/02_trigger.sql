-- =====================================================================
-- 02_trigger.sql - keep contacts.last_contacted_at current
-- GREATEST + COALESCE: a back-dated log entry never moves the date backwards,
-- and the first entry works even when last_contacted_at is NULL.
-- =====================================================================
DROP TRIGGER IF EXISTS trg_interactions_after_insert;

DELIMITER $$
CREATE TRIGGER trg_interactions_after_insert
AFTER INSERT ON interactions
FOR EACH ROW
BEGIN
  UPDATE contacts
     SET last_contacted_at = GREATEST(COALESCE(last_contacted_at, NEW.interaction_at), NEW.interaction_at)
   WHERE contact_id = NEW.contact_id;
END$$
DELIMITER ;
