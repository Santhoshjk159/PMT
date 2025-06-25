<?php
session_start();
require 'db.php';

if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

if (isset($_GET)) {
    error_log("GET parameters received: " . print_r($_GET, true));
}

// Get logged-in user's email, ID, role, and userwithempid
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT id, role, userwithempid, name AS full_name FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$userId = $userData['id'];
$userRole = $userData['role'];
$userWithEmpId = $userData['userwithempid'];
$userName = $userData['full_name'];

// Determine if the user is an admin
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');

// Set up basic filters and pagination
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$submittedBy = $_GET['submitted_by'] ?? '';
$status = $_GET['status'] ?? '';
$recordsPerPage = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Set up advanced filters
$client = $_GET['client'] ?? '';
$jobTitle = $_GET['job_title'] ?? '';
$businessUnit = $_GET['business_unit'] ?? '';
$deliveryManager = $_GET['delivery_manager'] ?? '';
$accountLead = $_GET['account_lead'] ?? '';
$location = $_GET['location'] ?? '';
$dateType = $_GET['date_type'] ?? 'created';
$sortBy = $_GET['sort_by'] ?? 'created_desc';

$offset = ($page - 1) * $recordsPerPage;

// Define the columns to match with userwithempid in paperworkdetails
$columnsToCheck = [
    "delivery_manager", "delivery_account_lead", "team_lead",
    "associate_team_lead", "business_unit", "client_account_lead",
    "associate_director_delivery", "delivery_manager1", "delivery_account_lead1",
    "team_lead1", "associate_team_lead1", "recruiter_name", "pt_support", "pt_ownership"
];
$columnConditions = [];

if ($userWithEmpId) {
    foreach ($columnsToCheck as $column) {
        $columnConditions[] = "$column = ?";
    }
}

// Count the total records after filters are applied
$sqlCount = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE 1";
$paramsCount = [];
$bindTypesCount = '';

// For non-admins, apply access control based on userwithempid or email
if (!$isAdmin) {
    if ($userWithEmpId) {
        $sqlCount .= " AND (" . implode(" OR ", $columnConditions) . ")";
        foreach ($columnConditions as $condition) {
            $paramsCount[] = $userWithEmpId;
            $bindTypesCount .= 's';
        }
    } else {
        $sqlCount .= " AND submittedby = ?";
        $paramsCount[] = $userEmail;
        $bindTypesCount .= 's';
    }
}

// Apply filters to count query
if (!empty($search)) {
    $sqlCount .= " AND (cfirstname LIKE ? OR clastname LIKE ? OR cemail LIKE ? OR id LIKE ?)";
    $searchParam = "%$search%";
    $paramsCount = array_merge($paramsCount, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $bindTypesCount .= 'ssss';
}

if (!empty($startDate) && !empty($endDate)) {
    $sqlCount .= " AND DATE(created_at) BETWEEN ? AND ?";
    $paramsCount = array_merge($paramsCount, [$startDate, $endDate]);
    $bindTypesCount .= 'ss';
}

if (!empty($submittedBy)) {
    $sqlCount .= " AND submittedby LIKE ?";
    $paramsCount[] = "%$submittedBy%";
    $bindTypesCount .= 's';
}

if (!empty($status)) {
    $sqlCount .= " AND status = ?";
    $paramsCount[] = $status;
    $bindTypesCount .= 's';
}

// Advanced filters for COUNT query
if (!empty($client)) {
    $sqlCount .= " AND client LIKE ?";
    $paramsCount[] = "%$client%";
    $bindTypesCount .= 's';
}

if (!empty($jobTitle)) {
    $sqlCount .= " AND job_title LIKE ?";
    $paramsCount[] = "%$jobTitle%";
    $bindTypesCount .= 's';
}

if (!empty($businessUnit)) {
    $sqlCount .= " AND business_unit = ?";
    $paramsCount[] = $businessUnit;
    $bindTypesCount .= 's';
}

if (!empty($deliveryManager)) {
    $sqlCount .= " AND (delivery_manager = ? OR delivery_manager1 = ?)";
    $paramsCount[] = $deliveryManager;
    $paramsCount[] = $deliveryManager;
    $bindTypesCount .= 'ss';
}

if (!empty($accountLead)) {
    $sqlCount .= " AND (client_account_lead = ?)";
    $paramsCount[] = $accountLead;
    $bindTypesCount .= 's';
}

if (!empty($location)) {
    $sqlCount .= " AND (location LIKE ? OR clocation LIKE ?)";
    $paramsCount[] = "%$location%";
    $paramsCount[] = "%$location%";
    $bindTypesCount .= 'ss';
}

// Execute the count query
$stmtCount = $conn->prepare($sqlCount);
if (!empty($paramsCount)) {
    $stmtCount->bind_param($bindTypesCount, ...$paramsCount);
}
$stmtCount->execute();
$totalFilteredRecords = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalFilteredRecords / $recordsPerPage);

if ($totalPages < 1) {
    $totalPages = 1;
}

// If current page is beyond available pages, reset to page 1
if ($page > $totalPages) {
    $page = 1;
    $offset = 0;
}

// Main query for fetching records
$sql = "SELECT p.*, u.name as submitter_name,
        CASE 
            WHEN p.submittedby = ? THEN 'Own Record'
            ELSE CONCAT('Team Member: ', u.name)
        END as record_type
        FROM paperworkdetails p
        LEFT JOIN users u ON u.email = p.submittedby
        WHERE 1";

$params = [$userEmail];
$bindTypes = 's';

// Define the orderByClause based on sortBy parameter
$orderByClause = "";
switch ($sortBy) {
    case 'created_asc':
        $orderByClause = " ORDER BY p.created_at ASC";
        break;
    case 'updated_desc':
        $orderByClause = " ORDER BY p.updated_at DESC";
        break;
    case 'updated_asc':
        $orderByClause = " ORDER BY p.updated_at ASC";
        break;
    case 'name_asc':
        $orderByClause = " ORDER BY p.cfirstname ASC, p.clastname ASC";
        break;
    case 'name_desc':
        $orderByClause = " ORDER BY p.cfirstname DESC, p.clastname DESC";
        break;
    case 'created_desc':
    default:
        $orderByClause = " ORDER BY p.created_at DESC";
        break;
}

// Apply filtering based on userwithempid for non-admins
if (!$isAdmin) {
    if ($userWithEmpId) {
        $sql .= " AND (" . implode(" OR ", $columnConditions) . ")";
        foreach ($columnConditions as $condition) {
            $params[] = $userWithEmpId;
            $bindTypes .= 's';
        }
    } else {
        $sql .= " AND p.submittedby = ?";
        $params[] = $userEmail;
        $bindTypes .= 's';
    }
}

// Apply ALL filters to main query
if (!empty($search)) {
    $sql .= " AND (p.cfirstname LIKE ? OR p.clastname LIKE ? OR p.cemail LIKE ? OR p.id LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $bindTypes .= 'ssss';
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
    $params = array_merge($params, [$startDate, $endDate]);
    $bindTypes .= 'ss';
}

if (!empty($submittedBy)) {
    $sql .= " AND p.submittedby LIKE ?";
    $params[] = "%$submittedBy%";
    $bindTypes .= 's';
}

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
    $bindTypes .= 's';
}

