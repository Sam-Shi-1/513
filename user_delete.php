<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No user specified for deletion.";
    header("Location: users.php");
    exit;
}

$user_id = $_GET['id'];

try {
    $wp_host = "sql103.infinityfree.com";
    $wp_db_name = "if0_39913189_wp887";
    $wp_username = "if0_39913189";
    $wp_password = "lyE2sjuBnU";
    
    $wp_db = new PDO("mysql:host=$wp_host;dbname=$wp_db_name", $wp_username, $wp_password);
    $wp_db->exec("set names utf8");
    $wp_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $wp_db = null;
    error_log("WordPress database connection failed: " . $e->getMessage());
}

$success = false;
$error = '';

if ($wp_db) {
    try {
        $delete_user_query = "DELETE FROM wppw_fc_subscribers WHERE id = :user_id";
        $delete_user_stmt = $wp_db->prepare($delete_user_query);
        $delete_user_stmt->bindParam(':user_id', $user_id);
        
        if ($delete_user_stmt->execute()) {
            $success = true;
            $_SESSION['success'] = "User has been successfully deleted.";
        } else {
            $error = "Failed to delete user.";
            $_SESSION['error'] = $error;
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $_SESSION['error'] = $error;
    }
} else {
    $_SESSION['error'] = "WordPress database connection not available.";
}

header("Location: users.php");
exit;
?>