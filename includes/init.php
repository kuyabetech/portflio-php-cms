<?php
// includes/init.php
// Bootstrap file - Load everything in correct order with error suppression

// Turn off error reporting for duplicate warnings temporarily
error_reporting(E_ALL & ~E_WARNING);

// Load files only once
require_once __DIR__ . '/config.php';

// Only load these if not already loaded
if (!function_exists('sanitize')) {
    require_once __DIR__ . '/functions.php';
}

if (!class_exists('Database')) {
    require_once __DIR__ . '/database.php';
}

if (!class_exists('Auth')) {
    require_once __DIR__ . '/auth.php';
}

require_once "mailer.php";
// Turn error reporting back on
error_reporting(E_ALL);
?>