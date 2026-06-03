<?php
/**
 * Backup storage configuration (copy to backup_config.php on the campus server).
 *
 * Recommended: set backup_root to a folder OUTSIDE the web root (htdocs)
 * so SQL and config snapshots are not web-accessible.
 */
return [
    // Absolute path on the campus server. Empty = project/backups/ (default for XAMPP dev).
    // Windows example: 'D:\\campus_data\\evsu_library_backups'
    // Linux example:   '/var/lib/evsu_library/backups'
    'backup_root' => '',

    // Optional: allow backup downloads only from these IPs (empty = no filter).
    // 'allowed_download_ips' => ['127.0.0.1', '::1', '192.168.10.0/24'],
    'allowed_download_ips' => [],
];
