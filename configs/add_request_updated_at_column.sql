SET @db_name := DATABASE();
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'requests'
      AND COLUMN_NAME = 'request_updated_at'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE requests ADD COLUMN request_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER request_submitted_at',
    'SELECT ''Column request_updated_at already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