// Advanced filters for MAIN query
if (!empty($client)) {
    $sql .= " AND p.client LIKE ?";
    $params[] = "%$client%";
    $bindTypes .= 's';
}

if (!empty($jobTitle)) {
    $sql .= " AND p.job_title LIKE ?";
    $params[] = "%$jobTitle%";
    $bindTypes .= 's';
}

if (!empty($businessUnit)) {
    $sql .= " AND p.business_unit = ?";
    $params[] = $businessUnit;
    $bindTypes .= 's';
}

if (!empty($deliveryManager)) {
    $sql .= " AND (p.delivery_manager = ? OR p.delivery_manager1 = ?)";
    $params[] = $deliveryManager;
    $params[] = $deliveryManager;
    $bindTypes .= 'ss';
}

if (!empty($accountLead)) {
    $sql .= " AND (p.client_account_lead = ?)";
    $params[] = $accountLead;
    $bindTypes .= 's';
}

if (!empty($location)) {
    $sql .= " AND (p.location LIKE ? OR p.clocation LIKE ?)";
    $params[] = "%$location%";
    $params[] = "%$location%";
    $bindTypes .= 'ss';
}

// Add pagination
$sql .= $orderByClause . " LIMIT ? OFFSET ?";
$params = array_merge($params, [$recordsPerPage, $offset]);
$bindTypes .= 'ii';

// Execute main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($bindTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Store all records in an array
$allRecords = [];
while ($row = $result->fetch_assoc()) {
    $allRecords[] = $row;
}
$recordCount = count($allRecords);

