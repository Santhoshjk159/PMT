<?php
// log_status_change.php
session_start();
require_once 'ActivityLogger.php';

// Make sure user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Get the user's email
$userEmail = $_SESSION['email'];

// Get POST parameters
$recordId = isset($_POST['record_id']) ? $_POST['record_id'] : null;
$oldStatus = isset($_POST['old_status']) ? $_POST['old_status'] : null;
$newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : null;

// Validate parameters
if (!$recordId || !$oldStatus || !$newStatus) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Create readable status names
function getStatusText($status) {
    switch($status) {
        case 'paperwork_created': return 'Paperwork Created';
        case 'initiated_agreement_bgv': return 'Initiated – Agreement, BGV';
        case 'paperwork_closed': return 'Paperwork Closed';
        case 'started': return 'Started';
        case 'client_hold': return 'Client – Hold';
        case 'client_dropped': return 'Client – Dropped';
        case 'backout': return 'Backout';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}

// Format the status names for readability
$oldStatusText = getStatusText($oldStatus);
$newStatusText = getStatusText($newStatus);

// Log the activity
$logger = new ActivityLogger();
$logger->log('status', "Changed status from '$oldStatusText' to '$newStatusText'", $userEmail, $recordId);

// Return success
echo json_encode(['status' => 'success']);
?>