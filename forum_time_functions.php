<?php
/**
 * Forum Time Functions
 */

// Set China timezone
date_default_timezone_set('Asia/Shanghai');

/**
 * Format time for display in China timezone
 * @param string $datetime Date time string
 * @param string $format Output format
 * @return string
 */
function formatForumTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Unknown time';
    }
    
    try {
        $timezone = new DateTimeZone('Asia/Shanghai');
        $date = new DateTime($datetime, $timezone);
        return $date->format($format);
    } catch (Exception $e) {
        return 'Invalid time';
    }
}

/**
 * Display relative time
 * @param string $datetime Date time string
 * @return string
 */
function timeAgo($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Unknown';
    }
    
    try {
        $timezone = new DateTimeZone('Asia/Shanghai');
        $time = new DateTime($datetime, $timezone);
        $now = new DateTime('now', $timezone);
        $diff = $now->getTimestamp() - $time->getTimestamp();
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return $time->format('M j, Y');
        }
    } catch (Exception $e) {
        return 'Time error';
    }
}

/**
 * Get current China time
 * @param string $format Output format
 * @return string
 */
function getChinaTime($format = 'Y-m-d H:i:s') {
    $timezone = new DateTimeZone('Asia/Shanghai');
    $now = new DateTime('now', $timezone);
    return $now->format($format);
}
?>