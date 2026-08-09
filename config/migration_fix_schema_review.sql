-- ============================================================================
-- MIGRATION: Fix Schema Review Priorities (2026-07-22)
-- Freelancer Marketplace Platform
--
-- Fixes:
--   1. UNIQUE constraints on freelancer_skills and job_skills
--   2. Missing columns: jobs.is_featured, jobs.is_archived, reviews.is_hidden
--   3. ON DELETE behavior for reviews, contracts.cancelled_by, dispute_tickets
--   4. Missing updated_at on payments table
--
-- Safe to run MULTIPLE TIMES (idempotent)
-- ============================================================================

USE `freelancer_platform_db`;

-- ============================================================================
-- PART 1: UNIQUE CONSTRAINTS ON JUNCTION TABLES
-- ============================================================================

-- 1a. Deduplicate freelancer_skills (keep lowest id per pair)
SET @dup_count = (
    SELECT COUNT(*) FROM (
        SELECT freelancer_id, skill_id
        FROM `freelancer_skills`
        GROUP BY freelancer_id, skill_id
        HAVING COUNT(*) > 1
    ) AS dups
);

SET @dedup_sql = IF(@dup_count > 0,
    'DELETE fs1 FROM `freelancer_skills` fs1 INNER JOIN `freelancer_skills` fs2 WHERE fs1.freelancer_id = fs2.freelancer_id AND fs1.skill_id = fs2.skill_id AND fs1.id > fs2.id',
    'SELECT 1'
);
PREPARE stmt_dedup1 FROM @dedup_sql;
EXECUTE stmt_dedup1;
DEALLOCATE PREPARE stmt_dedup1;

-- Add UNIQUE constraint on freelancer_skills if missing
SET @uniq_fs = (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'freelancer_skills'
      AND INDEX_NAME = 'uniq_freelancer_skill'
    LIMIT 1
);
SET @add_uniq_fs = IF(@uniq_fs IS NULL,
    'ALTER TABLE `freelancer_skills` ADD UNIQUE KEY `uniq_freelancer_skill` (`freelancer_id`, `skill_id`)',
    'SELECT 1'
);
PREPARE stmt_uniq1 FROM @add_uniq_fs;
EXECUTE stmt_uniq1;
DEALLOCATE PREPARE stmt_uniq1;

-- 1b. Deduplicate job_skills (keep lowest id per pair)
SET @dup_count2 = (
    SELECT COUNT(*) FROM (
        SELECT job_id, skill_id
        FROM `job_skills`
        GROUP BY job_id, skill_id
        HAVING COUNT(*) > 1
    ) AS dups
);

SET @dedup_sql2 = IF(@dup_count2 > 0,
    'DELETE js1 FROM `job_skills` js1 INNER JOIN `job_skills` js2 WHERE js1.job_id = js2.job_id AND js1.skill_id = js2.skill_id AND js1.id > js2.id',
    'SELECT 1'
);
PREPARE stmt_dedup2 FROM @dedup_sql2;
EXECUTE stmt_dedup2;
DEALLOCATE PREPARE stmt_dedup2;

-- Add UNIQUE constraint on job_skills if missing
SET @uniq_js = (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'job_skills'
      AND INDEX_NAME = 'uniq_job_skill'
    LIMIT 1
);
SET @add_uniq_js = IF(@uniq_js IS NULL,
    'ALTER TABLE `job_skills` ADD UNIQUE KEY `uniq_job_skill` (`job_id`, `skill_id`)',
    'SELECT 1'
);
PREPARE stmt_uniq2 FROM @add_uniq_js;
EXECUTE stmt_uniq2;
DEALLOCATE PREPARE stmt_uniq2;

-- ============================================================================
-- PART 2: ADD MISSING COLUMNS
-- ============================================================================

