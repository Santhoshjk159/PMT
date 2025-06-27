<?php
require 'db.php'; // Include your database connection
session_start(); // Ensure the session is started

if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php"); // Redirect if not logged in
    exit();
}

// Include activity logger
require_once 'log_activity.php';

// Log page view
logActivity('view', 'Accessed new paperwork form');

// Get all dropdown options at once to avoid multiple database queries
function getAllDropdownOptions() {
  global $conn;
  
  $query = "SELECT c.category_key, o.value, COALESCE(o.label, o.value) AS label, o.display_order
            FROM dropdown_options o
            JOIN dropdown_categories c ON o.category_id = c.id
            WHERE o.is_active = 1
            ORDER BY c.category_key, o.display_order";
            
  $result = $conn->query($query);
  
  $options = [];
  while ($row = $result->fetch_assoc()) {
      $options[$row['category_key']][] = [
          'value' => $row['value'],
          'label' => $row['label']
      ];
  }
  
  return $options;
}

// Fetch all dropdown options
$allDropdownOptions = getAllDropdownOptions();

// Helper function to output dropdown options
function outputDropdownOptions($categoryKey, $allOptions, $defaultOption = true) {
  if (!isset($allOptions[$categoryKey])) {
      return '<!-- No options found for ' . htmlspecialchars($categoryKey) . ' -->';
  }
  
  $output = '';
  if ($defaultOption) {
      $output .= '<option value="" disabled selected>Select option</option>';
  }
  
  foreach ($allOptions[$categoryKey] as $option) {
      $output .= '<option value="' . htmlspecialchars($option['value']) . '">' 
               . htmlspecialchars($option['label']) . '</option>';
  }
  
  return $output;
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
    $userName = $row['name'];     // Add this line
    $userRole = $row['role'];     // This was already there
} else {
    // Handle case where user does not exist
    echo "User not found.";
    exit; // Stop execution if the user does not exist
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the form data here
    // You would validate and sanitize the inputs
    // Then insert into your database
    
    // For demonstration, just showing a success message
    $formSubmitted = true;

    if (isset($formSubmitted) && $formSubmitted) {
      $recordId = $conn->insert_id; // Get the ID of the inserted record
      $candidateName = $_POST['cfirst_name'] . ' ' . $_POST['clast_name'];
      logActivity('create', "Created new paperwork for $candidateName", $recordId);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Consultant | Paperwork System</title>
    
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
            --border-radius: 0;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark-grey);
            line-height: 1.6;
        }

        /* Main Content Layout */
        .main {
    margin-left: 290px;
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
        }

        /* Search Field */
        .search {
            position: relative;
            max-width: 400px;
            width: 100%;
        }

        .search input {
            width: 100%;
            height: 48px;
            padding: 0 48px 0 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            color: var(--dark-grey);
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .search input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .search i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-grey);
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border-left: 4px solid var(--success-green);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger-red);
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            counter-reset: step;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--medium-grey);
            z-index: 1;
        }

        .progress-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            border: 3px solid var(--medium-grey);
            margin: 0 auto 12px;
            color: var(--primary-grey);
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .progress-step.active .step-number {
            background: var(--secondary-blue);
            color: var(--white);
            border-color: var(--secondary-blue);
        }

        .progress-step.completed .step-number {
            background: var(--success-green);
            color: var(--white);
            border-color: var(--success-green);
        }

        .step-label {
            font-size: 0.9rem;
            color: var(--primary-grey);
            font-weight: 500;
        }

        .progress-step.active .step-label {
            color: var(--secondary-blue);
            font-weight: 600;
        }

        .progress-step.completed .step-label {
            color: var(--success-green);
            font-weight: 600;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 32px;
            margin-bottom: 32px;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 24px;
            background: var(--white);
            border: 2px solid var(--medium-grey);
            overflow: hidden;
            transition: var(--transition);
        }

        .form-section:hover {
            border-color: var(--secondary-blue);
        }

        .section-header {
            background: var(--primary-grey);
            color: var(--white);
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            user-select: none;
        }

        .section-header:hover {
            background: var(--dark-grey);
        }

        .section-header.active {
            background: var(--secondary-blue);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header i.fa-chevron-down {
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .section-header.active i.fa-chevron-down {
            transform: rotate(180deg);
        }

        .section-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--light-grey);
        }

        .section-content.active {
            max-height: 3000px;
            padding: 32px 24px;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-grey);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .required::after {
            content: '*';
            color: var(--danger-red);
            margin-left: 4px;
            font-weight: 700;
        }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--medium-grey);
            background: var(--white);
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-grey);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-control:hover {
            border-color: var(--primary-grey);
        }

        /* Select Dropdowns */
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Section Headings */
        .section-content h4 {
            margin: 32px 0 24px 0;
            color: var(--dark-grey);
            font-size: 1.3rem;
            font-weight: 600;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--medium-grey);
        }

        /* Payment Details Special Layout */
        .payment-layout {
            display: flex;
            gap: 32px;
            margin-top: 24px;
        }

        .payment-section {
            flex: 1;
            background: var(--white);
            padding: 24px;
            border: 2px solid var(--medium-grey);
        }

        .payment-section h4 {
            margin: 0 0 20px 0;
            color: var(--dark-grey);
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rate-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .rate-input-group input {
            flex: 2;
        }

        .rate-input-group select {
            flex: 1;
            min-width: 120px;
        }

        .rate-currency {
            font-weight: 700;
            color: var(--secondary-blue);
            font-size: 1.1rem;
            background: var(--light-grey);
            padding: 14px 16px;
            border: 2px solid var(--medium-grey);
            min-width: 50px;
            text-align: center;
            border-radius: 0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid var(--medium-grey);
        }

        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 140px;
            justify-content: center;
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

        .btn-danger {
            background: var(--danger-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        /* Highlight Effect */
        .highlight {
            background: #fff3cd !important;
            border-color: #ffc107 !important;
            animation: highlightPulse 2s ease-in-out;
        }

        @keyframes highlightPulse {
            0%, 100% { background: #fff3cd; }
            50% { background: #ffe69c; }
        }

        /* Hidden Fields */
        .hidden-field {
            display: none;
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Responsive Design */
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
            
            .search {
                max-width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .payment-layout {
                flex-direction: column;
                gap: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .progress-bar {
                flex-wrap: wrap;
                gap: 16px;
            }
            
            .progress-step {
                flex: 0 0 calc(50% - 8px);
            }
            
            .step-label {
                font-size: 0.8rem;
            }
            
            .form-container,
            .progress-container {
                padding: 20px;
            }
            
            .section-content.active {
                padding: 24px 16px;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 16px;
            }
            
            .dashboard-heading {
                font-size: 1.75rem;
            }
            
            .form-container,
            .progress-container {
                padding: 16px;
            }
            
            .section-header {
                padding: 16px;
            }
            
            .section-header h3 {
                font-size: 1.1rem;
            }
            
            .section-content.active {
                padding: 20px 12px;
            }
            
            .payment-section {
                padding: 16px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            
            .progress-step {
                flex: 0 0 100%;
            }
            
            .step-number {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .rate-input-group {
                flex-wrap: wrap;
                gap: 8px;
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
        
        /* Animation for section transitions */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-content.active {
            animation: fadeIn 0.3s ease-out;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-grey);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-grey);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-grey);
        }


        /* Sidebar Styles - ADD THIS BLOCK */
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

    </style>
</head>

<body>
    

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
            <a href="paperwork.php" class="sidebar-item active">
                <i class="fas fa-file-alt"></i>
                <span>Paperwork</span>
            </a>
            <a href="activitylogs.php" class="sidebar-item">
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


    <div class="main" id="mainContent">
        <!-- Topbar -->
        <div class="topbar">
            <div class="heading-container">
                <h1 class="dashboard-heading">Add New Consultant</h1>
            </div>
            
            <div class="search">
                <input type="text" id="searchField" placeholder="Search for fields...">
                <i class="fas fa-search"></i>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if(isset($formSubmitted) && $formSubmitted): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Consultant information has been successfully submitted!</span>
        </div>
        <?php endif; ?>

        <!-- Progress Container -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div class="step-label">Consultant Info</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-label">Employer Details</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div class="step-label">Collaboration</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">4</div>
                    <div class="step-label">Recruiter Info</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">5</div>
                    <div class="step-label">Project Details</div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <form id="consultantForm" method="post" action="process_form.php">
                
                <!-- Consultant Details Section -->
                <div class="form-section">
                    <div class="section-header active" onclick="toggleSection(this)">
                        <h3><i class="fas fa-user-tie"></i> Consultant Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content active">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Candidate First Name</label>
                                <input type="text" class="form-control" name="cfirst_name" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Candidate Last Name</label>
                                <input type="text" class="form-control" name="clast_name" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Ceipal Applicant ID</label>
                                <input type="number" class="form-control" name="ceipal_id" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Candidate LinkedIn URL</label>
                                <input type="text" class="form-control" name="clinkedin_url" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Date of Birth</label>
                                <input type="date" class="form-control" name="cdob" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Mobile Number</label>
                                <input type="number" class="form-control" name="cmobilenumber" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Email</label>
                                <input type="email" class="form-control" name="cemail" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Location (city, state)</label>
                                <input type="text" class="form-control" name="clocation" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Home Address</label>
                                <input type="text" class="form-control" name="chomeaddress" required>
                            </div>
                            <div class="form-group">
                                <label class="required">SSN (Last 4 digits)</label>
                                <input type="text" class="form-control" name="cssn" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Work Authorization Status</label>
                                <select class="form-control" name="cwork_authorization_status" id="work-status" required>
                                    <option value="" disabled selected>Select Work Status</option>
                                    <?php echo outputDropdownOptions('work_authorization', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="v_validate_status_field">
                                <label class="required">V-Validate Status</label>
                                <select class="form-control" name="cv_validate_status" id="v_validate_status">
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('v_validate_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Has Certifications</label>
                                <select class="form-control" id="has_certifications" name="has_certifications" onchange="toggleCertificationsField()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('certification_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="certifications_field">
                                <label class="required">Certification Names</label>
                                <input type="text" class="form-control" id="ccertifications" name="ccertifications" placeholder="Enter certification names separated by commas" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Overall Experience</label>
                                <input type="text" class="form-control" name="coverall_experience" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Recent Job Title</label>
                                <input type="text" class="form-control" name="crecent_job_title" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Candidate Source</label>
                                <select class="form-control" name="ccandidate_source" id="candidate_source" onchange="toggleOptions()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('candidate_source', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="shared_field">
                                <label id="shared_label" class="required"></label>
                                <select class="form-control" name="shared_option" id="shared_option" onchange="combineSource()">
                                    <option value="" disabled selected>Select Option</option>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="sourced_person_field">
                                <label class="required">Sourced By</label>
                                <select class="form-control" name="sourced_person" id="sourced_person" onchange="combineSource()" required>
                                    <option value="" disabled selected>Select Person</option>
                                    <?php echo outputDropdownOptions('sourced_person', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Resume Attached</label>
                                <select class="form-control" name="cresume_attached" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('resume_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Photo-ID Attached</label>
                                <select class="form-control" name="cphoto_id_attached" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('photo_id_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">WA Attached</label>
                                <select class="form-control" name="cwa_attached" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('wa_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Any Other Specify</label>
                                <input type="text" class="form-control" name="cany_other_specify" required>
                            </div>
                        </div>
                    </div>
                </div>                        <input type="hidden" id="final_candidate_source_hidden" name="final_candidate_source" value="">
                        <input type="hidden" id="client_rate_combined_hidden" name="client_rate_combined" value="">
                        <input type="hidden" id="pay_rate_combined_hidden" name="pay_rate_combined" value="">
                        <input type="hidden" id="final_benefits_hidden" name="benifits" value="">

                <!-- Employer Details Section -->
                <div class="form-section">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3><i class="fas fa-building"></i> Employer Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Own Corporation</label>
                                <select class="form-control" name="cemployer_own_corporation" id="own_corporation" onchange="toggleEmployerFields()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('own_corporation', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Employer Corporation Name</label>
                                <input type="text" class="form-control" name="employer_corporation_name" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">FED ID Number</label>
                                <input type="text" class="form-control" name="fed_id_number" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Name (Signing authority)</label>
                                <input type="text" class="form-control" name="contact_person_name" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Designation</label>
                                <input type="text" class="form-control" name="contact_person_designation" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Address</label>
                                <input type="text" class="form-control" name="contact_person_address" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Phone Number</label>
                                <input type="text" class="form-control" name="contact_person_phone_number" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Extension Number</label>
                                <input type="text" class="form-control" name="contact_person_extension_number" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Contact Person Email ID</label>
                                <input type="text" class="form-control" name="contact_person_email_id" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Benchsale Recruiter Name</label>
                                <input type="text" class="form-control" name="benchsale_recruiter_name" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Benchsale Recruiter Phone Number</label>
                                <input type="text" class="form-control" name="benchsale_recruiter_phone_number" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Benchsale Recruiter Extension Number</label>
                                <input type="text" class="form-control" name="benchsale_recruiter_extension_number" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Benchsale Recruiter Email ID</label>
                                <input type="text" class="form-control" name="benchsale_recruiter_email_id" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Website Link</label>
                                <input type="text" class="form-control" name="website_link" required>
                            </div>
                            <div class="form-group employer-field">
                                <label class="required">Employer LinkedIn URL</label>
                                <input type="text" class="form-control" name="employer_linkedin_url" required>
                            </div>
                        </div>

                        <h4>Additional Employer Details</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Employer Type</label>
                                <select class="form-control" name="employer_type" id="employer_type" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('employer_type', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group" id="employer_corporation_name_field">
                                <label class="required">Employer Corporation Name</label>
                                <input type="text" class="form-control" name="employer_corporation_name1" id="employer_corporation_name1" required>
                            </div>
                            <div class="form-group" id="fed_id_number_field">
                                <label class="required">FED ID Number</label>
                                <input type="text" class="form-control" name="fed_id_number1" id="fed_id_number1" required>
                            </div>
                            <div class="form-group" id="contact_person_name_field">
                                <label class="required">Contact Person Name (Signing authority)</label>
                                <input type="text" class="form-control" name="contact_person_name1" id="contact_person_name1" required>
                            </div>
                            <div class="form-group" id="contact_person_designation_field">
                                <label class="required">Contact Person Designation</label>
                                <input type="text" class="form-control" name="contact_person_designation1" id="contact_person_designation1" required>
                            </div>
                            <div class="form-group" id="contact_person_address_field">
                                <label class="required">Contact Person Address</label>
                                <input type="text" class="form-control" name="contact_person_address1" id="contact_person_address1" required>
                            </div>
                            <div class="form-group" id="contact_person_phone_number_field">
                                <label class="required">Contact Person Phone Number</label>
                                <input type="text" class="form-control" name="contact_person_phone_number1" id="contact_person_phone_number1" required>
                            </div>
                            <div class="form-group" id="contact_person_extension_number_field">
                                <label class="required">Contact Person Extension Number</label>
                                <input type="text" class="form-control" name="contact_person_extension_number1" id="contact_person_extension_number1" required>
                            </div>
                            <div class="form-group" id="contact_person_email_id_field">
                                <label class="required">Contact Person Email ID</label>
                                <input type="text" class="form-control" name="contact_person_email_id1" id="contact_person_email_id1" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collaboration Details Section -->
                <div class="form-section">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3><i class="fas fa-handshake"></i> Collaboration Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Collaborate</label>
                                <select class="form-control" name="collaboration_collaborate" id="collaboration_collaborate" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('collaboration_status', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="delivery_manager_field">
                                <label class="required">Delivery Manager</label>
                                <select class="form-control" name="delivery_manager" id="delivery_manager" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('delivery_manager', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="delivery_account_lead_field">
                                <label class="required">Delivery Account Lead</label>
                                <select class="form-control" name="delivery_account_lead" id="delivery_account_lead" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('delivery_account_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="team_lead_field">
                                <label class="required">Team Lead</label>
                                <select class="form-control" name="team_lead" id="team_lead" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('team_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="associate_team_lead_field">
                                <label class="required">Associate Team Lead</label>
                                <select class="form-control" name="associate_team_lead" id="associate_team_lead" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('associate_team_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recruiter Details Section -->
                <div class="form-section">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3><i class="fas fa-user-friends"></i> Recruiter Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Business Unit</label>
                                <select class="form-control" name="business_unit" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('business_unit', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Client Account Lead</label>
                                <select class="form-control" name="client_account_lead" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('client_account_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Client Partner</label>
                                <select class="form-control" name="client_partner" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('client_partner', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Associate Director Delivery</label>
                                <select class="form-control" name="associate_director_delivery" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('associate_director_delivery', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Delivery Manager</label>
                                <select class="form-control" name="delivery_manager1" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('delivery_manager', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Delivery Account Lead</label>
                                <select class="form-control" name="delivery_account_lead1" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('delivery_account_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Team Lead</label>
                                <select class="form-control" name="team_lead1" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('team_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Associate Team Lead</label>
                                <select class="form-control" name="associate_team_lead1" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('associate_team_lead', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Recruiter Name</label>
                                <select class="form-control" name="recruiter_name" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('recruiter_name', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">PT Support</label>
                                <select class="form-control" name="pt_support" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('pt_support', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">PT Ownership</label>
                                <select class="form-control" name="pt_ownership" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('pt_ownership', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Details Section -->
                <div class="form-section">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3><i class="fas fa-project-diagram"></i> Project Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">GEO</label>
                                <select class="form-control" name="geo" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('geo', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Entity</label>
                                <select class="form-control" name="entity" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('entity', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Client</label>
                                <input type="text" class="form-control" name="client" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Client Manager</label>
                                <input type="text" class="form-control" name="client_manager" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Client Manager Email ID</label>
                                <input type="email" class="form-control" name="client_manager_email_id" required>
                            </div>
                            <div class="form-group">
                                <label class="required">End Client</label>
                                <input type="text" class="form-control" name="end_client" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Business Track</label>
                                <select class="form-control" name="business_track" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('business_track', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Industry</label>
                                <input type="text" class="form-control" name="industry" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Experience in Expertise Role | Hands on</label>
                                <input type="number" class="form-control" name="experience_in_expertise_role" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Job Code</label>
                                <input type="text" class="form-control" name="job_code" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Job Title / Role</label>
                                <input type="text" class="form-control" name="job_title" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Primary Skill</label>
                                <input type="text" class="form-control" name="primary_skill" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Secondary Skill</label>
                                <input type="text" class="form-control" name="secondary_skill" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Term</label>
                                <select class="form-control" name="term" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('term', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Duration</label>
                                <input type="text" class="form-control" name="duration" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Project Location</label>
                                <input type="text" class="form-control" name="project_location" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label class="required">End Date</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Type</label>
                                <select class="form-control" name="type" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('type', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Section -->
                <div class="form-section">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3><i class="fas fa-money-bill-wave"></i> Payment Details</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="payment-layout">
                            <!-- BILL RATE Section -->
                            <div class="payment-section">
                                <h4>Bill Rate</h4>
                                <div class="form-group">
                                    <label class="required">Tax Term</label>
                                    <select class="form-control" name="bill_type" required>
                                        <option value="" disabled selected>Select option</option>
                                        <?php echo outputDropdownOptions('tax_term', $allDropdownOptions, false); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="required">Client Bill Rate</label>
                                    <div class="rate-input-group">
                                        <input type="number" class="form-control" name="clientrate" step="0.01" min="0" placeholder="Enter amount" required>
                                        <span class="rate-currency">USD</span>
                                        <select class="form-control" name="bill_rate_period" required>
                                            <option value="" disabled selected>Select period</option>
                                            <option value="hour">/hour</option>
                                            <option value="day">/day</option>
                                            <option value="week">/week</option>
                                            <option value="month">/month</option>
                                            <option value="annum">/annum</option>
                                            <option value="project">/project</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- PAY RATE Section -->
                            <div class="payment-section">
                                <h4>Pay Rate</h4>
                                <div class="form-group">
                                    <label class="required">Tax Term</label>
                                    <select class="form-control" name="pay_tax_term" required>
                                        <option value="" disabled selected>Select option</option>
                                        <?php echo outputDropdownOptions('tax_term', $allDropdownOptions, false); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="required">Pay Rate</label>
                                    <div class="rate-input-group">
                                        <input type="number" class="form-control" name="payrate" step="0.01" min="0" placeholder="Enter amount" required>
                                        <span class="rate-currency">USD</span>
                                        <select class="form-control" name="pay_rate_period" required>
                                            <option value="" disabled selected>Select period</option>
                                            <option value="hour">/hour</option>
                                            <option value="day">/day</option>
                                            <option value="week">/week</option>
                                            <option value="month">/month</option>
                                            <option value="annum">/annum</option>
                                            <option value="project">/project</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Payment Fields -->
                        <div class="form-grid" style="margin-top: 32px;">
                            <div class="form-group">
                                <label class="required">Margin</label>
                                <input type="number" class="form-control" name="margin" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Benefits</label>
                                <select class="form-control" name="benefits_type" id="benefits_type" onchange="toggleBenefitsField()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <option value="with_benefits">With Benefits</option>
                                    <option value="without_benefits">Without Benefits</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="benefits_others_field">
                                <label class="required">Specify Benefits</label>
                                <input type="text" class="form-control" name="benefits_others" id="benefits_others" placeholder="Please specify the benefits details" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Additional Vendor Fee (If applicable)</label>
                                <input type="text" class="form-control" name="vendor_fee" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Margin Deviation Approval (Yes/No)</label>
                                <select class="form-control" name="margin_deviation_approval" id="margin_deviation_approval" onchange="toggleMarginDeviationReason()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('yes_no', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="margin_deviation_reason_field">
                                <label class="required">Margin Deviation Reason</label>
                                <input type="text" class="form-control" name="margin_deviation_reason" id="margin_deviation_reason" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Ratecard Adherence (Yes/No)</label>
                                <select class="form-control" name="ratecard_adherence" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('yes_no', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Ratecard Deviation Approved (Yes/No)</label>
                                <select class="form-control" name="ratecard_deviation_approved" id="ratecard_deviation_approved" onchange="toggleRatecardDeviationReason()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('yes_no', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="ratecard_deviation_reason_field">
                                <label class="required">Ratecard Deviation Reason</label>
                                <input type="text" class="form-control" name="ratecard_deviation_reason" id="ratecard_deviation_reason" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Payment Term</label>
                                <input type="text" class="form-control" name="payment_term" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Payment Term Approval (Yes/No)</label>
                                <select class="form-control" name="payment_term_approval" id="payment_term_approval" onchange="togglePaymentTermDeviationReason()" required>
                                    <option value="" disabled selected>Select option</option>
                                    <?php echo outputDropdownOptions('yes_no', $allDropdownOptions, false); ?>
                                </select>
                            </div>
                            <div class="form-group hidden-field" id="payment_term_deviation_reason_field">
                                <label class="required">Payment Term Deviation Reason</label>
                                <input type="text" class="form-control" name="payment_term_deviation_reason" id="payment_term_deviation_reason" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="saveAsDraft">
                        <i class="fas fa-save"></i> Save as Draft
                    </button>
                    <button type="reset" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
    
    <script>
        // Initialize form functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            setupEventListeners();
            updateProgress();
            checkForSavedData();
            resetInactivityTimer();
        });

        // Toggle section visibility
        function toggleSection(header) {
            const content = header.nextElementSibling;
            const isActive = content.classList.contains('active');
            
            if (isActive) {
                content.classList.remove('active');
                header.classList.remove('active');
            } else {
                content.classList.add('active');
                header.classList.add('active');
            }
        }

        // Initialize form
        function initializeForm() {
            // Make first section active by default
            const firstSection = document.querySelector('.section-content');
            if (firstSection) {
                firstSection.classList.add('active');
                firstSection.previousElementSibling.classList.add('active');
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Work authorization status
            document.getElementById('work-status').addEventListener('change', function() {
                const field = document.getElementById('v_validate_status_field');
                const input = document.getElementById('v_validate_status');
                
                if (this.value === 'h1b') {
                    field.classList.remove('hidden-field');
                    input.required = true;
                } else {
                    field.classList.add('hidden-field');
                    input.required = false;
                    input.value = 'NA';
                }
                updateProgress();
            });

            // Collaboration status
            document.getElementById('collaboration_collaborate').addEventListener('change', function() {
                const fields = [
                    {field: 'delivery_manager_field', input: 'delivery_manager'},
                    {field: 'delivery_account_lead_field', input: 'delivery_account_lead'},
                    {field: 'team_lead_field', input: 'team_lead'},
                    {field: 'associate_team_lead_field', input: 'associate_team_lead'}
                ];
                
                fields.forEach(item => {
                    const field = document.getElementById(item.field);
                    const input = document.getElementById(item.input);
                    
                    if (this.value === 'yes') {
                        field.classList.remove('hidden-field');
                        input.required = true;
                    } else {
                        field.classList.add('hidden-field');
                        input.required = false;
                        input.value = 'NA';
                    }
                });
                updateProgress();
            });

            // Employer type
            document.getElementById('employer_type').addEventListener('change', function() {
                const employerFields = [
                    {field: 'employer_corporation_name_field', input: 'employer_corporation_name1'},
                    {field: 'fed_id_number_field', input: 'fed_id_number1'},
                    {field: 'contact_person_name_field', input: 'contact_person_name1'},
                    {field: 'contact_person_designation_field', input: 'contact_person_designation1'},
                    {field: 'contact_person_address_field', input: 'contact_person_address1'},
                    {field: 'contact_person_phone_number_field', input: 'contact_person_phone_number1'},
                    {field: 'contact_person_extension_number_field', input: 'contact_person_extension_number1'},
                    {field: 'contact_person_email_id_field', input: 'contact_person_email_id1'}
                ];
                
                employerFields.forEach(item => {
                    const field = document.getElementById(item.field);
                    const input = document.getElementById(item.input);
                    
                    if (this.value === 'NA') {
                        field.classList.add('hidden-field');
                        input.required = false;
                        if (input.name.includes('phone') || input.name.includes('extension')) {
                            input.value = '00000';
                        } else {
                            input.value = 'NA';
                        }
                    } else {
                        field.classList.remove('hidden-field');
                        input.required = true;
                        input.value = '';
                    }
                });
                updateProgress();
            });

            // Search functionality
            document.getElementById('searchField').addEventListener('keyup', function() {
                const searchQuery = this.value.toLowerCase();
                const formGroups = document.querySelectorAll('.form-group');
                
                // Remove existing highlights
                document.querySelectorAll('.highlight').forEach(el => {
                    el.classList.remove('highlight');
                });

                if (searchQuery.length < 2) return;

                formGroups.forEach(function(group) {
                    const label = group.querySelector('label');
                    if (!label) return;
                    
                    const labelText = label.innerText.toLowerCase();
                    
                    if (labelText.includes(searchQuery)) {
                        // Open the section if it's closed
                        const sectionContent = group.closest('.section-content');
                        if (sectionContent && !sectionContent.classList.contains('active')) {
                            toggleSection(sectionContent.previousElementSibling);
                        }
                        
                        // Highlight and scroll to the field
                        group.classList.add('highlight');
                        group.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Remove highlight after delay
                        setTimeout(() => {
                            group.classList.remove('highlight');
                        }, 3000);
                        
                        return false; // Stop after first match
                    }
                });
            });

            // Pay type listener for rate units (removed - no longer needed)
            
            // Add event listeners for rate combination
            document.querySelector('input[name="clientrate"]').addEventListener('input', combineClientRate);
            document.querySelector('select[name="bill_rate_period"]').addEventListener('change', combineClientRate);
            document.querySelector('select[name="bill_type"]').addEventListener('change', combineClientRate);
            
            document.querySelector('input[name="payrate"]').addEventListener('input', combinePayRate);
            document.querySelector('select[name="pay_rate_period"]').addEventListener('change', combinePayRate);
            document.querySelector('select[name="pay_tax_term"]').addEventListener('change', combinePayRate);
            
            // Add event listener for benefits others field
            document.querySelector('input[name="benefits_others"]').addEventListener('input', function() {
                const finalBenefitsHidden = document.getElementById("final_benefits_hidden");
                finalBenefitsHidden.value = this.value;
            });
        }

        // Rate combination functions
        function combineClientRate() {
            const rate = document.querySelector('input[name="clientrate"]').value;
            const period = document.querySelector('select[name="bill_rate_period"]').value;
            const taxTerm = document.querySelector('select[name="bill_type"]').value;
            
            if (rate && period && taxTerm) {
                let periodText = '';
                switch(period) {
                    case 'hour': periodText = '/hour'; break;
                    case 'day': periodText = '/day'; break;
                    case 'week': periodText = '/week'; break;
                    case 'month': periodText = '/month'; break;
                    case 'year': periodText = '/year'; break;
                    case 'project': periodText = '/project'; break;
                    default: periodText = '/' + period;
                }
                
                const combined = `${rate} USD ${periodText} on ${taxTerm}`;
                document.getElementById('client_rate_combined_hidden').value = combined;
            }
        }
        
        function combinePayRate() {
            const rate = document.querySelector('input[name="payrate"]').value;
            const period = document.querySelector('select[name="pay_rate_period"]').value;
            const taxTerm = document.querySelector('select[name="pay_tax_term"]').value;
            
            if (rate && period && taxTerm) {
                let periodText = '';
                switch(period) {
                    case 'hour': periodText = '/hour'; break;
                    case 'day': periodText = '/day'; break;
                    case 'week': periodText = '/week'; break;
                    case 'month': periodText = '/month'; break;
                    case 'year': periodText = '/year'; break;
                    case 'project': periodText = '/project'; break;
                    default: periodText = '/' + period;
                }
                
                const combined = `${rate} USD ${periodText} on ${taxTerm}`;
                document.getElementById('pay_rate_combined_hidden').value = combined;
            }
        }

        // Candidate Source Functions
        function toggleOptions() {
            const source = document.getElementById("candidate_source").value;
            const sharedField = document.getElementById("shared_field");
            const sharedLabel = document.getElementById("shared_label");
            const sharedOption = document.getElementById("shared_option");
            const sourcedPersonField = document.getElementById("sourced_person_field");
            const sourcedPerson = document.getElementById("sourced_person");
            
            // Reset fields
            sharedField.classList.add('hidden-field');
            sourcedPersonField.classList.add('hidden-field');
            sharedOption.required = false;
            sourcedPerson.required = false;
            
            // Reset options
            sharedOption.innerHTML = '<option value="" disabled selected>Select Option</option>';
            
            // Set initial value
            document.getElementById("final_candidate_source_hidden").value = source;
            
            if (source === "cx_bench") {
                sharedLabel.innerHTML = "CX Bench Options";
                sharedOption.innerHTML += `
                    <option value="sushmitha_s">Sushmitha S</option>
                    <option value="swarnabharathi_mu">Swarnabharathi M U</option>
                `;
                sharedField.classList.remove('hidden-field');
                sharedOption.required = true;
            } 
            else if (source === "linkedin_rps") {
                sharedLabel.innerHTML = "LinkedIn RPS Options";
                sharedOption.innerHTML += `
                    <option value="balaji_kumar">Balaji Kumar</option>
                    <option value="balaji_mohan">Balaji Mohan</option>
                    <option value="dharani_g">Dharani G</option>
                    <option value="dhinakar_nk">Dhinakar N K</option>
                    <option value="elizabeth_g">Elizabeth G</option>
                    <option value="jaffar_j">Jaffar J</option>
                    <option value="jayasri_g">Jayasri G</option>
                    <option value="kirubakaran_f">Kirubakaran F</option>
                    <option value="praveen_kumar_m">Praveen Kumar M</option>
                    <option value="shyam_prasath_s">Shyam Prasath S</option>
                    <option value="tharun_kumar_g">Tharun Kumar G</option>
                    <option value="vanitha_b">Vanitha B</option>
                `;
                sharedField.classList.remove('hidden-field');
                sharedOption.required = true;
            } 
            else if (source === "srm") {
                sharedLabel.innerHTML = "SRM Options";
                sharedOption.innerHTML += `
                    <option value="harish_babu_m">Harish Babu M</option>
                `;
                sharedField.classList.remove('hidden-field');
                sharedOption.required = true;
            } 
            else if (source === "linkedin_sourcer") {
                sharedLabel.innerHTML = "LinkedIn Sourcer Options";
                sharedOption.innerHTML += `
                    <option value="karthik_t">Karthik T</option>
                `;
                sharedField.classList.remove('hidden-field');
                sharedOption.required = true;
            } 
            else if (source === "sourcing") {
                sourcedPersonField.classList.remove('hidden-field');
                sourcedPerson.required = true;
            }
            
            combineSource();
            updateProgress();
        }

        function combineSource() {
            const source = document.getElementById("candidate_source").value;
            
            if (source === "sourcing") {
                const sourcedPerson = document.getElementById("sourced_person").value;
                if (sourcedPerson) {
                    document.getElementById("final_candidate_source_hidden").value = source + " - " + sourcedPerson;
                }
            } else if (["cx_bench", "linkedin_rps", "srm", "linkedin_sourcer"].includes(source)) {
                const sharedOption = document.getElementById("shared_option").value;
                if (sharedOption) {
                    const sharedOptionText = document.getElementById("shared_option").options[
                        document.getElementById("shared_option").selectedIndex
                    ].text;
                    document.getElementById("final_candidate_source_hidden").value = source + " - " + sharedOptionText;
                }
            }
        }

        // Toggle Functions
        function toggleCertificationsField() {
            const hasCertifications = document.getElementById("has_certifications").value;
            const certificationsField = document.getElementById("certifications_field");
            const certificationsInput = document.getElementById("ccertifications");
            
            if (hasCertifications === "yes") {
                certificationsField.classList.remove('hidden-field');
                certificationsInput.required = true;
                certificationsInput.value = "";
            } else if (hasCertifications === "no") {
                certificationsField.classList.add('hidden-field');
                certificationsInput.required = false;
                certificationsInput.value = "NA";
            } else {
                certificationsField.classList.add('hidden-field');
                certificationsInput.required = false;
                certificationsInput.value = "";
            }
            updateProgress();
        }

        function toggleEmployerFields() {
            const ownCorporation = document.getElementById("own_corporation").value;
            const employerFields = document.querySelectorAll(".employer-field");
            
            if (ownCorporation === "NA") {
                employerFields.forEach(field => {
                    field.classList.add('hidden-field');
                    const input = field.querySelector("input, select");
                    if (input) {
                        input.required = false;
                        if (input.name.includes('phone') || input.name.includes('extension')) {
                            input.value = '00000';
                        } else {
                            input.value = 'NA';
                        }
                    }
                });
            } else {
                employerFields.forEach(field => {
                    field.classList.remove('hidden-field');
                    const input = field.querySelector("input, select");
                    if (input) {
                        input.required = true;
                        input.value = '';
                    }
                });
            }
            updateProgress();
        }

        function toggleMarginDeviationReason() {
            const approval = document.getElementById("margin_deviation_approval").value;
            const reasonField = document.getElementById("margin_deviation_reason_field");
            const reasonInput = document.getElementById("margin_deviation_reason");
            
            if (approval === "No") {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "NA";
            } else if (approval === "Yes") {
                reasonField.classList.remove('hidden-field');
                reasonInput.required = true;
                reasonInput.value = "";
            } else {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "";
            }
            updateProgress();
        }

        function toggleRatecardDeviationReason() {
            const approval = document.getElementById("ratecard_deviation_approved").value;
            const reasonField = document.getElementById("ratecard_deviation_reason_field");
            const reasonInput = document.getElementById("ratecard_deviation_reason");
            
            if (approval === "No") {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "NA";
            } else if (approval === "Yes") {
                reasonField.classList.remove('hidden-field');
                reasonInput.required = true;
                reasonInput.value = "";
            } else {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "";
            }
            updateProgress();
        }

        function togglePaymentTermDeviationReason() {
            const approval = document.getElementById("payment_term_approval").value;
            const reasonField = document.getElementById("payment_term_deviation_reason_field");
            const reasonInput = document.getElementById("payment_term_deviation_reason");
            
            if (approval === "No") {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "NA";
            } else if (approval === "Yes") {
                reasonField.classList.remove('hidden-field');
                reasonInput.required = true;
                reasonInput.value = "";
            } else {
                reasonField.classList.add('hidden-field');
                reasonInput.required = false;
                reasonInput.value = "";
            }
            updateProgress();
        }

        function toggleBenefitsField() {
            const benefitsType = document.getElementById("benefits_type").value;
            const benefitsOthersField = document.getElementById("benefits_others_field");
            const benefitsOthersInput = document.getElementById("benefits_others");
            const finalBenefitsHidden = document.getElementById("final_benefits_hidden");
            
            if (benefitsType === "others") {
                benefitsOthersField.classList.remove('hidden-field');
                benefitsOthersInput.required = true;
                benefitsOthersInput.value = "";
                finalBenefitsHidden.value = "";
            } else {
                benefitsOthersField.classList.add('hidden-field');
                benefitsOthersInput.required = false;
                if (benefitsType === "with_benefits") {
                    benefitsOthersInput.value = "With Benefits";
                    finalBenefitsHidden.value = "With Benefits";
                } else if (benefitsType === "without_benefits") {
                    benefitsOthersInput.value = "Without Benefits";
                    finalBenefitsHidden.value = "Without Benefits";
                } else {
                    benefitsOthersInput.value = "";
                    finalBenefitsHidden.value = "";
                }
            }
            updateProgress();
        }

        // Progress tracking
        function updateProgress() {
            const form = document.getElementById('consultantForm');
            const requiredFields = form.querySelectorAll('[required]');
            const totalRequired = requiredFields.length;
            let completedRequired = 0;
            
            requiredFields.forEach(field => {
                const isHidden = field.closest('.hidden-field') !== null;
                if (!isHidden && field.value && field.value !== '' && field.value !== 'Select option') {
                    completedRequired++;
                }
            });
            
            const progressSteps = document.querySelectorAll('.progress-step');
            const completionPercentage = completedRequired / totalRequired;
            
            // Reset all steps
            progressSteps.forEach(step => {
                step.classList.remove('active', 'completed');
            });
            
            // Calculate active step
            let activeStep = 0;
            if (completionPercentage >= 0.8) activeStep = 4;
            else if (completionPercentage >= 0.6) activeStep = 3;
            else if (completionPercentage >= 0.4) activeStep = 2;
            else if (completionPercentage >= 0.2) activeStep = 1;
            
            // Update steps
            for (let i = 0; i < progressSteps.length; i++) {
                if (i < activeStep) {
                    progressSteps[i].classList.add('completed');
                } else if (i === activeStep) {
                    progressSteps[i].classList.add('active');
                }
            }
        }

        // Draft functionality
        function checkForSavedData() {
            const savedData = localStorage.getItem('savedFormData');
            if (savedData) {
                Swal.fire({
                    title: 'Draft Found',
                    text: 'Would you like to load your previously saved draft?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, load it',
                    cancelButtonText: 'No, start fresh'
                }).then((result) => {
                    if (result.isConfirmed) {
                        loadDraft(savedData);
                    }
                });
            }
        }

        function loadDraft(draftData) {
            const formData = JSON.parse(draftData);
            const form = document.getElementById('consultantForm');
            
            Object.keys(formData).forEach(key => {
                const input = form.elements[key];
                if (input) {
                    input.value = formData[key];
                    
                    // Trigger change events for dependent fields
                    if (['cwork_authorization_status', 'collaboration_collaborate', 'employer_type', 
                         'cemployer_own_corporation', 'margin_deviation_approval', 
                         'ratecard_deviation_approved', 'payment_term_approval', 
                         'ccandidate_source'].includes(key)) {
                        const event = new Event('change');
                        input.dispatchEvent(event);
                    }
                }
            });
            
            updateProgress();
            
            Swal.fire({
                icon: 'success',
                title: 'Draft Loaded',
                text: 'Your saved draft has been loaded successfully.',
                confirmButtonColor: '#007bff'
            });
        }

        // Save as draft
        document.getElementById('saveAsDraft').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('consultantForm'));
            const formObject = {};
            
            formData.forEach((value, key) => {
                formObject[key] = value;
            });
            
            localStorage.setItem('savedFormData', JSON.stringify(formObject));
            
            Swal.fire({
                icon: 'success',
                title: 'Draft Saved',
                text: 'Your form data has been saved as a draft. You can continue later.',
                confirmButtonColor: '#007bff'
            });
        });

        // Form submission
        document.getElementById('consultantForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Set final candidate source
            const candidateSource = document.getElementById('candidate_source').value;
            let finalSource = candidateSource;
            
            if (candidateSource === 'sourcing') {
                const sourcedPerson = document.getElementById('sourced_person').value;
                if (sourcedPerson) {
                    finalSource = candidateSource + ' - ' + sourcedPerson;
                }
            } else if (['cx_bench', 'linkedin_rps', 'srm', 'linkedin_sourcer'].includes(candidateSource)) {
                const sharedOption = document.getElementById('shared_option').value;
                if (sharedOption) {
                    finalSource = candidateSource + ' - ' + sharedOption;
                }
            }
            
            document.getElementById('final_candidate_source_hidden').value = finalSource;
            
            // Ensure rate combinations are set
            combineClientRate();
            combinePayRate();
            
            // Create FormData and submit
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            fetch('process_form.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        localStorage.removeItem('savedFormData');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: `Form has been successfully submitted! Your reference code is: ${data.pwcode}`,
                            confirmButtonColor: '#007bff'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Failed',
                            text: data.message || 'There was an error submitting the form. Please try again.',
                            confirmButtonColor: '#007bff'
                        });
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'The server returned an invalid response. Please check the console for details.',
                        confirmButtonColor: '#007bff'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'There was a network error. Please try again.',
                    confirmButtonColor: '#007bff'
                });
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Inactivity timer
        let inactivityTimer;
        
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(showInactivityAlert, 5 * 60 * 1000); // 5 minutes
        }
        
        function showInactivityAlert() {
            Swal.fire({
                title: 'Still working?',
                text: 'Your form has been idle for a while. Would you like to save your progress as a draft?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Save as Draft',
                cancelButtonText: 'Continue Working'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('saveAsDraft').click();
                } else {
                    resetInactivityTimer();
                }
            });
        }
        
        // Reset timer on user activity
        ['mousemove', 'keypress', 'click', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer);
        });

        // Sidebar toggle functionality
        const updateMainMargin = () => {
            const main = document.querySelector('.main');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth > 1200) {
                main.style.marginLeft = sidebar && sidebar.classList.contains('collapsed') ? '105px' : '290px';
            } else {
                main.style.marginLeft = '0';
            }
        };

        const sidebarToggler = document.querySelector('.toggle');
        if (sidebarToggler) {
            sidebarToggler.addEventListener('click', updateMainMargin);
        }
        window.addEventListener('resize', updateMainMargin);
    </script>

    <script>
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