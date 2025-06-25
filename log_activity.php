<?php
/**
 * log_activity.php - Helper function to easily log user activities
 */

require_once 'ActivityLogger.php';

/**
 * Log a user activity
 * 
 * @param string $action The action being performed
 * @param string $details Additional details about the action
 * @param string $recordId ID of the record being modified (optional)
 * @return bool Success or failure
 */
function logActivity($action, $details, $recordId = null) {
    // Get user email from session
    $userEmail = $_SESSION['email'] ?? 'unknown';
    
    // Initialize the logger
    $logger = new ActivityLogger();
    
    // Log the activity
    return $logger->log($action, $details, $userEmail, $recordId);
}
?>