<?php
/**
 * Database Credentials — KEEP THIS FILE SECURE
 * 
 * On Hostinger shared hosting, this file should be protected by .htaccess rules.
 * Change the password via Hostinger hPanel before deploying to production.
 * 
 * DEPLOYMENT NOTE:
 * 1. Log into Hostinger hPanel → Databases → MySQL
 * 2. Change the database password to a new strong password
 * 3. Update DB_PASS below with the new password
 * 4. Verify .htaccess denies direct HTTP access to this file
 */

if (!defined('_DB_CREDENTIALS_LOADED')) {
    define('_DB_CREDENTIALS_LOADED', true);
    
    // Auto-detect environment:
    // On Hostinger server → use 'localhost' (faster, avoids DNS/TCP overhead)
    // On local XAMPP dev  → use remote hostname (connects to Hostinger MySQL remotely)
    $is_hostinger = (
        isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'nextacademyindia') !== false
    ) || file_exists('/home/u946810828');
    
    define('DB_HOST', $is_hostinger ? 'localhost' : 'srv842.hstgr.io');
    define('DB_USER', 'u946810828_Next_academy');
    define('DB_PASS', 'NextAcademy2806');
    define('DB_NAME', 'u946810828_Next');
}
?>
