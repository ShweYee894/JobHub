-- ============================================================================
-- MIGRATION: Add social_links JSON column to freelancers table
-- Migrates existing portfolio_url data into the new JSON structure
-- Safe to run MULTIPLE TIMES (idempotent)
-- ============================================================================

USE `freelancer_platform_db`;

-- Step 1: Add social_links JSON column after portfolio_url (if not exists)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'freelancers'
      AND COLUMN_NAME = 'social_links'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `freelancers` ADD COLUMN `social_links` JSON DEFAULT NULL AFTER `portfolio_url`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Migrate existing portfolio_url data into social_links JSON
-- Only migrates rows where portfolio_url is not empty AND social_links is still NULL
UPDATE freelancers
SET social_links = JSON_OBJECT('website', portfolio_url, 'github', NULL, 'linkedin', NULL)
WHERE portfolio_url IS NOT NULL
  AND portfolio_url != ''
  AND (social_links IS NULL OR social_links = 'null');
