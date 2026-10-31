<?php

return [
    'freshness_seconds' => 180,
    'storage_disks' => ['local', 'documents'],
    'queue' => env('TECHNIQUE_QUEUE', 'default'),
    'mysqldump_binary' => env('TECHNIQUE_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('TECHNIQUE_MYSQL_BINARY', 'mysql'),
    'sqlite_binary' => env('TECHNIQUE_SQLITE_BINARY', 'sqlite3'),
    'process_timeout' => (int) env('TECHNIQUE_BACKUP_TIMEOUT', 3600),
    'retention_count' => (int) env('TECHNIQUE_BACKUP_RETENTION', 30),
];
