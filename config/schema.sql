CREATE DATABASE IF NOT EXISTS `freelancer_platform_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `freelancer_platform_db`;

-- 1. USERS TABLE 

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255),
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `role` ENUM('client', 'freelancer', 'admin') NOT NULL,
    `status` ENUM('active', 'flagged', 'suspended') DEFAULT 'active',
    `fraud_score` INT DEFAULT 0, 
    `wallet_balance` DECIMAL(10, 2) DEFAULT 2000.00,
    `remember_token` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_role` (`role`),
    INDEX `idx_user_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Freelancer Extension Profile Table (1:1 with users)
CREATE TABLE freelancers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL, -- e.g., "Full Stack Developer"
    bio TEXT NULL,
    skills_vector JSON DEFAULT NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    portfolio_url VARCHAR(255) NULL,
    resume_file VARCHAR(255) NULL,
    years_of_experience INT DEFAULT 0,
    availability ENUM('Available', 'Busy', 'Unavailable') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Enforce 1:1 relationship and cascade deletes if user account is deleted
    CONSTRAINT fk_freelancer_user 
        FOREIGN KEY (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Client Extension Profile Table (1:1 with users)
CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,  -- references users.id
    company_logo VARCHAR(255),
    company_name VARCHAR(150) NULL,
    company_website VARCHAR(255) NULL,
    industry VARCHAR(100) NULL,
    company_size ENUM('Startup','Small','Medium','Large'),
    total_spent DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_client_user 
        FOREIGN KEY (client_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- 4. SKILLS TABLE (Master Skills List)

CREATE TABLE IF NOT EXISTS `skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `skill_name` VARCHAR(100) NOT NULL UNIQUE,
    `category` VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_skill_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. FREELANCER SKILLS TABLE (Freelancer Skills Mapping)

CREATE TABLE IF NOT EXISTS `freelancer_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `freelancer_id` INT UNSIGNED NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. JOBS TABLE 

CREATE TABLE IF NOT EXISTS `jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `budget` DECIMAL(10, 2) NOT NULL,
    `job_type` ENUM('hourly', 'fixed') DEFAULT 'fixed',
    `experience_level` ENUM('entry', 'intermediate', 'expert') DEFAULT 'intermediate',
    `project_duration` VARCHAR(50) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `deadline` DATE DEFAULT NULL,
    `max_freelancers` INT DEFAULT 1,
    `status` ENUM('open', 'in_progress', 'completed', 'disputed', 'cancelled') DEFAULT 'open',
    `embedding_vector` JSON DEFAULT NULL,
    `proposal_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
    INDEX `idx_job_status` (`status`),
    INDEX `idx_job_type` (`job_type`),
    INDEX `idx_job_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  Keyword Search 
ALTER TABLE `jobs` ADD FULLTEXT `ft_job_search` (`title`, `description`);


-- 7. JOB SKILLS TABLE (Job Skills Mapping)

CREATE TABLE IF NOT EXISTS `job_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT UNSIGNED NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. PROPOSALS TABLE 

CREATE TABLE IF NOT EXISTS `proposals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT UNSIGNED NOT NULL,
    `freelancer_id` INT UNSIGNED NOT NULL,
    `proposal_text` TEXT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_freelancer_job` (`job_id`, `freelancer_id`), 
    INDEX `idx_proposal_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. CONTRACTS TABLE 

CREATE TABLE IF NOT EXISTS `contracts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_id` INT UNSIGNED NOT NULL,
    `job_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `freelancer_id` INT UNSIGNED NOT NULL,
    `contract_type` ENUM('fixed', 'hourly') DEFAULT 'fixed',
    `total_budget` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('active', 'completed', 'disputed', 'terminated') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 10. CHAT ROOMS TABLE 
CREATE TABLE IF NOT EXISTS `chat_rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contract_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 11. CHAT MESSAGES TABLE 

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `payload` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_chat_msg_room_id` (`room_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 12. MILESTONES TABLE 

