<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all POST data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST Data Received: " . print_r($_POST, true));
}

require 'db.php'; // Include database connection
session_start(); // Ensure the session is started

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php"); // Redirect if not logged in
    exit();
}

// Include activity logger
require_once 'log_activity.php';

// Include email notification system
require_once 'EmailSystem/AdminNotificationMailer.php';

// Include email configuration
require_once 'email_config.php';


function prepareStatement($conn, $query, $errorContext = '') {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Prepare statement failed" . ($errorContext ? " for $errorContext" : "") . 
            ": " . $conn->error . " (Query: $query)");
    }
    return $stmt;
}

// Get the user's email from the session
$userEmail = $_SESSION['email'] ?? '';

// Query to fetch the user's role from the database
$roleQuery = "SELECT role, userwithempid, name AS full_name FROM users WHERE email = ?";
$stmt = $conn->prepare($roleQuery);
if ($stmt === false) {
    // Log or display the error to help with debugging
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();

// Check if the user exists and get their role
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userRole = $row['role']; 
    $userWithEmpId = $row['userwithempid'];
    $userName = $row['full_name']; // ADD THIS LINE
} else {
    echo "User not found.";
    exit;
}

// Check if an ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "No record ID provided.";
    exit;
}

$recordId = intval($_GET['id']);



// Log page access
logActivity('view', "Accessed edit form", $recordId);

// Fetch the record details
$query = "SELECT * FROM paperworkdetails WHERE id = ?";
$stmt = prepareStatement($conn, $query, "PLC code query");
if ($stmt === false) {
    die("Prepare statement failed: " . $conn->error . " (Query: $query)");
}
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Record not found.";
    exit;
}

$record = $result->fetch_assoc();

// Initialize default component values
$clientRateComponents = [
    'rate' => '',
    'currency' => 'USD', 
    'unit' => '/hour',
    'tax_term' => ''
];

$payRateComponents = [
    'rate' => '',
    'currency' => 'USD',
    'unit' => '/hour',
    'tax_term' => ''
];

// Determine placement type from the combined rate values
$placementType = 'Hourly'; // Default value

// Parse client_rate_combined if it exists and isn't empty
if (isset($record['clientrate']) && !empty($record['clientrate'])) {
    $clientRateValue = $record['clientrate'];
    
    // Check if it's a complex format with currency, unit and tax term
    if (preg_match('/(\d+(?:\.\d+)?)\s+([A-Z]+)\s+(\/\w+(?:-\w+)?)\s+on\s+(.+)/', $clientRateValue, $matches)) {
        $clientRateComponents['rate'] = $matches[1]; 
        $clientRateComponents['currency'] = $matches[2];
        $clientRateComponents['unit'] = $matches[3];
        $clientRateComponents['tax_term'] = $matches[4];
        
        // Determine placement type based on unit
        if (strpos($matches[3], '/month') !== false) {
            $placementType = 'Monthly';
        } else if (strpos($matches[3], '/day') !== false) {
            $placementType = 'Daily';
        } else if (strpos($matches[3], '/week') !== false && strpos($matches[3], '/bi-week') === false) {
            $placementType = 'Weekly';
        } else if (strpos($matches[3], '/bi-week') !== false) {
            $placementType = 'Bi-Weekly';
        } else if (strpos($matches[3], '/semi-month') !== false) {
            $placementType = 'Semi-Monthly';
        } else if (strpos($matches[3], '/year') !== false) {
            $placementType = 'Per Annum';
        }
    } else {
        // Handle simple numeric value (assumed to be hourly rate)
        $clientRateComponents['rate'] = $clientRateValue;
    }
}

// Parse pay_rate_combined if it exists and isn't empty
if (isset($record['payrate']) && !empty($record['payrate'])) {
    $payRateValue = $record['payrate'];
    
    // Check if it's a complex format
    if (preg_match('/(\d+(?:\.\d+)?)\s+([A-Z]+)\s+(\/\w+(?:-\w+)?)\s+on\s+(.+)/', $payRateValue, $matches)) {
        $payRateComponents['rate'] = $matches[1];
        $payRateComponents['currency'] = $matches[2];
        $payRateComponents['unit'] = $matches[3];
        $payRateComponents['tax_term'] = $matches[4];
    } else {
        // Handle simple numeric value
        $payRateComponents['rate'] = $payRateValue;
    }
}

// Check if user has access to edit this record
// Admin/Contracts can edit any record, others can only edit their own records or records in their team
$hasAccess = false;

if ($userRole === 'Admin' || $userRole === 'Contracts') {
    $hasAccess = true;
} else if ($record['submittedby'] === $userEmail) {
    $hasAccess = true;
} else if ($userWithEmpId) {
    // Check if the user is in any of the team fields
    $teamFields = [
        'delivery_manager', 'delivery_account_lead', 'team_lead', 'associate_team_lead',
        'business_unit', 'client_account_lead', 'associate_director_delivery',
        'delivery_manager1', 'delivery_account_lead1', 'team_lead1', 'associate_team_lead1',
        'recruiter_name', 'pt_support', 'pt_ownership'
    ];
    
    foreach ($teamFields as $field) {
        if (isset($record[$field]) && $record[$field] === $userWithEmpId) {
            $hasAccess = true;
            break;
        }
    }
}

if (!$hasAccess) {
    echo "You do not have permission to edit this record.";
    exit;
}

// Fetch PLC code information if available
// Fetch PLC code information directly from the paperworkdetails record
$plcCode = $record['plc_code'] ?? '';
$plcLastUpdated = $record['plc_updated_at'] ?? '';
$plcUpdatedBy = $record['plc_updated_by'] ?? '';

// Fetch record history
$historyRecords = [];
try {
    $historyQuery = "SELECT * FROM record_history WHERE record_id = ? ORDER BY modified_date DESC";
    $stmt = $conn->prepare($historyQuery);
    
    if ($stmt === false) {
        // Log error but continue without history data
        error_log("Error preparing history query: " . $conn->error);
    } else {
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $historyResult = $stmt->get_result();

        while ($historyRow = $historyResult->fetch_assoc()) {
            $historyRecords[] = $historyRow;
        }
    }
} catch (Exception $e) {
    // Log exception but continue without history data
    error_log("Exception in history query: " . $e->getMessage());
}

// Process form submission
// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Form submitted to: " . $_SERVER['PHP_SELF']);
    error_log("POST data: " . print_r($_POST, true));

    if (isset($_POST['action']) && $_POST['action'] === 'update_record') {

        // Make sure we have the record ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $errorMessage = "No record ID provided for update.";
            error_log($errorMessage);
            return;
        }
        
        $recordId = intval($_POST['id']);

        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Log the form submission for debugging
            error_log("Form submitted: " . print_r($_POST, true));
            
            // Initialize array to collect all changes for email notification
            $emailChanges = [];
            
            // Prepare update query
            $updateFields = [];
            $updateParams = [];
            $updateTypes = "";
            
            // Map of field names to readable descriptions
            $fieldDescriptions = [
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
                'employer_own_corporation' => 'Own Corporation',
                'employer_corporation_name' => 'Employer Corporation Name',
                'fed_id_number' => 'FED ID Number',
                'contact_person_name' => 'Contact Person Name',
                'contact_person_designation' => 'Contact Person Designation',
                'contact_person_address' => 'Contact Person Address',
                'employer_type' => 'Employer Type',
                'employer_corporation_name1' => 'Vendor Employer Corporation Name',
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
                // 'placement_type' => 'Placement Type',
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
                'payment_term_deviation_reason' => 'Payment Term Deviation Reason'
            ];
            
            // Update standard fields
            foreach ($fieldDescriptions as $fieldName => $description) {
                if (isset($_POST[$fieldName]) && $_POST[$fieldName] !== $record[$fieldName]) {
                    // Add to update query
                    $updateFields[] = "$fieldName = ?";
                    $updateParams[] = $_POST[$fieldName];
                    $updateTypes .= "s";
                    
                    // Collect change for email notification
                    $emailChanges[] = [
                        'field_name' => $description,
                        'old_value' => $record[$fieldName],
                        'new_value' => $_POST[$fieldName]
                    ];
                    
                    // Add to history
                    $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                    $historyStmt = $conn->prepare($historyQuery);
                    if ($historyStmt !== false) {
                        $modificationDetails = "$description updated";
                        $oldValue = $record[$fieldName] ?? '';
                        $newValue = $_POST[$fieldName] ?? '';
                        $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                        $historyStmt->execute();
                    }
                }
            }
            
            // Special case fields with different POST and DB names
            
            // Candidate Source
            if (isset($_POST['final_candidate_source']) && $_POST['final_candidate_source'] !== $record['ccandidate_source']) {
                $updateFields[] = "ccandidate_source = ?";
                $updateParams[] = $_POST['final_candidate_source'];
                $updateTypes .= "s";
                
                // Collect change for email notification
                $emailChanges[] = [
                    'field_name' => 'Candidate Source',
                    'old_value' => $record['ccandidate_source'],
                    'new_value' => $_POST['final_candidate_source']
                ];
                
                // Add to history
                $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                if ($historyStmt !== false) {
                    $modificationDetails = "Candidate Source updated";
                    $oldValue = $record['ccandidate_source'] ?? '';
                    $newValue = $_POST['final_candidate_source'] ?? '';
                    $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                    $historyStmt->execute();
                }
            }
            
            // Client Rate
            if (isset($_POST['client_rate_combined']) && $_POST['client_rate_combined'] !== $record['clientrate']) {
                $updateFields[] = "clientrate = ?";
                $updateParams[] = $_POST['client_rate_combined'];
                $updateTypes .= "s";
                
                // Collect change for email notification
                $emailChanges[] = [
                    'field_name' => 'Client Rate',
                    'old_value' => $record['clientrate'],
                    'new_value' => $_POST['client_rate_combined']
                ];
                
                // Add to history
                $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                if ($historyStmt !== false) {
                    $modificationDetails = "Client Rate updated";
                    $oldValue = $record['clientrate'] ?? '';
                    $newValue = $_POST['client_rate_combined'] ?? '';
                    $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                    $historyStmt->execute();
                }
            }
            
            // Pay Rate
            if (isset($_POST['pay_rate_combined']) && $_POST['pay_rate_combined'] !== $record['payrate']) {
                $updateFields[] = "payrate = ?";
                $updateParams[] = $_POST['pay_rate_combined'];
                $updateTypes .= "s";
                
                // Collect change for email notification
                $emailChanges[] = [
                    'field_name' => 'Pay Rate',
                    'old_value' => $record['payrate'],
                    'new_value' => $_POST['pay_rate_combined']
                ];
                
                // Add to history
                $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                if ($historyStmt !== false) {
                    $modificationDetails = "Pay Rate updated";
                    $oldValue = $record['payrate'] ?? '';
                    $newValue = $_POST['pay_rate_combined'] ?? '';
                    $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                    $historyStmt->execute();
                }
            }
            
            // Status change
            if (isset($_POST['status']) && $_POST['status'] !== $record['status']) {
                $updateFields[] = "status = ?";
                $updateParams[] = $_POST['status'];
                $updateTypes .= "s";
                
                // Collect change for email notification
                $emailChanges[] = [
                    'field_name' => 'Status',
                    'old_value' => $record['status'] ?? '',
                    'new_value' => $_POST['status'] ?? ''
                ];
                
                // Add entry to record history
                $modificationDetails = "Status changed to " . $_POST['status'];
                $oldValue = $record['status'] ?? '';
                $newValue = $_POST['status'] ?? '';
                $reason = $_POST['status_reason'] ?? '';

                $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                if ($historyStmt !== false) {
                    $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                    $historyStmt->execute();
                }
            }
            
            // Update PLC code if provided
            if (isset($_POST['plc_code']) && $_POST['plc_code'] !== $plcCode) {
                // Add PLC fields to the update query for paperworkdetails
                $updateFields[] = "plc_code = ?";
                $updateParams[] = $_POST['plc_code'];
                $updateTypes .= "s";
                
                $updateFields[] = "plc_updated_at = NOW()";
                
                $updateFields[] = "plc_updated_by = ?";
                $updateParams[] = $userEmail;
                $updateTypes .= "s";
                
                // Collect change for email notification
                $emailChanges[] = [
                    'field_name' => 'PLC Code',
                    'old_value' => $plcCode ?? '',
                    'new_value' => $_POST['plc_code'] ?? ''
                ];
                
                // Add to history
                $historyQuery = "INSERT INTO record_history (record_id, modified_by, modified_date, modification_details, old_value, new_value) VALUES (?, ?, NOW(), ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                if ($historyStmt !== false) {
                    $modificationDetails = "PLC Code updated";
                    $oldValue = $plcCode ?? '';
                    $newValue = $_POST['plc_code'] ?? '';
                    $historyStmt->bind_param("issss", $recordId, $userEmail, $modificationDetails, $oldValue, $newValue);
                    $historyStmt->execute();
                }
            }
            
            // Only run the update if we have fields to update
            if (count($updateFields) > 0) {
                $updateQuery = "UPDATE paperworkdetails SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $updateTypes .= "i"; // Add the record ID type
                $updateParams[] = $recordId; // Add record ID as the last parameter
                
                error_log("Update query: " . $updateQuery);
                error_log("Update types: " . $updateTypes);
                error_log("Record ID: " . $recordId);
                
                $updateStmt = $conn->prepare($updateQuery);
                if ($updateStmt === false) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }
                
                $bindResult = $updateStmt->bind_param($updateTypes, ...$updateParams);
                if ($bindResult === false) {
                    throw new Exception("Bind parameters failed: " . $updateStmt->error);
                }
                
                $execResult = $updateStmt->execute();
                if ($execResult === false) {
                    throw new Exception("Execute statement failed: " . $updateStmt->error);
                }
                
                error_log("Update successful, affected rows: " . $updateStmt->affected_rows);
            } else {
                error_log("No fields to update");
            }
            
            // Commit transaction
            $commitResult = $conn->commit();
            if ($commitResult === false) {
                throw new Exception("Transaction commit failed");
            }
                        
            error_log("Transaction committed successfully");
            
            // Success message and redirect
            $successMessage = "Record updated successfully!";

            if ($commitResult !== false) {
                $candidateName = $_POST['cfirstname'] . ' ' . $_POST['clastname'];
                
                // Send email notification to admin if there were changes
                if (!empty($emailChanges)) {
                    try {
                        $adminMailer = new AdminNotificationMailer();
                        
                        // Prepare record information for email
                        $recordInfo = [
                            'candidate_name' => $candidateName,
                            'record_id' => $recordId
                        ];
                        
                        // Send the notification
                        $emailSent = $adminMailer->sendChangeNotification($emailChanges, $recordInfo, $userEmail, $userName);
                        
                        if ($emailSent) {
                            error_log("Admin notification email sent successfully for record ID: $recordId");
                        } else {
                            error_log("Failed to send admin notification email for record ID: $recordId");
                        }
                    } catch (Exception $emailException) {
                        error_log("Exception while sending admin notification email: " . $emailException->getMessage());
                        // Don't fail the entire operation if email fails
                    }
                }
                
                // If status changed, log it specifically
                if (isset($_POST['status']) && $_POST['status'] !== $record['status']) {
                    $oldStatus = $record['status'];
                    $newStatus = $_POST['status'];
                    logActivity('status', "Changed status from '$oldStatus' to '$newStatus'", $recordId);
                }
                
                // Log general update
                logActivity('update', "Updated paperwork for $candidateName", $recordId);
            }

            error_log("Update successful, redirecting to index page");
            header("Location: paperworkallrecords.php?success=1&message=" . urlencode($successMessage));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = "Error updating record: " . $e->getMessage();
            error_log($errorMessage);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Paperwork | <?php echo htmlspecialchars($record['cfirstname'] . ' ' . $record['clastname']); ?></title>
    
    <!-- Linking Google fonts for icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Importing Google Fonts - Poppins */
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;500;600;700;800&display=swap');

        :root {
            --primary-color: #151A2D;
            --primary-light: #2c3352;
            --accent-color: #4dabf7;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --bg-color: #F1FAFF;
            --bg-gradient: linear-gradient(#F1FAFF, #CBE4FF);
            --card-bg: white;
            --text-color: #333;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.05);
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --radius: 0px;
            --radius-sm: 0px;
            --radius-xs: 0px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--bg-gradient);
            color: var(--text-color);
        }

        .main {
    padding: 20px;
    margin-left: 280px;
    transition: var(--transition);
}

        /* Page header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .page-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 0px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        /* Tabs Navigation */
        .tabs-container {
            background-color: var(--card-bg);
            border-radius: 0px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .tabs-nav {
            display: flex;
            background-color: var(--primary-color);
            padding: 0 20px;
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
        }

        .tabs-nav::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .tab-item {
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.7);
            border-bottom: 3px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: var(--transition);
            font-weight: 500;
        }

        .tab-item:hover {
            color: white;
        }

        .tab-item.active {
            color: white;
            border-bottom-color: var(--accent-color);
        }

        .tab-content {
            display: none;
            padding: 30px;
        }

        .tab-content.active {
            display: block;
        }

        /* Info Panel */
        .info-panel {
            background-color: var(--primary-color);
            border-radius: 0px;
            padding: 20px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-title {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .info-subtitle {
            opacity: 0.8;
            font-size: 14px;
        }

        .info-status {
            padding: 8px 15px;
            border-radius: 0px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-paperwork_created { background-color: var(--accent-color); }
        .status-initiated_agreement_bgv { background-color: var(--accent-color); }
        .status-paperwork_closed { background-color: var(--success-color); }
        .status-started { background-color: var(--success-color); }
        .status-client_hold { background-color: var(--warning-color); }
        .status-client_dropped { background-color: var(--danger-color); }
        .status-backout { background-color: #6c757d; }

        /* Form Styles */
        .form-container {
            background-color: var(--card-bg);
            border-radius: 0px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 0px;
            transition: var(--transition);
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.2);
            outline: none;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        .required::after {
            content: '*';
            color: var(--danger-color);
            margin-left: 4px;
        }

        /* Status Tab */
        .status-container {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: var(--radius-sm);
            margin-bottom: 30px;
        }

        .status-update {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .status-update select {
            flex: 1;
        }

        .status-reason-container {
            margin-top: 15px;
            display: none;
        }

        .history-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 2px solid white;
        }

        .timeline-date {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .timeline-content {
            background-color: white;
            padding: 15px;
            border-radius: 0px;
            box-shadow: var(--shadow-sm);
        }

        .timeline-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            color: white;
            margin-bottom: 10px;
        }

        .timeline-user {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
        }

        /* PLC Tab */
        .plc-container {
            background-color: white;
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 30px;
        }

        .plc-current {
            margin-bottom: 30px;
        }

        .plc-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .plc-code {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 0px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 10px;
        }

        .plc-meta {
            font-size: 13px;
            color: #777;
        }

        .plc-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .plc-textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 0px;
            font-family: monospace;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            margin-bottom: 20px;
            margin-right: 15px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .info-panel {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Form validation styling */
        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-control.is-invalid + .invalid-feedback {
            display: block;
        }

        /* Status form updates */
        .status-update .form-control {
            flex: 2;
        }

        /* Tooltip styles */
        .tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
            color: #777;
        }

        .tooltip:hover .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            pointer-events: none;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        /* Dependent fields styling */
        .dependent-field {
            display: none;
        }
        
        /* Success and error alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Loading spinners */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Unsaved changes warning */
        .unsaved-warning {
            position: fixed;
            bottom: 20px;
            right: 30px;
            background-color: var(--warning-color);
            color: white;
            padding: 15px 20px;
            border-radius: 0px;
            box-shadow: var(--shadow);
            display: none;
            z-index: 1000;
            animation: slideIn 0.3s forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-right: 1px solid var(--border-color);
    box-shadow: var(--shadow-xl);
    transform: translateX(0);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

.sidebar.collapsed {
    transform: translateX(-100%);
}

.sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    font-weight: 700;
}

.sidebar-logo i {
    font-size: 1.5rem;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    border: 2px solid rgba(255, 255, 255, 0.3);
    text-transform: uppercase;
}

.sidebar-user-info {
    flex: 1;
}

.sidebar-user-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 2px;
}

.sidebar-user-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

.sidebar-nav {
    flex: 1;
    padding: 1.5rem 0;
    overflow-y: auto;
}

.sidebar-section {
    margin-bottom: 2rem;
}

.sidebar-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--primary-color);
    padding: 0 1.5rem;
    margin-bottom: 1rem;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 1.5rem;
    color: var(--text-color);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    margin: 2px 12px;
    border-radius: var(--radius-xs);
}

.sidebar-item:hover {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    transform: translateX(4px);
}

.sidebar-item.active {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: 0 4px 12px rgba(21, 26, 45, 0.3);
}

.sidebar-item.active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    background: var(--accent-color);
    border-radius: 2px;
}

