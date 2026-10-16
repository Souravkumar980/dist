<?php
if (basename($_SERVER['PHP_SELF'] ?? '') === 'db_config.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db_config.php') {
    http_response_code(403);
    exit(json_encode(['error' => 'Direct access forbidden']));
}
/**
 * Exposed Propagandus - Database & Environment Configuration
 * 
 * Edit this file if your MySQL credentials or database details change on InfinityFree, cPanel, or localhost.
 */

// MySQL Database Credentials
defined('DB_HOST') || define('DB_HOST', 'sql200.infinityfree.com');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_USER') || define('DB_USER', 'if0_37976074');
defined('DB_PASS') || define('DB_PASS', 'fACI2gSxa2zDmYT');
defined('DB_NAME') || define('DB_NAME', 'if0_37976074_epsite');

// Master Admin Security Key & Fallback Credentials
defined('ADMIN_SECRET_KEY') || define('ADMIN_SECRET_KEY', 'JaiHindBharatMataKiJai2024!');
defined('ADMIN_EMAIL')      || define('ADMIN_EMAIL', 'omkumar.working@gmail.com');
defined('ADMIN_PASSWORD')   || define('ADMIN_PASSWORD', 'Q0gng04bk3');
