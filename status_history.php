<?php
// status_history.php - Retrieves status change history for a record

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo '<div class="error-message">';
    echo '<p>You must be logged in to view status history.</p>';
    echo '</div>';
    exit();
}

// Check if ID parameter is provided
if (!isset($_GET['id'])) {
    echo '<div class="error-message">';
    echo '<p>No record ID provided.</p>';
    echo '</div>';
    exit();
}

// Sanitize input
$recordId = intval($_GET['id']);

// First check if record exists
$recordCheck = "SELECT id, status FROM paperworkdetails WHERE id = ?";
$stmt = $conn->prepare($recordCheck);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="error-message">';
    echo '<p>Record not found.</p>';
    echo '</div>';
    exit();
}

// Now query the status_change_log table
$query = "SELECT 
            scl.id,
            scl.old_status,
            scl.new_status,
            scl.reason,
            scl.changed_by,
            scl.changed_at,
            u.name as user_name
          FROM 
            status_change_log scl
          LEFT JOIN 
            users u ON scl.changed_by = u.email
          WHERE 
            scl.record_id = ?
          ORDER BY 
            scl.changed_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

// Start building the HTML output
echo '<div class="status-history-container">';
echo '<h4 class="history-heading">Status Change History</h4>';

if ($result->num_rows > 0) {
    echo '<div class="timeline">';
    
    while ($row = $result->fetch_assoc()) {
        $timestamp = new DateTime($row['changed_at']);
        $formattedDate = $timestamp->format('M d, Y h:i A');
        $userName = !empty($row['user_name']) ? $row['user_name'] : $row['changed_by'];
        
        // Get readable status names
        $oldStatus = getStatusText($row['old_status']);
        $newStatus = getStatusText($row['new_status']);
        
        echo '<div class="timeline-item">';
        echo '<div class="timeline-header">';
        echo '<span class="timeline-date">' . htmlspecialchars($formattedDate) . '</span>';
        echo '<span class="timeline-user">' . htmlspecialchars($userName) . '</span>';
        echo '</div>';
        
        echo '<div class="timeline-content">';
        echo '<p>Status changed from <strong>' . htmlspecialchars($oldStatus) . '</strong> to <strong>' . htmlspecialchars($newStatus) . '</strong></p>';
        
        // Show reason if available
        if (!empty($row['reason'])) {
            // Check if it's a start date reason
            if (strpos($row['reason'], 'Start Date:') !== false) {
                $startDate = trim(str_replace('Start Date:', '', $row['reason']));
                echo '<p><strong>Start Date:</strong> ' . htmlspecialchars($startDate) . '</p>';
            } else {
                echo '<p><strong>Reason:</strong> ' . htmlspecialchars($row['reason']) . '</p>';
            }
        }
        
        // Add the status badge
        echo '<span class="timeline-status status-' . htmlspecialchars($row['new_status']) . '">' . htmlspecialchars($newStatus) . '</span>';
        echo '</div>'; // End timeline-content
        echo '</div>'; // End timeline-item
    }
    
    echo '</div>'; // End timeline
} else {
    echo '<div class="no-history">';
    echo '<p>No status change history found for this record.</p>';
    echo '</div>';
}

echo '</div>'; // End status-history-container

// Helper function to get readable status text
function getStatusText($status) {
    switch($status) {
        case 'paperwork_created': return 'Paperwork Created';
        case 'initiated_agreement_bgv': return 'Initiated - Agreement, BGV';
        case 'paperwork_closed': return 'Paperwork Closed';
        case 'started': return 'Started';
        case 'client_hold': return 'Client - Hold';
        case 'client_dropped': return 'Client - Dropped';
        case 'backout': return 'Backout';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>