<?php
// fetch_history.php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Get the record ID from the GET parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<p class="error-message">No record ID provided.</p>';
    exit;
}

$recordId = intval($_GET['id']);

// Fetch record history from the record_history table
$historyQuery = "SELECT * FROM record_history 
                WHERE record_id = ? 
                ORDER BY modified_date DESC";

$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

// Check if there are any history records
if ($result->num_rows === 0) {
    echo '<div class="no-history">
            <p>No history records found for this paperwork.</p>
          </div>';
    exit;
}

// Fetch the record details for displaying the title
$recordQuery = "SELECT cfirstname, clastname FROM paperworkdetails WHERE id = ?";
$recordStmt = $conn->prepare($recordQuery);
$recordStmt->bind_param("i", $recordId);
$recordStmt->execute();
$recordResult = $recordStmt->get_result();
$recordData = $recordResult->fetch_assoc();
?>

<div class="history-header">
    <h2 class="history-title">
        <i class="fas fa-history"></i>
        History for <?php echo htmlspecialchars($recordData['cfirstname'] . ' ' . $recordData['clastname']); ?>
    </h2>
    <p class="history-subtitle">Paperwork ID: <?php echo $recordId; ?></p>
</div>

<div class="timeline">
    <?php while ($row = $result->fetch_assoc()): ?>
    <div class="timeline-item">
        <div class="timeline-header">
            <span class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($row['modified_date'])); ?></span>
            <span class="timeline-user"><?php echo htmlspecialchars($row['modified_by']); ?></span>
        </div>
        <div class="timeline-content">
            <p><strong><?php echo htmlspecialchars($row['modification_details']); ?></strong></p>
            
            <?php if (!empty($row['old_value']) || !empty($row['new_value'])): ?>
                <?php if ($row['old_value'] !== null): ?>
                <p>Changed from: <span class="old-value"><?php echo htmlspecialchars($row['old_value']); ?></span></p>
                <?php endif; ?>
                
                <?php if ($row['new_value'] !== null): ?>
                <p>Changed to: <span class="new-value"><?php echo htmlspecialchars($row['new_value']); ?></span></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>