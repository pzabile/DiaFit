-- ----------------------------------------------------------------
-- DiaFitus — seed a test member account
-- ----------------------------------------------------------------
-- Run this AFTER schema.sql, in Hostinger -> phpMyAdmin -> SQL tab.
-- Creates one member you can log in as for testing:
--
--   Sign-in URL:   https://diafitus.com/login
--   Username:      test
--   Password:      password
--
-- Each member account is completely isolated: the queries on the
-- dashboard always filter by lead_id, and the foreign keys cascade
-- on delete. One member never sees another member's data.
--
-- Delete this seed before going live (or change the password).
-- ----------------------------------------------------------------

INSERT INTO `leads`
  (`email`, `phone`, `first_name`, `paid`, `password_hash`, `started_at`, `answers_json`)
VALUES
  ('test@diafitus.local',
   '+1 555 000 0000',
   'test',
   1,
   '$2y$12$UjENXLTYQFuQhJW7T8SsXe4p33llxfrzEQsFZDvUfrVEzwSzBs5AS',
   CURDATE(),
   '{"diabetes_type":"type_2","gender":"male","age":"38","weight":"82","motivation":"control_glucose","doctor_recommended":"yes","exercise_history":"long_ago","side_effects":["fatigue"],"goals":["lower_a1c","fat_loss"],"location":"home","days_per_week":"4","minutes_per_day":"30","email":"test@diafitus.local"}')
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  paid          = 1,
  first_name    = 'test',
  started_at    = COALESCE(started_at, CURDATE());

-- To remove the test account later:
--   DELETE FROM leads WHERE email = 'test@diafitus.local';
-- (cascades to weekly_notes, daily_logs, meal_photos, coach_notes)
