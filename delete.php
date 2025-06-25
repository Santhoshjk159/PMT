<?php
session_start();
require 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set content type
header('Content-Type: application/json');

// Debug: Log all received data
error_log("Delete.php called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents('php://input'));

// Check if user is authenticated
if (!isset($_SESSION['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit();
}

// Get user information
$userEmail = $_SESSION['email'];
$userQuery = "SELECT id, role, name FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$userRole = $userData['role'];

// Check if user has admin privileges
if ($userRole !== 'Admin') {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions']);
    exit();
}

// Get the record ID - try both POST form data and raw input
$recordId = null;

// First try regular POST data
if (isset($_POST['id'])) {
    $recordId = intval($_POST['id']);
    error_log("Got ID from POST: " . $recordId);
}

// If not found, try JSON input (like bulk delete)
if (!$recordId) {
    $input = file_get_contents('php://input');
    if ($input) {
        $data = json_decode($input, true);
        if (isset($data['id'])) {
            $recordId = intval($data['id']);
            error_log("Got ID from JSON: " . $recordId);
        }
    }
}

// Validate record ID
if (!$recordId || $recordId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid record ID']);
    exit();
}

// Log the deletion attempt
error_log("Delete attempt - User: {$userEmail}, Record ID: {$recordId}");

// Begin transaction
$conn->begin_transaction();

try {
    // First, check if the record exists and get some details for logging
    $checkQuery = "SELECT id, cfirstname, clastname, cemail, submittedby FROM paperworkdetails WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception("Failed to prepare check query: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $recordId);
    $checkStmt->execute();
    $recordResult = $checkStmt->get_result();
    
    if ($recordResult->num_rows === 0) {
        error_log("Delete.php: Record not found with ID: " . $recordId);
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        exit();
    }
    
    $recordData = $recordResult->fetch_assoc();
    $candidateName = trim($recordData['cfirstname'] . ' ' . $recordData['clastname']);
    $candidateEmail = $recordData['cemail'];
    $submittedBy = $recordData['submittedby'];
    
    error_log("Delete.php: Found record - Candidate: {$candidateName} ({$candidateEmail})");
    
    // Delete related records from plc_codes table (if table exists)
    $plcDeleted = 0;
    try {
        $plcDeleteQuery = "DELETE FROM plc_codes WHERE paperwork_id = ?";
        $plcStmt = $conn->prepare($plcDeleteQuery);
        
        if ($plcStmt) {
            $plcStmt->bind_param("i", $recordId);
            $plcStmt->execute();
            $plcDeleted = $plcStmt->affected_rows;
            $plcStmt->close();
            error_log("Delete.php: PLC records deleted: " . $plcDeleted);
        }
    } catch (Exception $plcError) {
        error_log("Delete.php: PLC table might not exist: " . $plcError->getMessage());
    }
    
    // Delete related records from paperwork_history table (if table exists)
    $historyDeleted = 0;
    try {
        $historyDeleteQuery = "DELETE FROM paperwork_history WHERE paperwork_id = ?";
        $historyStmt = $conn->prepare($historyDeleteQuery);
        
        if ($historyStmt) {
            $historyStmt->bind_param("i", $recordId);
            $historyStmt->execute();
            $historyDeleted = $historyStmt->affected_rows;
            $historyStmt->close();
            error_log("Delete.php: History records deleted: " . $historyDeleted);
        }
    } catch (Exception $historyError) {
        error_log("Delete.php: History table might not exist: " . $historyError->getMessage());
    }
    
    // Delete the main record from paperworkdetails table
    $deleteQuery = "DELETE FROM paperworkdetails WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Failed to prepare main delete query: " . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $recordId);
    $deleteStmt->execute();
    $mainDeleted = $deleteStmt->affected_rows;
    error_log("Delete.php: Main records deleted: " . $mainDeleted);
    
    if ($mainDeleted === 0) {
        throw new Exception("No main record was deleted");
    }
    
    // Commit the transaction
    $conn->commit();
    error_log("Delete.php: Transaction committed successfully");
    
    // Log the successful deletion
    error_log("Successful deletion - Record ID: {$recordId}, Candidate: {$candidateName} ({$candidateEmail}), PLC records deleted: {$plcDeleted}, History records deleted: {$historyDeleted}");
    
    // Insert into system logs if the table exists
    try {
        $logQuery = "INSERT INTO system_logs (action, user_email, details, created_at) VALUES (?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $action = "delete_record";
            $details = "Deleted paperwork record ID: {$recordId} for candidate: {$candidateName} ({$candidateEmail}). Originally submitted by: {$submittedBy}. Related records deleted - PLC: {$plcDeleted}, History: {$historyDeleted}";
            $logStmt->bind_param("sss", $action, $userEmail, $details);
            $logStmt->execute();
            $logStmt->close();
            error_log("Delete.php: System log entry created");
        }
    } catch (Exception $logError) {
        // Log the error but don't fail the main operation
        error_log("Logging error in delete.php: " . $logError->getMessage());
    }
    
    // Close prepared statements
    $checkStmt->close();
    $deleteStmt->close();
    
    // Return success response in JSON format
    error_log("Delete.php: Returning success");
    echo json_encode([
        'status' => 'success', 
        'message' => "Record for {$candidateName} has been successfully deleted."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the detailed error
    error_log("Delete error - Record ID: {$recordId}, Error: " . $e->getMessage());
    error_log("Delete error trace: " . $e->getTraceAsString());
    
    // Return detailed error for debugging
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to delete record: ' . $e->getMessage()
    ]);
    
} finally {
    // Close database connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>