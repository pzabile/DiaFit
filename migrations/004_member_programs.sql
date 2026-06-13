-- Multiple programs per member (one per week)
CREATE TABLE IF NOT EXISTS member_programs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    lead_id      INT NOT NULL,
    week_number  INT NOT NULL DEFAULT 1,
    title        VARCHAR(255) NULL,
    file_path    VARCHAR(255) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_lead_week (lead_id, week_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