CREATE TABLE IF NOT EXISTS `milestones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contract_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'funded_in_escrow', 'submitted', 'released', 'disputed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 13. PAYMENTS TABLE 

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `milestone_id` INT UNSIGNED NOT NULL,
    `payer_id` INT UNSIGNED NOT NULL,
    `payee_id` INT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `platform_fee` DECIMAL(10, 2) DEFAULT 0.00, -- 10%
    `freelancer_net` DECIMAL(10, 2) DEFAULT 0.00, -- 90%
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 14. REVIEWS TABLE 

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contract_id` INT UNSIGNED NOT NULL,
    `reviewer_id` INT UNSIGNED NOT NULL,
    `reviewee_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users (id),
    FOREIGN KEY (reviewee_id) REFERENCES users (id),
    UNIQUE KEY `uniq_review_per_reviewer` (`contract_id`, `reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 15. USER BEHAVIOR LOGS TABLE 

    CREATE TABLE IF NOT EXISTS user_behavior_logs (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `action_type` VARCHAR(100) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `payload` JSON DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        INDEX idx_action_user (user_id, action_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 16. TYPING INDICATORS TABLE

CREATE TABLE IF NOT EXISTS `typing_indicators` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `is_typing` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_room_user` (`room_id`, `user_id`),
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- ADDITIONAL TABLES (Added from schema review)
-- ============================================================================

-- 17. WALLET TRANSACTIONS TABLE (Audit trail for wallet balance changes)
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('deposit', 'withdrawal', 'escrow_hold', 'escrow_release', 'refund', 'platform_fee', 'signup_bonus') NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `balance_after` DECIMAL(10, 2) NOT NULL,
    `reference_id` INT UNSIGNED NULL COMMENT 'payment_id or contract_id',
    `reference_type` VARCHAR(50) NULL COMMENT 'payment, contract, milestone',
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_wt_user_date` (`user_id`, `created_at`),
    INDEX `idx_wt_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. DISPUTE TICKETS TABLE
CREATE TABLE IF NOT EXISTS `dispute_tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contract_id` INT UNSIGNED NOT NULL,
    `milestone_id` INT UNSIGNED NULL,
    `raised_by` INT UNSIGNED NOT NULL COMMENT 'user_id who raised dispute',
    `against` INT UNSIGNED NOT NULL COMMENT 'user_id being disputed',
    `reason` ENUM('non_delivery', 'quality_issue', 'scope_dispute', 'payment_issue', 'other') NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('open', 'investigating', 'resolved', 'dismissed', 'escalated') DEFAULT 'open',
    `resolution` TEXT NULL,
    `resolved_by` INT UNSIGNED NULL COMMENT 'admin user_id',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`),
    FOREIGN KEY (`against`) REFERENCES `users` (`id`),
    INDEX `idx_dispute_status` (`status`),
    INDEX `idx_dispute_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. PLATFORM FEE RULES TABLE
CREATE TABLE IF NOT EXISTS `platform_fee_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fee_percent` DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
    `min_fee` DECIMAL(10, 2) NOT NULL DEFAULT 5.00,
    `max_fee` DECIMAL(10, 2) NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_fee_active` (`is_active`, `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default fee rule
INSERT INTO `platform_fee_rules` (`fee_percent`, `min_fee`, `max_fee`, `effective_from`, `is_active`)
VALUES (10.00, 5.00, 500.00, '2025-01-01', 1);

-- 20. FRAUD ALERTS TABLE
CREATE TABLE IF NOT EXISTS `fraud_alerts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `rule_name` VARCHAR(100) NOT NULL,
    `alert_level` ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    `description` TEXT NOT NULL,
    `score_impact` INT NOT NULL,
    `status` ENUM('open', 'investigating', 'resolved', 'dismissed') DEFAULT 'open',
    `resolved_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_fraud_alert_user` (`user_id`, `status`),
    INDEX `idx_fraud_alert_level` (`alert_level`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- PERFORMANCE INDEXES (Critical for production)
-- ============================================================================

CREATE INDEX `idx_chat_msg_room_time` ON `chat_messages` (`room_id`, `created_at`);
CREATE INDEX `idx_chat_msg_unread` ON `chat_messages` (`room_id`, `is_read`, `created_at`);
CREATE INDEX `idx_proposals_job_status` ON `proposals` (`job_id`, `status`);
CREATE INDEX `idx_proposals_freelancer_status` ON `proposals` (`freelancer_id`, `status`);
CREATE INDEX `idx_contracts_status_date` ON `contracts` (`status`, `created_at`);
CREATE INDEX `idx_contracts_client` ON `contracts` (`client_id`, `status`);
CREATE INDEX `idx_contracts_freelancer` ON `contracts` (`freelancer_id`, `status`);
CREATE INDEX `idx_payments_status_date` ON `payments` (`status`, `created_at`);
CREATE INDEX `idx_payments_payer` ON `payments` (`payer_id`, `status`);
CREATE INDEX `idx_payments_payee` ON `payments` (`payee_id`, `status`);
CREATE INDEX `idx_milestones_contract_status` ON `milestones` (`contract_id`, `status`);
CREATE INDEX `idx_jobs_status_date` ON `jobs` (`status`, `created_at`);
CREATE INDEX `idx_behavior_ip_date` ON `user_behavior_logs` (`ip_address`, `created_at`);
CREATE INDEX `idx_behavior_action_date` ON `user_behavior_logs` (`action_type`, `created_at`);
CREATE INDEX `idx_users_fraud_status` ON `users` (`fraud_score`, `status`);
CREATE INDEX `idx_js_skill` ON `job_skills` (`skill_id`);
CREATE INDEX `idx_fs_skill` ON `freelancer_skills` (`skill_id`);