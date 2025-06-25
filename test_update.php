<?php
// Simple test script to directly update a record
require_once 'db.php';

// Use a specific record ID for testing (replace with an actual ID from your database)
$recordId = 524; // Change this to an existing ID in your table
$newStatus = "started";
$reason = "Start Date: 2025-03-28";

// Direct update query
$query = "UPDATE paperworkdetails SET status = ?, reason = ? WHERE id = ?";

$conn->begin_transaction();
try {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $newStatus, $reason, $recordId);
    $result = $stmt->execute();
    
    if (!$result) {
        echo "Update failed: " . $conn->error;
    } else {
        echo "Update successful! Affected rows: " . $stmt->affected_rows;
        
        // Now check if the update was actually saved
        $checkQuery = "SELECT id, status, reason FROM paperworkdetails WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $recordId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($row = $checkResult->fetch_assoc()) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        } else {
            echo "Could not find record after update!";
        }
    }
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>