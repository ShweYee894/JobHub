-- Drop unused user_agent column from user_behavior_logs
-- This column was never written to (user_agent is stored in payload JSON instead)

ALTER TABLE user_behavior_logs DROP COLUMN user_agent;
