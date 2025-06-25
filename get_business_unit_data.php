<?php
// get_business_unit_data.php

// Include database connection
require 'db.php';

// Start the session to access session variables
session_start();

// Get the user's email from the session
$userEmail = $_SESSION['email'] ?? '';

// Get the time period from the request
$period = $_GET['period'] ?? 'last30';

// Get the user's role from the database
$roleQuery = "SELECT role FROM users WHERE email = ?";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userRole = $row['role'];
} else {
    // Handle case where user does not exist
    http_response_code(403);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Define time period
$dateFilter = "";
switch($period) {
    case 'last90':
        $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;
    case 'last180':
        $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
        break;
    case 'lastyear':
        $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default: // last30
        $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// Initialize the query base with role filtering
$queryBase = "FROM paperworkdetails WHERE 1=1 $dateFilter";
if (!($userRole === 'Admin' || $userRole === 'Contracts')) {
    $queryBase .= " AND submittedby = '$userEmail'";
}

// Get combined data (closure, PT, PTR) by business unit
$combinedQuery = "SELECT 
                business_unit,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'paperwork_closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN type = 'PT' THEN 1 ELSE 0 END) as pt_count,
                SUM(CASE WHEN type = 'PTR' THEN 1 ELSE 0 END) as ptr_count
                $queryBase
                GROUP BY business_unit";

$combinedResult = $conn->query($combinedQuery);
$combinedData = [];

if ($combinedResult && $combinedResult->num_rows > 0) {
    while ($row = $combinedResult->fetch_assoc()) {
        $combinedData[] = [
            'business_unit' => $row['business_unit'],
            'total' => (int)$row['total'],
            'closed' => (int)$row['closed'],
            'pt_count' => (int)$row['pt_count'],
            'ptr_count' => (int)$row['ptr_count']
        ];
    }
}

// Get paperwork status flow by business unit
$statusQuery = "SELECT 
               business_unit,
               SUM(CASE WHEN status = 'paperwork_created' THEN 1 ELSE 0 END) as paperwork_created,
               SUM(CASE WHEN status = 'initiated_agreement_bgv' THEN 1 ELSE 0 END) as initiated,
               SUM(CASE WHEN status = 'paperwork_closed' THEN 1 ELSE 0 END) as paperwork_closed,
               SUM(CASE WHEN status = 'started' THEN 1 ELSE 0 END) as started,
               SUM(CASE WHEN status = 'client_hold' THEN 1 ELSE 0 END) as client_hold,
               SUM(CASE WHEN status = 'client_dropped' THEN 1 ELSE 0 END) as client_dropped,
               SUM(CASE WHEN status = 'backout' THEN 1 ELSE 0 END) as backout
               $queryBase
               GROUP BY business_unit";

$statusResult = $conn->query($statusQuery);
$statusData = [];

if ($statusResult && $statusResult->num_rows > 0) {
    while ($row = $statusResult->fetch_assoc()) {
        $statusData[] = [
            'business_unit' => $row['business_unit'],
            'paperwork_created' => (int)$row['paperwork_created'],
            'initiated' => (int)$row['initiated'],
            'paperwork_closed' => (int)$row['paperwork_closed'],
            'started' => (int)$row['started'],
            'client_hold' => (int)$row['client_hold'],
            'client_dropped' => (int)$row['client_dropped'],
            'backout' => (int)$row['backout']
        ];
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode(['combined' => $combinedData, 'status' => $statusData]);
?>