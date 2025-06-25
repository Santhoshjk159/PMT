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

// Get user's name for updated_by field
$userEmail = $_SESSION['email'];
$userQuery = "SELECT name FROM users WHERE email = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $userEmail);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userName = ($userResult && $userRow = $userResult->fetch_assoc()) ? $userRow['name'] : $userEmail;

// Check if POST data is provided
if (!isset($_POST['paperwork_id']) || !isset($_POST['plc_code'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields'
    ]);
    exit;
}

$paperworkId = intval($_POST['paperwork_id']);
$plcCode = trim($_POST['plc_code']);

try {
    // Update the plc_code field in the paperworkdetails table
    $updateQuery = "UPDATE paperworkdetails 
                   SET plc_code = ?, 
                       plc_updated_at = NOW(), 
                       plc_updated_by = ? 
                   WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ssi", $plcCode, $userName, $paperworkId);
    $success = $updateStmt->execute();
    
    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'PLC code saved successfully'
        ]);
    } else {
        throw new Exception("Database operation failed");
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error saving PLC code: ' . $e->getMessage()
    ]);
}