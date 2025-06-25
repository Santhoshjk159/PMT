<?php
// Prevent any output before JSON response
error_reporting(0); // Disable error reporting for production
header('Content-Type: application/json'); // Set correct content type

// Start session and include database connection
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No paperwork ID provided'
    ]);
    exit;
}

$paperworkId = intval($_GET['id']);

try {
    // Get paperwork details including status and plc_code from paperworkdetails table
    $query = "SELECT status, plc_code, plc_updated_at, plc_updated_by 
              FROM paperworkdetails 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $paperworkId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        // Return data with status and PLC info
        echo json_encode([
            'status' => $row['status'],
            'plc_code' => $row['plc_code'],
            'updated_at' => $row['plc_updated_at'],
            'updated_by' => $row['plc_updated_by']
        ]);
    } else {
        // Return error if record not found
        echo json_encode([
            'status' => 'error',
            'message' => 'Record not found'
        ]);
    }
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}