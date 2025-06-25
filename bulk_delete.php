<?php
session_start();
require 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit();
}

// Get user role
$userEmail = $_SESSION['email'];
$userQuery = "SELECT role FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();

// Check if user is admin
if (!$userData || $userData['role'] !== 'Admin') {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions']);
    exit();
}

// Get the request body
$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true);

// Debug logging
error_log("Bulk delete - Raw request body: " . $requestBody);
error_log("Bulk delete - Parsed request data: " . json_encode($requestData));

// Check if request data is valid JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit();
}

// Check if IDs are provided
if (!isset($requestData['ids']) || !is_array($requestData['ids']) || empty($requestData['ids'])) {
    echo json_encode(['status' => 'error', 'message' => 'No records selected']);
    exit();
}

// Filter and validate IDs
$ids = array_filter($requestData['ids'], function($id) {
    return is_numeric($id) && $id > 0;
});

// Re-index array to ensure consecutive keys
$ids = array_values($ids);

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid record IDs']);
    exit();
}

// Debug logging
error_log("Bulk delete - Filtered IDs: " . json_encode($ids));

// Begin transaction
$conn->begin_transaction();

try {
    $deletedCount = 0;
    
    // Method 1: Using prepared statements with IN clause (more efficient)
    if (count($ids) <= 100) { // Limit for safety
        // Create placeholders for prepared statement
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $bindTypes = str_repeat('i', count($ids));
        
        // Delete related records from plc_codes table
        $plcDeleteQuery = "DELETE FROM plc_codes WHERE paperwork_id IN ($placeholders)";
        $plcStmt = $conn->prepare($plcDeleteQuery);
        if ($plcStmt) {
            $plcStmt->bind_param($bindTypes, ...$ids);
            $plcStmt->execute();
            $plcDeleted = $plcStmt->affected_rows;
            error_log("Bulk delete - PLC codes deleted: " . $plcDeleted);
            $plcStmt->close();
        }
        
        // Delete related records from paperwork_history table
        $historyDeleteQuery = "DELETE FROM paperwork_history WHERE paperwork_id IN ($placeholders)";
        $historyStmt = $conn->prepare($historyDeleteQuery);
        if ($historyStmt) {
            $historyStmt->bind_param($bindTypes, ...$ids);
            $historyStmt->execute();
            $historyDeleted = $historyStmt->affected_rows;
            error_log("Bulk delete - History records deleted: " . $historyDeleted);
            $historyStmt->close();
        }
        
        // Delete from paperworkdetails table
        $deleteQuery = "DELETE FROM paperworkdetails WHERE id IN ($placeholders)";
        $deleteStmt = $conn->prepare($deleteQuery);
        if ($deleteStmt) {
            $deleteStmt->bind_param($bindTypes, ...$ids);
            $deleteStmt->execute();
            $deletedCount = $deleteStmt->affected_rows;
            error_log("Bulk delete - Main records deleted: " . $deletedCount);
            $deleteStmt->close();
        }
    } else {
        // Method 2: Individual deletion for large datasets or if Method 1 fails
        foreach ($ids as $id) {
            // Delete related records from plc_codes table
            $plcDeleteQuery = "DELETE FROM plc_codes WHERE paperwork_id = ?";
            $plcStmt = $conn->prepare($plcDeleteQuery);
            if ($plcStmt) {
                $plcStmt->bind_param("i", $id);
                $plcStmt->execute();
                $plcStmt->close();
            }
            
            // Delete related records from paperwork_history table
            $historyDeleteQuery = "DELETE FROM paperwork_history WHERE paperwork_id = ?";
            $historyStmt = $conn->prepare($historyDeleteQuery);
            if ($historyStmt) {
                $historyStmt->bind_param("i", $id);
                $historyStmt->execute();
                $historyStmt->close();
            }
            
            // Delete from paperworkdetails table
            $deleteQuery = "DELETE FROM paperworkdetails WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $id);
                $deleteStmt->execute();
                
                if ($deleteStmt->affected_rows > 0) {
                    $deletedCount++;
                }
                $deleteStmt->close();
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the deletion action (after successful commit)
    try {
        $idsStr = implode(', ', $ids);
        $logQuery = "INSERT INTO system_logs (action, user_email, details, created_at) VALUES (?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $action = "bulk_delete";
            $details = "Deleted " . $deletedCount . " records with IDs: " . $idsStr;
            $logStmt->bind_param("sss", $action, $userEmail, $details);
            $logStmt->execute();
            $logStmt->close();
        }
    } catch (Exception $logError) {
        // Log the error but don't fail the main operation
        error_log("Bulk delete - Logging error: " . $logError->getMessage());
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => $deletedCount > 0 ? 'Records deleted successfully' : 'No records were deleted',
        'count' => $deletedCount,
        'requested_count' => count($ids)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Bulk delete error: " . $e->getMessage());
    error_log("Bulk delete error trace: " . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to delete records: ' . $e->getMessage(),
        'debug_info' => [
            'ids_count' => count($ids),
            'ids' => $ids
        ]
    ]);
} finally {
    // Close connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>