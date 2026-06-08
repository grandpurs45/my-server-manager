INSERT INTO os_lifecycle_references (
    os_family,
    os_version,
    os_codename,
    support_ends_at,
    upgrade_target_version,
    upgrade_target_label,
    source,
    notes
) VALUES
('ubuntu', '12.04', 'precise', '2017-04-30', '14.04', 'Ubuntu 14.04 LTS (Trusty Tahr)', 'Ubuntu Releases', 'Ubuntu 12.04 LTS is out of standard support.'),
('ubuntu', '14.04', 'trusty', '2019-04-30', '16.04', 'Ubuntu 16.04 LTS (Xenial Xerus)', 'Ubuntu Releases', 'Ubuntu 14.04 LTS is out of standard support.'),
('ubuntu', '16.04', 'xenial', '2021-04-30', '18.04', 'Ubuntu 18.04 LTS (Bionic Beaver)', 'Ubuntu Releases', 'Ubuntu 16.04 LTS is out of standard support.'),
('ubuntu', '18.04', 'bionic', '2023-05-31', '20.04', 'Ubuntu 20.04 LTS (Focal Fossa)', 'Ubuntu Releases', 'Ubuntu 18.04 LTS reached end of standard support on 31 May 2023.'),
('ubuntu', '20.04', 'focal', '2025-04-30', '22.04', 'Ubuntu 22.04 LTS (Jammy Jellyfish)', 'Ubuntu Releases', 'Ubuntu 20.04 LTS is out of standard support without Ubuntu Pro/ESM.')
ON DUPLICATE KEY UPDATE
    os_codename = VALUES(os_codename),
    support_ends_at = VALUES(support_ends_at),
    upgrade_target_version = VALUES(upgrade_target_version),
    upgrade_target_label = VALUES(upgrade_target_label),
    source = VALUES(source),
    notes = VALUES(notes);
