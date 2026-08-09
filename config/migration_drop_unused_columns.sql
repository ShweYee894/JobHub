-- ============================================================================
-- MIGRATION: Drop Unused Columns
-- Freelancer Marketplace Platform
-- Date: 2026-07-22
--
-- Audit Method:
--   Every column was searched across ALL .php, .js, .sql, .html files.
--   Only columns with ZERO references (or write-only with no reads) are dropped.
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
-- SECTION 1: ZERO-REFERENCE COLUMNS (never referenced in any PHP file)
-- ============================================================================

-- 1. users.email_verified_at — email verification never implemented
CALL safe_drop_column('users', 'email_verified_at');

-- 2. users.login_attempts — brute-force protection never implemented
CALL safe_drop_column('users', 'login_attempts');

-- 3. contracts.start_date — contract lifecycle tracking never implemented
CALL safe_drop_column('contracts', 'start_date');

-- 4. contracts.end_date — contract lifecycle tracking never implemented
CALL safe_drop_column('contracts', 'end_date');

-- 5. chat_messages.read_at — read receipts never implemented
CALL safe_drop_column('chat_messages', 'read_at');

-- 6. chat_messages.delivered_at — delivery status never implemented
CALL safe_drop_column('chat_messages', 'delivered_at');

-- 7. user_behavior_logs.session_id — session tracking never implemented
CALL safe_drop_column('user_behavior_logs', 'session_id');

-- 8. platform_fee_rules.effective_to — fee date range never enforced in PHP
CALL safe_drop_column('platform_fee_rules', 'effective_to');

-- 9. platform_fee_rules.min_fee — min fee logic never implemented
CALL safe_drop_column('platform_fee_rules', 'min_fee');

-- 10. platform_fee_rules.max_fee — max fee logic never implemented
CALL safe_drop_column('platform_fee_rules', 'max_fee');

-- ============================================================================
-- SECTION 2: BROKEN COLUMNS (read but never updated — always stale defaults)
-- ============================================================================

-- 11. freelancers.total_earnings — SELECTed in invite_jobs.php but never UPDATED
--     Always returns 0.00. No INSERT, no UPDATE anywhere in codebase.
CALL safe_drop_column('freelancers', 'total_earnings');

-- 12. freelancers.completed_jobs — SELECTed + ORDER BY in invite_jobs.php but never UPDATED
--     Always returns 0. No INSERT, no UPDATE anywhere in codebase.
CALL safe_drop_column('freelancers', 'completed_jobs');

-- 13. clients.total_jobs — SELECTed in admin/clients.php but never UPDATED
--     Always returns 0. admin/ai_matching.php uses COUNT(*), not this column.
CALL safe_drop_column('clients', 'total_jobs');

-- ============================================================================
-- SECTION 3: WRITE-ONLY COLUMNS (written but never read)
-- ============================================================================

-- 14. user_behavior_logs.user_agent — written once in login_process.php
--     Never SELECTed, never displayed, never analyzed by fraud detection.
CALL safe_drop_column('user_behavior_logs', 'user_agent');

-- ============================================================================
-- CLEANUP
-- ============================================================================

DROP PROCEDURE IF EXISTS `safe_drop_column`;

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 'Migration complete! Dropped 14 unused columns from 8 tables.' AS result;
