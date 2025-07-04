-- 2025-07-04-03_add_latency_column.sql

ALTER TABLE servers
ADD COLUMN latency INT DEFAULT NULL;
