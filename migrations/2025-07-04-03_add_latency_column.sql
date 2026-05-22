-- 2025-07-04-03_add_latency_column.sql

ALTER TABLE servers
ADD COLUMN IF NOT EXISTS latency INT DEFAULT NULL;
