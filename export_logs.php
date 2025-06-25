<?php
/**
 * export_logs.php - Export activity logs as CSV
 */

require 'db.php'; // Include your database connection
require_once 'ActivityLogger.php'; // Include the activity logger

// Start session and check login
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Get logged-in user's email and role
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT role FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$userRole = $userData['role'];

// Check if user has admin rights to export logs
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');
if (!$isAdmin) {
    header("Location: index.php");
    exit();
}

// Initialize the activity logger
$logger = new ActivityLogger();

// Get query parameters for filtering
$date = $_GET['date'] ?? date('Y-m-d');
$userFilter = $_GET['user'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$recordIdFilter = $_GET['record_id'] ?? '';
$limit = 10000; // Large limit for export

// Get logs
$logs = $logger->getLogs($date, $limit, $userFilter, $actionFilter, $recordIdFilter);

// Log this export activity
$logger->log('export', "Exported activity logs for date $date", $userEmail);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="activity_logs_' . $date . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['Timestamp', 'User', 'IP Address', 'Action', 'Record ID', 'Details']);

// Write log entries
foreach ($logs as $log) {
    fputcsv($output, [
        $log['timestamp'],
        $log['user'],
        $log['ip'],
        $log['action'],
        $log['record_id'] ?? '',
        $log['details']
    ]);
}

// Close the file pointer
fclose($output);
exit;
?>