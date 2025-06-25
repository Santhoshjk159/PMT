<?php
session_start();
include 'db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Check form submission
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['action']) || $_POST['action'] !== 'update_record') {
    header("Location: paperworkallrecords.php?error=1&message=" . urlencode("Invalid request method or missing action parameter."));
    exit;
}

// Check for record ID
if (!isset($_POST['record_id']) || empty($_POST['record_id'])) {
    header("Location: paperworkallrecords.php?error=1&message=" . urlencode("No record ID provided."));
    exit;
}

$record_id = intval($_POST['record_id']);
$user_email = $_SESSION['email'];

// Log submission info
error_log("Processing update for record ID: $record_id by user: $user_email");

// Get user role for permission checking
$role_query = "SELECT role, userwithempid FROM users WHERE email = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("s", $user_email);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$user_data = $role_result->fetch_assoc();

if (!$user_data) {
    header("Location: paperworklogin.php?message=" . urlencode("Session expired. Please login again."));
    exit;
}

$user_role = $user_data['role'];
$user_with_emp_id = $user_data['userwithempid'];

// Retrieve the original record
$original_query = "SELECT * FROM paperworkdetails WHERE id = ?";
$original_stmt = $conn->prepare($original_query);
$original_stmt->bind_param("i", $record_id);
$original_stmt->execute();
$original_result = $original_stmt->get_result();
$original_data = $original_result->fetch_assoc();

if (!$original_data) {
    header("Location: paperworkallrecords.php?error=1&message=" . urlencode("Record not found."));
    exit;
}

// Check permissions
$has_access = false;
if ($user_role === 'Admin' || $user_role === 'Contracts') {
    $has_access = true;
} else if ($original_data['submittedby'] === $user_email) {
    $has_access = true;
} else if ($user_with_emp_id) {
    // Check if user is in any team fields
    $team_fields = [
        'delivery_manager', 'delivery_account_lead', 'team_lead', 'associate_team_lead',
        'business_unit', 'client_account_lead', 'associate_director_delivery',
        'delivery_manager1', 'delivery_account_lead1', 'team_lead1', 'associate_team_lead1',
        'recruiter_name', 'pt_support', 'pt_ownership'
    ];
    
    foreach ($team_fields as $field) {
        if (isset($original_data[$field]) && $original_data[$field] === $user_with_emp_id) {
            $has_access = true;
            break;
        }
    }
}

if (!$has_access) {
    header("Location: paperworkallrecords.php?error=1&message=" . urlencode("You do not have permission to edit this record."));
    exit;
}

// Process payment rate fields
$placement_type = $_POST['placement_type'] ?? 'Hourly';
$unit = '/hour'; // Default

// Determine rate unit based on placement type
if ($placement_type === 'Monthly') $unit = '/month';
else if ($placement_type === 'Daily') $unit = '/day';
else if ($placement_type === 'Weekly') $unit = '/week';
else if ($placement_type === 'Bi-Weekly') $unit = '/bi-week';
else if ($placement_type === 'Semi-Monthly') $unit = '/semi-month';
else if ($placement_type === 'Per Annum') $unit = '/year';

// Create combined rate values if all components exist
if (isset($_POST['clientrate']) && isset($_POST['bill_currency']) && isset($_POST['bill_type'])) {
    $_POST['clientrate'] = $_POST['clientrate'] . ' ' . $_POST['bill_currency'] . ' ' . $unit . ' on ' . $_POST['bill_type'];
}

if (isset($_POST['payrate']) && isset($_POST['pay_currency']) && isset($_POST['pay_type'])) {
    $_POST['payrate'] = $_POST['payrate'] . ' ' . $_POST['pay_currency'] . ' ' . $unit . ' on ' . $_POST['pay_type'];
}

