<?php

use App\Http\Middleware\Admin\EnsureAdminIsActive;

return [
    'prefix' => trim((string) config('admin.alias', 'admin'), '/').'/backups',
    'middleware' => ['web', 'auth:admin', EnsureAdminIsActive::class],
    'guard' => 'admin',
    'page' => 'admin/backups/Index',
    'abilities' => [
        'view' => 'backups.view',
        'create' => 'backups.create',
        'download' => 'backups.download',
        'delete' => 'backups.delete',
        'manage' => 'backups.manage',
    ],
    'encryption_key' => env('DRIVESNAP_ENCRYPTION_KEY'),
    'client_id' => env('DRIVESNAP_GOOGLE_CLIENT_ID') ?: env('GMAIL_CLIENT_ID'),
    'client_secret' => env('DRIVESNAP_GOOGLE_CLIENT_SECRET') ?: env('GMAIL_CLIENT_SECRET'),
    'revoke_on_disconnect' => false,
    'queue' => 'backups',
    'binaries' => array_filter(['MySQL' => env('DRIVESNAP_MYSQL_BINARY'), 'MariaDB' => env('DRIVESNAP_MARIADB_BINARY')]),
    'dump_timeout' => (int) env('DRIVESNAP_DUMP_TIMEOUT', 120),
    'job_timeout' => (int) env('DRIVESNAP_JOB_TIMEOUT', 300),
    'backup_binary' => env('DRIVESNAP_DUMP_BINARY', config('database.backup_binary', 'mysqldump')),
    'connection' => null,
    'daily_at' => '02:00',
    'timezone' => 'Asia/Dhaka',
];
