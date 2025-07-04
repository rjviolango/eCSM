<?php
// eCSM - ARTA-2242-3
// includes.php

// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecsm_db');
define('DB_USER', 'ecsm_db');
define('DB_PASS', 'pZ9b09~3w');

// --- SITE CONFIGURATION ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

// --- START SESSION ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- DATABASE CONNECTION (PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// --- LOAD SITE SETTINGS FROM DATABASE ---
$CONFIG = [];
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $CONFIG[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // If settings table doesn't exist yet during initial setup, don't crash
    // Fallback to default values
    $CONFIG['agency_name'] = 'CITY GOVERNMENT OF GINGOOG';
    $CONFIG['province_name'] = 'Misamis Oriental';
    $CONFIG['region_name'] = 'Region X';
    $CONFIG['agency_logo'] = 'default_logo.png';
}


// --- HELPER FUNCTIONS ---

/**
 * Checks if a user is logged in.
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user is an admin.
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * A simple helper to prevent XSS
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

?>