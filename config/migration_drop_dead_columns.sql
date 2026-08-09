-- ============================================================================
-- MIGRATION: Drop Truly Dead Implementation Artifacts
-- Freelancer Marketplace Platform
-- Date: 2026-07-22
--
-- Columns removed:
--   1. users.login_attempts     — brute-force protection never implemented
--   2. chat_messages.delivered_at — delivery status never implemented
--   3. user_behavior_logs.session_id — session tracking never implemented
--   4. user_behavior_logs.user_agent — write-only; code writes to payload JSON instead
--
-- Safe to run MULTIPLE TIMES (idempotent)
-- ============================================================================

USE `freelancer_platform_db`;

-- Helper procedure for safe column drop
DELIMITER $$

DROP PROCEDURE IF EXISTS `safe_drop_column`$$
CREATE PROCEDURE `safe_drop_column`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Dropped: ', p_table, '.', p_column) AS result;
    ELSE
        SELECT CONCAT('Already dropped: ', p_table, '.', p_column) AS result;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- Drop truly dead columns
-- ============================================================================

-- 1. users.login_attempts — brute-force protection never implemented
CALL safe_drop_column('users', 'login_attempts');

-- 2. chat_messages.delivered_at — delivery status never implemented
CALL safe_drop_column('chat_messages', 'delivered_at');

-- 3. user_behavior_logs.session_id — session tracking never implemented
CALL safe_drop_column('user_behavior_logs', 'session_id');

-- 4. user_behavior_logs.user_agent — code writes to payload JSON column instead
CALL safe_drop_column('user_behavior_logs', 'user_agent');

-- ============================================================================
-- CLEANUP
-- ============================================================================

DROP PROCEDURE IF EXISTS `safe_drop_column`;

SELECT 'Migration complete! Dropped 4 dead implementation artifact columns.' AS result;
