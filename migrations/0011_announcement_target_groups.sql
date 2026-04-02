-- Migration: 0011_announcement_target_groups
-- Description: Add target groups table for announcements
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_target_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    group_id INT NOT NULL,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add unique constraint on announcement_dismissals if not present
ALTER TABLE announcement_dismissals ADD UNIQUE KEY uq_dismissal (announcement_id, user_id);

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0011_announcement_target_groups');
