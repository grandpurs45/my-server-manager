ALTER TABLE patch_checks
    ADD COLUMN IF NOT EXISTS collector VARCHAR(50) DEFAULT NULL AFTER server_id;

UPDATE patch_checks pc
JOIN servers s ON s.id = pc.server_id
SET pc.collector = CASE
    WHEN pc.collector IS NOT NULL THEN pc.collector
    WHEN pc.status = 'unsupported' THEN 'unsupported'
    WHEN LOWER(s.os) LIKE '%rocky%' THEN 'dnf'
    WHEN LOWER(s.os) LIKE '%red hat%' THEN 'dnf'
    WHEN LOWER(s.os) LIKE '%centos%' THEN 'dnf'
    WHEN LOWER(s.os) LIKE '%fedora%' THEN 'dnf'
    WHEN LOWER(s.os) LIKE '%ubuntu%' THEN 'apt'
    WHEN LOWER(s.os) LIKE '%debian%' THEN 'apt'
    WHEN LOWER(s.os) LIKE '%proxmox%' THEN 'apt'
    ELSE NULL
END
WHERE pc.collector IS NULL;
