<?php
// eCSM - ARTA-2242-3
// includes.php

// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecsm_db_v2');
define('DB_USER', 'ecsm_db_v2');
define('DB_PASS', 'si9#7u8X2');

// --- SITE CONFIGURATION ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- LOAD SITE SETTINGS FROM DATABASE ---
// Establish a temporary connection to fetch settings first
try {
    $temp_pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $temp_pdo->query("SELECT setting_name, setting_value FROM settings");
    $CONFIG = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $CONFIG[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Fallback if DB connection or settings table fails
    $CONFIG = [
        'agency_name' => 'CITY GOVERNMENT OF GINGOOG',
        'province_name' => 'Misamis Oriental',
        'region_name' => 'Region X',
        'agency_logo' => 'default_logo.png',
        'password_complexity' => 'medium',
        'timezone' => 'Asia/Manila' // Default fallback timezone
    ];
}

// --- reCAPTCHA CONFIGURATION ---
$CONFIG['recaptcha_enabled'] = true;
$CONFIG['recaptcha_site_key'] = '6Lfp14QrAAAAAKr2EXbD4bAVoeqUo7YZUdYFY-k0';
$CONFIG['recaptcha_secret_key'] = '6Lfp14QrAAAAANOObkBczmPS2NXUKbtnudkfvynD';

// *** MODIFIED: Set timezone based on database config with a fallback ***
date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Manila');

// --- START SESSION ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- DATABASE CONNECTION (PDO) ---
// Use the permanent PDO object from now on
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
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
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function log_system_action($pdo, $user_id, $action) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, ip_address, user_agent, action) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $ip_address, $user_agent, $action]);
}

/**
 * Converts a UTC datetime string to the user-configured timezone.
 *
 * @param string|null $datetime_string The UTC datetime string from the database.
 * @param string $format The desired output format for the date.
 * @return string The formatted and converted datetime string, or 'N/A' if input is empty.
 */
function convert_to_user_timezone($datetime_string, $format = 'Y-m-d H:i:s') {
    if (empty($datetime_string)) {
        return 'N/A';
    }

    global $CONFIG;
    // Use the timezone from the configuration, with a fallback to a default value.
    $timezone_identifier = $CONFIG['timezone'] ?? 'Asia/Manila';

    try {
        // Create a DateTime object from the input string, explicitly specifying it's in UTC.
        $date = new DateTime($datetime_string, new DateTimeZone('UTC'));
        // Set the timezone to the one specified by the user's configuration.
        $date->setTimezone(new DateTimeZone($timezone_identifier));
        // Return the date formatted as requested.
        return $date->format($format);
    } catch (Exception $e) {
        // In case of an error (e.g., invalid date format), return the original string as a fallback.
        error_log("Error converting timezone: " . $e->getMessage());
        return $datetime_string;
    }
}
?>