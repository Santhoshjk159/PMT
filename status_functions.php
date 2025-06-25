<?php
/**
 * Updates a paperwork record's status and records the change in history
 * 
 * @param mysqli $conn Database connection
 * @param int $paperworkId ID of the paperwork record
 * @param string $newStatus New status code
 * @param string $previousStatus Previous status code
 * @param string $changedBy Email of the user making the change
 * @param string $reason Reason for the status change (optional)
 * @return bool True if successful, false otherwise
 */
function recordStatusChange($conn, $paperworkId, $newStatus, $previousStatus, $changedBy, $reason = '') {
    try {
        // Insert into history table
        $query = "INSERT INTO paperwork_status_history 
                 (paperwork_id, status_code, previous_status, changed_by, reason) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("issss", $paperworkId, $newStatus, $previousStatus, $changedBy, $reason);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to record status history: " . $stmt->error);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording status change: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets the status history for a paperwork record
 * 
 * @param mysqli $conn Database connection
 * @param int $paperworkId ID of the paperwork record
 * @return array Status history records
 */
function getStatusHistory($conn, $paperworkId) {
    try {
        $query = "SELECT h.*, t.status_name, t.color_code 
                 FROM paperwork_status_history h
                 JOIN status_types t ON h.status_code = t.status_code
                 WHERE h.paperwork_id = ?
                 ORDER BY h.changed_date DESC";
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $paperworkId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            // Get previous status name if available
            if (!empty($row['previous_status'])) {
                $prevStatusQuery = "SELECT status_name FROM status_types WHERE status_code = ?";
                $prevStmt = $conn->prepare($prevStatusQuery);
                $prevStmt->bind_param("s", $row['previous_status']);
                $prevStmt->execute();
                $prevResult = $prevStmt->get_result();
                
                if ($prevResult->num_rows > 0) {
                    $prevRow = $prevResult->fetch_assoc();
                    $row['previous_status_name'] = $prevRow['status_name'];
                }
            }
            
            $history[] = $row;
        }
        
        return $history;
    } catch (Exception $e) {
        error_log("Error getting status history: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets all available status types
 * 
 * @param mysqli $conn Database connection
 * @param bool $activeOnly Whether to return only active status types
 * @return array Status types
 */
function getStatusTypes($conn, $activeOnly = true) {
    try {
        $query = "SELECT * FROM status_types";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = TRUE";
        }
        
        $query .= " ORDER BY display_order ASC";
        
        $result = $conn->query($query);
        
        $statusTypes = [];
        while ($row = $result->fetch_assoc()) {
            $statusTypes[] = $row;
        }
        
        return $statusTypes;
    } catch (Exception $e) {
        error_log("Error getting status types: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets the formatted status name and color code based on status code
 * 
 * @param mysqli $conn Database connection
 * @param string $statusCode Status code to look up
 * @return array|null Status details or null if not found
 */
function getStatusDetails($conn, $statusCode) {
    try {
        $query = "SELECT * FROM status_types WHERE status_code = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $statusCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting status details: " . $e->getMessage());
        return null;
    }
}