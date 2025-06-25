<?php

session_start();

require 'db.php'; // Include your database connection

// Include activity logger
require_once 'log_activity.php';

// Log page view
logActivity('view', 'Viewed dashboard page');

if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php"); 
    exit();
}

// Get the user's email from the session
$userEmail = $_SESSION['email'] ?? '';

// Query to fetch the user's role and name from the database
$userQuery = "SELECT name, role FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();

// Check if the user exists and get their info
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userName = $row['name'];
    $userRole = $row['role']; // Fetch the role of the current user
} else {
    // Handle case where user does not exist
    echo "User not found.";
    exit; // Stop execution if the user does not exist
}

// Check if the user is an admin or has Contracts role
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paperwork Dashboard</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

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
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark-grey);
            line-height: 1.6;
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
            text-decoration: none;
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

        /* Topbar */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 0 32px 0;
            margin-bottom: 32px;
            border-bottom: 2px solid var(--medium-grey);
        }

        .dashboard-heading {
            color: var(--dark-grey);
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .add-paperwork-widget {
            background: var(--secondary-blue);
            color: var(--white);
            padding: 16px 24px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .add-paperwork-widget:hover {
            background: var(--hover-blue);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
            color: var(--white);
            text-decoration: none;
        }

        /* Main Cards */
        .cardBox {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--white);
            padding: 32px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border-left: 4px solid var(--secondary-blue);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, transparent 0%, rgba(0, 123, 255, 0.05) 100%);
            transform: rotate(45deg) translate(25px, -25px);
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }

        .card-paperwork {
            border-left-color: var(--secondary-blue);
        }

        .card-deals {
            border-left-color: var(--success-green);
        }

        .card-ptptr {
            border-left-color: var(--warning-orange);
        }

        .card .numbers {
            font-size: 3rem;
            font-weight: 800;
            color: var(--dark-grey);
            margin-bottom: 8px;
            line-height: 1;
        }

        .card .cardName {
            font-size: 1.1rem;
            color: var(--primary-grey);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card .iconBx {
            position: absolute;
            right: 24px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--light-grey);
            padding: 16px;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card .iconBx i {
            font-size: 1.5rem;
            color: var(--primary-grey);
        }

        /* Status Cards Grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }

        .status-card {
            background: var(--white);
            padding: 24px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            border-top: 3px solid var(--medium-grey);
        }

        .status-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .status-card.created { border-top-color: #17a2b8; }
        .status-card.initiated { border-top-color: var(--success-green); }
        .status-card.closed { border-top-color: var(--secondary-blue); }
        .status-card.started { border-top-color: var(--warning-orange); }
        .status-card.hold { border-top-color: #ffc107; }
        .status-card.dropped { border-top-color: var(--danger-red); }
        .status-card.backout { border-top-color: var(--primary-grey); }

        .status-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            background: var(--light-grey);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-grey);
        }

        .status-count {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-grey);
            margin-bottom: 8px;
        }

        .status-title {
            font-size: 0.9rem;
            color: var(--primary-grey);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Draft Container */
        .draft-container {
            background: linear-gradient(135deg, var(--secondary-blue), var(--hover-blue));
            color: var(--white);
            padding: 24px;
            margin: 24px 0;
            display: none;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
        }

        .draft-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .draft-info p {
            opacity: 0.9;
            font-weight: 400;
        }

        .draft-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--white);
            color: var(--secondary-blue);
        }

        .btn-primary:hover {
            background: var(--light-grey);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Charts Section */
        .charts-section {
            background: var(--white);
            padding: 32px;
            margin: 40px 0;
            box-shadow: var(--shadow);
        }

        .charts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--light-grey);
        }

        .charts-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-grey);
        }

        .time-filter {
            padding: 12px 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            color: var(--dark-grey);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .time-filter:focus {
            outline: none;
            border-color: var(--secondary-blue);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 32px;
        }

        .chart-card {
            background: var(--light-grey);
            padding: 24px;
            height: 400px;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 20px;
        }

        /* Recent Paperworks Table */
        .recent-section {
            background: var(--white);
            padding: 32px;
            margin: 40px 0;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--light-grey);
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-grey);
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: var(--light-grey);
        }

        .data-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-grey);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--medium-grey);
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--light-grey);
            color: var(--primary-grey);
        }

        .data-table tbody tr {
            transition: var(--transition);
        }

        .data-table tbody tr:hover {
            background: var(--light-grey);
        }

        .view-details-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--secondary-blue);
            text-decoration: none;
            padding: 8px 16px;
            background: var(--light-grey);
            font-weight: 500;
            transition: var(--transition);
        }

        .view-details-btn:hover {
            background: var(--secondary-blue);
            color: var(--white);
            transform: translateY(-2px);
            text-decoration: none;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-wrapper {
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-content {
            background: var(--white);
            padding: 32px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            position: absolute;
            right: 24px;
            top: 24px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--primary-grey);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: var(--light-grey);
            color: var(--dark-grey);
        }

        /* Loading Animation */
        .loading-spinner {
            border: 4px solid var(--light-grey);
            border-top: 4px solid var(--secondary-blue);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
                padding: 20px;
            }
            
            .sidebar-toggle-btn {
                display: flex;
            }
            
            .cardBox {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-heading {
                font-size: 2rem;
            }
            
            .topbar {
                flex-direction: column;
                gap: 20px;
                align-items: stretch;
            }
            
            .cardBox {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .status-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 16px;
            }
            
            .card {
                padding: 24px;
            }
            
            .card .numbers {
                font-size: 2.5rem;
            }
            
            .charts-section,
            .recent-section {
                padding: 20px;
                margin: 20px 0;
            }
            
            .charts-header,
            .section-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .draft-container {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .draft-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .data-table {
                font-size: 14px;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 16px;
            }
            
            .dashboard-heading {
                font-size: 1.75rem;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 14px;
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
                <a href="index.php" class="sidebar-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="usermanagement.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
                <a href="paperworkallrecords.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Paperwork</span>
                </a>
                <a href="activity_logs.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Activity Logs</span>
                </a>
            </div>
            
            <!-- <div class="sidebar-section">
                <div class="sidebar-section-title">Quick Actions</div>
                <a href="paperwork.php" class="sidebar-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Paperwork</span>
                </a>
                <a href="paperwork.php?status=paperwork_created" class="sidebar-item">
                    <i class="fas fa-file-signature"></i>
                    <span>Created</span>
                </a>
                <a href="paperwork.php?status=started" class="sidebar-item">
                    <i class="fas fa-play-circle"></i>
                    <span>Started</span>
                </a>
                <a href="paperwork.php?status=paperwork_closed" class="sidebar-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Closed</span>
                </a>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Reports</div>
                <a href="#" class="sidebar-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
                <a href="#" class="sidebar-item">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </a>
            </div> -->
            
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

    <!-- Modal -->
    <div id="myModal" class="modal-overlay">
        <div class="modal-wrapper">
            <div id="modal-content" class="modal-content">
                <button onclick="closeModal()" class="modal-close">&times;</button>
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <div class="main" id="mainContent">
        <!-- Topbar -->
        <div class="topbar">
            <div class="heading-container">
                <h1 class="dashboard-heading">Paperwork Dashboard</h1>
            </div>

            <a href="paperwork.php" class="add-paperwork-widget">
                <i class="fas fa-plus"></i>
                <span>Add Paperwork</span>
            </a>
        </div>

        <?php
        // Adjust queries based on whether the user is an admin or not
        if ($userRole === 'Admin' || $userRole === 'Contracts') {
            // Admin can see all records
            $totalPaperworksQuery = "SELECT COUNT(*) AS total FROM paperworkdetails";
            $totalDealsQuery = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE type = 'Deal'";
            $combinedPTPTRQuery = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE type IN ('PT', 'PTR')";
        } else {
            // Non-admin users can only see their own records
            $totalPaperworksQuery = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE submittedby = '$userEmail'";
            $totalDealsQuery = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE type = 'Deal' AND submittedby = '$userEmail'";
            $combinedPTPTRQuery = "SELECT COUNT(*) AS total FROM paperworkdetails WHERE type IN ('PT', 'PTR') AND submittedby = '$userEmail'";
        }

        // Execute queries
        $totalPaperworksResult = $conn->query($totalPaperworksQuery);
        $totalPaperworks = $totalPaperworksResult->fetch_assoc()['total'];

        $dealResult = $conn->query($totalDealsQuery);
        $totalDeals = $dealResult->fetch_assoc()['total'];

        $combinedPTPTRResult = $conn->query($combinedPTPTRQuery);
        $combinedTotal = $combinedPTPTRResult->fetch_assoc()['total'];

        // Get status counts
        if ($userRole === 'Admin' || $userRole === 'Contracts') {
            $statusCountsQuery = "SELECT status, COUNT(*) as count FROM paperworkdetails GROUP BY status";
        } else {
            $statusCountsQuery = "SELECT status, COUNT(*) as count FROM paperworkdetails WHERE submittedby = ? GROUP BY status";
        }

        $stmt = $conn->prepare($statusCountsQuery);
        if (!($userRole === 'Admin' || $userRole === 'Contracts')) {
            $stmt->bind_param("s", $userEmail);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Initialize status counts
        $statusCounts = [
            'paperwork_created' => 0,
            'initiated_agreement_bgv' => 0,
            'paperwork_closed' => 0,
            'started' => 0,
            'client_hold' => 0,
            'client_dropped' => 0,
            'backout' => 0
        ];

        while ($row = $result->fetch_assoc()) {
            $status = trim($row['status']);
            if (isset($statusCounts[$status])) {
                $statusCounts[$status] = (int)$row['count'];
            }
        }

        $paperworkCreated = $statusCounts['paperwork_created'];
        $initiated = $statusCounts['initiated_agreement_bgv'];
        $paperworkClosed = $statusCounts['paperwork_closed'];
        $started = $statusCounts['started'];
        $clientHold = $statusCounts['client_hold'];
        $clientDropped = $statusCounts['client_dropped'];
        $backout = $statusCounts['backout'];
        ?>

        <!-- Main Cards -->
        <div class="cardBox">
            <div class="card card-paperwork">
                <div>
                    <div class="numbers"><?php echo $totalPaperworks; ?></div>
                    <div class="cardName">Overall Paperworks</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>

            <div class="card card-deals">
                <div>
                    <div class="numbers"><?php echo $totalDeals; ?></div>
                    <div class="cardName">Deals</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-handshake"></i>
                </div>
            </div>

            <div class="card card-ptptr">
                <div>
                    <div class="numbers"><?php echo $combinedTotal; ?></div>
                    <div class="cardName">PT / PTR</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>

        <!-- Status Cards Grid -->
        <div class="status-grid">
            <div class="status-card created">
                <div class="status-icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="status-count"><?php echo $paperworkCreated; ?></div>
                <div class="status-title">Paperwork Created</div>
            </div>
            
            <div class="status-card initiated">
                <div class="status-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="status-count"><?php echo $initiated; ?></div>
                <div class="status-title">Initiated</div>
            </div>
            
            <div class="status-card closed">
                <div class="status-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-count"><?php echo $paperworkClosed; ?></div>
                <div class="status-title">Paperwork Closed</div>
            </div>
            
            <div class="status-card started">
                <div class="status-icon">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="status-count"><?php echo $started; ?></div>
                <div class="status-title">Started</div>
            </div>
            
            <div class="status-card hold">
                <div class="status-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="status-count"><?php echo $clientHold; ?></div>
                <div class="status-title">Client - Hold</div>
            </div>
            
            <div class="status-card dropped">
                <div class="status-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="status-count"><?php echo $clientDropped; ?></div>
                <div class="status-title">Client - Dropped</div>
            </div>
            
            <div class="status-card backout">
                <div class="status-icon">
                    <i class="fas fa-arrow-circle-left"></i>
                </div>
                <div class="status-count"><?php echo $backout; ?></div>
                <div class="status-title">Backout</div>
            </div>
        </div>

        <!-- Draft Container -->
        <div id="candidateContainer" class="draft-container">
            <div class="draft-info">
                <h3>Draft Information:</h3>
                <p id="candidateInfo"></p>
            </div>
            <div class="draft-actions">
                <button id="continueButton" onclick="confirmContinue()" class="btn btn-primary">
                    <i class="fas fa-play"></i>
                    Continue with Saved Data
                </button>
                <button id="deleteButton" onclick="confirmDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Delete Saved Data
                </button>
            </div>
        </div>

        <!-- Business Unit Trends Section -->
        <div class="charts-section">
            <div class="charts-header">
                <h2 class="charts-title">Business Unit Trends</h2>
                <select id="timeFilter" class="time-filter">
                    <option value="last30">Last 30 Days</option>
                    <option value="last90">Last 90 Days</option>
                    <option value="last180">Last 6 Months</option>
                    <option value="lastyear">Last Year</option>
                </select>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3 class="chart-title">Deal, PT & PTR by Business Unit</h3>
                    <div id="combinedChart" style="width: 100%; height: calc(100% - 40px);"></div>
                </div>
                
                <div class="chart-card">
                    <h3 class="chart-title">Paperwork Status by Business Unit</h3>
                    <div id="statusFlowChart" style="width: 100%; height: calc(100% - 40px);"></div>
                </div>
            </div>
        </div>

        <?php
        // Get recent paperworks
        $limit = 10;
        $recentPaperworksQuery = "SELECT p.*, 
                                  CONCAT(p.cfirstname, ' ', p.clastname) AS full_name,
                                  u.name as submitter_name
                                  FROM paperworkdetails p
                                  LEFT JOIN users u ON u.email = p.submittedby
                                  WHERE 1=1";

        if (!($userRole === 'Admin' || $userRole === 'Contracts')) {
            $recentPaperworksQuery .= " AND p.submittedby = '$userEmail'";
        }

        $recentPaperworksQuery .= " ORDER BY p.created_at DESC LIMIT $limit";
        $recentPaperworksResult = $conn->query($recentPaperworksQuery);
        ?>

        <!-- Recent Paperworks Section -->
        <div class="recent-section">
            <div class="section-header">
                <h2 class="section-title">Recent Paperworks</h2>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Candidate Name</th>
                            <th>Client</th>
                            <th>Business Unit</th>
                            <th>Date Submitted</th>
                            <th>Submitted By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentPaperworksResult && $recentPaperworksResult->num_rows > 0): ?>
                            <?php while ($paperwork = $recentPaperworksResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($paperwork['id']); ?></td>
                                <td><?php echo htmlspecialchars($paperwork['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($paperwork['client']); ?></td>
                                <td><?php echo htmlspecialchars($paperwork['business_unit']); ?></td>
                                <td><?php echo htmlspecialchars(date('M-d-y', strtotime($paperwork['created_at']))); ?></td>
                                <td><?php echo htmlspecialchars($paperwork['submitter_name']); ?></td>
                                <td>
                                    <a href="#" class="view-details view-details-btn" data-id="<?php echo htmlspecialchars($paperwork['id']); ?>">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Include libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <?php
    // Get data for Business Unit Trends
    function getBusinessUnitData($conn, $userEmail, $userRole, $period = 'last30') {
        $dateFilter = "";
        switch($period) {
            case 'last90':
                $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'last180':
                $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
                break;
            case 'lastyear':
                $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        $queryBase = "FROM paperworkdetails WHERE 1=1 $dateFilter";
        if (!($userRole === 'Admin' || $userRole === 'Contracts')) {
            $queryBase .= " AND submittedby = '$userEmail'";
        }
        
        // Get combined data
        $combinedQuery = "SELECT 
                        business_unit,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'paperwork_closed' THEN 1 ELSE 0 END) as closed,
                        SUM(CASE WHEN type = 'PT' THEN 1 ELSE 0 END) as pt_count,
                        SUM(CASE WHEN type = 'PTR' THEN 1 ELSE 0 END) as ptr_count
                        $queryBase
                        GROUP BY business_unit";
        
        $combinedResult = $conn->query($combinedQuery);
        $combinedData = [];
        
        if ($combinedResult && $combinedResult->num_rows > 0) {
            while ($row = $combinedResult->fetch_assoc()) {
                $combinedData[] = [
                    'business_unit' => $row['business_unit'],
                    'total' => (int)$row['total'],
                    'closed' => (int)$row['closed'],
                    'pt_count' => (int)$row['pt_count'],
                    'ptr_count' => (int)$row['ptr_count']
                ];
            }
        }
        
        // Get status data
        $statusQuery = "SELECT 
                       business_unit,
                       SUM(CASE WHEN status = 'paperwork_created' THEN 1 ELSE 0 END) as paperwork_created,
                       SUM(CASE WHEN status = 'initiated_agreement_bgv' THEN 1 ELSE 0 END) as initiated,
                       SUM(CASE WHEN status = 'paperwork_closed' THEN 1 ELSE 0 END) as paperwork_closed,
                       SUM(CASE WHEN status = 'started' THEN 1 ELSE 0 END) as started,
                       SUM(CASE WHEN status = 'client_hold' THEN 1 ELSE 0 END) as client_hold,
                       SUM(CASE WHEN status = 'client_dropped' THEN 1 ELSE 0 END) as client_dropped,
                       SUM(CASE WHEN status = 'backout' THEN 1 ELSE 0 END) as backout
                       $queryBase
                       GROUP BY business_unit";
        
        $statusResult = $conn->query($statusQuery);
        $statusData = [];
        
        if ($statusResult && $statusResult->num_rows > 0) {
            while ($row = $statusResult->fetch_assoc()) {
                $statusData[] = [
                    'business_unit' => $row['business_unit'],
                    'paperwork_created' => (int)$row['paperwork_created'],
                    'initiated' => (int)$row['initiated'],
                    'paperwork_closed' => (int)$row['paperwork_closed'],
                    'started' => (int)$row['started'],
                    'client_hold' => (int)$row['client_hold'],
                    'client_dropped' => (int)$row['client_dropped'],
                    'backout' => (int)$row['backout']
                ];
            }
        }
        
        return ['combined' => $combinedData, 'status' => $statusData];
    }

    $businessUnitData = getBusinessUnitData($conn, $userEmail, $userRole);
    $combinedDataJSON = json_encode($businessUnitData['combined']);
    $statusDataJSON = json_encode($businessUnitData['status']);
    ?>

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

        // Initialize everything when DOM loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modal event listeners
            initializeModal();
            
            // Check for saved draft data
            checkForSavedData();
            
            // Initialize charts
            const combinedData = <?php echo $combinedDataJSON; ?>;
            const statusData = <?php echo $statusDataJSON; ?>;
            
            renderCombinedChart(combinedData);
            renderStatusFlowChart(statusData);
            
            // Time filter event listener
            document.getElementById('timeFilter').addEventListener('change', function() {
                updateCharts(this.value);
            });
            
            // Add smooth animations to cards
            addCardAnimations();
        });

        // Modal Functions
        function initializeModal() {
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    const modal = document.getElementById('myModal');
                    const modalContent = document.getElementById('modal-content');

                    // Show loading state
                    modalContent.innerHTML = `
                        <button onclick="closeModal()" class="modal-close">&times;</button>
                        <div style="text-align: center; padding: 40px;">
                            <div class="loading-spinner"></div>
                            <p style="margin-top: 20px; color: var(--primary-grey);">Loading details...</p>
                        </div>
                    `;

                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';

                    // Fetch details
                    fetch(`fetchdetails.php?id=${id}`)
                        .then(response => response.text())
                        .then(data => {
                            modalContent.innerHTML = `
                                <button onclick="closeModal()" class="modal-close">&times;</button>
                                <div style="padding: 20px;">
                                    ${data}
                                </div>
                            `;
                        })
                        .catch(error => {
                            modalContent.innerHTML = `
                                <button onclick="closeModal()" class="modal-close">&times;</button>
                                <div style="text-align: center; padding: 40px; color: var(--danger-red);">
                                    <i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 20px;"></i>
                                    <p>Error loading details. Please try again.</p>
                                </div>
                            `;
                        });
                });
            });

            // Close modal on outside click
            document.getElementById('myModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }

        function closeModal() {
            const modal = document.getElementById('myModal');
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                modal.style.display = 'none';
                modal.style.opacity = '1';
                modal.style.transform = 'scale(1)';
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Draft Data Functions
        function checkForSavedData() {
            const savedData = localStorage.getItem('savedFormData');
            if (savedData) {
                const data = JSON.parse(savedData);
                const container = document.getElementById('candidateContainer');
                document.getElementById('candidateInfo').innerText = 
                    `Name: ${data.cfirst_name} ${data.clast_name} | Email: ${data.cemail}`;
                
                container.style.display = 'flex';
                container.style.opacity = '0';
                setTimeout(() => {
                    container.style.transition = 'opacity 0.3s ease';
                    container.style.opacity = '1';
                }, 100);
            }
        }

        function confirmContinue() {
            Swal.fire({
                title: 'Continue with Saved Data?',
                text: "You'll be able to resume your previous progress.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, continue!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'paperwork.php';
                }
            });
        }

        function confirmDelete() {
            Swal.fire({
                title: 'Delete Saved Data?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteSavedData();
                }
            });
        }

        function deleteSavedData() {
            localStorage.removeItem('savedFormData');
            Swal.fire({
                title: 'Success!',
                text: 'All saved data has been cleared.',
                icon: 'success',
                confirmButtonColor: '#007bff'
            }).then(() => {
                location.reload();
            });
        }

        // Chart Functions
        function renderCombinedChart(data) {
            const categories = data.map(item => item.business_unit);
            const dealData = data.map(item => item.total - (item.pt_count + item.ptr_count));
            const ptData = data.map(item => item.pt_count);
            const ptrData = data.map(item => item.ptr_count);
            const totalData = data.map(item => item.total);
            
            const annotations = {
                points: []
            };
            
            totalData.forEach((total, index) => {
                if (total > 0) {
                    let yPos = dealData[index] + ptData[index] + ptrData[index] + 2;
                    
                    annotations.points.push({
                        x: categories[index],
                        y: yPos,
                        marker: { size: 0 },
                        label: {
                            borderColor: 'transparent',
                            offsetY: 0,
                            style: {
                                color: '#495057',
                                background: 'transparent',
                                fontSize: '14px',
                                fontWeight: 'bold'
                            },
                            text: total.toString()
                        }
                    });
                }
            });
            
            const options = {
                series: [{
                    name: 'Deals',
                    data: dealData
                }, {
                    name: 'PT',
                    data: ptData
                }, {
                    name: 'PTR',
                    data: ptrData
                }],
                chart: {
                    type: 'bar',
                    height: '100%',
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'Montserrat'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%'
                    }
                },
                dataLabels: { enabled: false },
                stroke: {
                    width: 1,
                    colors: ['#fff']
                },
                colors: ['#007bff', '#28a745', '#fd7e14'],
                xaxis: {
                    categories: categories,
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Montserrat'
                        },
                        rotate: -45
                    }
                },
                yaxis: {
                    title: {
                        text: 'Count',
                        style: {
                            fontFamily: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val;
                        }
                    }
                },
                fill: { opacity: 1 },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontFamily: 'Montserrat'
                },
                annotations: annotations
            };

            const chart = new ApexCharts(document.querySelector("#combinedChart"), options);
            chart.render();
            window.combinedChart = chart;
        }

        function renderStatusFlowChart(data) {
            const categories = data.map(item => item.business_unit);
            
            const options = {
                series: [{
                    name: 'Created',
                    data: data.map(item => item.paperwork_created)
                }, {
                    name: 'Initiated',
                    data: data.map(item => item.initiated)
                }, {
                    name: 'Closed',
                    data: data.map(item => item.paperwork_closed)
                }, {
                    name: 'Started',
                    data: data.map(item => item.started)
                }, {
                    name: 'Client-Hold',
                    data: data.map(item => item.client_hold)
                }, {
                    name: 'Client-Dropped',
                    data: data.map(item => item.client_dropped)
                }, {
                    name: 'Backout',
                    data: data.map(item => item.backout)
                }],
                chart: {
                    type: 'bar',
                    height: '100%',
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'Montserrat'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%'
                    }
                },
                dataLabels: { enabled: false },
                stroke: {
                    width: 1,
                    colors: ['#fff']
                },
                colors: ['#17a2b8', '#28a745', '#007bff', '#fd7e14', '#ffc107', '#dc3545', '#6c757d'],
                xaxis: {
                    categories: categories,
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Montserrat'
                        },
                        rotate: -45
                    }
                },
                yaxis: {
                    title: {
                        text: 'Count',
                        style: {
                            fontFamily: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val;
                        }
                    }
                },
                fill: { opacity: 1 },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontFamily: 'Montserrat'
                }
            };

            const chart = new ApexCharts(document.querySelector("#statusFlowChart"), options);
            chart.render();
            window.statusFlowChart = chart;
        }

        function updateCharts(period) {
            // Show loading state
            document.querySelectorAll('.chart-card').forEach(wrapper => {
                wrapper.style.opacity = '0.6';
                wrapper.insertAdjacentHTML('beforeend', `
                    <div class="chart-loader" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        <div class="loading-spinner"></div>
                    </div>
                `);
            });
            
            fetch(`get_business_unit_data.php?period=${period}`)
                .then(response => response.json())
                .then(data => {
                    // Remove loading state
                    document.querySelectorAll('.chart-loader').forEach(loader => loader.remove());
                    document.querySelectorAll('.chart-card').forEach(wrapper => {
                        wrapper.style.opacity = '1';
                    });
                    
                    // Update charts with new data
                    updateCombinedChart(data.combined);
                    updateStatusChart(data.status);
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    document.querySelectorAll('.chart-loader').forEach(loader => loader.remove());
                    document.querySelectorAll('.chart-card').forEach(wrapper => {
                        wrapper.style.opacity = '1';
                    });
                });
        }

        function updateCombinedChart(data) {
            const categories = data.map(item => item.business_unit);
            const ptData = data.map(item => item.pt_count);
            const ptrData = data.map(item => item.ptr_count);
            const dealData = data.map(item => item.total - (item.pt_count + item.ptr_count));
            const totalData = data.map(item => item.total);
            
            const updatedAnnotations = {
                points: []
            };
            
            totalData.forEach((total, index) => {
                if (total > 0) {
                    let yPos = dealData[index] + ptData[index] + ptrData[index] + 2;
                    
                    updatedAnnotations.points.push({
                        x: categories[index],
                        y: yPos,
                        marker: { size: 0 },
                        label: {
                            borderColor: 'transparent',
                            offsetY: 0,
                            style: {
                                color: '#495057',
                                background: 'transparent',
                                fontSize: '14px',
                                fontWeight: 'bold'
                            },
                            text: total.toString()
                        }
                    });
                }
            });
            
            window.combinedChart.updateOptions({
                xaxis: { categories: categories },
                series: [{
                    name: 'Deals',
                    data: dealData
                }, {
                    name: 'PT',
                    data: ptData
                }, {
                    name: 'PTR',
                    data: ptrData
                }],
                annotations: updatedAnnotations
            });
        }

        function updateStatusChart(data) {
            const categories = data.map(item => item.business_unit);
            
            const series = [
                { name: 'Created', data: data.map(item => item.paperwork_created) },
                { name: 'Initiated', data: data.map(item => item.initiated) },
                { name: 'Closed', data: data.map(item => item.paperwork_closed) },
                { name: 'Started', data: data.map(item => item.started) },
                { name: 'Client-Hold', data: data.map(item => item.client_hold) },
                { name: 'Client-Dropped', data: data.map(item => item.client_dropped) },
                { name: 'Backout', data: data.map(item => item.backout) }
            ];
            
            window.statusFlowChart.updateOptions({
                xaxis: { categories: categories },
                series: series
            });
        }

        // Animation Functions
        function addCardAnimations() {
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });

            document.querySelectorAll('.status-card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
        }
    </script>

</body>
</html>