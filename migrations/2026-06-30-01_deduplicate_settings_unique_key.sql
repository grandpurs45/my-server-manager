DELETE s_old
FROM settings s_old
JOIN settings s_new
  ON s_new.category = s_old.category
 AND s_new.setting_key = s_old.setting_key
 AND (
      s_new.updated_at > s_old.updated_at
      OR (s_new.updated_at = s_old.updated_at AND s_new.id > s_old.id)
 )
WHERE s_old.id <> s_new.id;

SET @settings_unique_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'settings'
      AND index_name = 'unique_setting'
      AND non_unique = 0
);

SET @settings_unique_sql := IF(
    @settings_unique_exists = 0,
    'ALTER TABLE settings ADD UNIQUE KEY unique_setting (category, setting_key)',
    'SELECT 1'
);

PREPARE settings_unique_stmt FROM @settings_unique_sql;
EXECUTE settings_unique_stmt;
DEALLOCATE PREPARE settings_unique_stmt;