// Field labels for readable history records
$field_labels = [
    'cfirstname' => 'First Name',
    'clastname' => 'Last Name',
    'ceipalid' => 'Ceipal ID',
    'clinkedinurl' => 'LinkedIn URL',
    'cdob' => 'Date of Birth',
    'cmobilenumber' => 'Mobile Number',
    'cemail' => 'Email',
    'clocation' => 'Location',
    'chomeaddress' => 'Home Address',
    'cssn' => 'SSN',
    'cwork_authorization_status' => 'Work Authorization Status',
    'cv_validate_status' => 'V-Validate Status',
    'has_certifications' => 'Has Certifications',
    'ccertifications' => 'Certifications',
    'coverall_experience' => 'Overall Experience',
    'crecent_job_title' => 'Recent Job Title',
    'ccandidate_source' => 'Candidate Source',
    'employer_own_corporation' => 'Own Corporation',
    'employer_corporation_name' => 'Employer Corporation Name',
    'fed_id_number' => 'FED ID Number',
    'contact_person_name' => 'Contact Person Name',
    'contact_person_designation' => 'Contact Person Designation',
    'contact_person_address' => 'Contact Person Address',
    'employer_type' => 'Employer Type',
    'employer_corporation_name1' => 'Vendor Corporation Name',
    'fed_id_number1' => 'Vendor FED ID Number',
    'contact_person_name1' => 'Vendor Contact Person Name',
    'collaboration_collaborate' => 'Collaboration Status',
    'delivery_manager' => 'Delivery Manager',
    'delivery_account_lead' => 'Delivery Account Lead',
    'team_lead' => 'Team Lead',
    'associate_team_lead' => 'Associate Team Lead',
    'business_unit' => 'Business Unit',
    'client_account_lead' => 'Client Account Lead',
    'client_partner' => 'Client Partner',
    'associate_director_delivery' => 'Associate Director Delivery',
    'delivery_manager1' => 'Recruiter Delivery Manager',
    'delivery_account_lead1' => 'Recruiter Delivery Account Lead',
    'team_lead1' => 'Recruiter Team Lead',
    'associate_team_lead1' => 'Recruiter Associate Team Lead',
    'recruiter_name' => 'Recruiter Name',
    'pt_support' => 'PT Support',
    'pt_ownership' => 'PT Ownership',
    'geo' => 'GEO',
    'entity' => 'Entity',
    'client' => 'Client',
    'client_manager' => 'Client Manager',
    'client_manager_email_id' => 'Client Manager Email',
    'end_client' => 'End Client',
    'business_track' => 'Business Track',
    'industry' => 'Industry',
    'experience_in_expertise_role' => 'Experience in Role',
    'job_code' => 'Job Code',
    'job_title' => 'Job Title',
    'primary_skill' => 'Primary Skill',
    'secondary_skill' => 'Secondary Skill',
    'term' => 'Term',
    'duration' => 'Duration',
    'project_location' => 'Project Location',
    'start_date' => 'Start Date',
    'end_date' => 'End Date',
    'type' => 'Type',
    'placement_type' => 'Placement Type',
    'clientrate' => 'Client Rate',
    'payrate' => 'Pay Rate',
    'margin' => 'Margin',
    'benifits' => 'Benefits',
    'vendor_fee' => 'Vendor Fee',
    'margin_deviation_approval' => 'Margin Deviation Approval',
    'margin_deviation_reason' => 'Margin Deviation Reason',
    'ratecard_adherence' => 'Ratecard Adherence',
    'ratecard_deviation_approved' => 'Ratecard Deviation Approved',
    'ratecard_deviation_reason' => 'Ratecard Deviation Reason',
    'payment_term' => 'Payment Term',
    'payment_term_approval' => 'Payment Term Approval',
    'payment_term_deviation_reason' => 'Payment Term Deviation Reason',
    'status' => 'Status',
    'plc_code' => 'PLC Code'
];

// Collect changes for update
$update_fields = [];
$update_values = [];
$update_types = "";
$changed_fields = [];

// Process all form fields to detect changes
foreach ($_POST as $field => $new_value) {
    // Skip non-database fields and action/id parameters
    if (in_array($field, ['action', 'record_id', 'bill_currency', 'bill_type', 'pay_currency', 'pay_type'])) {
        continue;
    }
    
    // Check if field exists in original data and has changed
    if (array_key_exists($field, $original_data) && $original_data[$field] != $new_value) {
        $update_fields[] = "`$field` = ?";
        $update_values[] = $new_value;
        $update_types .= "s"; // Assume string type for all fields
        
        // Store the changed field info for history
        $changed_fields[] = [
            'field' => $field,
            'label' => $field_labels[$field] ?? $field,
            'old_value' => $original_data[$field],
            'new_value' => $new_value
        ];
    }
}