-- 2a. jobs.is_featured
SET @col1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'is_featured');
SET @sql1 = IF(@col1 = 0,
    'ALTER TABLE `jobs` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `proposal_count`',
    'SELECT "Column jobs.is_featured already exists" AS status'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2b. jobs.is_archived
SET @col2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'is_archived');
SET @sql2 = IF(@col2 = 0,
    'ALTER TABLE `jobs` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`',
    'SELECT "Column jobs.is_archived already exists" AS status'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 2c. reviews.is_hidden
SET @col3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'is_hidden');
SET @sql3 = IF(@col3 = 0,
    'ALTER TABLE `reviews` ADD COLUMN `is_hidden` TINYINT(1) DEFAULT 0 AFTER `comment`',
    'SELECT "Column reviews.is_hidden already exists" AS status'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;

-- 2d. payments.updated_at
SET @col4 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'updated_at');
SET @sql4 = IF(@col4 = 0,
    'ALTER TABLE `payments` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
    'SELECT "Column payments.updated_at already exists" AS status'
);
PREPARE s4 FROM @sql4; EXECUTE s4; DEALLOCATE PREPARE s4;

-- ============================================================================
-- PART 3: FIX ON DELETE BEHAVIOR (drop and recreate FKs)
-- ============================================================================

-- Helper procedure
DELIMITER $$

DROP PROCEDURE IF EXISTS `fix_fk_on_delete`$$
CREATE PROCEDURE `fix_fk_on_delete`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_ref_table VARCHAR(64),
    IN p_ref_column VARCHAR(64),
    IN p_on_delete VARCHAR(50)
)
BEGIN
    DECLARE v_fk_name VARCHAR(64);

    -- Find existing FK on this column
    SELECT CONSTRAINT_NAME INTO v_fk_name
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
      AND REFERENCED_TABLE_NAME = p_ref_table
      AND REFERENCED_COLUMN_NAME = p_ref_column
    LIMIT 1;

    -- Drop old FK if it exists
    IF v_fk_name IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', v_fk_name, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    -- Check if the new FK constraint already exists
    SET @new_fk_exists = (
        SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
          AND REFERENCED_TABLE_NAME = p_ref_table
          AND REFERENCED_COLUMN_NAME = p_ref_column
          AND REFERENCED_TABLE_NAME IS NOT NULL
        LIMIT 1
    );

    -- Add FK with correct ON DELETE if not already pointing correctly
    IF @new_fk_exists IS NULL OR v_fk_name IS NOT NULL THEN
        SET @constraint_name = CONCAT('fk_', p_table, '_', p_column);
        SET @sql2 = CONCAT(
            'ALTER TABLE `', p_table, '` ADD CONSTRAINT `', @constraint_name, '`',
            ' FOREIGN KEY (`', p_column, '`) REFERENCES `', p_ref_table, '`(`', p_ref_column, '`) ON DELETE ', p_on_delete
        );
        PREPARE stmt2 FROM @sql2;
        EXECUTE stmt2;
        DEALLOCATE PREPARE stmt2;
    END IF;
END$$

DELIMITER ;

-- 3a. reviews.reviewer_id -> ON DELETE SET NULL
CALL fix_fk_on_delete('reviews', 'reviewer_id', 'users', 'id', 'SET NULL');

-- 3b. reviews.reviewee_id -> ON DELETE SET NULL
CALL fix_fk_on_delete('reviews', 'reviewee_id', 'users', 'id', 'SET NULL');

-- 3c. contracts.cancelled_by -> ON DELETE SET NULL
CALL fix_fk_on_delete('contracts', 'cancelled_by', 'users', 'id', 'SET NULL');

-- 3d. dispute_tickets.resolved_by -> ON DELETE SET NULL
CALL fix_fk_on_delete('dispute_tickets', 'resolved_by', 'users', 'id', 'SET NULL');

-- ============================================================================
-- PART 4: CLEANUP
-- ============================================================================

DROP PROCEDURE IF EXISTS `fix_fk_on_delete`;

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 'Migration complete! Added UNIQUE constraints, 4 missing columns, fixed 4 FK ON DELETE behaviors.' AS result;
