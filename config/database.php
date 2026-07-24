<?php
/**
 * Database connection settings. Update before first deploy. cPanel usually
 * issues DB names in the form `cpaneluser_dbname`.
 */

return [
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'port'    => (int)(getenv('DB_PORT') ?: 3306),
    'name'    => getenv('DB_NAME') ?: 'afrotech',
    'user'    => getenv('DB_USER') ?: 'root',
    'pass'    => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