// Handle PLC code update separately
if (isset($_POST['plc_code'])) {
    $original_plc = $original_data['plc_code'] ?? '';
        
    if ($_POST['plc_code'] !== $original_plc) {
        $update_fields[] = "plc_code = ?";
        $update_values[] = $_POST['plc_code'];
        $update_types .= "s";
        
        $update_fields[] = "plc_updated_at = NOW()";
        
        $update_fields[] = "plc_updated_by = ?";
        $update_values[] = $user_email;
        $update_types .= "s";
        
        // Add to changed fields for history
        $changed_fields[] = [
            'field' => 'plc_code',
            'label' => 'PLC Code',
            'old_value' => $original_plc,
            'new_value' => $_POST['plc_code']
        ];
    }
}

// Add timestamp and user information
$update_fields[] = "updated_at = NOW()";
$update_fields[] = "updated_by = ?";
$update_values[] = $user_email;
$update_types .= "s";

// If no changes detected, redirect
if (empty($changed_fields)) {
    header("Location: paperworkallrecords.php?warning=1&message=" . urlencode("No changes were detected."));
    exit;
}

// Log the number of changes
error_log("Detected " . count($changed_fields) . " changes for record #$record_id");

// Function to safely add history record
function addHistoryRecord($conn, $record_id, $user, $field_label, $old, $new) {
    // Prepare values
    $old = ($old === null) ? '[NULL]' : $old;
    $new = ($new === null) ? '[NULL]' : $new;
    
    // Truncate if needed
    if (strlen($old) > 65000) $old = substr($old, 0, 65000) . '... (truncated)';
    if (strlen($new) > 65000) $new = substr($new, 0, 65000) . '... (truncated)';
    
    // Manually construct safe query with escaped strings
    $record_id = intval($record_id); // Ensure integer
    $user = $conn->real_escape_string($user);
    $field_label = $conn->real_escape_string($field_label);
    $old = $conn->real_escape_string($old);
    $new = $conn->real_escape_string($new);
    
    $sql = "INSERT INTO record_history 
            (record_id, modified_by, modified_date, modification_details, old_value, new_value) 
            VALUES ($record_id, '$user', NOW(), '$field_label', '$old', '$new')";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Failed to add history for '$field_label': " . $conn->error);
        return false;
    }
    
    error_log("Added history record for '$field_label' with ID: " . $conn->insert_id);
    return true;
}

// Start transaction for main record update
$conn->begin_transaction();

try {
    // Prepare the update query
    $update_query = "UPDATE paperworkdetails SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $update_values[] = $record_id;
    $update_types .= "i";
    
    // Execute the update
    $update_stmt = $conn->prepare($update_query);
    
    if ($update_stmt === false) {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    $update_stmt->bind_param($update_types, ...$update_values);
    $result = $update_stmt->execute();
    
    if (!$result) {
        throw new Exception("Failed to update record: " . $update_stmt->error);
    }
    
    // Commit the main record update
    $conn->commit();
    
    error_log("Successfully updated record #$record_id");
    
    // Check if any rows were affected
    if ($update_stmt->affected_rows == 0) {
        header("Location: paperworkallrecords.php?warning=1&message=" . urlencode("No changes were made to the record."));
        exit;
    }
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error updating record #$record_id: " . $e->getMessage());
    header("Location: paperworkedit.php?id=$record_id&error=1&message=" . urlencode("Error updating record: " . $e->getMessage()));
    exit;
}

// Now record history entries separately (outside the main transaction)
$history_success = true;
$history_failures = 0;

// Add each history record individually
foreach ($changed_fields as $change) {
    $success = addHistoryRecord(
        $conn, 
        $record_id, 
        $user_email, 
        $change['label'], 
        $change['old_value'], 
        $change['new_value']
    );
    
    if (!$success) {
        $history_failures++;
        $history_success = false;
    }
}

// Final redirect based on history recording success
if ($history_success) {
    header("Location: paperworkallrecords.php?success=1&message=" . urlencode("Record updated successfully with complete history."));
} else if ($history_failures == count($changed_fields)) {
    header("Location: paperworkallrecords.php?partial=1&message=" . urlencode("Record was updated but no history was recorded. Please contact support."));
} else {
    header("Location: paperworkallrecords.php?partial=1&message=" . urlencode("Record updated but some history entries were not recorded ($history_failures of " . count($changed_fields) . ")."));
}
exit;

?>