.sidebar-item i {
    width: 20px;
    text-align: center;
}

.sidebar-item.text-danger:hover {
    background: var(--danger-color);
    color: white;
}

.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.sidebar-toggle-btn {
    position: fixed;
    top: 2rem;
    left: 2rem;
    z-index: 1001;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: var(--shadow);
    backdrop-filter: blur(10px);
    transition: var(--transition);
    color: var(--primary-color);
}

.sidebar-toggle-btn:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-lg);
}

/* Main content adjustment */
.main {
    margin-left: 280px;
    transition: var(--transition);
}

.main.expanded {
    margin-left: 0;
}

/* Mobile responsive */
@media (max-width: 1200px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0 !important;
    }
    
    .sidebar-toggle-btn {
        display: flex;
    }
}

/* Sidebar scrollbar styling */
.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--primary-color);
}

    </style>
</head>
<body>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-cogs"></i>
            <span>VDart PMT</span>
        </div>
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php echo substr($userName, 0, 1); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($userRole ?? 'User'); ?></div>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Management</div>
            <a href="index.php" class="sidebar-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="usermanagement.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="paperworkallrecords.php" class="sidebar-item active">
                <i class="fas fa-file-alt"></i>
                <span>Paperwork</span>
            </a>
            <a href="activitylogs.php" class="sidebar-item">
                <i class="fas fa-file-alt"></i>
                <span>Activity Logs</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="profile.php" class="sidebar-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="dropdown_settings.php" class="sidebar-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-item text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

    <div class="main">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Edit Paperwork</h1>
            <div class="page-actions">
                <a href="paperworkallrecords.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <button type="button" id="save-btn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
        
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
        </div>
        <?php endif; ?>
        
        <!-- Info Panel -->
        <div class="info-panel">
            <div>
                <h2 class="info-title"><?php echo htmlspecialchars($record['cfirstname'] . ' ' . $record['clastname']); ?></h2>
                <p class="info-subtitle">Paperwork ID: <?php echo $recordId; ?> | Created: <?php echo date('M d, Y', strtotime($record['created_at'])); ?></p>
            </div>
            <div class="info-status status-<?php echo $record['status']; ?>">
                <?php 
                $statusText = '';
                switch($record['status']) {
                    case 'paperwork_created': $statusText = 'Paperwork Created'; break;
                    case 'initiated_agreement_bgv': $statusText = 'Initiated - Agreement, BGV'; break;
                    case 'paperwork_closed': $statusText = 'Paperwork Closed'; break;
                    case 'started': $statusText = 'Started'; break;
                    case 'client_hold': $statusText = 'Client - Hold'; break;
                    case 'client_dropped': $statusText = 'Client - Dropped'; break;
                    case 'backout': $statusText = 'Backout'; break;
                    default: $statusText = ucfirst(str_replace('_', ' ', $record['status']));
                }
                echo $statusText;
                ?>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <div class="tab-item active" data-tab="consultant">
                    <i class="fas fa-user-tie"></i> Consultant Details
                </div>
                <div class="tab-item" data-tab="employer">
                    <i class="fas fa-building"></i> Employer Details
                </div>
                <div class="tab-item" data-tab="collaboration">
                    <i class="fas fa-handshake"></i> Collaboration
                </div>
                <div class="tab-item" data-tab="recruiter">
                    <i class="fas fa-user-friends"></i> Recruiter
                </div>
                <div class="tab-item" data-tab="project">
                    <i class="fas fa-project-diagram"></i> Project
                </div>
                <div class="tab-item" data-tab="payment">
                    <i class="fas fa-money-bill-wave"></i> Payment
                </div>
                <div class="tab-item" data-tab="status">
                    <i class="fas fa-tasks"></i> Status
                </div>
                <div class="tab-item" data-tab="plc">
                    <i class="fas fa-code"></i> PLC Code
                </div>
            </div>
            
            <form id="edit-form" method="post" action="">
                <input type="hidden" name="id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="action" value="update_record">
                
                
                <!-- Consultant Tab -->
                <div class="tab-content active" id="consultant-tab">
                    <div class="form-section-title">
                        <i class="fas fa-user-tie"></i> Consultant Information
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cfirstname" class="form-label required">First Name</label>
                            <input type="text" id="cfirstname" name="cfirstname" class="form-control" value="<?php echo htmlspecialchars($record['cfirstname']); ?>" required>
                            <div class="invalid-feedback">Please enter the first name</div>
                        </div>
                        <div class="form-group">
                            <label for="clastname" class="form-label required">Last Name</label>
                            <input type="text" id="clastname" name="clastname" class="form-control" value="<?php echo htmlspecialchars($record['clastname']); ?>" required>
                            <div class="invalid-feedback">Please enter the last name</div>
                        </div>
                        <div class="form-group">
                            <label for="ceipal_id" class="form-label required">Ceipal Applicant ID</label>
                            <input type="text" id="ceipal_id" name="ceipalid" class="form-control" value="<?php echo htmlspecialchars($record['ceipalid']); ?>" required>
                            <div class="invalid-feedback">Please enter the Ceipal ID</div>
                        </div>
                        <div class="form-group">
                            <label for="clinkedin_url" class="form-label required">LinkedIn URL</label>
                            <input type="url" id="clinkedin_url" name="clinkedinurl" class="form-control" value="<?php echo htmlspecialchars($record['clinkedinurl']); ?>" required>
                            <div class="invalid-feedback">Please enter a valid LinkedIn URL</div>
                        </div>
                        <div class="form-group">
                            <label for="cdob" class="form-label required">Date of Birth</label>
                            <input type="date" id="cdob" name="cdob" class="form-control" value="<?php echo htmlspecialchars($record['cdob']); ?>" required>
                            <div class="invalid-feedback">Please select the date of birth</div>
                        </div>
                        <div class="form-group">
                            <label for="cmobilenumber" class="form-label required">Mobile Number</label>
                            <input type="tel" id="cmobilenumber" name="cmobilenumber" class="form-control" value="<?php echo htmlspecialchars($record['cmobilenumber']); ?>" required>
                            <div class="invalid-feedback">Please enter a valid mobile number</div>
                        </div>
                        <div class="form-group">
                            <label for="cemail" class="form-label required">Email</label>
                            <input type="email" id="cemail" name="cemail" class="form-control" value="<?php echo htmlspecialchars($record['cemail']); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                        <div class="form-group">
                            <label for="clocation" class="form-label required">Location (city, state)</label>
                            <input type="text" id="clocation" name="clocation" class="form-control" value="<?php echo htmlspecialchars($record['clocation']); ?>" required>
                            <div class="invalid-feedback">Please enter the location</div>
                        </div>
                        <div class="form-group">
                            <label for="chomeaddress" class="form-label required">Home Address</label>
                            <input type="text" id="chomeaddress" name="chomeaddress" class="form-control" value="<?php echo htmlspecialchars($record['chomeaddress']); ?>" required>
                            <div class="invalid-feedback">Please enter the home address</div>
                        </div>
                        <div class="form-group">
                            <label for="cssn" class="form-label required">SSN (Last 4 digits)</label>
                            <input type="text" id="cssn" name="cssn" class="form-control" value="<?php echo htmlspecialchars($record['cssn']); ?>" required pattern="\d{4}" maxlength="4">
                            <div class="invalid-feedback">Please enter the last 4 digits of SSN</div>
                        </div>
                        <div class="form-group">
                            <label for="cwork_authorization_status" class="form-label required">Work Authorization Status</label>
                            <select id="cwork_authorization_status" name="cwork_authorization_status" class="form-control form-select" required>
                                <option value="" disabled>Select Work Status</option>
                                <option value="us-citizen" <?php if($record['cwork_authorization_status'] == 'us-citizen') echo 'selected'; ?>>US Citizen</option>
                                <option value="green-card" <?php if($record['cwork_authorization_status'] == 'green-card') echo 'selected'; ?>>Green Card</option>
                                <option value="TN" <?php if($record['cwork_authorization_status'] == 'TN') echo 'selected'; ?>>TN</option>
                                <option value="h1b" <?php if($record['cwork_authorization_status'] == 'h1b') echo 'selected'; ?>>H1B</option>
                                <option value="mexican" <?php if($record['cwork_authorization_status'] == 'mexican') echo 'selected'; ?>>Mexican Citizen</option>
                                <option value="canadian" <?php if($record['cwork_authorization_status'] == 'canadian') echo 'selected'; ?>>Canadian Citizen</option>
                                <option value="canadian-permit" <?php if($record['cwork_authorization_status'] == 'canadian-permit') echo 'selected'; ?>>Canadian Work_Permit</option>
                                <option value="aus-citizen" <?php if($record['cwork_authorization_status'] == 'aus-citizen') echo 'selected'; ?>>Australian Citizen</option>
                                <option value="cr-citizen" <?php if($record['cwork_authorization_status'] == 'cr-citizen') echo 'selected'; ?>>CR Citizen</option>
                                <option value="gc-ead" <?php if($record['cwork_authorization_status'] == 'gc-ead') echo 'selected'; ?>>GC EAD</option>
                                <option value="opt" <?php if($record['cwork_authorization_status'] == 'opt') echo 'selected'; ?>>OPT EAD</option>
                                <option value="h4-ead" <?php if($record['cwork_authorization_status'] == 'h4-ead') echo 'selected'; ?>>H4 EAD</option>
                                <option value="cpt" <?php if($record['cwork_authorization_status'] == 'cpt') echo 'selected'; ?>>CPT</option>
                                <option value="Skilled worker - Dependant Partner" <?php if($record['cwork_authorization_status'] == 'Skilled worker - Dependant Partner') echo 'selected'; ?>>Skilled worker - Dependant Partner</option>
                                <option value="Permanent Resident" <?php if($record['cwork_authorization_status'] == 'Permanent Resident') echo 'selected'; ?>>Permanent Resident</option>
                                <option value="others" <?php if($record['cwork_authorization_status'] == 'others') echo 'selected'; ?>>Others</option>
                            </select>
                            <div class="invalid-feedback">Please select a work authorization status</div>
                        </div>
                        <div class="form-group" id="v_validate_status_field" style="<?php echo ($record['cwork_authorization_status'] == 'h1b') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="cv_validate_status" class="form-label required">V-Validate Status</label>
                            <select id="cv_validate_status" name="cv_validate_status" class="form-control form-select" <?php echo ($record['cwork_authorization_status'] == 'h1b') ? 'required' : ''; ?>>
                                <option value="" disabled>Select option</option>
                                <option value="Genuine" <?php if($record['cv_validate_status'] == 'Genuine') echo 'selected'; ?>>Genuine</option>
                                <option value="Questionable" <?php if($record['cv_validate_status'] == 'Questionable') echo 'selected'; ?>>Questionable</option>
                                <option value="Clear" <?php if($record['cv_validate_status'] == 'Clear') echo 'selected'; ?>>Clear</option>
                                <option value="Invalid Copy" <?php if($record['cv_validate_status'] == 'Invalid Copy') echo 'selected'; ?>>Invalid Copy</option>
                                <option value="Pending" <?php if($record['cv_validate_status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Not Sent - Stamp Copy" <?php if($record['cv_validate_status'] == 'Not Sent - Stamp Copy') echo 'selected'; ?>>Not Sent - Stamp Copy</option>
                                <option value="NA" <?php if($record['cv_validate_status'] == 'NA') echo 'selected'; ?>>NA</option>
                            </select>
                            <div class="invalid-feedback">Please select a V-Validate status</div>
                        </div>
                        <div class="form-group">
                            <label for="has_certifications" class="form-label required">Has Certifications</label>
                            <select id="has_certifications" name="has_certifications" class="form-control form-select" required>
                                <option value="" disabled>Select option</option>
                                <option value="yes" <?php if($record['has_certifications'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if($record['has_certifications'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                            <div class="invalid-feedback">Please select an option</div>
                        </div>
                        <div class="form-group" id="certifications_field" style="<?php echo ($record['has_certifications'] == 'yes') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="ccertifications" class="form-label required">Certification Names</label>
                            <input type="text" id="ccertifications" name="ccertifications" class="form-control" value="<?php echo htmlspecialchars($record['ccertifications']); ?>" <?php echo ($record['has_certifications'] == 'yes') ? 'required' : ''; ?> placeholder="Enter certification names separated by commas">
                            <div class="invalid-feedback">Please enter certification names</div>
                        </div>
                        <div class="form-group">
                            <label for="coverall_experience" class="form-label required">Overall Experience</label>
                            <input type="text" id="coverall_experience" name="coverall_experience" class="form-control" value="<?php echo htmlspecialchars($record['coverall_experience']); ?>" required>
                            <div class="invalid-feedback">Please enter overall experience</div>
                        </div>
                        <div class="form-group">
                            <label for="crecent_job_title" class="form-label required">Recent Job Title</label>
                            <input type="text" id="crecent_job_title" name="crecent_job_title" class="form-control" value="<?php echo htmlspecialchars($record['crecent_job_title']); ?>" required>
                            <div class="invalid-feedback">Please enter the recent job title</div>
                        </div>
                        <div class="form-group">
                            <label for="candidate_source" class="form-label required">Candidate Source</label>
                            <select id="candidate_source" name="candidate_source" class="form-control form-select" required>
                                <option value="" disabled>Select option</option>
                                <option value="PT" <?php if(strpos($record['ccandidate_source'], 'PT') === 0) echo 'selected'; ?>>PT</option>
                                <option value="PTR" <?php if(strpos($record['ccandidate_source'], 'PTR') === 0) echo 'selected'; ?>>PTR</option>
                                <option value="Dice Response" <?php if(strpos($record['ccandidate_source'], 'Dice Response') === 0) echo 'selected'; ?>>Dice Response</option>
                                <option value="CB" <?php if(strpos($record['ccandidate_source'], 'CB') === 0) echo 'selected'; ?>>CB</option>
                                <option value="Monster" <?php if(strpos($record['ccandidate_source'], 'Monster') === 0) echo 'selected'; ?>>Monster</option>
                                <option value="Dice" <?php if(strpos($record['ccandidate_source'], 'Dice') === 0 && strpos($record['ccandidate_source'], 'Dice Response') !== 0) echo 'selected'; ?>>Dice</option>
                                <option value="IDB-Dice" <?php if(strpos($record['ccandidate_source'], 'IDB-Dice') === 0) echo 'selected'; ?>>IDB-Dice</option>
                                <option value="IDB-CB" <?php if(strpos($record['ccandidate_source'], 'IDB-CB') === 0) echo 'selected'; ?>>IDB-CB</option>
                                <option value="IDB-Monster" <?php if(strpos($record['ccandidate_source'], 'IDB-Monster') === 0) echo 'selected'; ?>>IDB-Monster</option>
                                <option value="IDB-Rehire" <?php if(strpos($record['ccandidate_source'], 'IDB-Rehire') === 0) echo 'selected'; ?>>IDB-Rehire</option>
                                <option value="IDB-LinkedIn" <?php if(strpos($record['ccandidate_source'], 'IDB-LinkedIn') === 0) echo 'selected'; ?>>IDB-LinkedIn</option>
                                <option value="LinkedIn Personal" <?php if(strpos($record['ccandidate_source'], 'LinkedIn Personal') === 0) echo 'selected'; ?>>LinkedIn Personal</option>
                                <option value="LinkedIn RPS" <?php if(strpos($record['ccandidate_source'], 'LinkedIn RPS') === 0) echo 'selected'; ?>>LinkedIn RPS</option>
                                <option value="LinkedIn RPS - Job Response" <?php if(strpos($record['ccandidate_source'], 'LinkedIn RPS - Job Response') === 0) echo 'selected'; ?>>LinkedIn RPS - Job Response</option>
                                <option value="CX Bench" <?php if(strpos($record['ccandidate_source'], 'CX Bench') === 0) echo 'selected'; ?>>CX Bench</option>
                                <option value="Referral Client" <?php if(strpos($record['ccandidate_source'], 'Referral Client') === 0) echo 'selected'; ?>>Referral Client</option>
                                <option value="Referral Candidate" <?php if(strpos($record['ccandidate_source'], 'Referral Candidate') === 0) echo 'selected'; ?>>Referral Candidate</option>
                                <option value="Vendor Consolidation" <?php if(strpos($record['ccandidate_source'], 'Vendor Consolidation') === 0) echo 'selected'; ?>>Vendor Consolidation</option>
                                <option value="Referral Vendor" <?php if(strpos($record['ccandidate_source'], 'Referral Vendor') === 0) echo 'selected'; ?>>Referral Vendor</option>
                                <option value="Career Portal" <?php if(strpos($record['ccandidate_source'], 'Career Portal') === 0) echo 'selected'; ?>>Career Portal</option>
                                <option value="Indeed" <?php if(strpos($record['ccandidate_source'], 'Indeed') === 0) echo 'selected'; ?>>Indeed</option>
                                <option value="Signal Hire" <?php if(strpos($record['ccandidate_source'], 'Signal Hire') === 0) echo 'selected'; ?>>Signal Hire</option>
                                <option value="Sourcing" <?php if(strpos($record['ccandidate_source'], 'Sourcing') === 0) echo 'selected'; ?>>Sourcing</option>
                                <option value="Rehiring" <?php if(strpos($record['ccandidate_source'], 'Rehiring') === 0) echo 'selected'; ?>>Rehiring</option>
                                <option value="Prohires" <?php if(strpos($record['ccandidate_source'], 'Prohires') === 0) echo 'selected'; ?>>Prohires</option>
                                <option value="Zip Recruiter" <?php if(strpos($record['ccandidate_source'], 'Zip Recruiter') === 0) echo 'selected'; ?>>Zip Recruiter</option>
                                <option value="Mass Mail" <?php if(strpos($record['ccandidate_source'], 'Mass Mail') === 0) echo 'selected'; ?>>Mass Mail</option>
                                <option value="LinkedIn Sourcer" <?php if(strpos($record['ccandidate_source'], 'LinkedIn Sourcer') === 0) echo 'selected'; ?>>LinkedIn Sourcer</option>
                                <option value="Social Media" <?php if(strpos($record['ccandidate_source'], 'Social Media') === 0) echo 'selected'; ?>>Social Media</option>
                                <option value="SRM" <?php if(strpos($record['ccandidate_source'], 'SRM') === 0) echo 'selected'; ?>>SRM</option>
                            </select>
                            <div class="invalid-feedback">Please select a candidate source</div>
                        </div>
                        
                        <?php 
                        // Extract additional option for the dependent fields
                        $sourceOptionParts = explode(' - ', $record['ccandidate_source'], 2);
                        $sourcedPerson = isset($sourceOptionParts[1]) ? $sourceOptionParts[1] : '';
                        ?>
                        
                        <div class="form-group" id="shared_field" style="<?php echo (in_array(explode(' - ', $record['ccandidate_source'])[0], ['CX Bench', 'LinkedIn RPS', 'SRM', 'LinkedIn Sourcer'])) ? 'display: block;' : 'display: none;'; ?>">
                            <label id="shared_label" class="form-label required"><?php echo explode(' - ', $record['ccandidate_source'])[0]; ?> Options</label>
                            <select class="form-control form-select" id="shared_option" name="shared_option">
                                <option value="" disabled>Select Option</option>
                                <?php if(strpos($record['ccandidate_source'], 'CX Bench') === 0): ?>
                                <option value="Sushmitha S" <?php if($sourcedPerson == 'Sushmitha S') echo 'selected'; ?>>Sushmitha S</option>
                                <option value="Swarnabharathi M U" <?php if($sourcedPerson == 'Swarnabharathi M U') echo 'selected'; ?>>Swarnabharathi M U</option>
                                <?php elseif(strpos($record['ccandidate_source'], 'LinkedIn RPS') === 0): ?>
                                <option value="Balaji Kumar" <?php if($sourcedPerson == 'Balaji Kumar') echo 'selected'; ?>>Balaji Kumar</option>
                                <option value="Balaji Mohan" <?php if($sourcedPerson == 'Balaji Mohan') echo 'selected'; ?>>Balaji Mohan</option>
                                <?php elseif(strpos($record['ccandidate_source'], 'SRM') === 0): ?>
                                <option value="Harish Babu M" <?php if($sourcedPerson == 'Harish Babu M') echo 'selected'; ?>>Harish Babu M</option>
                                <?php elseif(strpos($record['ccandidate_source'], 'LinkedIn Sourcer') === 0): ?>
                                <option value="Karthik T" <?php if($sourcedPerson == 'Karthik T') echo 'selected'; ?>>Karthik T</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Please select an option</div>
                        </div>
                        
                        <div class="form-group" id="sourced_person_field" style="<?php echo (strpos($record['ccandidate_source'], 'Sourcing') === 0) ? 'display: block;' : 'display: none;'; ?>">
                            <label class="form-label required">Sourced By</label>
                            <select class="form-control form-select" id="sourced_person" name="sourced_person">
                                <option value="" disabled>Select Person</option>
                                <option value="David Johnson" <?php if($sourcedPerson == 'David Johnson') echo 'selected'; ?>>David Johnson</option>
                                <option value="Sarah Williams" <?php if($sourcedPerson == 'Sarah Williams') echo 'selected'; ?>>Sarah Williams</option>
                                <option value="Michael Smith" <?php if($sourcedPerson == 'Michael Smith') echo 'selected'; ?>>Michael Smith</option>
                                <option value="Jessica Brown" <?php if($sourcedPerson == 'Jessica Brown') echo 'selected'; ?>>Jessica Brown</option>
                                <option value="Robert Davis" <?php if($sourcedPerson == 'Robert Davis') echo 'selected'; ?>>Robert Davis</option>
                            </select>
                            <div class="invalid-feedback">Please select a sourcing person</div>
                        </div>
                        
                        <input type="hidden" id="final_candidate_source_hidden" name="final_candidate_source" value="<?php echo htmlspecialchars($record['ccandidate_source']); ?>">
                    </div>
                </div>
                
                <!-- Employer Tab -->
                <div class="tab-content" id="employer-tab">
                    <div class="form-section-title">
                        <i class="fas fa-building"></i> Employer Information
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cemployer_own_corporation" class="form-label required">Own Corporation</label>
                            <select id="cemployer_own_corporation" name="cemployer_own_corporation" class="form-control form-select" required>
                                <option value="" disabled>Select option</option>
                                <option value="Yes" <?php if($record['employer_own_corporation'] == 'Yes') echo 'selected'; ?>>Yes</option>
                                <option value="No" <?php if($record['employer_own_corporation'] == 'No') echo 'selected'; ?>>No</option>
                                <option value="NA" <?php if($record['employer_own_corporation'] == 'NA') echo 'selected'; ?>>NA</option>
                            </select>
                            <div class="invalid-feedback">Please select an option</div>
                        </div>
                        
                        <div class="form-group employer-field" style="<?php echo ($record['employer_own_corporation'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="employer_corporation_name" class="form-label required">Employer Corporation Name</label>
                            <input type="text" id="employer_corporation_name" name="employer_corporation_name" class="form-control" value="<?php echo htmlspecialchars($record['employer_corporation_name']); ?>" <?php echo ($record['employer_corporation_name'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the employer corporation name</div>
                        </div>
                        
                        <div class="form-group employer-field" style="<?php echo ($record['employer_own_corporation'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="fed_id_number" class="form-label required">FED ID Number</label>
                            <input type="text" id="fed_id_number" name="fed_id_number" class="form-control" value="<?php echo htmlspecialchars($record['fed_id_number']); ?>" <?php echo ($record['fed_id_number'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the FED ID number</div>
                        </div>
                        
                        <div class="form-group employer-field" style="<?php echo ($record['employer_own_corporation'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="contact_person_name" class="form-label required">Contact Person Name (Signing authority)</label>
                            <input type="text" id="contact_person_name" name="contact_person_name" class="form-control" value="<?php echo htmlspecialchars($record['contact_person_name']); ?>" <?php echo ($record['contact_person_name'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the contact person name</div>
                        </div>
                        
                        <div class="form-group employer-field" style="<?php echo ($record['employer_own_corporation'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="contact_person_designation" class="form-label required">Contact Person Designation</label>
                            <input type="text" id="contact_person_designation" name="contact_person_designation" class="form-control" value="<?php echo htmlspecialchars($record['contact_person_designation']); ?>" <?php echo ($record['contact_person_designation'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the contact person designation</div>
                        </div>
                        
                        <div class="form-group employer-field" style="<?php echo ($record['employer_own_corporation'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="contact_person_address" class="form-label required">Contact Person Address</label>
                            <input type="text" id="contact_person_address" name="contact_person_address" class="form-control" value="<?php echo htmlspecialchars($record['contact_person_address']); ?>" <?php echo ($record['contact_person_address'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the contact person address</div>
                        </div>
                    </div>
                    
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Additional Employer Details
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="employer_type" class="form-label required">Employer Type</label>
                            <select id="employer_type" name="employer_type" class="form-control form-select" required>
                                <option value="" disabled>Select option</option>
                                <option value="Vendor Change" <?php if($record['employer_type'] == 'Vendor Change') echo 'selected'; ?>>Vendor Change</option>
                                <option value="Vendor Reference" <?php if($record['employer_type'] == 'Vendor Reference') echo 'selected'; ?>>Vendor Reference</option>
                                <option value="NA" <?php if($record['employer_type'] == 'NA') echo 'selected'; ?>>NA</option>
                            </select>
                            <div class="invalid-feedback">Please select an employer type</div>
                        </div>
                        
                        <div class="form-group" id="employer_corporation_name_field" style="<?php echo ($record['employer_type'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="employer_corporation_name1" class="form-label required">Employer Corporation Name</label>
                            <input type="text" id="employer_corporation_name1" name="employer_corporation_name1" class="form-control" value="<?php echo htmlspecialchars($record['employer_corporation_name1']); ?>" <?php echo ($record['employer_type'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the employer corporation name</div>
                        </div>
                        
                        <div class="form-group" id="fed_id_number_field" style="<?php echo ($record['employer_type'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="fed_id_number1" class="form-label required">FED ID Number</label>
                            <input type="text" id="fed_id_number1" name="fed_id_number1" class="form-control" value="<?php echo htmlspecialchars($record['fed_id_number1']); ?>" <?php echo ($record['employer_type'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the FED ID number</div>
                        </div>
                        
                        <div class="form-group" id="contact_person_name_field" style="<?php echo ($record['employer_type'] != 'NA') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="contact_person_name1" class="form-label required">Contact Person Name (Signing authority)</label>
                            <input type="text" id="contact_person_name1" name="contact_person_name1" class="form-control" value="<?php echo htmlspecialchars($record['contact_person_name1']); ?>" <?php echo ($record['employer_type'] != 'NA') ? 'required' : ''; ?>>
                            <div class="invalid-feedback">Please enter the contact person name</div>
                        </div>
                    </div>
                </div>
                
                <!-- Collaboration Tab -->
                <div class="tab-content" id="collaboration-tab">
                    <div class="form-section-title">
                        <i class="fas fa-handshake"></i> Collaboration Details
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="collaboration_collaborate" class="form-label required">Collaborate</label>
                            <select id="collaboration_collaborate" name="collaboration_collaborate" class="form-control form-select" required>
                                <option value="" disabled>Select option</option>
                                <option value="yes" <?php if($record['collaboration_collaborate'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if($record['collaboration_collaborate'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                            <div class="invalid-feedback">Please select an option</div>
                        </div>
                        
                        <div class="form-group" id="delivery_manager_field" style="<?php echo ($record['collaboration_collaborate'] == 'yes') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="delivery_manager" class="form-label required">Delivery Manager</label>
                            <select id="delivery_manager" name="delivery_manager" class="form-control form-select" <?php echo ($record['collaboration_collaborate'] == 'yes') ? 'required' : ''; ?>>
                                <option value="" disabled>Select option</option>
                                <option value="Arun Franklin Joseph - 10344" <?php if($record['delivery_manager'] == 'Arun Franklin Joseph - 10344') echo 'selected'; ?>>Arun Franklin Joseph - 10344</option>
                                <option value="DinoZeoff M - 10097" <?php if($record['delivery_manager'] == 'DinoZeoff M - 10097') echo 'selected'; ?>>DinoZeoff M - 10097</option>
                                <option value="Faisal Ahamed - 12721" <?php if($record['delivery_manager'] == 'Faisal Ahamed - 12721') echo 'selected'; ?>>Faisal Ahamed - 12721</option>
                                <option value="Jack Sherman - 10137" <?php if($record['delivery_manager'] == 'Jack Sherman - 10137') echo 'selected'; ?>>Jack Sherman - 10137</option>
                                <option value="Johnathan Liazar - 10066" <?php if($record['delivery_manager'] == 'Johnathan Liazar - 10066') echo 'selected'; ?>>Johnathan Liazar - 10066</option>
                                <option value="Lance Taylor - 10082" <?php if($record['delivery_manager'] == 'Lance Taylor - 10082') echo 'selected'; ?>>Lance Taylor - 10082</option>
                                <option value="Michael Devaraj A - 10123" <?php if($record['delivery_manager'] == 'Michael Devaraj A - 10123') echo 'selected'; ?>>Michael Devaraj A - 10123</option>
                                <option value="Omar Mohamed - 10944" <?php if($record['delivery_manager'] == 'Omar Mohamed - 10944') echo 'selected'; ?>>Omar Mohamed - 10944</option>
                                <option value="Richa Verma - 10606" <?php if($record['delivery_manager'] == 'Richa Verma - 10606') echo 'selected'; ?>>Richa Verma - 10606</option>
                                <option value="Seliyan M - 10028" <?php if($record['delivery_manager'] == 'Seliyan M - 10028') echo 'selected'; ?>>Seliyan M - 10028</option>
                                <option value="Srivijayaraghavan M - 10270" <?php if($record['delivery_manager'] == 'Srivijayaraghavan M - 10270') echo 'selected'; ?>>Srivijayaraghavan M - 10270</option>
                                <option value="Vandhana R R - 10021" <?php if($record['delivery_manager'] == 'Vandhana R R - 10021') echo 'selected'; ?>>Vandhana R R - 10021</option>
                                <option value="NA" <?php if($record['delivery_manager'] == 'NA') echo 'selected'; ?>>NA</option>
                            </select>
                            <div class="invalid-feedback">Please select a delivery manager</div>
                        </div>
                        
                        <div class="form-group" id="delivery_account_lead_field" style="<?php echo ($record['collaboration_collaborate'] == 'yes') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="delivery_account_lead" class="form-label required">Delivery Account Lead</label>
                            <select id="delivery_account_lead" name="delivery_account_lead" class="form-control form-select" <?php echo ($record['collaboration_collaborate'] == 'yes') ? 'required' : ''; ?>>
                                <option value="" disabled>Select option</option>
                                <option value="Celestine S - 10269" <?php if($record['delivery_account_lead'] == 'Celestine S - 10269') echo 'selected'; ?>>Celestine S - 10269</option>
                                <option value="Felix B - 10094" <?php if($record['delivery_account_lead'] == 'Felix B - 10094') echo 'selected'; ?>>Felix B - 10094</option>
                                <option value="Prassanna Kumar - 11738" <?php if($record['delivery_account_lead'] == 'Prassanna Kumar - 11738') echo 'selected'; ?>>Prassanna Kumar - 11738</option>
                                <option value="Praveenkumar Kandasamy - 12422" <?php if($record['delivery_account_lead'] == 'Praveenkumar Kandasamy - 12422') echo 'selected'; ?>>Praveenkumar Kandasamy - 12422</option>
                                <option value="Sastha Karthick M - 10662" <?php if($record['delivery_account_lead'] == 'Sastha Karthick M - 10662') echo 'selected'; ?>>Sastha Karthick M - 10662</option>
                                <option value="Sinimary X - 10365" <?php if($record['delivery_account_lead'] == 'Sinimary X - 10365') echo 'selected'; ?>>Sinimary X - 10365</option>
                                <option value="Iyngaran C - 12706" <?php if($record['delivery_account_lead'] == 'Iyngaran C - 12706') echo 'selected'; ?>>Iyngaran C - 12706</option>
                                <option value="NA" <?php if($record['delivery_account_lead'] == 'NA') echo 'selected'; ?>>NA</option>
                            </select>
                            <div class="invalid-feedback">Please select a delivery account lead</div>
                        </div>
                        
                        <div class="form-group" id="team_lead_field" style="<?php echo ($record['collaboration_collaborate'] == 'yes') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="team_lead" class="form-label required">Team Lead</label>
                            <select id="team_lead" name="team_lead" class="form-control form-select" <?php echo ($record['collaboration_collaborate'] == 'yes') ? 'required' : ''; ?>>
                                <option value="" disabled>Select option</option>
                                <option value="Balaji K - 11082" <?php if($record['team_lead'] == 'Balaji K - 11082') echo 'selected'; ?>>Balaji K - 11082</option>
                                <option value="Deepak Ganesan - 12702" <?php if($record['team_lead'] == 'Deepak Ganesan - 12702') echo 'selected'; ?>>Deepak Ganesan - 12702</option>
                                <option value="Dinakaran G - 11426" <?php if($record['team_lead'] == 'Dinakaran G - 11426') echo 'selected'; ?>>Dinakaran G - 11426</option>
                                <option value="NA" <?php if($record['team_lead'] == 'NA') echo 'selected'; ?>>NA</option>
                                <!-- Add other team lead options here -->
                            </select>
                            <div class="invalid-feedback">Please select a team lead</div>
                        </div>
                        
                        <div class="form-group" id="associate_team_lead_field" style="<?php echo ($record['collaboration_collaborate'] == 'yes') ? 'display: block;' : 'display: none;'; ?>">
                            <label for="associate_team_lead" class="form-label required">Associate Team Lead</label>
                            <select id="associate_team_lead" name="associate_team_lead" class="form-control form-select" <?php echo ($record['collaboration_collaborate'] == 'yes') ? 'required' : ''; ?>>
                                <option value="" disabled>Select option</option>
                                <option value="Abarna S - 11538" <?php if($record['associate_team_lead'] == 'Abarna S - 11538') echo 'selected'; ?>>Abarna S - 11538</option>
                                <option value="Abirami Ramdoss - 11276" <?php if($record['associate_team_lead'] == 'Abirami Ramdoss - 11276') echo 'selected'; ?>>Abirami Ramdoss - 11276</option>
                                <option value="Balaji R - 11333" <?php if($record['associate_team_lead'] == 'Balaji R - 11333') echo 'selected'; ?>>Balaji R - 11333</option>
                                <option value="NA" <?php if($record['associate_team_lead'] == 'NA') echo 'selected'; ?>>NA</option>
                                <!-- Add other associate team lead options here -->
                            </select>
                            <div class="invalid-feedback">Please select an associate team lead</div>
                        </div>
                    </div>
                </div>
                

                <div class="tab-content" id="recruiter-tab">
    <div class="form-section-title">
        <i class="fas fa-user-friends"></i> Recruiter Information
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label for="business_unit" class="form-label required">Business Unit</label>
            <select id="business_unit" name="business_unit" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Sidd" <?php if($record['business_unit'] == 'Sidd') echo 'selected'; ?>>Sidd</option>
                <option value="Oliver" <?php if($record['business_unit'] == 'Oliver') echo 'selected'; ?>>Oliver</option>
                <option value="Nambu" <?php if($record['business_unit'] == 'Nambu') echo 'selected'; ?>>Nambu</option>
                <option value="Rohit" <?php if($record['business_unit'] == 'Rohit') echo 'selected'; ?>>Rohit</option>
                <option value="Vinay" <?php if($record['business_unit'] == 'Vinay') echo 'selected'; ?>>Vinay</option>
            </select>
            <div class="invalid-feedback">Please select a business unit</div>
        </div>
        <div class="form-group">
            <label for="client_account_lead" class="form-label required">Client Account Lead</label>
            <select id="client_account_lead" name="client_account_lead" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Amit" <?php if($record['client_account_lead'] == 'Amit') echo 'selected'; ?>>Amit</option>
                <option value="Abhishek" <?php if($record['client_account_lead'] == 'Abhishek') echo 'selected'; ?>>Abhishek</option>
                <option value="Aditya" <?php if($record['client_account_lead'] == 'Aditya') echo 'selected'; ?>>Aditya</option>
                <option value="Abhishek / Aditya" <?php if($record['client_account_lead'] == 'Abhishek / Aditya') echo 'selected'; ?>>Abhishek / Aditya</option>
                <option value="Vijay Methani" <?php if($record['client_account_lead'] == 'Vijay Methani') echo 'selected'; ?>>Vijay Methani</option>
                <option value="Valerie S" <?php if($record['client_account_lead'] == 'Valerie S') echo 'selected'; ?>>Valerie S</option>
                <option value="David" <?php if($record['client_account_lead'] == 'David') echo 'selected'; ?>>David</option>
                <option value="Devna" <?php if($record['client_account_lead'] == 'Devna') echo 'selected'; ?>>Devna</option>
                <option value="Don" <?php if($record['client_account_lead'] == 'Don') echo 'selected'; ?>>Don</option>
                <option value="Monse" <?php if($record['client_account_lead'] == 'Monse') echo 'selected'; ?>>Monse</option>
                <option value="Murugesan Sivaraman" <?php if($record['client_account_lead'] == 'Murugesan Sivaraman') echo 'selected'; ?>>Murugesan Sivaraman</option>
                <option value="Nambu" <?php if($record['client_account_lead'] == 'Nambu') echo 'selected'; ?>>Nambu</option>
                <option value="Narayan" <?php if($record['client_account_lead'] == 'Narayan') echo 'selected'; ?>>Narayan</option>
                <option value="Parijat" <?php if($record['client_account_lead'] == 'Parijat') echo 'selected'; ?>>Parijat</option>
                <option value="Priscilla" <?php if($record['client_account_lead'] == 'Priscilla') echo 'selected'; ?>>Priscilla</option>
                <option value="Sudip" <?php if($record['client_account_lead'] == 'Sudip') echo 'selected'; ?>>Sudip</option>
                <option value="Vinay" <?php if($record['client_account_lead'] == 'Vinay') echo 'selected'; ?>>Vinay</option>
                <option value="Prasanth Ravi" <?php if($record['client_account_lead'] == 'Prasanth Ravi') echo 'selected'; ?>>Prasanth Ravi</option>
                <option value="Sachin Sinha" <?php if($record['client_account_lead'] == 'Sachin Sinha') echo 'selected'; ?>>Sachin Sinha</option>
                <option value="Susan Johnson" <?php if($record['client_account_lead'] == 'Susan Johnson') echo 'selected'; ?>>Susan Johnson</option>
                <option value="NA" <?php if($record['client_account_lead'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select a client account lead</div>
        </div>
        <div class="form-group">
            <label for="client_partner" class="form-label required">Client Partner</label>
            <select id="client_partner" name="client_partner" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Amit" <?php if($record['client_partner'] == 'Amit') echo 'selected'; ?>>Amit</option>
                <option value="Abhishek" <?php if($record['client_partner'] == 'Abhishek') echo 'selected'; ?>>Abhishek</option>
                <option value="Aditya" <?php if($record['client_partner'] == 'Aditya') echo 'selected'; ?>>Aditya</option>
                <option value="Abhishek / Aditya" <?php if($record['client_partner'] == 'Abhishek / Aditya') echo 'selected'; ?>>Abhishek / Aditya</option>
                <option value="Vijay Methani" <?php if($record['client_partner'] == 'Vijay Methani') echo 'selected'; ?>>Vijay Methani</option>
                <option value="David" <?php if($record['client_partner'] == 'David') echo 'selected'; ?>>David</option>
                <option value="Sudip" <?php if($record['client_partner'] == 'Sudip') echo 'selected'; ?>>Sudip</option>
                <option value="NA" <?php if($record['client_partner'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select a client partner</div>
        </div>
        <div class="form-group">
            <label for="associate_director_delivery" class="form-label required">Associate Director Delivery</label>
            <select id="associate_director_delivery" name="associate_director_delivery" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Mohanavelu K.A - 12186" <?php if($record['associate_director_delivery'] == 'Mohanavelu K.A - 12186') echo 'selected'; ?>>Mohanavelu K.A - 12186</option>
                <option value="Ajay D - 10009" <?php if($record['associate_director_delivery'] == 'Ajay D - 10009') echo 'selected'; ?>>Ajay D - 10009</option>
                <option value="Arun Franklin Joseph - 10344" <?php if($record['associate_director_delivery'] == 'Arun Franklin Joseph - 10344') echo 'selected'; ?>>Arun Franklin Joseph - 10344</option>
                <option value="Soloman. S - 10006" <?php if($record['associate_director_delivery'] == 'Soloman. S - 10006') echo 'selected'; ?>>Soloman. S - 10006</option>
                <option value="Manoj B.G - 10058" <?php if($record['associate_director_delivery'] == 'Manoj B.G - 10058') echo 'selected'; ?>>Manoj B.G - 10058</option>
                <option value="Richa Verma - 10606" <?php if($record['associate_director_delivery'] == 'Richa Verma - 10606') echo 'selected'; ?>>Richa Verma - 10606</option>
                <option value="NA" <?php if($record['associate_director_delivery'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select an associate director delivery</div>
        </div>
        <div class="form-group">
            <label for="delivery_manager1" class="form-label required">Delivery Manager</label>
            <select id="delivery_manager1" name="delivery_manager1" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Arun Franklin Joseph - 10344" <?php if($record['delivery_manager1'] == 'Arun Franklin Joseph - 10344') echo 'selected'; ?>>Arun Franklin Joseph - 10344</option>
                <option value="DinoZeoff M - 10097" <?php if($record['delivery_manager1'] == 'DinoZeoff M - 10097') echo 'selected'; ?>>DinoZeoff M - 10097</option>
                <option value="Jack Sherman - 10137" <?php if($record['delivery_manager1'] == 'Jack Sherman - 10137') echo 'selected'; ?>>Jack Sherman - 10137</option>
                <option value="Johnathan Liazar - 10066" <?php if($record['delivery_manager1'] == 'Johnathan Liazar - 10066') echo 'selected'; ?>>Johnathan Liazar - 10066</option>
                <option value="Lance Taylor - 10082" <?php if($record['delivery_manager1'] == 'Lance Taylor - 10082') echo 'selected'; ?>>Lance Taylor - 10082</option>
                <option value="Michael Devaraj A - 10123" <?php if($record['delivery_manager1'] == 'Michael Devaraj A - 10123') echo 'selected'; ?>>Michael Devaraj A - 10123</option>
                <option value="Murugesan Sivaraman" <?php if($record['delivery_manager1'] == 'Murugesan Sivaraman') echo 'selected'; ?>>Murugesan Sivaraman</option>
                <option value="Omar Mohamed - 10944" <?php if($record['delivery_manager1'] == 'Omar Mohamed - 10944') echo 'selected'; ?>>Omar Mohamed - 10944</option>
                <option value="Richa Verma - 10606" <?php if($record['delivery_manager1'] == 'Richa Verma - 10606') echo 'selected'; ?>>Richa Verma - 10606</option>
                <option value="Seliyan M - 10028" <?php if($record['delivery_manager1'] == 'Seliyan M - 10028') echo 'selected'; ?>>Seliyan M - 10028</option>
                <option value="Srivijayaraghavan M - 10270" <?php if($record['delivery_manager1'] == 'Srivijayaraghavan M - 10270') echo 'selected'; ?>>Srivijayaraghavan M - 10270</option>
                <option value="Vandhana R R - 10021" <?php if($record['delivery_manager1'] == 'Vandhana R R - 10021') echo 'selected'; ?>>Vandhana R R - 10021</option>
                <option value="Faisal Ahamed - 12721" <?php if($record['delivery_manager1'] == 'Faisal Ahamed - 12721') echo 'selected'; ?>>Faisal Ahamed - 12721</option>
                <option value="NA" <?php if($record['delivery_manager1'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select a delivery manager</div>
        </div>
        <div class="form-group">
            <label for="delivery_account_lead1" class="form-label required">Delivery Account Lead</label>
            <select id="delivery_account_lead1" name="delivery_account_lead1" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Celestine S - 10269" <?php if($record['delivery_account_lead1'] == 'Celestine S - 10269') echo 'selected'; ?>>Celestine S - 10269</option>
                <option value="Felix B - 10094" <?php if($record['delivery_account_lead1'] == 'Felix B - 10094') echo 'selected'; ?>>Felix B - 10094</option>
                <option value="Jeorge S - 10444" <?php if($record['delivery_account_lead1'] == 'Jeorge S - 10444') echo 'selected'; ?>>Jeorge S - 10444</option>
                <option value="Prassanna Kumar - 11738" <?php if($record['delivery_account_lead1'] == 'Prassanna Kumar - 11738') echo 'selected'; ?>>Prassanna Kumar - 11738</option>
                <option value="Praveenkumar Kandasamy - 12422" <?php if($record['delivery_account_lead1'] == 'Praveenkumar Kandasamy - 12422') echo 'selected'; ?>>Praveenkumar Kandasamy - 12422</option>
                <option value="Sastha Karthick M - 10662" <?php if($record['delivery_account_lead1'] == 'Sastha Karthick M - 10662') echo 'selected'; ?>>Sastha Karthick M - 10662</option>
                <option value="Sinimary X - 10365" <?php if($record['delivery_account_lead1'] == 'Sinimary X - 10365') echo 'selected'; ?>>Sinimary X - 10365</option>
                <option value="Susan Johnson" <?php if($record['delivery_account_lead1'] == 'Susan Johnson') echo 'selected'; ?>>Susan Johnson</option>
                <option value="Iyngaran C - 12706" <?php if($record['delivery_account_lead1'] == 'Iyngaran C - 12706') echo 'selected'; ?>>Iyngaran C - 12706</option>
                <option value="NA" <?php if($record['delivery_account_lead1'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select a delivery account lead</div>
        </div>
        <div class="form-group">
            <label for="team_lead1" class="form-label required">Team Lead</label>
            <select id="team_lead1" name="team_lead1" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Balaji K - 11082" <?php if($record['team_lead1'] == 'Balaji K - 11082') echo 'selected'; ?>>Balaji K - 11082</option>
                <option value="Deepak Ganesan - 12702" <?php if($record['team_lead1'] == 'Deepak Ganesan - 12702') echo 'selected'; ?>>Deepak Ganesan - 12702</option>
                <option value="Dinakaran G - 11426" <?php if($record['team_lead1'] == 'Dinakaran G - 11426') echo 'selected'; ?>>Dinakaran G - 11426</option>
                <option value="Elankumaran V - 11110" <?php if($record['team_lead1'] == 'Elankumaran V - 11110') echo 'selected'; ?>>Elankumaran V - 11110</option>
                <option value="Guna Sekaran S - 10488" <?php if($record['team_lead1'] == 'Guna Sekaran S - 10488') echo 'selected'; ?>>Guna Sekaran S - 10488</option>
                <option value="Guru Samy N - 10924" <?php if($record['team_lead1'] == 'Guru Samy N - 10924') echo 'selected'; ?>>Guru Samy N - 10924</option>
                <option value="Jeorge S - 10444" <?php if($record['team_lead1'] == 'Jeorge S - 10444') echo 'selected'; ?>>Jeorge S - 10444</option>
                <option value="NA" <?php if($record['team_lead1'] == 'NA') echo 'selected'; ?>>NA</option>
                <!-- Add more options as needed based on your requirements -->
            </select>
            <div class="invalid-feedback">Please select a team lead</div>
        </div>
        <div class="form-group">
            <label for="associate_team_lead1" class="form-label required">Associate Team Lead</label>
            <select id="associate_team_lead1" name="associate_team_lead1" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Abarna S - 11538" <?php if($record['associate_team_lead1'] == 'Abarna S - 11538') echo 'selected'; ?>>Abarna S - 11538</option>
                <option value="Abirami Ramdoss - 11276" <?php if($record['associate_team_lead1'] == 'Abirami Ramdoss - 11276') echo 'selected'; ?>>Abirami Ramdoss - 11276</option>
                <option value="Balaji R - 11333" <?php if($record['associate_team_lead1'] == 'Balaji R - 11333') echo 'selected'; ?>>Balaji R - 11333</option>
                <option value="TBD" <?php if($record['associate_team_lead1'] == 'TBD') echo 'selected'; ?>>TBD</option>
                <option value="NA" <?php if($record['associate_team_lead1'] == 'NA') echo 'selected'; ?>>NA</option>
                <!-- Add more options as needed -->
            </select>
            <div class="invalid-feedback">Please select an associate team lead</div>
        </div>
        <div class="form-group">
            <label for="recruiter_name" class="form-label required">Recruiter Name</label>
            <select id="recruiter_name" name="recruiter_name" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Aarthy Arockyaraj - 11862" <?php if($record['recruiter_name'] == 'Aarthy Arockyaraj - 11862') echo 'selected'; ?>>Aarthy Arockyaraj - 11862</option>
                <option value="TBD" <?php if($record['recruiter_name'] == 'TBD') echo 'selected'; ?>>TBD</option>
                <option value="NA" <?php if($record['recruiter_name'] == 'NA') echo 'selected'; ?>>NA</option>
                <!-- Add more options as needed -->
            </select>
            <div class="invalid-feedback">Please select a recruiter name</div>
        </div>
        <div class="form-group">
            <label for="pt_support" class="form-label required">PT Support</label>
            <select id="pt_support" name="pt_support" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Abarna S - 11538" <?php if($record['pt_support'] == 'Abarna S - 11538') echo 'selected'; ?>>Abarna S - 11538</option>
                <option value="Abirami Ramdoss - 11276" <?php if($record['pt_support'] == 'Abirami Ramdoss - 11276') echo 'selected'; ?>>Abirami Ramdoss - 11276</option>
                <option value="NA" <?php if($record['pt_support'] == 'NA') echo 'selected'; ?>>NA</option>
                <!-- Add more options as needed -->
            </select>
            <div class="invalid-feedback">Please select PT support</div>
        </div>
        <div class="form-group">
            <label for="pt_ownership" class="form-label required">PT Ownership</label>
            <select id="pt_ownership" name="pt_ownership" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="Abarna S - 11538" <?php if($record['pt_ownership'] == 'Abarna S - 11538') echo 'selected'; ?>>Abarna S - 11538</option>
                <option value="Abirami Ramdoss - 11276" <?php if($record['pt_ownership'] == 'Abirami Ramdoss - 11276') echo 'selected'; ?>>Abirami Ramdoss - 11276</option>
                <option value="NA" <?php if($record['pt_ownership'] == 'NA') echo 'selected'; ?>>NA</option>
                <!-- Add more options as needed -->
            </select>
            <div class="invalid-feedback">Please select PT ownership</div>
        </div>
    </div>
</div>

<!-- Project Tab -->
<div class="tab-content" id="project-tab">
    <div class="form-section-title">
        <i class="fas fa-project-diagram"></i> Project Details
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label for="geo" class="form-label required">GEO</label>
            <select id="geo" name="geo" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="USA" <?php if($record['geo'] == 'USA') echo 'selected'; ?>>USA</option>
                <option value="MEX" <?php if($record['geo'] == 'MEX') echo 'selected'; ?>>MEX</option>
                <option value="CAN" <?php if($record['geo'] == 'CAN') echo 'selected'; ?>>CAN</option>
                <option value="CR" <?php if($record['geo'] == 'CR') echo 'selected'; ?>>CR</option>
                <option value="AUS" <?php if($record['geo'] == 'AUS') echo 'selected'; ?>>AUS</option>
                <option value="JAP" <?php if($record['geo'] == 'JAP') echo 'selected'; ?>>JAP</option>
                <option value="Spain" <?php if($record['geo'] == 'Spain') echo 'selected'; ?>>Spain</option>
                <option value="UAE" <?php if($record['geo'] == 'UAE') echo 'selected'; ?>>UAE</option>
                <option value="UK" <?php if($record['geo'] == 'UK') echo 'selected'; ?>>UK</option>
                <option value="PR" <?php if($record['geo'] == 'PR') echo 'selected'; ?>>PR</option>
                <option value="Brazil" <?php if($record['geo'] == 'Brazil') echo 'selected'; ?>>Brazil</option>
                <option value="Belgium" <?php if($record['geo'] == 'Belgium') echo 'selected'; ?>>Belgium</option>
                <option value="IND" <?php if($record['geo'] == 'IND') echo 'selected'; ?>>IND</option>
            </select>
            <div class="invalid-feedback">Please select a GEO</div>
        </div>
        <div class="form-group">
            <label for="entity" class="form-label required">Entity</label>
            <select id="entity" name="entity" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="VDart Inc" <?php if($record['entity'] == 'VDart Inc') echo 'selected'; ?>>VDart Inc</option>
                <option value="VDart Digital" <?php if($record['entity'] == 'VDart Digital') echo 'selected'; ?>>VDart Digital</option>
                <option value="VDart Canada" <?php if($record['entity'] == 'VDart Canada') echo 'selected'; ?>>VDart Canada</option>
                <option value="VDart Costa Rica" <?php if($record['entity'] == 'VDart Costa Rica') echo 'selected'; ?>>VDart Costa Rica</option>
                <option value="VDart UK" <?php if($record['entity'] == 'VDart UK') echo 'selected'; ?>>VDart UK</option>
                <option value="VDart Mexico" <?php if($record['entity'] == 'VDart Mexico') echo 'selected'; ?>>VDart Mexico</option>
                <option value="VDart Australia" <?php if($record['entity'] == 'VDart Australia') echo 'selected'; ?>>VDart Australia</option>
                <option value="VDart Puerto Rico" <?php if($record['entity'] == 'VDart Puerto Rico') echo 'selected'; ?>>VDart Puerto Rico</option>
                <option value="VDart Japan" <?php if($record['entity'] == 'VDart Japan') echo 'selected'; ?>>VDart Japan</option>
                <option value="VDart Spain" <?php if($record['entity'] == 'VDart Spain') echo 'selected'; ?>>VDart Spain</option>
                <option value="VDart Dubai" <?php if($record['entity'] == 'VDart Dubai') echo 'selected'; ?>>VDart Dubai</option>
                <option value="VDart Brazil" <?php if($record['entity'] == 'VDart Brazil') echo 'selected'; ?>>VDart Brazil</option>
                <option value="VDart Belgium" <?php if($record['entity'] == 'VDart Belgium') echo 'selected'; ?>>VDart Belgium</option>
                <option value="VDart India Private Limited" <?php if($record['entity'] == 'VDart India Private Limited') echo 'selected'; ?>>VDart India Private Limited</option>
                <option value="NA" <?php if($record['entity'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select an entity</div>
        </div>
        <div class="form-group">
            <label for="client" class="form-label required">Client</label>
            <input type="text" id="client" name="client" class="form-control" value="<?php echo htmlspecialchars($record['client']); ?>" required>
            <div class="invalid-feedback">Please enter a client</div>
        </div>
        <div class="form-group">
            <label for="client_manager" class="form-label required">Client Manager</label>
            <input type="text" id="client_manager" name="client_manager" class="form-control" value="<?php echo htmlspecialchars($record['client_manager']); ?>" required>
            <div class="invalid-feedback">Please enter a client manager</div>
        </div>
        <div class="form-group">
            <label for="client_manager_email_id" class="form-label required">Client Manager Email ID</label>
            <input type="email" id="client_manager_email_id" name="client_manager_email_id" class="form-control" value="<?php echo htmlspecialchars($record['client_manager_email_id']); ?>" required>
            <div class="invalid-feedback">Please enter a valid email</div>
        </div>
        <div class="form-group">
            <label for="end_client" class="form-label required">End Client</label>
            <input type="text" id="end_client" name="end_client" class="form-control" value="<?php echo htmlspecialchars($record['end_client']); ?>" required>
            <div class="invalid-feedback">Please enter an end client</div>
        </div>
        <div class="form-group">
            <label for="business_track" class="form-label required">Business Track</label>
            <select id="business_track" name="business_track" class="form-control form-select" required>
                <option value="" disabled>Select option</option>
                <option value="BFSI" <?php if($record['business_track'] == 'BFSI') echo 'selected'; ?>>BFSI</option>
                <option value="NON BFSI" <?php if($record['business_track'] == 'NON BFSI') echo 'selected'; ?>>NON BFSI</option>
                <option value="HCL - CS" <?php if($record['business_track'] == 'HCL - CS') echo 'selected'; ?>>HCL - CS</option>
                <option value="HCL - FS" <?php if($record['business_track'] == 'HCL - FS') echo 'selected'; ?>>HCL - FS</option>
                <option value="HCL - CI" <?php if($record['business_track'] == 'HCL - CI') echo 'selected'; ?>>HCL - CI</option>
                <option value="Infra" <?php if($record['business_track'] == 'Infra') echo 'selected'; ?>>Infra</option>
                <option value="Infra - Noram 3" <?php if($record['business_track'] == 'Infra - Noram 3') echo 'selected'; ?>>Infra - Noram 3</option>
                <option value="IBM" <?php if($record['business_track'] == 'IBM') echo 'selected'; ?>>IBM</option>
                <option value="ERS" <?php if($record['business_track'] == 'ERS') echo 'selected'; ?>>ERS</option>
                <option value="NORAM 3" <?php if($record['business_track'] == 'NORAM 3') echo 'selected'; ?>>NORAM 3</option>
                <option value="DPO" <?php if($record['business_track'] == 'DPO') echo 'selected'; ?>>DPO</option>
                <option value="Accenture - IT" <?php if($record['business_track'] == 'Accenture - IT') echo 'selected'; ?>>Accenture - IT</option>
                <option value="Engineering" <?php if($record['business_track'] == 'Engineering') echo 'selected'; ?>>Engineering</option>
                <option value="NON IT" <?php if($record['business_track'] == 'NON IT') echo 'selected'; ?>>NON IT</option>
                <option value="Digital" <?php if($record['business_track'] == 'Digital') echo 'selected'; ?>>Digital</option>
                <option value="NON Digital" <?php if($record['business_track'] == 'NON Digital') echo 'selected'; ?>>NON Digital</option>
                <option value="CIS - Cognizant Infrastructure Services" <?php if($record['business_track'] == 'CIS - Cognizant Infrastructure Services') echo 'selected'; ?>>CIS - Cognizant Infrastructure Services</option>
                <option value="NA" <?php if($record['business_track'] == 'NA') echo 'selected'; ?>>NA</option>
            </select>
            <div class="invalid-feedback">Please select a business track</div>
        </div>
            <div class="form-group">
                <label for="industry" class="form-label required">Industry</label>
                <input type="text" id="industry" name="industry" class="form-control" value="<?php echo htmlspecialchars($record['industry']); ?>" required>
                <div class="invalid-feedback">Please enter an industry</div>
            </div>
            <div class="form-group">
                <label for="experience_in_expertise_role" class="form-label required">Experience in Expertise Role | Hands on</label>
                <input type="number" id="experience_in_expertise_role" name="experience_in_expertise_role" class="form-control" value="<?php echo htmlspecialchars($record['experience_in_expertise_role']); ?>" required>
                <div class="invalid-feedback">Please enter experience in years</div>
            </div>
            <div class="form-group">
                <label for="job_code" class="form-label required">Job Code</label>
                <input type="text" id="job_code" name="job_code" class="form-control" value="<?php echo htmlspecialchars($record['job_code']); ?>" required>
                <div class="invalid-feedback">Please enter a job code</div>
            </div>
            <div class="form-group">
                <label for="job_title" class="form-label required">Job Title / Role</label>
                <input type="text" id="job_title" name="job_title" class="form-control" value="<?php echo htmlspecialchars($record['job_title']); ?>" required>
                <div class="invalid-feedback">Please enter a job title</div>
            </div>
            <div class="form-group">
                <label for="primary_skill" class="form-label required">Primary Skill</label>
                <input type="text" id="primary_skill" name="primary_skill" class="form-control" value="<?php echo htmlspecialchars($record['primary_skill']); ?>" required>
                <div class="invalid-feedback">Please enter primary skill</div>
            </div>
            <div class="form-group">
                <label for="secondary_skill" class="form-label required">Secondary Skill</label>
                <input type="text" id="secondary_skill" name="secondary_skill" class="form-control" value="<?php echo htmlspecialchars($record['secondary_skill']); ?>" required>
                <div class="invalid-feedback">Please enter secondary skill</div>
            </div>
            <div class="form-group">
                <label for="term" class="form-label required">Term</label>
                <select id="term" name="term" class="form-control form-select" required>
                    <option value="" disabled>Select option</option>
                    <option value="CON" <?php if($record['term'] == 'CON') echo 'selected'; ?>>CON</option>
                    <option value="C2H" <?php if($record['term'] == 'C2H') echo 'selected'; ?>>C2H</option>
                    <option value="FTE" <?php if($record['term'] == 'FTE') echo 'selected'; ?>>FTE</option>
                    <option value="1099" <?php if($record['term'] == '1099') echo 'selected'; ?>>1099</option>
                </select>
                <div class="invalid-feedback">Please select a term</div>
            </div>
            <div class="form-group">
                <label for="duration" class="form-label required">Duration</label>
                <input type="text" id="duration" name="duration" class="form-control" value="<?php echo htmlspecialchars($record['duration']); ?>" required>
                <div class="invalid-feedback">Please enter the duration</div>
            </div>
            <div class="form-group">
                <label for="project_location" class="form-label required">Project Location</label>
                <input type="text" id="project_location" name="project_location" class="form-control" value="<?php echo htmlspecialchars($record['project_location']); ?>" required>
                <div class="invalid-feedback">Please enter the project location</div>
            </div>
            <div class="form-group">
                <label for="start_date" class="form-label required">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($record['start_date']); ?>" required>
                <div class="invalid-feedback">Please select a start date</div>
            </div>
            <div class="form-group">
                <label for="end_date" class="form-label required">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($record['end_date']); ?>" required>
                <div class="invalid-feedback">Please select an end date</div>
            </div>
            <div class="form-group">
                <label for="type" class="form-label required">Type</label>
                <select id="type" name="type" class="form-control form-select" required>
                    <option value="" disabled>Select option</option>
                    <option value="Deal" <?php if($record['type'] == 'Deal') echo 'selected'; ?>>Deal</option>
                    <option value="PT" <?php if($record['type'] == 'PT') echo 'selected'; ?>>PT</option>
                    <option value="PTR" <?php if($record['type'] == 'PTR') echo 'selected'; ?>>PTR</option>
                    <option value="VC" <?php if($record['type'] == 'VC') echo 'selected'; ?>>VC</option>
                </select>
                <div class="invalid-feedback">Please select a type</div>
            </div>
        </div>
    </div>

    <!-- Payment Tab -->
    <div class="tab-content" id="payment-tab">
        <div class="form-section-title">
            <i class="fas fa-money-bill-wave"></i> Payment Details
        </div>
        <div class="form-grid">
        <div class="form-group">
            <label for="placement_type" class="form-label required">Pay Type</label>
            <select id="placement_type" name="placement_type" class="form-control form-select" required onchange="updateRateUnits()">
                <option value="" disabled>Select option</option>
                <option value="Hourly" <?php echo ($placementType == 'Hourly') ? 'selected' : ''; ?>>Hourly</option>
                <option value="Monthly" <?php echo ($placementType == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                <option value="Daily" <?php echo ($placementType == 'Daily') ? 'selected' : ''; ?>>Daily</option>
                <option value="Weekly" <?php echo ($placementType == 'Weekly') ? 'selected' : ''; ?>>Weekly</option>
                <option value="Bi-Weekly" <?php echo ($placementType == 'Bi-Weekly') ? 'selected' : ''; ?>>Bi-Weekly</option>
                <option value="Semi-Monthly" <?php echo ($placementType == 'Semi-Monthly') ? 'selected' : ''; ?>>Semi-Monthly</option>
                <option value="Per Annum" <?php echo ($placementType == 'Per Annum') ? 'selected' : ''; ?>>Per Annum</option>
            </select>
        </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
            <!-- BILL RATE Section -->
            <div style="width: 48%;">
                <h4 style="margin-bottom: 15px; font-weight: 500;">BILL RATE</h4>
                <div class="form-group">
                    <label for="bill_type" class="form-label required">Tax Term</label>
                    <select id="bill_type" name="bill_type" class="form-control form-select" required>
                        <option value="" disabled>Select option</option>
                        <option value="1099" <?php if($clientRateComponents['tax_term'] == '1099') echo 'selected'; ?>>1099</option>
                        <option value="C2C" <?php if($clientRateComponents['tax_term'] == 'C2C') echo 'selected'; ?>>C2C</option>
                        <option value="C2C - Own Corp" <?php if($clientRateComponents['tax_term'] == 'C2C - Own Corp') echo 'selected'; ?>>C2C - Own Corp</option>
                        <option value="Full Time" <?php if($clientRateComponents['tax_term'] == 'Full Time') echo 'selected'; ?>>Full Time</option>
                        <option value="T4" <?php if($clientRateComponents['tax_term'] == 'T4') echo 'selected'; ?>>T4</option>
                        <option value="W2" <?php if($clientRateComponents['tax_term'] == 'W2') echo 'selected'; ?>>W2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="clientrate" class="form-label required">Client Bill Rate</label>
                    <div style="display: flex; gap: 10px;">
                    <input type="number" id="clientrate" name="clientrate" class="form-control" 
                        value="<?php echo !empty($clientRateComponents['rate']) ? 
                                htmlspecialchars($clientRateComponents['rate']) : '0'; ?>" 
                        min="0" step="any" required>
                        <span style="display: flex; align-items: center;" class="rate-unit"><?php echo htmlspecialchars($clientRateComponents['unit']); ?></span>
                        <select id="bill_currency" name="bill_currency" class="form-control" style="width: 30%;" required>
                            <option value="USD" <?php if($clientRateComponents['currency'] == 'USD') echo 'selected'; ?>>USD</option>
                            <option value="CAD" <?php if($clientRateComponents['currency'] == 'CAD') echo 'selected'; ?>>CAD</option>
                            <option value="MXN" <?php if($clientRateComponents['currency'] == 'MXN') echo 'selected'; ?>>MXN</option>
                            <option value="AUD" <?php if($clientRateComponents['currency'] == 'AUD') echo 'selected'; ?>>AUD</option>
                            <option value="GBP" <?php if($clientRateComponents['currency'] == 'GBP') echo 'selected'; ?>>GBP</option>
                            <option value="EUR" <?php if($clientRateComponents['currency'] == 'EUR') echo 'selected'; ?>>EUR</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- PAY RATE Section -->
            <div style="width: 48%;">
                <h4 style="margin-bottom: 15px; font-weight: 500;">PAY RATE</h4>
                <div class="form-group">
                    <label for="pay_type" class="form-label required">Tax Term</label>
                    <select id="pay_type" name="pay_type" class="form-control form-select" required>
                        <option value="" disabled>Select option</option>
                        <option value="1099" <?php if($payRateComponents['tax_term'] == '1099') echo 'selected'; ?>>1099</option>
                        <option value="C2C" <?php if($payRateComponents['tax_term'] == 'C2C') echo 'selected'; ?>>C2C</option>
                        <option value="C2C - Own Corp" <?php if($payRateComponents['tax_term'] == 'C2C - Own Corp') echo 'selected'; ?>>C2C - Own Corp</option>
                        <option value="Full Time" <?php if($payRateComponents['tax_term'] == 'Full Time') echo 'selected'; ?>>Full Time</option>
                        <option value="T4" <?php if($payRateComponents['tax_term'] == 'T4') echo 'selected'; ?>>T4</option>
                        <option value="W2" <?php if($payRateComponents['tax_term'] == 'W2') echo 'selected'; ?>>W2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payrate" class="form-label required">Pay Rate</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" id="payrate" name="payrate" class="form-control" style="width: 70%;" 
                        value="<?php echo !empty($payRateComponents['rate']) ? 
                                htmlspecialchars($payRateComponents['rate']) : '0'; ?>" 
                        min="0" step="any" required>
                        <span style="display: flex; align-items: center;" class="rate-unit"><?php echo htmlspecialchars($payRateComponents['unit']); ?></span>
                        <select id="pay_currency" name="pay_currency" class="form-control" style="width: 30%;" required>
                            <option value="USD" <?php if($payRateComponents['currency'] == 'USD') echo 'selected'; ?>>USD</option>
                            <option value="CAD" <?php if($payRateComponents['currency'] == 'CAD') echo 'selected'; ?>>CAD</option>
                            <option value="MXN" <?php if($payRateComponents['currency'] == 'MXN') echo 'selected'; ?>>MXN</option>
                            <option value="AUD" <?php if($payRateComponents['currency'] == 'AUD') echo 'selected'; ?>>AUD</option>
                            <option value="GBP" <?php if($payRateComponents['currency'] == 'GBP') echo 'selected'; ?>>GBP</option>
                            <option value="EUR" <?php if($payRateComponents['currency'] == 'EUR') echo 'selected'; ?>>EUR</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Margin Section -->
        <div style="margin-top: 20px;">
            <div class="form-group">
                <label for="margin" class="form-label required">Margin</label>
                <input type="number" id="margin" name="margin" class="form-control" style="max-width: 200px;" value="<?php echo htmlspecialchars($record['margin']); ?>" required>
                <div class="invalid-feedback">Please enter the margin</div>
            </div>

            <div class="form-group">
                <label for="benifits" class="form-label required">Benefits</label>
                <input type="text" id="benifits" name="benifits" class="form-control" value="<?php echo htmlspecialchars($record['benifits'] ?? ''); ?>" required>
                <div class="invalid-feedback">Please enter benefits</div>
            </div>

            <div class="form-group">
                <label for="vendor_fee" class="form-label required">Additional Vendor Fee (If applicable)</label>
                <input type="text" id="vendor_fee" name="vendor_fee" class="form-control" value="<?php echo htmlspecialchars($record['vendor_fee']); ?>" required>
                <div class="invalid-feedback">Please enter vendor fee details</div>
            </div>
            
            <div class="form-group">
                <label for="margin_deviation_approval" class="form-label required">Margin Deviation Approval (Yes/No)</label>
                <select id="margin_deviation_approval" name="margin_deviation_approval" class="form-control form-select" onchange="toggleMarginDeviationReason()" required>
                    <option value="" disabled>Select option</option>
                    <option value="Yes" <?php if($record['margin_deviation_approval'] == 'Yes') echo 'selected'; ?>>Yes</option>
                    <option value="No" <?php if($record['margin_deviation_approval'] == 'No') echo 'selected'; ?>>No</option>
                </select>
                <div class="invalid-feedback">Please select an option</div>
            </div>
            
            <div class="form-group" id="margin_deviation_reason_field" style="<?php echo ($record['margin_deviation_approval'] == 'Yes') ? 'display: block;' : 'display: none;'; ?>">
                <label for="margin_deviation_reason" class="form-label required">Margin Deviation Reason</label>
                <input type="text" id="margin_deviation_reason" name="margin_deviation_reason" class="form-control" value="<?php echo htmlspecialchars($record['margin_deviation_reason']); ?>" <?php echo ($record['margin_deviation_approval'] == 'Yes') ? 'required' : ''; ?>>
                <div class="invalid-feedback">Please enter the reason for deviation</div>
            </div>
            
            <div class="form-group">
                <label for="ratecard_adherence" class="form-label required">Ratecard Adherence (Yes/No)</label>
                <select id="ratecard_adherence" name="ratecard_adherence" class="form-control form-select" required>
                    <option value="" disabled>Select option</option>
                    <option value="Yes" <?php if($record['ratecard_adherence'] == 'Yes') echo 'selected'; ?>>Yes</option>
                    <option value="No" <?php if($record['ratecard_adherence'] == 'No') echo 'selected'; ?>>No</option>
                </select>
                <div class="invalid-feedback">Please select an option</div>
            </div>
            
            <div class="form-group">
                <label for="ratecard_deviation_approved" class="form-label required">Ratecard Deviation Approved (Yes/No)</label>
                <select id="ratecard_deviation_approved" name="ratecard_deviation_approved" class="form-control form-select" onchange="toggleRatecardDeviationReason()" required>
                    <option value="" disabled>Select option</option>
                    <option value="Yes" <?php if($record['ratecard_deviation_approved'] == 'Yes') echo 'selected'; ?>>Yes</option>
                    <option value="No" <?php if($record['ratecard_deviation_approved'] == 'No') echo 'selected'; ?>>No</option>
                </select>
                <div class="invalid-feedback">Please select an option</div>
            </div>
            
            <div class="form-group" id="ratecard_deviation_reason_field" style="<?php echo ($record['ratecard_deviation_approved'] == 'Yes') ? 'display: block;' : 'display: none;'; ?>">
                <label for="ratecard_deviation_reason" class="form-label required">Ratecard Deviation Reason</label>
                <input type="text" id="ratecard_deviation_reason" name="ratecard_deviation_reason" class="form-control" value="<?php echo htmlspecialchars($record['ratecard_deviation_reason']); ?>" <?php echo ($record['ratecard_deviation_approved'] == 'Yes') ? 'required' : ''; ?>>
                <div class="invalid-feedback">Please enter the reason for deviation</div>
            </div>
            
            <div class="form-group">
                <label for="payment_term" class="form-label required">Payment Term</label>
                <input type="text" id="payment_term" name="payment_term" class="form-control" value="<?php echo htmlspecialchars($record['payment_term']); ?>" required>
                <div class="invalid-feedback">Please enter payment terms</div>
            </div>
            
            <div class="form-group">
                <label for="payment_term_approval" class="form-label required">Payment Term Approval (Yes/No)</label>
                <select id="payment_term_approval" name="payment_term_approval" class="form-control form-select" onchange="togglePaymentTermDeviationReason()" required>
                    <option value="" disabled>Select option</option>
                    <option value="Yes" <?php if($record['payment_term_approval'] == 'Yes') echo 'selected'; ?>>Yes</option>
                    <option value="No" <?php if($record['payment_term_approval'] == 'No') echo 'selected'; ?>>No</option>
                </select>
                <div class="invalid-feedback">Please select an option</div>
            </div>
            
            <div class="form-group" id="payment_term_deviation_reason_field" style="<?php echo ($record['payment_term_approval'] == 'Yes') ? 'display: block;' : 'display: none;'; ?>">
                <label for="payment_term_deviation_reason" class="form-label required">Payment Term Deviation Reason</label>
                <input type="text" id="payment_term_deviation_reason" name="payment_term_deviation_reason" class="form-control" value="<?php echo htmlspecialchars($record['payment_term_deviation_reason']); ?>" <?php echo ($record['payment_term_approval'] == 'Yes') ? 'required' : ''; ?>>
                <div class="invalid-feedback">Please enter the reason for deviation</div>
            </div>

            <!-- Hidden fields for rate values -->
            <input type="hidden" id="client_rate" name="client_rate" value="<?php echo htmlspecialchars($clientRateComponents['rate']); ?>">
            <input type="hidden" id="pay_rate" name="pay_rate" value="<?php echo htmlspecialchars($payRateComponents['rate']); ?>">

            <!-- Hidden fields for display values -->
            <input type="hidden" id="client_rate_display" name="client_rate_display">
            <input type="hidden" id="pay_rate_display" name="pay_rate_display">

            <!-- Existing hidden fields for combined values -->
            <input type="hidden" id="client_rate_combined" name="client_rate_combined" value="<?php echo isset($record['client_rate_combined']) ? htmlspecialchars($record['client_rate_combined']) : ''; ?>">
            <input type="hidden" id="pay_rate_combined" name="pay_rate_combined" value="<?php echo isset($record['pay_rate_combined']) ? htmlspecialchars($record['pay_rate_combined']) : ''; ?>">
        </div>
    </div>
                <!-- Status Tab -->
                <div class="tab-content" id="status-tab">
                    <div class="form-section-title">
                        <i class="fas fa-tasks"></i> Status Management
                    </div>
                    
                    <div class="status-container">
                        <div class="form-group">
                            <label for="status" class="form-label">Current Status</label>
                            <select id="status" name="status" class="form-control form-select">
                                <option value="paperwork_created" <?php if($record['status'] == 'paperwork_created') echo 'selected'; ?>>Paperwork Created</option>
                                <option value="initiated_agreement_bgv" <?php if($record['status'] == 'initiated_agreement_bgv') echo 'selected'; ?>>Initiated – Agreement, BGV</option>
                                <option value="paperwork_closed" <?php if($record['status'] == 'paperwork_closed') echo 'selected'; ?>>Paperwork Closed</option>
                                <option value="started" <?php if($record['status'] == 'started') echo 'selected'; ?>>Started</option>
                                <option value="client_hold" <?php if($record['status'] == 'client_hold') echo 'selected'; ?>>Client – Hold</option>
                                <option value="client_dropped" <?php if($record['status'] == 'client_dropped') echo 'selected'; ?>>Client – Dropped</option>
                                <option value="backout" <?php if($record['status'] == 'backout') echo 'selected'; ?>>Backout</option>
                            </select>
                        </div>
                        
                        <div class="form-group status-reason-container" id="status_reason_container">
                            <label for="status_reason" class="form-label">Reason for Status Change</label>
                            <textarea id="status_reason" name="status_reason" class="form-control" rows="3"></textarea>
                            <small class="text-muted">Required when changing status to "Client – Hold", "Client – Dropped", or "Backout".</small>
                        </div>
                    </div>
                    
                    <div class="form-section-title">
                        <i class="fas fa-history"></i> Status History
                    </div>
                    
                    <div class="timeline">
                    <?php if (count($historyRecords) > 0): ?>
                        <?php foreach ($historyRecords as $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($history['modified_date'])); ?></div>
                            <div class="timeline-content">
                                <span class="timeline-status">
                                    <?php echo htmlspecialchars($history['modification_details']); ?>
                                </span>
                                <?php if (!empty($history['old_value']) && !empty($history['new_value'])): ?>
                                <p>Changed from: <?php echo htmlspecialchars($history['old_value']); ?></p>
                                <p>Changed to: <?php echo htmlspecialchars($history['new_value']); ?></p>
                                <?php endif; ?>
                                <div class="timeline-user">Updated by: <?php echo htmlspecialchars($history['modified_by']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No status history available.</p>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- PLC Tab -->
                <div class="tab-content" id="plc-tab">
                    <div class="form-section-title">
                        <i class="fas fa-code"></i> PLC Code Management
                    </div>
                    
                    <div class="plc-container">
                        <?php if (!empty($plcCode)): ?>
                        <div class="plc-current">
                            <h3 class="plc-title">Current PLC Code</h3>
                            <div class="plc-code"><?php echo htmlspecialchars($plcCode); ?></div>
                            <div class="plc-meta">
                                Last updated: <?php echo !empty($plcLastUpdated) ? date('M d, Y h:i A', strtotime($plcLastUpdated)) : 'N/A'; ?> by <?php echo htmlspecialchars($plcUpdatedBy); ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="plc-current">
                            <h3 class="plc-title">No PLC Code</h3>
                            <p>No PLC code has been assigned to this record yet.</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="plc-form">
                            <label for="plc_code" class="form-label">Update PLC Code</label>
                            <textarea id="plc_code" name="plc_code" class="plc-textarea"><?php echo htmlspecialchars($plcCode); ?></textarea>
                            <small class="text-muted">Enter the new PLC code to update the record.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="paperworkallrecords.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>
        
        <!-- Unsaved Changes Warning -->
        <div class="unsaved-warning" id="unsaved-warning">
            <i class="fas fa-exclamation-triangle"></i> You have unsaved changes! Save before leaving this page.
        </div>
    </div>

    
    
    <!-- Form handling script -->
    <!-- -->

<script>
// Make submitForm globally available
window.submitForm = function() {
    console.log('Submit form called');

    // Update rate values before submission
    updateRateValues();

    // Validate form
    if (validateForm()) {
        console.log('Form is valid, preparing to submit');

        // Reset formChanged to prevent warning
        window.formChanged = false;
        document.getElementById('unsaved-warning').style.display = 'none';

        // Update final candidate source
        updateFinalCandidateSource();

        // Show loading state on buttons
        document.getElementById('submit-btn').innerHTML = '<div class="spinner"></div> Saving...';
        document.getElementById('submit-btn').disabled = true;
        document.getElementById('save-btn').innerHTML = '<div class="spinner"></div> Saving...';
        document.getElementById('save-btn').disabled = true;

        // Submit the form
        document.getElementById('edit-form').submit();
    } else {
        console.log('Form validation failed');
        
        // Scroll to first error
        const firstError = document.querySelector('.is-invalid');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize global variables
    window.formChanged = false;
    const unsavedWarning = document.getElementById('unsaved-warning');
    const form = document.getElementById('edit-form');
    
    // Tab switching functionality
    const tabItems = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabItems.forEach(item => {
        item.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabItems.forEach(tab => tab.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
    
    // Function to update rate values and create combined rate strings
    window.updateRateValues = function() {
        const placementType = document.getElementById('placement_type').value;
        const clientRateInput = document.getElementById('clientrate');
        const payRateInput = document.getElementById('payrate');
        
        // Ensure numeric fields have valid values
        if (clientRateInput.value === '' || isNaN(parseFloat(clientRateInput.value))) {
            clientRateInput.value = '0';
        }
        
        if (payRateInput.value === '' || isNaN(parseFloat(payRateInput.value))) {
            payRateInput.value = '0';
        }
        
        const clientRateValue = clientRateInput.value;
        const payRateValue = payRateInput.value;
        const billCurrency = document.getElementById('bill_currency').value;
        const payCurrency = document.getElementById('pay_currency').value;
        const billType = document.getElementById('bill_type').value;
        const payType = document.getElementById('pay_type').value;
        
        let unitLabel;
        switch(placementType) {
            case 'Hourly': unitLabel = '/hour'; break;
            case 'Monthly': unitLabel = '/month'; break;
            case 'Daily': unitLabel = '/day'; break;
            case 'Weekly': unitLabel = '/week'; break;
            case 'Bi-Weekly': unitLabel = '/bi-week'; break;
            case 'Semi-Monthly': unitLabel = '/semi-month'; break;
            case 'Per Annum': unitLabel = '/year'; break;
            default: unitLabel = '/hour'; break;
        }
        
        // Update rate unit displays
        document.querySelectorAll('.rate-unit').forEach(span => {
            span.textContent = unitLabel;
        });
        
        // Create combined rate strings
        const clientRateCombined = billType ? 
            `${clientRateValue} ${billCurrency} ${unitLabel} on ${billType}` : '';
        const payRateCombined = payType ? 
            `${payRateValue} ${payCurrency} ${unitLabel} on ${payType}` : '';
        
        // Set hidden field values
        document.getElementById('client_rate_combined').value = clientRateCombined;
        document.getElementById('pay_rate_combined').value = payRateCombined;
    };

    // Function to update units when placement type changes
    window.updateRateUnits = function() {
        window.updateRateValues();
    };
    
    // Validation function
    window.validateForm = function() {
        let isValid = true;
        
        // Reset all validation states
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.classList.remove('is-invalid');
        });
        
        // Handle numeric fields first
        const clientrateField = document.getElementById('clientrate');
        const payrateField = document.getElementById('payrate');
        
        if (clientrateField.value === '' || isNaN(parseFloat(clientrateField.value))) {
            clientrateField.value = '0';
        }
        
        if (payrateField.value === '' || isNaN(parseFloat(payrateField.value))) {
            payrateField.value = '0';
        }
        
        // Validate required fields that are visible
        const requiredFields = document.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            // Check if the field is visible
            const isVisible = window.isFieldVisible(field);
            
            if (isVisible && field.value.trim() === '') {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        return isValid;
    };
    
    // Check if a field is visible
    window.isFieldVisible = function(field) {
        if (field.style.display === 'none') {
            return false;
        }
        
        let parent = field.parentElement;
        while (parent && !parent.classList.contains('form-grid')) {
            if (parent.style.display === 'none') {
                return false;
            }
            parent = parent.parentElement;
        }
        
        return true;
    };
    
    // Update the final candidate source hidden field
    window.updateFinalCandidateSource = function() {
        const candidateSourceField = document.getElementById('candidate_source');
        const hiddenField = document.getElementById('final_candidate_source_hidden');
        const sourceValue = candidateSourceField.value;
        
        const sharedOption = document.getElementById('shared_option');
        const sourcedPerson = document.getElementById('sourced_person');
        
        if (sourceValue === 'Sourcing' && sourcedPerson.value) {
            hiddenField.value = sourceValue + ' - ' + sourcedPerson.value;
        }
        else if (['CX Bench', 'LinkedIn RPS', 'SRM', 'LinkedIn Sourcer'].includes(sourceValue) && sharedOption.value) {
            hiddenField.value = sourceValue + ' - ' + sharedOption.value;
        }
        else {
            hiddenField.value = sourceValue;
        }
    };
    
    // Toggle functions for deviation fields
    window.toggleMarginDeviationReason = function() {
        const approval = document.getElementById('margin_deviation_approval').value;
        const reasonField = document.getElementById('margin_deviation_reason_field');
        const reasonInput = document.getElementById('margin_deviation_reason');
        
        if (approval === 'Yes') {
            reasonField.style.display = 'block';
            reasonInput.required = true;
        } else {
            reasonField.style.display = 'none';
            reasonInput.required = false;
            reasonInput.value = '';
        }
    };

    window.toggleRatecardDeviationReason = function() {
        const approval = document.getElementById('ratecard_deviation_approved').value;
        const reasonField = document.getElementById('ratecard_deviation_reason_field');
        const reasonInput = document.getElementById('ratecard_deviation_reason');
        
        if (approval === 'Yes') {
            reasonField.style.display = 'block';
            reasonInput.required = true;
        } else {
            reasonField.style.display = 'none';
            reasonInput.required = false;
            reasonInput.value = '';
        }
    };

    window.togglePaymentTermDeviationReason = function() {
        const approval = document.getElementById('payment_term_approval').value;
        const reasonField = document.getElementById('payment_term_deviation_reason_field');
        const reasonInput = document.getElementById('payment_term_deviation_reason');
        
        if (approval === 'Yes') {
            reasonField.style.display = 'block';
            reasonInput.required = true;
        } else {
            reasonField.style.display = 'none';
            reasonInput.required = false;
            reasonInput.value = '';
        }
    };
    
    // Helper function to populate shared options
    function populateSharedOptions(options) {
        const sharedOption = document.getElementById('shared_option');
        sharedOption.innerHTML = '<option value="" disabled selected>Select Option</option>';
        options.forEach(option => {
            sharedOption.innerHTML += `<option value="${option}">${option}</option>`;
        });
    }
    
    // Initialize dependent fields
    function initializeDependentFields() {
        const workStatusField = document.getElementById('cwork_authorization_status');
        const vValidateField = document.getElementById('v_validate_status_field');
        const vValidateSelect = document.getElementById('cv_validate_status');
        
        if (workStatusField.value === 'h1b') {
            vValidateField.style.display = 'block';
            vValidateSelect.required = true;
        }
        
        const hasCertificationsField = document.getElementById('has_certifications');
        const certificationsField = document.getElementById('certifications_field');
        const certificationsInput = document.getElementById('ccertifications');
        
        if (hasCertificationsField.value === 'yes') {
            certificationsField.style.display = 'block';
            certificationsInput.required = true;
        }
        
        // Initialize candidate source fields
        const candidateSourceField = document.getElementById('candidate_source');
        const sharedField = document.getElementById('shared_field');
        const sharedLabel = document.getElementById('shared_label');
        const sharedOption = document.getElementById('shared_option');
        const sourcedPersonField = document.getElementById('sourced_person_field');
        const sourcedPerson = document.getElementById('sourced_person');
        
        const currentSource = candidateSourceField.value;
        if (currentSource === 'Sourcing') {
            sourcedPersonField.style.display = 'block';
            sourcedPerson.required = true;
        } 
        else if (['CX Bench', 'LinkedIn RPS', 'SRM', 'LinkedIn Sourcer'].includes(currentSource)) {
            sharedField.style.display = 'block';
            sharedOption.required = true;
            
            // Set the label text
            sharedLabel.innerHTML = `${currentSource} Options`;
            
            // Determine which options to show
            let options = [];
            if (currentSource === 'CX Bench') {
                options = ['Sushmitha S', 'Swarnabharathi M U'];
            } else if (currentSource === 'LinkedIn RPS') {
                options = ['Balaji Kumar', 'Balaji Mohan'];
            } else if (currentSource === 'SRM') {
                options = ['Harish Babu M'];
            } else if (currentSource === 'LinkedIn Sourcer') {
                options = ['Karthik T'];
            }
            
            // Re-populate with the current options
            populateSharedOptions(options);
        }
        
        // Employer own corporation
        const employerOwnCorporation = document.getElementById('cemployer_own_corporation');
        const employerFields = document.querySelectorAll('.employer-field');
        
        if (employerOwnCorporation.value === 'NA') {
            employerFields.forEach(field => {
                field.style.display = 'none';
                const input = field.querySelector('input, select');
                if (input) {
                    input.required = false;
                }
            });
        }
        
        // Employer type for additional details
        const employerType = document.getElementById('employer_type');
        const employerFields2 = [
            document.getElementById('employer_corporation_name_field'),
            document.getElementById('fed_id_number_field'),
            document.getElementById('contact_person_name_field')
        ];
        
        if (employerType.value === 'NA') {
            employerFields2.forEach(field => {
                if (field) {
                    field.style.display = 'none';
                    const input = field.querySelector('input, select');
                    if (input) {
                        input.required = false;
                    }
                }
            });
        }
        
        // Collaboration
        const collaborationField = document.getElementById('collaboration_collaborate');
        const deliveryManagerField = document.getElementById('delivery_manager_field');
        const deliveryAccountLeadField = document.getElementById('delivery_account_lead_field');
        const teamLeadField = document.getElementById('team_lead_field');
        const associateTeamLeadField = document.getElementById('associate_team_lead_field');
        
        if (collaborationField.value !== 'yes') {
            deliveryManagerField.style.display = 'none';
            deliveryAccountLeadField.style.display = 'none';
            teamLeadField.style.display = 'none';
            associateTeamLeadField.style.display = 'none';
        }
        
        // Status reason
        const statusField = document.getElementById('status');
        const statusReasonContainer = document.getElementById('status_reason_container');
        
        if (['client_hold', 'client_dropped', 'backout'].includes(statusField.value)) {
            statusReasonContainer.style.display = 'block';
        }
        
        // Deviation reasons
        const marginDeviationApproval = document.getElementById('margin_deviation_approval');
        const marginDeviationReasonField = document.getElementById('margin_deviation_reason_field');
        
        if (marginDeviationApproval.value === 'Yes') {
            marginDeviationReasonField.style.display = 'block';
        }
        
        const ratecardDeviationApproval = document.getElementById('ratecard_deviation_approved');
        const ratecardDeviationReasonField = document.getElementById('ratecard_deviation_reason_field');
        
        if (ratecardDeviationApproval.value === 'Yes') {
            ratecardDeviationReasonField.style.display = 'block';
        }
        
        const paymentTermApproval = document.getElementById('payment_term_approval');
        const paymentTermDeviationReasonField = document.getElementById('payment_term_deviation_reason_field');
        
        if (paymentTermApproval.value === 'Yes') {
            paymentTermDeviationReasonField.style.display = 'block';
        }
    }
    
    // Add event listeners
    
    // Save button
    document.getElementById('save-btn').addEventListener('click', function(e) {
        e.preventDefault();
        window.submitForm();
    });
    
    // Form submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        window.submitForm();
    });
    
    // Event listeners for dependent fields
    
    // 1. Work Authorization Status changes
    const workStatusField = document.getElementById('cwork_authorization_status');
    const vValidateField = document.getElementById('v_validate_status_field');
    const vValidateSelect = document.getElementById('cv_validate_status');
    
    workStatusField.addEventListener('change', function() {
        if (this.value === 'h1b') {
            vValidateField.style.display = 'block';
            vValidateSelect.required = true;
        } else {
            vValidateField.style.display = 'none';
            vValidateSelect.required = false;
            vValidateSelect.value = 'NA';
        }
    });
    
    // 2. Has Certifications changes
    const hasCertificationsField = document.getElementById('has_certifications');
    const certificationsField = document.getElementById('certifications_field');
    const certificationsInput = document.getElementById('ccertifications');
    
    hasCertificationsField.addEventListener('change', function() {
        if (this.value === 'yes') {
            certificationsField.style.display = 'block';
            certificationsInput.required = true;
        } else {
            certificationsField.style.display = 'none';
            certificationsInput.required = false;
            certificationsInput.value = 'NA';
        }
    });
    
    // 3. Candidate Source changes
    const candidateSourceField = document.getElementById('candidate_source');
    const sharedField = document.getElementById('shared_field');
    const sharedLabel = document.getElementById('shared_label');
    const sharedOption = document.getElementById('shared_option');
    const sourcedPersonField = document.getElementById('sourced_person_field');
    const sourcedPerson = document.getElementById('sourced_person');
    
    candidateSourceField.addEventListener('change', function() {
        // Reset both fields first
        sharedField.style.display = 'none';
        sharedOption.required = false;
        sourcedPersonField.style.display = 'none';
        sourcedPerson.required = false;
        
        // Update based on selected value
        if (this.value === 'CX Bench') {
            sharedLabel.innerHTML = 'CX Bench Options';
            populateSharedOptions(['Sushmitha S', 'Swarnabharathi M U']);
            sharedField.style.display = 'block';
            sharedOption.required = true;
        } 
        else if (this.value === 'LinkedIn RPS') {
            sharedLabel.innerHTML = 'LinkedIn RPS Options';
            populateSharedOptions(['Balaji Kumar', 'Balaji Mohan']);
            sharedField.style.display = 'block';
            sharedOption.required = true;
        }
        else if (this.value === 'SRM') {
            sharedLabel.innerHTML = 'SRM Options';
            populateSharedOptions(['Harish Babu M']);
            sharedField.style.display = 'block';
            sharedOption.required = true;
        }
        else if (this.value === 'LinkedIn Sourcer') {
            sharedLabel.innerHTML = 'LinkedIn Sourcer Options';
            populateSharedOptions(['Karthik T']);
            sharedField.style.display = 'block';
            sharedOption.required = true;
        }
        else if (this.value === 'Sourcing') {
            sourcedPersonField.style.display = 'block';
            sourcedPerson.required = true;
        }
        
        // Update the final candidate source value
        updateFinalCandidateSource();
    });
    
    // Listeners for updating the final candidate source
    sharedOption.addEventListener('change', window.updateFinalCandidateSource);
    sourcedPerson.addEventListener('change', window.updateFinalCandidateSource);
    
    // 4. Employer type changes
    const employerOwnCorporation = document.getElementById('cemployer_own_corporation');
    const employerFields = document.querySelectorAll('.employer-field');
    
    employerOwnCorporation.addEventListener('change', function() {
        const isNA = this.value === 'NA';
        
        employerFields.forEach(field => {
            const input = field.querySelector('input, select');
            if (isNA) {
                field.style.display = 'none';
                if (input) {
                    input.required = false;
                    if (input.name.includes('phone') || input.name.includes('extension')) {
                        input.value = '00000';
                    } else {
                        input.value = 'NA';
                    }
                }
            } else {
                field.style.display = 'block';
                if (input) {
                    input.required = true;
                }
            }
        });
    });
    
    // 5. Employer type for additional details
    const employerType = document.getElementById('employer_type');
    const employerFields2 = [
        document.getElementById('employer_corporation_name_field'),
        document.getElementById('fed_id_number_field'),
        document.getElementById('contact_person_name_field')
    ];
    
    employerType.addEventListener('change', function() {
        const isNA = this.value === 'NA';
        
        employerFields2.forEach(field => {
            if (field) {
                const input = field.querySelector('input, select');
                if (isNA) {
                    field.style.display = 'none';
                    if (input) {
                        input.required = false;
                        if (input.name.includes('phone') || input.name.includes('extension')) {
                            input.value = '00000';
                        } else {
                            input.value = 'NA';
                        }
                    }
                } else {
                    field.style.display = 'block';
                    if (input) {
                        input.required = true;
                    }
                }
            }
        });
    });
    
    // 6. Collaboration changes
    const collaborationField = document.getElementById('collaboration_collaborate');
    const deliveryManagerField = document.getElementById('delivery_manager_field');
    const deliveryAccountLeadField = document.getElementById('delivery_account_lead_field');
    const teamLeadField = document.getElementById('team_lead_field');
    const associateTeamLeadField = document.getElementById('associate_team_lead_field');
    
    const deliveryManager = document.getElementById('delivery_manager');
    const deliveryAccountLead = document.getElementById('delivery_account_lead');
    const teamLead = document.getElementById('team_lead');
    const associateTeamLead = document.getElementById('associate_team_lead');
    
    collaborationField.addEventListener('change', function() {
        if (this.value === 'yes') {
            deliveryManagerField.style.display = 'block';
            deliveryAccountLeadField.style.display = 'block';
            teamLeadField.style.display = 'block';
            associateTeamLeadField.style.display = 'block';
            
            deliveryManager.required = true;
            deliveryAccountLead.required = true;
            teamLead.required = true;
            associateTeamLead.required = true;
        } else {
            deliveryManagerField.style.display = 'none';
            deliveryAccountLeadField.style.display = 'none';
            teamLeadField.style.display = 'none';
            associateTeamLeadField.style.display = 'none';
            
            deliveryManager.required = false;
            deliveryAccountLead.required = false;
            teamLead.required = false;
            associateTeamLead.required = false;
            
            deliveryManager.value = 'NA';
            deliveryAccountLead.value = 'NA';
            teamLead.value = 'NA';
            associateTeamLead.value = 'NA';
        }
    });
    
    // 7. Status change handling
    const statusField = document.getElementById('status');
    const statusReasonContainer = document.getElementById('status_reason_container');
    const statusReason = document.getElementById('status_reason');
    
    statusField.addEventListener('change', function() {
        if (['client_hold', 'client_dropped', 'backout'].includes(this.value)) {
            statusReasonContainer.style.display = 'block';
            statusReason.required = true;
        } else {
            statusReasonContainer.style.display = 'none';
            statusReason.required = false;
            statusReason.value = '';
        }
    });
    
    // Rate value change listeners
    const rateUpdateTriggers = [
        'placement_type', 'bill_type', 'pay_type', 
        'clientrate', 'payrate', 'bill_currency', 'pay_currency'
    ];
    
    rateUpdateTriggers.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', window.updateRateValues);
            element.addEventListener('input', window.updateRateValues);
        }
    });
    
    // Track unsaved changes
    const formElements = form.querySelectorAll('input, select, textarea');
    formElements.forEach(element => {
        element.addEventListener('change', function() {
            window.formChanged = true;
            unsavedWarning.style.display = 'block';
        });
        
        if (element.tagName === 'TEXTAREA' || element.type === 'text' || element.type === 'email') {
            element.addEventListener('keyup', function() {
                window.formChanged = true;
                unsavedWarning.style.display = 'block';
            });
        }
    });
    
    // Warn user before leaving page with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (window.formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Handle missing sidebar.js
    var sidebarScript = document.createElement('script');
    sidebarScript.src = 'sidebar.js';
    sidebarScript.onerror = function() {
        console.log('Sidebar.js not found - continuing without it');
    };
    document.body.appendChild(sidebarScript);
    
    // Initialize form state
    initializeDependentFields();
    window.updateRateValues();
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    // Get the status dropdown element
    const statusDropdown = document.getElementById('status');
    if (statusDropdown) {
        const originalStatus = statusDropdown.value;
        
        // Add event listener for change
        statusDropdown.addEventListener('change', function() {
            const recordId = <?php echo $recordId; ?>; // This gets the record ID from PHP
            const newStatus = this.value; // New selected status
            
            // If status actually changed
            if (originalStatus !== newStatus) {
                // Log the status change after the dropdown change
                fetch('log_status_change.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `record_id=${recordId}&old_status=${originalStatus}&new_status=${newStatus}`
                })
                .then(response => response.json())
                .catch(error => {
                    console.error('Error logging status change:', error);
                });
            }
        });
    }
});
</script>

<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main');
    
    if (window.innerWidth <= 1200) {
        sidebar.classList.toggle('open');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main');
    
    if (window.innerWidth > 1200) {
        sidebar.classList.remove('open');
        if (sidebar.classList.contains('collapsed')) {
            mainContent.classList.add('expanded');
        } else {
            mainContent.classList.remove('expanded');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
    }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle-btn');
    
    if (window.innerWidth <= 1200 && 
        sidebar.classList.contains('open') && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});
</script>

</body>
</html>