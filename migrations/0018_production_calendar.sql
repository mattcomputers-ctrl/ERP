-- Production calendar for workday tracking
CREATE TABLE IF NOT EXISTS production_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    calendar_date DATE NOT NULL,
    is_workday TINYINT(1) NOT NULL DEFAULT 1,
    override_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_facility_date (facility_id, calendar_date),
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_name) VALUES ('0018_production_calendar');