// Function to fetch PLC code for a paperwork
function getPLCCode($conn, $paperworkId) {
    $query = "SELECT plc_code, updated_at, updated_by FROM plc_codes WHERE paperwork_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $paperworkId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDart PMT - Paperwork Records</title>
    <link rel="icon" href="images.png" type="image/png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    
    
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        :root {
            --primary-grey: #6c757d;
            --secondary-blue: #007bff;
            --dark-grey: #495057;
            --light-grey: #f8f9fa;
            --medium-grey: #dee2e6;
            --hover-blue: #0056b3;
            --success-green: #28a745;
            --warning-orange: #fd7e14;
            --danger-red: #dc3545;
            --info-blue: #17a2b8;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark-grey);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Main Content Layout */
        .main {
    margin-left: 280px;
    padding: 24px;
    transition: var(--transition);
    min-height: 100vh;
}

.main.expanded {
    margin-left: 0;
}

        .sidebar.collapsed + .main {
            margin-left: 105px;
        }

        /* Topbar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0 32px 0;
            margin-bottom: 32px;
            border-bottom: 2px solid var(--medium-grey);
        }

        .dashboard-heading {
            color: var(--dark-grey);
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            position: relative;
        }

        .dashboard-heading::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--secondary-blue);
        }

        /* Toast Notifications */
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            width: 350px;
            padding: 16px 20px;
            color: white;
            background-color: var(--success-green);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--hover-shadow);
            animation: slide-in 0.3s ease, fade-out 0.5s ease 2.5s forwards;
            overflow: hidden;
        }

        .toast i {
            margin-right: 12px;
            font-size: 18px;
        }

        .toast-content {
            flex-grow: 1;
            font-weight: 500;
        }

        @keyframes slide-in {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fade-out {
            from { opacity: 1; }
            to { opacity: 0; transform: translateY(-20px); }
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--white);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
            position: relative;
            border-left: 4px solid var(--secondary-blue);
        }

        .filter-title {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 24px;
        }

        .filter-title i {
            margin-right: 12px;
            color: var(--secondary-blue);
            font-size: 1.3rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-grey);
            transition: var(--transition);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .filter-input:hover {
            border-color: var(--primary-grey);
        }

        /* Select Dropdowns */
        select.filter-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            min-width: 120px;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--secondary-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--hover-blue);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-secondary {
            background: var(--primary-grey);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--dark-grey);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-success {
            background: var(--success-green);
            color: var(--white);
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-danger {
            background: var(--danger-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-info {
            background: var(--info-blue);
            color: var(--white);
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        /* Toggle Filter Button */
        .toggle-filters-btn {
            width: 100%;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            border: 2px solid var(--medium-grey);
            padding: 16px;
            color: var(--secondary-blue);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .toggle-filters-btn:hover {
            background: var(--light-grey);
            border-color: var(--secondary-blue);
        }

        .toggle-filters-btn i {
            margin-right: 10px;
            transition: transform 0.3s ease;
        }

        .toggle-filters-btn.expanded i {
            transform: rotate(180deg);
        }

        /* Advanced Filters */
        .advanced-filters {
            display: none;
        }

        /* Records Panel */
        .records-panel {
            background: var(--white);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .records-header {
            background: var(--light-grey);
            padding: 24px 32px;
            border-bottom: 2px solid var(--medium-grey);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .records-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-grey);
            display: flex;
            align-items: center;
        }

        .records-count {
            background: var(--secondary-blue);
            color: var(--white);
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: 12px;
            letter-spacing: 0.5px;
        }

        .bulk-actions {
            display: none;
            gap: 12px;
        }

        /* Select Controls */
        .select-controls {
            padding: 20px 32px;
            background: var(--light-grey);
            border-bottom: 1px solid var(--medium-grey);
            display: flex;
            align-items: center;
        }

        .select-checkbox {
            transform: scale(1.3);
            margin-right: 12px;
            accent-color: var(--secondary-blue);
        }

        .select-label {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-grey);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--dark-grey);
            color: var(--white);
            padding: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid var(--medium-grey);
        }

        .table td {
            padding: 16px;
            font-size: 0.95rem;
            color: var(--dark-grey);
            border-bottom: 1px solid var(--light-grey);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: var(--light-grey);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Expand/Collapse Button */
        .expand-row-btn {
            width: 36px;
            height: 36px;
            background: none;
            border: 2px solid var(--medium-grey);
            color: var(--primary-grey);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .expand-row-btn:hover {
            background: var(--secondary-blue);
            color: var(--white);
            border-color: var(--secondary-blue);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--white);
        }

        .status-paperwork_created { background: var(--secondary-blue); }
        .status-initiated_agreement_bgv { background: var(--info-blue); }
        .status-paperwork_closed { background: var(--success-green); }
        .status-started { background: var(--success-green); }
        .status-client_hold { background: var(--warning-orange); }
        .status-client_dropped { background: var(--danger-red); }
        .status-backout { background: var(--primary-grey); }

        /* Action Buttons */
        .record-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .action-preview { background: var(--secondary-blue); }
        .action-edit { background: var(--info-blue); }
        .action-history { background: var(--primary-grey); }
        .action-export { background: var(--warning-orange); }
        .action-delete { background: var(--danger-red); }

        /* Expanded Row Content */
        .expanded-content {
            display: none;
        }

        .expanded-details {
            background: var(--light-grey);
            padding: 32px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .management-container {
            background: var(--white);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .management-header {
            background: var(--secondary-blue);
            color: var(--white);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .management-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .management-title i {
            margin-right: 12px;
        }

        .paperwork-id {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        /* Tab Navigation */
        .tab-nav {
            background: var(--dark-grey);
            display: flex;
            border-bottom: 2px solid var(--medium-grey);
        }

        .tab-btn {
            padding: 16px 24px;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .tab-btn:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.1);
        }

        .tab-btn.active {
            color: var(--white);
            background: var(--secondary-blue);
        }

        .tab-content {
            display: none;
            padding: 24px;
        }

        .tab-content.active {
            display: block;
        }

        /* Management Grid */
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .management-section {
            background: var(--light-grey);
            padding: 24px;
            border-left: 4px solid var(--secondary-blue);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--medium-grey);
        }

        .section-title i {
            margin-right: 8px;
            color: var(--secondary-blue);
        }

        .current-status {
            margin-bottom: 20px;
        }

        .status-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-grey);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-dropdown {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-grey);
            cursor: pointer;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .status-dropdown:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        /* PLC Code Section */
        .plc-display {
            background: var(--white);
            border: 1px solid var(--medium-grey);
            padding: 16px;
            margin: 16px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .plc-meta {
            font-size: 0.8rem;
            color: var(--primary-grey);
            margin-top: 8px;
            display: flex;
            align-items: center;
        }

        .plc-meta i {
            margin-right: 6px;
        }

        .plc-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 16px;
        }

        .plc-input {
            padding: 12px 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            font-size: 1rem;
            transition: var(--transition);
        }

        .plc-input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .plc-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px;
            background: var(--white);
            border: 2px dashed var(--medium-grey);
            color: var(--primary-grey);
        }

        .plc-empty i {
            font-size: 2rem;
            margin-bottom: 12px;
            color: var(--medium-grey);
        }

        /* History Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-left: 10px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 2px;
            background: var(--medium-grey);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 24px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 0;
            width: 14px;
            height: 14px;
            background: var(--secondary-blue);
            border: 3px solid var(--white);
            box-shadow: var(--shadow);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .timeline-date {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-grey);
        }

        .timeline-user {
            font-size: 0.8rem;
            padding: 4px 8px;
            background: var(--light-grey);
            color: var(--primary-grey);
        }

        .timeline-content {
            background: var(--white);
            padding: 16px;
            box-shadow: var(--shadow);
            border-left: 3px solid var(--secondary-blue);
        }

        .timeline-status {
            display: inline-block;
            padding: 4px 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--white);
            margin-top: 8px;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin: 32px 0;
        }

        .page-counter {
            font-size: 0.9rem;
            color: var(--primary-grey);
            font-weight: 500;
        }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: var(--dark-grey);
            text-decoration: none;
            font-weight: 500;
            background: var(--white);
            border: 2px solid var(--medium-grey);
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--light-grey);
            border-color: var(--secondary-blue);
        }

        .pagination a.active {
            background: var(--secondary-blue);
            color: var(--white);
            border-color: var(--secondary-blue);
        }

        .pagination-disabled {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: var(--medium-grey);
            background: var(--light-grey);
            border: 2px solid var(--medium-grey);
            cursor: not-allowed;
        }

        .pagination-ellipsis {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: var(--primary-grey);
        }

        /* No Records */
        .no-records {
            text-align: center;
            padding: 64px 32px;
            background: var(--white);
        }

        .no-records i {
            font-size: 4rem;
            color: var(--medium-grey);
            margin-bottom: 24px;
        }

        .no-records h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 16px;
        }

        .no-records p {
            font-size: 1rem;
            color: var(--primary-grey);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: var(--white);
            box-shadow: var(--hover-shadow);
            margin: 5% auto;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 32px;
            border-bottom: 2px solid var(--light-grey);
            background: var(--light-grey);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-grey);
        }

        .modal-close {
            font-size: 1.5rem;
            color: var(--primary-grey);
            cursor: pointer;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--danger-red);
            background: var(--white);
        }

        .modal-body {
            padding: 32px;
        }

        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light-grey);
            border-top: 4px solid var(--secondary-blue);
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main {
                margin-left: 0;
                padding: 20px;
            }
            
            .sidebar.collapsed + .main {
                margin-left: 0;
            }
            
            .management-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-heading {
                font-size: 2rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 12px;
            }
            
            .records-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .bulk-actions {
                justify-content: center;
            }
            
            .table th,
            .table td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }
            
            .action-btn {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-header,
            .modal-body {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 16px;
            }
            
            .dashboard-heading {
                font-size: 1.75rem;
            }
            
            .filter-panel,
            .records-panel {
                margin-bottom: 20px;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 0.8rem;
                min-width: 100px;
            }
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-primary { color: var(--secondary-blue); }
        .text-success { color: var(--success-green); }
        .text-warning { color: var(--warning-orange); }
        .text-danger { color: var(--danger-red); }
        .bg-primary { background-color: var(--secondary-blue); }
        .bg-light { background-color: var(--light-grey); }
        .shadow { box-shadow: var(--shadow); }
        .shadow-hover { box-shadow: var(--hover-shadow); }
        .d-none { display: none; }
        .d-block { display: block; }
        .d-flex { display: flex; }


        /* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-right: 1px solid var(--medium-grey);
    box-shadow: var(--hover-shadow);
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
    border-bottom: 1px solid var(--medium-grey);
    background: linear-gradient(135deg, var(--secondary-blue), var(--primary-grey));
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
    color: var(--primary-grey);
    padding: 0 1.5rem;
    margin-bottom: 1rem;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 1.5rem;
    color: var(--dark-grey);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    margin: 2px 12px;
    border-radius: 12px;
}

.sidebar-item:hover {
    background: linear-gradient(135deg, var(--secondary-blue), var(--primary-grey));
    color: white;
    transform: translateX(4px);
}

.sidebar-item.active {
    background: linear-gradient(135deg, var(--secondary-blue), var(--primary-grey));
    color: white;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.sidebar-item.active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    background: var(--secondary-blue);
    border-radius: 2px;
}

.sidebar-item i {
    width: 20px;
    text-align: center;
}

.sidebar-item.text-danger:hover {
    background: var(--danger-red);
    color: white;
}

.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--medium-grey);
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
    box-shadow: var(--hover-shadow);
    backdrop-filter: blur(10px);
    transition: var(--transition);
    color: var(--secondary-blue);
}

.sidebar-toggle-btn:hover {
    transform: scale(1.1);
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
    background: var(--medium-grey);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--primary-grey);
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
                <div class="sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></div>
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

            <a href="activity_logs.php" class="sidebar-item">
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


    <div class="main" id="mainContent">
        <!-- Topbar -->
        <div class="topbar">
            <div class="heading-container">
                <h1 class="dashboard-heading">Paperwork Records</h1>
            </div>
        </div>

        <!-- Toast notification container -->
        <div id="toast-container"></div>

        <!-- Basic Filter Panel -->
        <div class="filter-panel">
            <h2 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Records
            </h2>
            <form id="filter-form" method="GET" action="">
                <div class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label" for="search">Search</label>
                        <input type="text" id="search" name="search" class="filter-input" placeholder="Name, email, ID" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" for="start_date">From Date</label>
                        <input type="date" id="start_date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" for="end_date">To Date</label>
                        <input type="date" id="end_date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" for="submitted_by">Submitted By</label>
                        <input type="text" id="submitted_by" name="submitted_by" class="filter-input" placeholder="Email address" value="<?php echo htmlspecialchars($submittedBy); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" for="status">Status</label>
                        <select id="status" name="status" class="filter-input">
                            <option value="">All Statuses</option>
                            <option value="paperwork_created" <?php if (isset($_GET['status']) && $_GET['status'] == 'paperwork_created') echo 'selected'; ?>>Paperwork Created</option>
                            <option value="initiated_agreement_bgv" <?php if (isset($_GET['status']) && $_GET['status'] == 'initiated_agreement_bgv') echo 'selected'; ?>>Initiated – Agreement, BGV</option>
                            <option value="paperwork_closed" <?php if (isset($_GET['status']) && $_GET['status'] == 'paperwork_closed') echo 'selected'; ?>>Paperwork Closed</option>
                            <option value="started" <?php if (isset($_GET['status']) && $_GET['status'] == 'started') echo 'selected'; ?>>Started</option>
                            <option value="client_hold" <?php if (isset($_GET['status']) && $_GET['status'] == 'client_hold') echo 'selected'; ?>>Client – Hold</option>
                            <option value="client_dropped" <?php if (isset($_GET['status']) && $_GET['status'] == 'client_dropped') echo 'selected'; ?>>Client – Dropped</option>
                            <option value="backout" <?php if (isset($_GET['status']) && $_GET['status'] == 'backout') echo 'selected'; ?>>Backout</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" id="reset-filters" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Advanced Filters Toggle -->
        <button type="button" id="toggle-advanced-filters" class="toggle-filters-btn">
            <i class="fas fa-chevron-down"></i> Show Advanced Filters
        </button>

        <!-- Advanced Filter Panel -->
        <div class="filter-panel advanced-filters" id="advanced-filter-panel">
            <h2 class="filter-title">
                <i class="fas fa-sliders-h"></i>
                Advanced Filters
            </h2>
            <form id="advanced-filter-form" method="GET" action="">
                <!-- Hidden fields to maintain basic filter values -->
                <input type="hidden" id="page-copy" name="page" value="<?php echo $page; ?>">
                <input type="hidden" id="search-copy" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" id="start_date-copy" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                <input type="hidden" id="end_date-copy" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                <input type="hidden" id="submitted_by-copy" name="submitted_by" value="<?php echo htmlspecialchars($submittedBy); ?>">
                <input type="hidden" id="status-copy" name="status" value="<?php echo htmlspecialchars($status); ?>">
                
                <div class="filter-form">
                    <!-- Client Name Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="client_filter">Client</label>
                        <input type="text" id="client_filter" name="client" class="filter-input" placeholder="Filter by client name" value="<?php echo htmlspecialchars($_GET['client'] ?? ''); ?>">
                    </div>
                    
                    <!-- Job Title Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="job_title_filter">Job Title</label>
                        <input type="text" id="job_title_filter" name="job_title" class="filter-input" placeholder="Filter by job title" value="<?php echo htmlspecialchars($_GET['job_title'] ?? ''); ?>">
                    </div>
                    
                    <!-- Business Unit Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="business_unit_filter">Business Unit</label>
                        <select id="business_unit_filter" name="business_unit" class="filter-input">
                            <option value="">All Business Units</option>
                            <?php
                            $businessUnitsQuery = "SELECT DISTINCT business_unit FROM paperworkdetails WHERE business_unit IS NOT NULL AND business_unit != '' ORDER BY business_unit";
                            $businessUnitsResult = $conn->query($businessUnitsQuery);
                            
                            if ($businessUnitsResult && $businessUnitsResult->num_rows > 0) {
                                while ($unit = $businessUnitsResult->fetch_assoc()) {
                                    $selected = (isset($_GET['business_unit']) && $_GET['business_unit'] == $unit['business_unit']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($unit['business_unit']) . '" ' . $selected . '>' . htmlspecialchars($unit['business_unit']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Delivery Manager Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="delivery_manager_filter">Delivery Manager</label>
                        <select id="delivery_manager_filter" name="delivery_manager" class="filter-input">
                            <option value="">All Delivery Managers</option>
                            <?php
                            $managersQuery = "SELECT DISTINCT delivery_manager FROM paperworkdetails WHERE delivery_manager IS NOT NULL AND delivery_manager != '' ORDER BY delivery_manager";
                            $managersResult = $conn->query($managersQuery);
                            
                            if ($managersResult && $managersResult->num_rows > 0) {
                                while ($manager = $managersResult->fetch_assoc()) {
                                    $selected = (isset($_GET['delivery_manager']) && $_GET['delivery_manager'] == $manager['delivery_manager']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($manager['delivery_manager']) . '" ' . $selected . '>' . htmlspecialchars($manager['delivery_manager']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Account Lead Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="account_lead_filter">Account Lead</label>
                        <select id="account_lead_filter" name="account_lead" class="filter-input">
                            <option value="">All Account Leads</option>
                            <?php
                            $leadsQuery = "SELECT DISTINCT client_account_lead FROM paperworkdetails WHERE client_account_lead IS NOT NULL AND client_account_lead != '' ORDER BY client_account_lead";
                            $leadsResult = $conn->query($leadsQuery);
                            
                            if ($leadsResult && $leadsResult->num_rows > 0) {
                                while ($lead = $leadsResult->fetch_assoc()) {
                                    $selected = (isset($_GET['account_lead']) && $_GET['account_lead'] == $lead['client_account_lead']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($lead['client_account_lead']) . '" ' . $selected . '>' . htmlspecialchars($lead['client_account_lead']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Location Filter -->
                    <div class="filter-group">
                        <label class="filter-label" for="location_filter">Location</label>
                        <input type="text" id="location_filter" name="location" class="filter-input" placeholder="Filter by location" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
                    </div>
                    
                    <!-- Sort By -->
                    <div class="filter-group">
                        <label class="filter-label" for="sort_by_filter">Sort By</label>
                        <select id="sort_by_filter" name="sort_by" class="filter-input">
                            <option value="created_desc" <?php echo (!isset($_GET['sort_by']) || $_GET['sort_by'] == 'created_desc') ? 'selected' : ''; ?>>Created Date (Newest First)</option>
                            <option value="created_asc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'created_asc') ? 'selected' : ''; ?>>Created Date (Oldest First)</option>
                            <option value="updated_desc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'updated_desc') ? 'selected' : ''; ?>>Updated Date (Newest First)</option>
                            <option value="updated_asc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'updated_asc') ? 'selected' : ''; ?>>Updated Date (Oldest First)</option>
                            <option value="name_asc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                        </select>
                    </div>
                    
                    <!-- Display Limit -->
                    <div class="filter-group">
                        <label class="filter-label" for="limit_filter">Records Per Page</label>
                        <select id="limit_filter" name="limit" class="filter-input">
                            <option value="10" <?php echo (!isset($_GET['limit']) || $_GET['limit'] == '10') ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '25') ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '50') ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '100') ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button type="button" id="reset-advanced-filters" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="button" id="toggle-basic-filters" class="btn btn-info">
                            <i class="fas fa-chevron-up"></i> Basic Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Records Panel -->
        <div class="records-panel">
            <div class="records-header">
                <div class="records-title">
                    Records
                    <span class="records-count"><?php echo $totalFilteredRecords; ?></span>
                </div>
                
                <?php if($userRole === "Admin" || $userRole === "Contracts") : ?>
                <div class="bulk-actions" id="action-buttons">
                    <button id="bulk-export" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <?php if($userRole === "Admin"): ?>
                    <button id="bulk-delete" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
            <div class="select-controls">
                <input type="checkbox" id="select-all" class="select-checkbox">
                <label for="select-all" class="select-label">Select All Records</label>
            </div>
            <?php endif; ?>

            <?php if ($recordCount > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                <th style="width: 50px;"><input type="checkbox" id="table-select-all"></th>
                                <?php endif; ?>
                                <th style="width: 50px;"></th> <!-- Expand/collapse column -->
                                <th>Name</th>
                                <th>Email</th>
                                <th>Job Title</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRecords as $row): ?>
                                <tr>
                                    <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                    <td>
                                        <input type="checkbox" class="table-checkbox" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <button class="expand-row-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['cfirstname'] . ' ' . $row['clastname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cemail']); ?></td>
                                    <td><?php echo htmlspecialchars($row['job_title'] ?? 'Not specified'); ?></td>
                                    <td><?php echo htmlspecialchars($row['client'] ?? 'Not specified'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                                            <?php 
                                            $statusText = '';
                                            switch($row['status']) {
                                                case 'paperwork_created': $statusText = 'Paperwork Created'; break;
                                                case 'initiated_agreement_bgv': $statusText = 'Initiated - Agreement, BGV'; break;
                                                case 'paperwork_closed': $statusText = 'Paperwork Closed'; break;
                                                case 'started': $statusText = 'Started'; break;
                                                case 'client_hold': $statusText = 'Client - Hold'; break;
                                                case 'client_dropped': $statusText = 'Client - Dropped'; break;
                                                case 'backout': $statusText = 'Backout'; break;
                                                default: $statusText = ucfirst(str_replace('_', ' ', $row['status']));
                                            }
                                            echo htmlspecialchars($statusText);
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="record-actions">
                                            <button class="action-btn action-preview" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="Preview Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($row['status'] == 'paperwork_created' || $userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                            <a href="paperworkedit.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn action-edit" title="Edit Record">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn action-history" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="View History">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            
                                            <?php if($userRole === 'Admin' || $userRole === 'Contracts') : ?>
                                            <a href="testexport1.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn action-export" title="Export Record">
                                                <i class="fas fa-file-export"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($userRole === "Admin") : ?>
                                            <button class="action-btn action-delete" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="Delete Record">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Expanded content row -->
                                <tr class="expanded-content" id="expanded-<?php echo htmlspecialchars($row['id']); ?>">
                                    <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Contracts') ? 9 : 8; ?>">
                                        <div class="expanded-details">
                                            <!-- Content will be loaded here -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($allRecords) == 0): ?>
                            <tr>
                                <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Contracts') ? 9 : 8; ?>" class="text-center" style="padding: 40px;">
                                    <div class="no-records">
                                        <i class="fas fa-search"></i>
                                        <h3>No Records Found</h3>
                                        <p>Try adjusting your search criteria or reset the filters to see more results.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalFilteredRecords > 0): ?>
                <div class="pagination-container">
                    <div class="page-counter">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalFilteredRecords; ?> records)
                    </div>
                    
                    <div class="pagination">
                        <?php 
                        $totalPages = ceil($totalFilteredRecords / $recordsPerPage);
                        
                        if ($page > $totalPages) {
                            $page = 1;
                        }
                        
                        $queryParams = http_build_query([
                            'search' => $search,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'submitted_by' => $submittedBy,
                            'status' => $status,
                            'client' => $client ?? '',
                            'job_title' => $jobTitle ?? '',
                            'business_unit' => $businessUnit ?? '',
                            'delivery_manager' => $deliveryManager ?? '',
                            'account_lead' => $accountLead ?? '',
                            'location' => $location ?? '',
                            'date_type' => $dateType ?? '',
                            'sort_by' => $sortBy ?? '',
                            'limit' => $recordsPerPage
                        ]);
                        
                        if ($totalPages > 1):
                        ?>
                        
                        <!-- Previous page button -->
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo $queryParams; ?>" title="Previous Page">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php else: ?>
                        <span class="pagination-disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <!-- Page numbers -->
                        <?php 
                        $maxPagesToShow = 5;
                        $startPage = max(1, min($page - floor($maxPagesToShow/2), $totalPages - $maxPagesToShow + 1));
                        $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                        
                        if ($endPage - $startPage + 1 < $maxPagesToShow && $endPage < $totalPages) {
                            $startPage = max(1, $endPage - $maxPagesToShow + 1);
                        }
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1&' . $queryParams . '">1</a>';
                            
                            if ($startPage > 2) {
                                echo '<span class="pagination-ellipsis">&hellip;</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo $queryParams; ?>" class="<?php if ($i == $page) echo 'active'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php 
                        endfor; 
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="pagination-ellipsis">&hellip;</span>';
                            }
                            
                            echo '<a href="?page=' . $totalPages . '&' . $queryParams . '">' . $totalPages . '</a>';
                        }
                        ?>
                        
                        <!-- Next page button -->
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo $queryParams; ?>" title="Next Page">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php else: ?>
                        <span class="pagination-disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-search"></i>
                    <h3>No Records Found</h3>
                    <p>Try adjusting your search criteria or reset the filters to see more results.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Record Details</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="preview-content">
                    <div class="spinner"></div>
                    <p style="text-align: center; margin-top: 16px;">Loading details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Status History</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="history-content">
                    <div class="spinner"></div>
                    <p style="text-align: center; margin-top: 16px;">Loading history...</p>
                </div>
            </div>
        </div>
    </div>

    

    <script>
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventHandlers();
            initializeFilters();
            initializeCheckboxes();
            initializeToastHandler();
            initializePagination();
        });

        // Event Handlers
        function initializeEventHandlers() {
            // Table row expansion
            document.addEventListener('click', function(event) {
                if (event.target.closest('.expand-row-btn')) {
                    handleRowExpansion(event.target.closest('.expand-row-btn'));
                }
                
                if (event.target.closest('.tab-btn')) {
                    handleTabSwitch(event.target.closest('.tab-btn'));
                }
                
                if (event.target.closest('.action-preview')) {
                    handlePreviewModal(event.target.closest('.action-preview'));
                }
                
                if (event.target.closest('.action-history')) {
                    handleHistoryModal(event.target.closest('.action-history'));
                }
                
                if (event.target.closest('.action-delete')) {
                    handleDeleteRecord(event.target.closest('.action-delete'));
                }
                
                if (event.target.classList.contains('modal-close')) {
                    closeModal(event.target.closest('.modal'));
                }
                
                if (event.target.classList.contains('modal')) {
                    closeModal(event.target);
                }
            });
        }

        // Row Expansion Handler
        function handleRowExpansion(button) {
            const recordId = button.getAttribute('data-id');
            const expandedRow = document.getElementById(`expanded-${recordId}`);
            
            if (expandedRow) {
                if (expandedRow.style.display === 'none' || !expandedRow.style.display) {
                    expandedRow.style.display = 'table-row';
                    button.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    
                    const expandedDetails = expandedRow.querySelector('.expanded-details');
                    if (expandedDetails) {
                        fetchExpandedDetails(recordId, expandedDetails);
                    }
                } else {
                    expandedRow.style.display = 'none';
                    button.innerHTML = '<i class="fas fa-chevron-down"></i>';
                }
            }
        }

        // Fetch Expanded Details
        function fetchExpandedDetails(recordId, container) {
            container.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 16px;">Loading details...</p>';
            
            fetch(`get_plc_code.php?id=${recordId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    renderExpandedContent(recordId, data, container);
                })
                .catch(error => {
                    console.error('Error fetching details:', error);
                    container.innerHTML = `
                        <div style="color: var(--danger-red); padding: 20px; text-align: center;">
                            <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <p style="margin: 0;">Error loading details. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Render Expanded Content
        function renderExpandedContent(recordId, data, container) {
            const html = `
                <div class="management-container">
                    <div class="management-header">
                        <h3 class="management-title">
                            <i class="fas fa-clipboard-list"></i>
                            Paperwork Management
                        </h3>
                        <span class="paperwork-id">Paperwork ID: ${recordId}</span>
                    </div>
                    
                    <div class="tab-nav">
                        <button class="tab-btn active" data-tab="management" data-id="${recordId}">
                            <i class="fas fa-cogs"></i> Management
                        </button>
                        <button class="tab-btn" data-tab="history" data-id="${recordId}">
                            <i class="fas fa-history"></i> Status History
                        </button>
                    </div>
                    
                    <div id="management-${recordId}" class="tab-content active">
                        <div class="management-grid">
                            <div class="management-section">
                                <h4 class="section-title">
                                    <i class="fas fa-tasks"></i>
                                    Status Management
                                </h4>
                                
                                <div class="current-status">
                                    <div class="status-label">Current Status:</div>
                                    <div class="status-badge status-${data.status || 'paperwork_created'}">
                                        ${getStatusText(data.status || 'paperwork_created')}
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="status-label">Change Status:</div>
                                    <select class="status-dropdown" data-id="${recordId}" data-current="${data.status || 'paperwork_created'}">
                                        <option value="paperwork_created" ${(data.status || 'paperwork_created') === 'paperwork_created' ? 'selected' : ''}>Paperwork Created</option>
                                        <option value="initiated_agreement_bgv" ${data.status === 'initiated_agreement_bgv' ? 'selected' : ''}>Initiated – Agreement, BGV</option>
                                        <option value="paperwork_closed" ${data.status === 'paperwork_closed' ? 'selected' : ''}>Paperwork Closed</option>
                                        <option value="started" ${data.status === 'started' ? 'selected' : ''}>Started</option>
                                        <option value="client_hold" ${data.status === 'client_hold' ? 'selected' : ''}>Client – Hold</option>
                                        <option value="client_dropped" ${data.status === 'client_dropped' ? 'selected' : ''}>Client – Dropped</option>
                                        <option value="backout" ${data.status === 'backout' ? 'selected' : ''}>Backout</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="management-section">
                                <h4 class="section-title">
                                    <i class="fas fa-code"></i>
                                    PLC Code Management
                                </h4>
                                
                                ${data.plc_code ? 
                                    `<div class="plc-display">${data.plc_code}</div>
                                    <div class="plc-meta">
                                        <i class="fas fa-history"></i>
                                        Last updated: ${data.updated_at ? new Date(data.updated_at).toLocaleString() : 'N/A'} 
                                        by ${data.updated_by || 'Unknown'}
                                    </div>`
                                    : 
                                    `<div class="plc-empty">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>No PLC code has been assigned yet.</p>
                                    </div>`
                                }
                                
                                <form class="plc-form" data-id="${recordId}">
                                    <input type="text" name="plc_code" class="plc-input" placeholder="Enter PLC Code" value="${data.plc_code || ''}">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save PLC Code
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div id="history-${recordId}" class="tab-content">
                        <div class="spinner"></div>
                        <p style="text-align: center; margin-top: 16px;">Loading status history...</p>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            // Add event listeners
            addPLCFormListener(recordId);
            addStatusDropdownListener(recordId);
        }

        // Tab Switch Handler
        function handleTabSwitch(tabBtn) {
            const tabName = tabBtn.getAttribute('data-tab');
            const recordId = tabBtn.getAttribute('data-id');
            
            // Remove active class from all tabs
            const tabsContainer = tabBtn.closest('.tab-nav');
            tabsContainer.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked tab
            tabBtn.classList.add('active');
            
            // Hide all tab contents
            const container = tabBtn.closest('.management-container');
            container.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected tab content
            const tabContent = document.getElementById(`${tabName}-${recordId}`);
            if (tabContent) {
                tabContent.classList.add('active');
                
                // Load history if switching to history tab
                if (tabName === 'history') {
                    fetchStatusHistory(recordId, tabContent);
                }
            }
        }

        // Fetch Status History
        function fetchStatusHistory(recordId, container) {
            container.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 16px;">Loading status history...</p>';
            
            fetch(`status_history.php?id=${recordId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    container.innerHTML = data;
                })
                .catch(error => {
                    console.error('Error fetching status history:', error);
                    container.innerHTML = `
                        <div style="color: var(--danger-red); padding: 20px; text-align: center;">
                            <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <p style="margin: 0;">Error loading status history. Please try again.</p>
                        </div>
                    `;
                });
        }

        // PLC Form Listener
        function addPLCFormListener(recordId) {
            const form = document.querySelector(`.plc-form[data-id="${recordId}"]`);
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const plcCode = this.querySelector('input[name="plc_code"]').value.trim();
                    
                    if (!plcCode) {
                        showAlert('Error!', 'Please enter a PLC code.', 'error');
                        return;
                    }
                    
                    showAlert('Saving...', 'Please wait while we save the PLC code.', 'info', true);
                    
                    fetch('save_plc_code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `paperwork_id=${recordId}&plc_code=${encodeURIComponent(plcCode)}`
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(result => {
                        if (result.status === 'success') {
                            showAlert('Success!', 'PLC code saved successfully.', 'success');
                            // Refresh the expanded details
                            const expandedDetails = document.querySelector(`#expanded-${recordId} .expanded-details`);
                            if (expandedDetails) {
                                fetchExpandedDetails(recordId, expandedDetails);
                            }
                        } else {
                            throw new Error(result.message || 'Failed to save PLC code');
                        }
                    })
                    .catch(error => {
                        showAlert('Error!', 'Failed to save the PLC code. Please try again.', 'error');
                    });
                });
            }
        }

        // Status Dropdown Listener
        function addStatusDropdownListener(recordId) {
            const dropdown = document.querySelector(`.status-dropdown[data-id="${recordId}"]`);
            if (dropdown) {
                dropdown.addEventListener('change', function() {
                    const newStatus = this.value;
                    const currentStatus = this.getAttribute('data-current');
                    
                    if (newStatus === currentStatus) return;
                    
                    // Handle special status changes that require additional input
                    if (newStatus === 'client_hold' || newStatus === 'client_dropped') {
                        promptForReason(recordId, newStatus, currentStatus, this);
                    } else if (newStatus === 'started') {
                        promptForStartDate(recordId, newStatus, currentStatus, this);
                    } else {
                        updateStatus(recordId, newStatus, null, this);
                    }
                });
            }
        }

        // Modal Handlers
        function handlePreviewModal(button) {
            const recordId = button.getAttribute('data-id');
            const modal = document.getElementById('previewModal');
            
            modal.style.display = 'block';
            
            const contentContainer = document.getElementById('preview-content');
            contentContainer.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 16px;">Loading details...</p>';
            
            fetch(`fetchdetails.php?id=${recordId}`)
                .then(response => response.text())
                .then(data => {
                    contentContainer.innerHTML = data;
                })
                .catch(error => {
                    contentContainer.innerHTML = '<p style="text-align: center; color: var(--danger-red);"><i class="fas fa-exclamation-circle"></i> Failed to load details. Please try again.</p>';
                });
        }

        function handleHistoryModal(button) {
            const recordId = button.getAttribute('data-id');
            const modal = document.getElementById('historyModal');
            
            modal.style.display = 'block';
            
            const contentContainer = document.getElementById('history-content');
            contentContainer.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 16px;">Loading history...</p>';
            
            fetch(`fetch_history.php?id=${recordId}`)
                .then(response => response.text())
                .then(data => {
                    contentContainer.innerHTML = data;
                })
                .catch(error => {
                    contentContainer.innerHTML = '<p style="text-align: center; color: var(--danger-red);"><i class="fas fa-exclamation-circle"></i> Failed to load history. Please try again.</p>';
                });
        }

        function handleDeleteRecord(button) {
    const recordId = button.getAttribute('data-id');
    
    Swal.fire({
        title: 'Are you sure?',
        text: "This record will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--danger-red)',
        cancelButtonColor: 'var(--primary-grey)',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait while we delete this record.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send JSON data like bulk delete
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: recordId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json(); // Parse JSON response
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Deleted!',
                        text: data.message || 'Record has been successfully deleted.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete record');
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete record: ' + error.message,
                    icon: 'error'
                });
            });
        }
    });
}

        function closeModal(modal) {
            modal.style.display = 'none';
        }

        // Helper Functions
        function getStatusText(status) {
            switch(status) {
                case 'paperwork_created': return 'Paperwork Created';
                case 'initiated_agreement_bgv': return 'Initiated - Agreement, BGV';
                case 'paperwork_closed': return 'Paperwork Closed';
                case 'started': return 'Started';
                case 'client_hold': return 'Client - Hold';
                case 'client_dropped': return 'Client - Dropped';
                case 'backout': return 'Backout';
                default: return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            }
        }

        function getStatusColor(status) {
            switch(status) {
                case 'paperwork_created': return 'var(--secondary-blue)';
                case 'initiated_agreement_bgv': return 'var(--info-blue)';
                case 'paperwork_closed': return 'var(--success-green)';
                case 'started': return 'var(--success-green)';
                case 'client_hold': return 'var(--warning-orange)';
                case 'client_dropped': return 'var(--danger-red)';
                case 'backout': return 'var(--primary-grey)';
                default: return 'var(--secondary-blue)';
            }
        }

        function promptForReason(recordId, newStatus, currentStatus, dropdown) {
            Swal.fire({
                title: newStatus === 'client_hold' ? 'Reason for Client Hold' : 'Reason for Client Drop',
                input: 'textarea',
                inputPlaceholder: 'Enter the reason...',
                showCancelButton: true,
                confirmButtonText: 'Update Status',
                preConfirm: (reason) => {
                    if (!reason.trim()) {
                        Swal.showValidationMessage('Please provide a reason');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateStatus(recordId, newStatus, result.value, dropdown);
                } else {
                    dropdown.value = currentStatus;
                }
            });
        }

        function promptForStartDate(recordId, newStatus, currentStatus, dropdown) {
            Swal.fire({
                title: 'Enter Start Date',
                input: 'date',
                inputAttributes: {
                    'aria-label': 'Start date'
                },
                showCancelButton: true,
                confirmButtonText: 'Update Status',
                preConfirm: (date) => {
                    if (!date) {
                        Swal.showValidationMessage('Please select a date');
                        return false;
                    }
                    return date;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateStatus(recordId, newStatus, result.value, dropdown, true);
                } else {
                    dropdown.value = currentStatus;
                }
            });
        }

        function updateStatus(recordId, newStatus, additionalData, dropdown, isStartDate = false) {
            const formData = new FormData();
            formData.append('id', recordId);
            formData.append('status', newStatus);
            
            if (additionalData) {
                if (isStartDate) {
                    formData.append('start_date', additionalData);
                } else {
                    formData.append('reason', additionalData);
                }
            }
            
            fetch('paperworkstatus.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully', 'success');
                    updateStatusUI(recordId, newStatus);
                    dropdown.setAttribute('data-current', newStatus);
                } else {
                    throw new Error(data.message || 'Failed to update status');
                }
            })
            .catch(error => {
                showAlert('Error!', 'Failed to update status. Please try again.', 'error');
                dropdown.value = dropdown.getAttribute('data-current');
            });
        }

        function updateStatusUI(recordId, status) {
            const statusText = getStatusText(status);
            
            // Update in table
            const tableRow = document.querySelector(`.table-checkbox[data-id="${recordId}"]`)?.closest('tr');
            if (tableRow) {
                const statusBadge = tableRow.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = 'status-badge';
                    statusBadge.classList.add(`status-${status}`);
                    statusBadge.textContent = statusText;
                }
            }
            
            // Update in expanded view
            const currentStatusBadge = document.querySelector(`#expanded-${recordId} .status-badge`);
            if (currentStatusBadge) {
                currentStatusBadge.className = 'status-badge';
                currentStatusBadge.classList.add(`status-${status}`);
                currentStatusBadge.textContent = statusText;
            }
        }

        function showAlert(title, text, icon, loading = false) {
            const config = {
                title: title,
                text: text,
                icon: icon
            };
            
            if (loading) {
                config.allowOutsideClick = false;
                config.didOpen = () => {
                    Swal.showLoading();
                };
            }
            
            Swal.fire(config);
        }

        // Filter Functionality
        function initializeFilters() {
            const basicFilterPanel = document.querySelector('.filter-panel:not(.advanced-filters)');
            const advancedFilterPanel = document.getElementById('advanced-filter-panel');
            const toggleAdvancedBtn = document.getElementById('toggle-advanced-filters');
            const toggleBasicBtn = document.getElementById('toggle-basic-filters');
            const resetFiltersBtn = document.getElementById('reset-filters');
            const resetAdvancedBtn = document.getElementById('reset-advanced-filters');
            
            // Toggle advanced filters
            if (toggleAdvancedBtn) {
                toggleAdvancedBtn.addEventListener('click', function() {
                    if (advancedFilterPanel.style.display === 'none' || !advancedFilterPanel.style.display) {
                        advancedFilterPanel.style.display = 'block';
                        basicFilterPanel.style.display = 'none';
                        this.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Advanced Filters';
                        this.classList.add('expanded');
                        
                        // Update hidden fields
                        document.getElementById('search-copy').value = document.getElementById('search').value;
                        document.getElementById('start_date-copy').value = document.getElementById('start_date').value;
                        document.getElementById('end_date-copy').value = document.getElementById('end_date').value;
                        document.getElementById('submitted_by-copy').value = document.getElementById('submitted_by').value;
                        document.getElementById('status-copy').value = document.getElementById('status').value;
                    } else {
                        advancedFilterPanel.style.display = 'none';
                        basicFilterPanel.style.display = 'block';
                        this.innerHTML = '<i class="fas fa-chevron-down"></i> Show Advanced Filters';
                        this.classList.remove('expanded');
                    }
                });
            }
            
            // Toggle back to basic filters
            if (toggleBasicBtn) {
                toggleBasicBtn.addEventListener('click', function() {
                    advancedFilterPanel.style.display = 'none';
                    basicFilterPanel.style.display = 'block';
                    if (toggleAdvancedBtn) {
                        toggleAdvancedBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show Advanced Filters';
                        toggleAdvancedBtn.classList.remove('expanded');
                    }
                });
            }
            
            // Reset filters
            if (resetFiltersBtn) {
                resetFiltersBtn.addEventListener('click', function() {
                    const form = document.getElementById('filter-form');
                    form.reset();
                    form.submit();
                });
            }
            
            if (resetAdvancedBtn) {
                resetAdvancedBtn.addEventListener('click', function() {
                    const form = document.getElementById('advanced-filter-form');
                    form.querySelectorAll('input, select').forEach(input => {
                        if (!input.id.includes('-copy')) {
                            if (input.tagName.toLowerCase() === 'select') {
                                input.selectedIndex = 0;
                            } else {
                                input.value = '';
                            }
                        }
                    });
                    form.submit();
                });
            }
            
            // Show advanced filters if any are active
            const urlParams = new URLSearchParams(window.location.search);
            const advancedParams = ['client', 'job_title', 'business_unit', 'delivery_manager', 
                                  'account_lead', 'location', 'date_type', 'sort_by', 'limit'];
            
            let hasAdvancedFilter = false;
            advancedParams.forEach(param => {
                if (urlParams.has(param) && urlParams.get(param) !== '') {
                    hasAdvancedFilter = true;
                }
            });
            
            if (hasAdvancedFilter && toggleAdvancedBtn && advancedFilterPanel && basicFilterPanel) {
                advancedFilterPanel.style.display = 'block';
                basicFilterPanel.style.display = 'none';
                toggleAdvancedBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Advanced Filters';
                toggleAdvancedBtn.classList.add('expanded');
            }
        }

        // Checkbox Functionality
        function initializeCheckboxes() {
            const selectAll = document.getElementById('select-all');
            const tableSelectAll = document.getElementById('table-select-all');
            
            function syncCheckboxes(source) {
                const checkboxes = document.querySelectorAll('.table-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = source.checked;
                });
                toggleActionButtons();
            }
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    if (tableSelectAll) tableSelectAll.checked = this.checked;
                    syncCheckboxes(this);
                });
            }
            
            if (tableSelectAll) {
                tableSelectAll.addEventListener('change', function() {
                    if (selectAll) selectAll.checked = this.checked;
                    syncCheckboxes(this);
                });
            }
            
            document.querySelectorAll('.table-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAll();
                    toggleActionButtons();
                });
            });
            
            function updateSelectAll() {
                const allCheckboxes = document.querySelectorAll('.table-checkbox');
                const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                
                if (selectAll) selectAll.checked = allChecked;
                if (tableSelectAll) tableSelectAll.checked = allChecked;
            }
            
            function toggleActionButtons() {
                const anyChecked = Array.from(document.querySelectorAll('.table-checkbox')).some(cb => cb.checked);
                const actionButtons = document.getElementById('action-buttons');
                if (actionButtons) {
                    actionButtons.style.display = anyChecked ? 'flex' : 'none';
                }
            }
            
            // Bulk actions
            const bulkExportBtn = document.getElementById('bulk-export');
            const bulkDeleteBtn = document.getElementById('bulk-delete');
            
            if (bulkExportBtn) {
                bulkExportBtn.addEventListener('click', function() {
                    const selectedIds = getSelectedRecordIds();
                    
                    if (selectedIds.length === 0) {
                        showAlert('No Records Selected', 'Please select at least one record to export.', 'warning');
                        return;
                    }
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'bulk_export.php';
                    form.style.display = 'none';
                    
                    selectedIds.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
            
            if (bulkDeleteBtn) {
                bulkDeleteBtn.addEventListener('click', function() {
                    const selectedIds = getSelectedRecordIds();
                    
                    if (selectedIds.length === 0) {
                        showAlert('No Records Selected', 'Please select at least one record to delete.', 'warning');
                        return;
                    }
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: `You are about to delete ${selectedIds.length} records. This action cannot be undone.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--danger-red)',
                        cancelButtonColor: 'var(--primary-grey)',
                        confirmButtonText: 'Yes, delete them!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Deleting...',
                                text: 'Please wait while we delete the selected records.',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            fetch('bulk_delete.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ ids: selectedIds })
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Deleted!',
                                        text: `Successfully deleted ${result.count} records.`,
                                        icon: 'success',
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    throw new Error(result.message || 'Failed to delete records');
                                }
                            })
                            .catch(error => {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Failed to delete records. Please try again.',
                                    icon: 'error'
                                });
                            });
                        }
                    });
                });
            }
        }

        function getSelectedRecordIds() {
            const selectedIds = [];
            document.querySelectorAll('.table-checkbox:checked').forEach(checkbox => {
                selectedIds.push(checkbox.getAttribute('data-id'));
            });
            return selectedIds;
        }

        // Toast Handler
        function initializeToastHandler() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const message = urlParams.get('message') || 'Record updated successfully!';
            
            if (success === '1') {
                showToast(message, 'success');
                
                const url = window.location.pathname;
                window.history.replaceState({}, document.title, url);
            }
        }

        function showToast(message, type) {
            const toastContainer = document.getElementById('toast-container');
            if (toastContainer) {
                const toast = document.createElement('div');
                toast.className = 'toast';
                
                if (type === 'success') {
                    toast.style.backgroundColor = 'var(--success-green)';
                } else if (type === 'error') {
                    toast.style.backgroundColor = 'var(--danger-red)';
                } else if (type === 'warning') {
                    toast.style.backgroundColor = 'var(--warning-orange)';
                } else if (type === 'info') {
                    toast.style.backgroundColor = 'var(--info-blue)';
                }
                
                toast.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                    <div class="toast-content">${message}</div>
                `;
                
                toastContainer.appendChild(toast);
                
                setTimeout(() => {
                    toast.addEventListener('animationend', function() {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    });
                }, 3000);
            }
        }

        // Pagination
        function initializePagination() {
            // Sidebar toggle functionality
            const updateMainMargin = () => {
    const main = document.querySelector('.main');
    const sidebar = document.querySelector('.sidebar');
    
    if (window.innerWidth > 1200) {
        main.style.marginLeft = sidebar && sidebar.classList.contains('collapsed') ? '0' : '280px';
    } else {
        main.style.marginLeft = '0';
    }
};

            const sidebarToggler = document.querySelector('.toggle');
            if (sidebarToggler) {
                sidebarToggler.addEventListener('click', updateMainMargin);
            }
            window.addEventListener('resize', updateMainMargin);
        }
    </script>

    <script>
        // Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
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
    const mainContent = document.getElementById('mainContent');
    
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