<?php

return [
    // Paths to the MySQL client tools used for backup and restore.
    'mysqldump' => env('HRMS_MYSQLDUMP', 'mysqldump'),
    'mysql' => env('HRMS_MYSQL', 'mysql'),

    // Number of database backups to keep.
    'backup_keep' => (int) env('HRMS_BACKUP_KEEP', 30),

    // Upload limits for supporting / contract documents (kilobytes).
    'max_upload_kb' => (int) env('HRMS_MAX_UPLOAD_KB', 10240),
    'upload_mimes' => 'pdf,jpg,jpeg,png,doc,docx',
];
