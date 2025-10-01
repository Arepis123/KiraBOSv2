-- Add 'view_menu' to action_type ENUM in activity_logs table

ALTER TABLE activity_logs
MODIFY COLUMN action_type ENUM('create','update','delete','login','logout','enable','disable','view_menu') NOT NULL;

-- Verify the change
DESCRIBE activity_logs;
