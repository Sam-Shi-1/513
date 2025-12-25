<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SITE_NAME', 'GameVault');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('BASE_PATH', realpath(dirname(__FILE__) . '/../'));

require_once 'database.php';

if (!date_default_timezone_get()) {
    date_default_timezone_set('Asia/Shanghai');
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    $db = null;
    error_log("Database connection failed: " . $e->getMessage());
}
?>