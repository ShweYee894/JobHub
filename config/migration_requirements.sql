-- ============================================================================
-- MIGRATION: Fix Schema Issues & Add Missing Requirements
-- Freelancer Marketplace Platform
-- 
-- Based on ACTUAL database structure from phpMyAdmin export
-- Safe to run MULTIPLE TIMES (idempotent)
-- ============================================================================

USE `freelancer_platform_db`;

-- ============================================================================
-- PART 0: CRITICAL FIX - jobs.client_id FK is WRONG
-- Currently: jobs.client_id -> clients(id) [auto-increment ID]
-- Should be: jobs.client_id -> clients(client_id) [which is the users.id]
-- 
-- The clients table has: id (auto-inc), client_id (references users.id)
-- The jobs.client_id stores the USER_ID of the client, not clients.id
-- So it must reference clients(client_id), NOT clients(id)
-- ============================================================================

-- Find and drop the wrong FK
SET @wrong_fk = (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'jobs'
      AND COLUMN_NAME = 'client_id'
      AND REFERENCED_TABLE_NAME = 'clients'
      AND REFERENCED_COLUMN_NAME = 'id'
    LIMIT 1
);

SET @drop_fk_sql = IF(@wrong_fk IS NOT NULL,
    CONCAT('ALTER TABLE `jobs` DROP FOREIGN KEY `', @wrong_fk, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @drop_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add correct FK: jobs.client_id -> clients(client_id)
-- (client_id in clients table = users.id, which is what jobs.client_id stores)
SET @correct_fk_exists = (
    SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'jobs'
      AND COLUMN_NAME = 'client_id'
      AND REFERENCED_TABLE_NAME = 'clients'
      AND REFERENCED_COLUMN_NAME = 'client_id'
    LIMIT 1
);

SET @add_fk_sql = IF(@correct_fk_exists IS NULL,
    'ALTER TABLE `jobs` ADD CONSTRAINT `fk_jobs_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`client_id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt2 FROM @add_fk_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ============================================================================
-- PART 0b: Fix contracts.proposal_id - add missing FK
-- ============================================================================

SET @proposal_fk_exists = (
    SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contracts'
      AND COLUMN_NAME = 'proposal_id'
      AND REFERENCED_TABLE_NAME = 'proposals'
    LIMIT 1
);

SET @add_proposal_fk = IF(@proposal_fk_exists IS NULL,
    'ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals`(`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt3 FROM @add_proposal_fk;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- ============================================================================
-- PART 1: HELPER PROCEDURES
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `safe_add_column`$$
CREATE PROCEDURE `safe_add_column`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `safe_add_index`$$
CREATE PROCEDURE `safe_add_index`(
    IN p_table VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_columns TEXT,
    IN p_is_unique TINYINT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index_name
    ) THEN
        IF p_is_unique = 1 THEN
            SET @sql = CONCAT('CREATE UNIQUE INDEX `', p_index_name, '` ON `', p_table, '` (', p_columns, ')');
        ELSE
            SET @sql = CONCAT('CREATE INDEX `', p_index_name, '` ON `', p_table, '` (', p_columns, ')');
        END IF;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `safe_drop_index_by_column`$$
CREATE PROCEDURE `safe_drop_index_by_column`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    DECLARE v_index_name VARCHAR(64);
    
    SELECT INDEX_NAME INTO v_index_name
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
      AND NON_UNIQUE = 0
      AND INDEX_NAME != 'PRIMARY'
      AND SEQ_IN_INDEX = 1
    LIMIT 1;
    
    IF v_index_name IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', v_index_name, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- PART 2: UNIQUE CONSTRAINTS
-- ============================================================================

-- 1 chat room per contract
CALL safe_add_index('chat_rooms', 'uniq_room_per_contract', 'contract_id', 1);

-- 1 skill per job (already exists as uniq_job_skill - skip)
-- 1 skill per freelancer (already exists as uniq_freelancer_skill - skip)

-- Fix reviews: allow 2 reviews per contract (one from each party)
-- Current: UNIQUE on contract_id alone (only 1 review per contract)
-- Change to: UNIQUE on (contract_id, reviewer_id)
CALL safe_drop_index_by_column('reviews', 'contract_id');
CALL safe_add_index('reviews', 'uniq_review_per_reviewer', 'contract_id, reviewer_id', 1);

-- ============================================================================
-- PART 3: PERFORMANCE INDEXES
-- ============================================================================

-- Chat: fetch messages by room (most frequent query)
CALL safe_add_index('chat_messages', 'idx_chat_msg_room_time', 'room_id, created_at', 0);
CALL safe_add_index('chat_messages', 'idx_chat_msg_unread', 'room_id, is_read, created_at', 0);

-- Proposals: filter by job or freelancer + status
CALL safe_add_index('proposals', 'idx_proposals_job_status', 'job_id, status', 0);
CALL safe_add_index('proposals', 'idx_proposals_freelancer_status', 'freelancer_id, status', 0);

-- Contracts: filter by status, client, or freelancer
CALL safe_add_index('contracts', 'idx_contracts_status_date', 'status, created_at', 0);
CALL safe_add_index('contracts', 'idx_contracts_client', 'client_id, status', 0);
CALL safe_add_index('contracts', 'idx_contracts_freelancer', 'freelancer_id, status', 0);

-- Payments: filter by status, payer, or payee
CALL safe_add_index('payments', 'idx_payments_status_date', 'status, created_at', 0);
CALL safe_add_index('payments', 'idx_payments_payer', 'payer_id, status', 0);
CALL safe_add_index('payments', 'idx_payments_payee', 'payee_id, status', 0);

-- Milestones: filter by contract + status
CALL safe_add_index('milestones', 'idx_milestones_contract_status', 'contract_id, status', 0);

-- Jobs: filter by status + date
CALL safe_add_index('jobs', 'idx_jobs_status_date', 'status, created_at', 0);

-- Fraud detection: IP and action type queries
CALL safe_add_index('user_behavior_logs', 'idx_behavior_ip_date', 'ip_address, created_at', 0);
CALL safe_add_index('user_behavior_logs', 'idx_behavior_action_date', 'action_type, created_at', 0);

-- User admin: fraud score filtering
CALL safe_add_index('users', 'idx_users_fraud_status', 'fraud_score, status', 0);

-- Skill search: join performance
CALL safe_add_index('job_skills', 'idx_js_skill', 'skill_id', 0);
CALL safe_add_index('freelancer_skills', 'idx_fs_skill', 'skill_id', 0);

-- ============================================================================
-- PART 4: NEW TABLES
-- ============================================================================

-- WALLET TRANSACTIONS: Full audit trail for every wallet balance change
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`        INT UNSIGNED NOT NULL,
    `type`           ENUM('deposit','withdrawal','escrow_hold','escrow_release','refund','platform_fee','signup_bonus') NOT NULL,
    `amount`         DECIMAL(10,2) NOT NULL,
    `balance_after`  DECIMAL(10,2) NOT NULL,
    `reference_id`   INT UNSIGNED NULL,
    `reference_type` VARCHAR(50) NULL,
    `description`    TEXT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_wt_user_date` (`user_id`, `created_at`),
    INDEX `idx_wt_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DISPUTE TICKETS: Track contract/milestone disputes
CREATE TABLE IF NOT EXISTS `dispute_tickets` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contract_id`    INT UNSIGNED NOT NULL,
    `milestone_id`   INT UNSIGNED NULL,
    `raised_by`      INT UNSIGNED NOT NULL,
    `against`        INT UNSIGNED NOT NULL,
    `reason`         ENUM('non_delivery','quality_issue','scope_dispute','payment_issue','other') NOT NULL,
    `description`    TEXT NOT NULL,
    `status`         ENUM('open','investigating','resolved','dismissed','escalated') DEFAULT 'open',
    `resolution`     TEXT NULL,
    `resolved_by`    INT UNSIGNED NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`contract_id`)  REFERENCES `contracts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`milestone_id`) REFERENCES `milestones`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`raised_by`)    REFERENCES `users`(`id`),
    FOREIGN KEY (`against`)      REFERENCES `users`(`id`),
    INDEX `idx_dispute_status` (`status`),
    INDEX `idx_dispute_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PLATFORM FEE RULES: Configurable fee percentages
CREATE TABLE IF NOT EXISTS `platform_fee_rules` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fee_percent`    DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `min_fee`        DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    `max_fee`        DECIMAL(10,2) NULL,
    `effective_from` DATE NOT NULL,
    `effective_to`   DATE NULL,
    `is_active`      TINYINT(1) DEFAULT 1,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_fee_active` (`is_active`, `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `platform_fee_rules`
    (`fee_percent`, `min_fee`, `max_fee`, `effective_from`, `is_active`)
SELECT 10.00, 5.00, 500.00, '2025-01-01', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_fee_rules` LIMIT 1);

-- FRAUD ALERTS: Track automated fraud detections
CREATE TABLE IF NOT EXISTS `fraud_alerts` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`        INT UNSIGNED NOT NULL,
    `rule_name`      VARCHAR(100) NOT NULL,
    `alert_level`    ENUM('low','medium','high','critical') NOT NULL,
    `description`    TEXT NOT NULL,
    `score_impact`   INT NOT NULL,
    `status`         ENUM('open','investigating','resolved','dismissed') DEFAULT 'open',
    `resolved_by`    INT UNSIGNED NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_fraud_alert_user` (`user_id`, `status`),
    INDEX `idx_fraud_alert_level` (`alert_level`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PART 5: ADD MISSING COLUMNS
-- ============================================================================

-- payments: refund tracking + payment method
CALL safe_add_column('payments', 'refund_amount',  'DECIMAL(10,2) DEFAULT 0.00 AFTER `freelancer_net`');
CALL safe_add_column('payments', 'refund_reason',  'TEXT NULL AFTER `refund_amount`');
CALL safe_add_column('payments', 'refunded_at',    'TIMESTAMP NULL AFTER `refund_reason`');
CALL safe_add_column('payments', 'payment_method', "ENUM('wallet','stripe','paypal','bank_transfer') DEFAULT 'wallet' AFTER `status`");

-- chat_messages: read/delivered timestamps
CALL safe_add_column('chat_messages', 'read_at',     'TIMESTAMP NULL AFTER `is_read`');
CALL safe_add_column('chat_messages', 'delivered_at','TIMESTAMP NULL AFTER `read_at`');

-- contracts: lifecycle management
CALL safe_add_column('contracts', 'start_date',          'DATE NULL AFTER `status`');
CALL safe_add_column('contracts', 'end_date',            'DATE NULL AFTER `start_date`');
CALL safe_add_column('contracts', 'deadline',            'DATE NULL AFTER `end_date`');
CALL safe_add_column('contracts', 'cancellation_reason', 'TEXT NULL AFTER `deadline`');
CALL safe_add_column('contracts', 'cancelled_by',        'INT UNSIGNED NULL AFTER `cancellation_reason`');
CALL safe_add_column('contracts', 'dispute_status',      "ENUM('none','open','resolved','escalated') DEFAULT 'none' AFTER `cancelled_by`");

-- milestones: due dates and ordering
CALL safe_add_column('milestones', 'due_date',    'DATE NULL AFTER `amount`');
CALL safe_add_column('milestones', 'sort_order',  'INT DEFAULT 0 AFTER `due_date`');
CALL safe_add_column('milestones', 'description', 'TEXT NULL AFTER `sort_order`');

-- user_behavior_logs: session tracking for fraud detection
CALL safe_add_column('user_behavior_logs', 'user_agent', 'VARCHAR(500) NULL AFTER `ip_address`');
CALL safe_add_column('user_behavior_logs', 'session_id', 'VARCHAR(100) NULL AFTER `user_agent`');

-- users: security tracking
CALL safe_add_column('users', 'email_verified_at', 'TIMESTAMP NULL AFTER `email`');
CALL safe_add_column('users', 'last_login_at',     'TIMESTAMP NULL AFTER `wallet_balance`');
CALL safe_add_column('users', 'login_attempts',    'INT DEFAULT 0 AFTER `last_login_at`');

-- jobs: deadline + denormalized proposal count
CALL safe_add_column('jobs', 'deadline',        'DATE NULL AFTER `budget`');
CALL safe_add_column('jobs', 'proposal_count',  'INT DEFAULT 0 AFTER `embedding_vector`');

-- freelancers: performance stats
CALL safe_add_column('freelancers', 'total_earnings', 'DECIMAL(10,2) DEFAULT 0.00 AFTER `years_of_experience`');
CALL safe_add_column('freelancers', 'completed_jobs', 'INT DEFAULT 0 AFTER `total_earnings`');

-- clients: job count
CALL safe_add_column('clients', 'total_jobs', 'INT DEFAULT 0 AFTER `total_spent`');

-- ============================================================================
-- PART 6: CLEANUP
-- ============================================================================

DROP PROCEDURE IF EXISTS `safe_add_column`;
DROP PROCEDURE IF EXISTS `safe_add_index`;
DROP PROCEDURE IF EXISTS `safe_drop_index_by_column`;

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 'Migration complete! Fixed jobs.client_id FK, added contracts.proposal_id FK, 4 tables, 17 indexes, 20+ columns.' AS result;
