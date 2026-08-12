-- Migration: Add evidence file support to dispute_tickets
-- Run this SQL on the jobhub database

-- Add evidence files storage (JSON array of file metadata)
ALTER TABLE `dispute_tickets` ADD COLUMN `evidence_files` JSON NULL AFTER `updated_at`;

-- Add evidence request tracking columns
ALTER TABLE `dispute_tickets` ADD COLUMN `evidence_request_note` TEXT NULL AFTER `evidence_files`;
ALTER TABLE `dispute_tickets` ADD COLUMN `evidence_request_target` INT UNSIGNED NULL COMMENT 'user_id being asked for evidence' AFTER `evidence_request_note`;
ALTER TABLE `dispute_tickets` ADD COLUMN `evidence_request_fulfilled` TINYINT(1) DEFAULT 0 COMMENT '1 = party responded to evidence request' AFTER `evidence_request_target`;
