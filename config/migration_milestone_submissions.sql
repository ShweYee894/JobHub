-- ============================================================================
-- MIGRATION: Add Milestone Submission Columns
-- Adds GitHub URL, file attachment, submission note, and submission date
-- Safe to run MULTIPLE TIMES (idempotent)
-- ============================================================================

USE `freelancer_platform_db`;

-- Add submission columns to milestones table
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'milestones'
      AND COLUMN_NAME = 'submission_github_url'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE milestones ADD COLUMN submission_github_url VARCHAR(500) NULL AFTER description',
    'SELECT "Column submission_github_url already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'milestones'
      AND COLUMN_NAME = 'submission_file'
);

SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE milestones ADD COLUMN submission_file VARCHAR(255) NULL AFTER submission_github_url',
    'SELECT "Column submission_file already exists" AS status'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @col_exists3 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'milestones'
      AND COLUMN_NAME = 'submission_note'
);

SET @sql3 = IF(@col_exists3 = 0,
    'ALTER TABLE milestones ADD COLUMN submission_note TEXT NULL AFTER submission_file',
    'SELECT "Column submission_note already exists" AS status'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @col_exists4 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'milestones'
      AND COLUMN_NAME = 'submission_date'
);

SET @sql4 = IF(@col_exists4 = 0,
    'ALTER TABLE milestones ADD COLUMN submission_date TIMESTAMP NULL AFTER submission_note',
    'SELECT "Column submission_date already exists" AS status'
);
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- Add index for submission_date
SET @idx_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'milestones'
      AND INDEX_NAME = 'idx_milestones_submission_date'
);

SET @sql5 = IF(@idx_exists = 0,
    'ALTER TABLE milestones ADD INDEX idx_milestones_submission_date (submission_date)',
    'SELECT "Index already exists" AS status'
);
PREPARE stmt5 FROM @sql5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

SELECT 'Migration complete: milestones submission columns added' AS result;
