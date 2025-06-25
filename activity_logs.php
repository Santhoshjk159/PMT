<?php
require 'db.php'; // Include your database connection
require_once 'ActivityLogger.php'; // Include the activity logger

// Start session and check login
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Get logged-in user's email, name and role
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT name, role FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

// Check if user exists
if ($userData) {
    $userRole = $userData['role'];
    $userName = $userData['name'];
} else {
    // Handle case where user does not exist
    echo "User not found.";
    exit();
}

// Check if user has admin rights to view logs
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');
if (!$isAdmin) {
    // Show access denied message with faded background for non-admin users
    echo '
    <style>
        /* Overlay for non-admin users */
        .access-denied-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Hide main content behind overlay */
        body.access-denied {
            overflow: hidden;
        }
        
        .main.access-denied-blur {
            filter: blur(4px);
            pointer-events: none;
        }
        
        .activity-sidebar.access-denied-blur {
            filter: blur(4px);
            pointer-events: none;
        }
        
        /* Custom access denied popup styling */
        .access-denied-popup {
            background: linear-gradient(135deg, white 0%, var(--primary-50) 100%);
            padding: 3rem;
            border-radius: 0;
            box-shadow: var(--shadow-2xl);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--accent-500);
            position: relative;
            overflow: hidden;
        }
        
        .access-denied-popup::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-600), var(--primary-700));
        }
        
        .access-denied-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .access-denied-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-800);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .access-denied-message {
            font-size: 1.1rem;
            color: var(--primary-600);
            margin-bottom: 2rem;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .access-denied-btn {
            background: linear-gradient(135deg, var(--accent-600), var(--primary-700));
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: var(--transition-all);
            border-radius: 0;
            box-shadow: var(--shadow-lg);
        }
        
        .access-denied-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, var(--accent-700), var(--primary-800));
        }
    </style>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Add blur effect to main content and sidebar
            document.body.classList.add("access-denied");
            const mainContent = document.querySelector(".main");
            const sidebar = document.querySelector(".activity-sidebar");
            
            if (mainContent) {
                mainContent.classList.add("access-denied-blur");
            }
            if (sidebar) {
                sidebar.classList.add("access-denied-blur");
            }
            
            // Create custom access denied overlay
            const overlay = document.createElement("div");
            overlay.className = "access-denied-overlay";
            overlay.innerHTML = `
                <div class="access-denied-popup">
                    <div class="access-denied-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2 class="access-denied-title">Access Restricted</h2>
                    <p class="access-denied-message">
                        You do not have permission to view activity logs. 
                        Only administrators and contracts personnel can access this section.
                    </p>
                    <button class="access-denied-btn" onclick="window.location.href=\'index.php\'">
                        <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                        Return to Dashboard
                    </button>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Prevent any interaction with background content
            document.addEventListener("click", function(e) {
                if (!overlay.contains(e.target)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);
            
            document.addEventListener("keydown", function(e) {
                e.preventDefault();
                if (e.key === "Enter" || e.key === " ") {
                    window.location.href = "index.php";
                }
            });
        });
    </script>';
    // Continue loading the page but will redirect once DOM is loaded
}

// Initialize the activity logger
$logger = new ActivityLogger();

// Get query parameters for filtering
$date = $_GET['date'] ?? date('Y-m-d');
$userFilter = $_GET['user'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$recordIdFilter = $_GET['record_id'] ?? '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get logs
$logs = $logger->getLogs($date, $limit, $userFilter, $actionFilter, $recordIdFilter, $offset);
$totalCount = $logger->getLogsCount($date, $userFilter, $actionFilter, $recordIdFilter);
$totalPages = ceil($totalCount / $limit);

// Get available dates for date selector
$availableDates = $logger->getAvailableDates();

// Get unique users from the current log for filter dropdown
$uniqueUsers = $logger->getUniqueUsers();
$uniqueActions = $logger->getUniqueActions();

// Clear logs if requested
if (isset($_POST['clear_logs']) && $_POST['clear_logs'] == 'confirm') {
    $clearDate = $_POST['clear_date'] ?? $date;
    if ($logger->clearLogs($clearDate)) {
        header("Location: activity_logs.php?cleared=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | Paperwork Management</title>
    
    <!-- Google Fonts - Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root {
            /* Primary Color Palette */
            --primary-900: #0f172a;
            --primary-800: #1e293b;
            --primary-700: #334155;
            --primary-600: #475569;
            --primary-500: #64748b;
            --primary-400: #94a3b8;
            --primary-300: #cbd5e1;
            --primary-200: #e2e8f0;
            --primary-100: #f1f5f9;
            --primary-50: #f8fafc;
            
            /* Accent Colors */
            --accent-600: #2563eb;
            --accent-500: #3b82f6;
            --accent-400: #60a5fa;
            --accent-300: #93c5fd;
            --accent-200: #bfdbfe;
            --accent-100: #dbeafe;
            --accent-50: #eff6ff;
            
            /* Status Colors */
            --success-500: #10b981;
            --success-dark: #059669;
            --warning-500: #f59e0b;
            --warning-dark: #d97706;
            --error-500: #ef4444;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --info: var(--accent-500);
            
            /* Typography */
            --font-family: 'Montserrat', system-ui, -apple-system, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            
            /* Spacing */
            --spacing-1: 0.25rem;
            --spacing-2: 0.5rem;
            --spacing-3: 0.75rem;
            --spacing-4: 1rem;
            --spacing-5: 1.25rem;
            --spacing-6: 1.5rem;
            --spacing-8: 2rem;
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            --space-16: 4rem;
            --space-20: 5rem;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Transitions */
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-all: all 0.2s ease-in-out;
            
            /* Layout */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--accent-50) 100%);
            color: var(--primary-800);
            line-height: 1.6;
            font-size: 14px;
            min-height: 100vh;
        }

        /* ===== SIDEBAR STYLES ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
            box-shadow: var(--shadow-2xl);
            transition: var(--transition-slow);
            z-index: 1000;
            overflow: hidden;
            border-right: 1px solid var(--primary-700);
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--accent-500) 0%, var(--accent-600) 100%);
            z-index: 1;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            height: var(--header-height);
            padding: var(--spacing-4) var(--spacing-5);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
            border-bottom: 2px solid var(--primary-600);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-500), transparent);
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            text-decoration: none;
            transition: var(--transition-base);
        }

        .header-logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border: 3px solid var(--accent-500);
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .header-logo:hover img {
            transform: scale(1.05) rotate(5deg);
            border-color: var(--accent-400);
            box-shadow: var(--shadow-lg);
        }

        .logo-text {
            color: white;
            font-weight: 800;
            font-size: var(--font-size-lg);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 1;
            transition: var(--transition-base);
            background: linear-gradient(135deg, white, var(--accent-300));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            transform: translateX(-20px);
        }

        .toggler {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, white 0%, var(--primary-50) 100%);
            color: var(--primary-800);
            border: none;
            cursor: pointer;
            transition: var(--transition-base);
            font-weight: 600;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .toggler::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--accent-500);
            transition: var(--transition-base);
            transform: translate(-50%, -50%);
        }

        .toggler:hover::before {
            width: 100%;
            height: 100%;
        }

        .toggler:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .toggler span {
            font-size: 1.5rem;
            z-index: 1;
            position: relative;
            transition: var(--transition-base);
        }

        .sidebar-toggler {
            transition: var(--transition-slow);
        }

        .sidebar.collapsed .sidebar-toggler {
            transform: rotate(180deg);
        }

        .sidebar.collapsed .sidebar-toggler span {
            transform: rotate(180deg);
        }

        .menu-toggler {
            display: none;
        }

        .sidebar-nav {
            height: calc(100vh - var(--header-height));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .nav-list {
            list-style: none;
            padding: var(--spacing-4) var(--spacing-3);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-1);
        }

        .primary-nav {
            flex: 1;
            padding-top: var(--spacing-6);
        }

        .secondary-nav {
            border-top: 2px solid var(--primary-600);
            padding-top: var(--spacing-4);
            margin-top: var(--spacing-4);
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            padding: var(--spacing-3) var(--spacing-4);
            color: var(--primary-200);
            text-decoration: none;
            font-weight: 600;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: var(--transition-base);
            position: relative;
            overflow: hidden;
            margin-bottom: var(--spacing-1);
            border: 2px solid transparent;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: var(--transition-base);
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            color: var(--primary-800);
            background: linear-gradient(135deg, white 0%, var(--accent-50) 100%);
            transform: translateX(8px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-400);
        }

        .nav-link.active {
            color: var(--primary-800);
            background: linear-gradient(135deg, white 0%, var(--accent-50) 100%);
            border-color: var(--accent-500);
            box-shadow: var(--shadow-lg);
            transform: translateX(6px);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--accent-500) 0%, var(--accent-600) 100%);
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: var(--spacing-3) var(--spacing-2);
        }

        .nav-icon {
            font-size: var(--font-size-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            transition: var(--transition-base);
        }

        .nav-label {
            transition: var(--transition-base);
            white-space: nowrap;
        }

        .sidebar.collapsed .nav-label {
            opacity: 0;
            pointer-events: none;
            transform: translateX(-20px);
        }

        .nav-link:hover .nav-icon,
        .nav-link.active .nav-icon {
            color: var(--accent-600);
            transform: scale(1.1);
        }

        .nav-tooltip {
            position: absolute;
            top: 50%;
            left: calc(100% + 20px);
            transform: translateY(-50%);
            background: linear-gradient(135deg, white 0%, var(--primary-50) 100%);
            color: var(--primary-800);
            padding: var(--spacing-2) var(--spacing-4);
            font-weight: 600;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-xl);
            border: 2px solid var(--accent-200);
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-base);
            z-index: 1001;
            white-space: nowrap;
        }

        .nav-tooltip::before {
            content: '';
            position: absolute;
            top: 50%;
            left: -8px;
            transform: translateY(-50%);
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 8px solid var(--accent-200);
        }

        .nav-tooltip::after {
            content: '';
            position: absolute;
            top: 50%;
            left: -6px;
            transform: translateY(-50%);
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            border-right: 6px solid white;
        }

        .sidebar.collapsed .nav-tooltip {
            display: block;
        }

        .nav-item:hover .nav-tooltip {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(-50%) translateX(8px);
        }

        /* ===== MAIN CONTENT STYLES ===== */
        .main {
            margin-left: var(--sidebar-width);
            padding: var(--space-6);
            transition: var(--transition-slow);
            min-height: 100vh;
        }

        .sidebar.collapsed + .main {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-8);
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
            padding: var(--space-6) var(--space-8);
            color: white;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: var(--space-1);
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .page-title i {
            color: var(--accent-400);
        }

        .header-actions {
            display: flex;
            gap: var(--space-3);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-6);
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1;
            cursor: pointer;
            transition: var(--transition-all);
            border: 2px solid transparent;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            overflow: hidden;
            border-radius: 0;
            font-family: var(--font-family);
        }

        .btn i {
            font-size: 1rem;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            transition: var(--transition-all);
            transform: translate(-50%, -50%);
        }

        .btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-500));
            color: white;
            border-color: var(--accent-600);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-700), var(--accent-600));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-700);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border-color: white;
        }

        .btn-outline:hover {
            background: white;
            color: var(--primary-800);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
            color: white;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, var(--danger-dark), #b91c1c);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--danger-dark);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-400));
            color: white;
            border-color: var(--primary-500);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-500));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-600);
        }

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            background: white;
            border: 1px solid var(--primary-200);
            box-shadow: var(--shadow-lg);
            padding: var(--space-6);
            transition: var(--transition-all);
            display: flex;
            align-items: center;
            gap: var(--space-4);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-500);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.2) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: var(--transition-all);
        }

        .stat-card:hover .stat-icon::before {
            transform: translateX(100%);
        }

        .icon-blue { background: linear-gradient(135deg, var(--accent-600), var(--accent-500)); }
        .icon-green { background: linear-gradient(135deg, var(--success-500), var(--success-dark)); }
        .icon-orange { background: linear-gradient(135deg, var(--warning-500), var(--warning-dark)); }
        .icon-red { background: linear-gradient(135deg, var(--danger), var(--danger-dark)); }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-800);
            margin-bottom: var(--space-1);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--primary-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Filter Container */
        .filter-container {
            background: white;
            border: 1px solid var(--primary-200);
            box-shadow: var(--shadow-lg);
            padding: var(--space-8);
            margin-bottom: var(--space-8);
            position: relative;
            overflow: hidden;
        }

        .filter-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--accent-500), var(--accent-400));
        }

        .filter-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-800);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-title i {
            color: var(--accent-500);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-5);
            margin-bottom: var(--space-6);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            display: block;
            margin-bottom: var(--space-2);
            font-size: 0.875rem;
            color: var(--primary-700);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--primary-300);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition-all);
            background-color: white;
            color: var(--primary-800);
            border-radius: 0;
            font-family: var(--font-family);
        }

        .filter-select:focus, .filter-input:focus {
            border-color: var(--accent-500);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
            background-color: var(--accent-50);
        }

        .filter-select:hover, .filter-input:hover {
            border-color: var(--accent-400);
            background-color: var(--primary-50);
        }

        .filter-actions {
            display: flex;
            gap: var(--space-3);
            justify-content: flex-start;
            grid-column: 1 / -1;
        }

        /* Logs Container */
        .logs-container {
            background: white;
            border: 1px solid var(--primary-200);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-6) var(--space-8);
            background: linear-gradient(135deg, var(--primary-100), var(--accent-50));
            border-bottom: 2px solid var(--primary-200);
        }

        .logs-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-800);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .logs-count {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-500));
            color: white;
            padding: var(--space-2) var(--space-4);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .logs-table-container {
            overflow-x: auto;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th {
            background: linear-gradient(135deg, var(--primary-800), var(--primary-700));
            color: white;
            text-align: left;
            padding: var(--space-4) var(--space-5);
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logs-table td {
            padding: var(--space-4) var(--space-5);
            border-bottom: 1px solid var(--primary-200);
            font-size: 0.875rem;
            vertical-align: middle;
            font-weight: 500;
        }

        .logs-table tr:nth-child(even) {
            background-color: var(--primary-50);
        }

        .logs-table tr:hover {
            background: linear-gradient(135deg, var(--accent-50), var(--primary-50));
            transform: scale(1.002);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .timestamp {
            min-width: 180px;
            white-space: nowrap;
            font-weight: 600;
            color: var(--primary-700);
        }

        .user {
            min-width: 180px;
        }

        .user-name {
            font-weight: 600;
            color: var(--primary-800);
            margin-bottom: var(--space-1);
        }

        .ip-address {
            color: var(--primary-500);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .action {
            min-width: 120px;
        }

        .record-id {
            min-width: 80px;
            text-align: center;
        }

        .action-tag {
            display: inline-block;
            padding: var(--space-1) var(--space-3);
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
        }

        .action-login { background: linear-gradient(135deg, var(--info), var(--accent-600)); }
        .action-view { background: linear-gradient(135deg, var(--primary-600), var(--primary-500)); }
        .action-create { background: linear-gradient(135deg, var(--success-500), var(--success-dark)); }
        .action-update { background: linear-gradient(135deg, var(--warning-500), var(--warning-dark)); }
        .action-delete { background: linear-gradient(135deg, var(--danger), var(--danger-dark)); }
        .action-export { background: linear-gradient(135deg, var(--accent-500), var(--accent-400)); }
        .action-status { background: linear-gradient(135deg, var(--info), var(--accent-600)); }

        .log-details {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--primary-600);
            font-weight: 500;
        }

        .log-details:hover {
            overflow: visible;
            white-space: normal;
            background: white;
            box-shadow: var(--shadow-xl);
            position: relative;
            z-index: 1;
            padding: var(--space-3);
            border: 2px solid var(--accent-200);
        }

        .record-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-2) var(--space-3);
            background: linear-gradient(135deg, var(--primary-100), var(--accent-50));
            color: var(--primary-800);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-all);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .record-link:hover {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-500));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Empty State */
        .empty-logs {
            padding: var(--space-16) var(--space-8);
            text-align: center;
            color: var(--primary-500);
        }

        .empty-logs i {
            font-size: 4rem;
            margin-bottom: var(--space-6);
            color: var(--primary-300);
        }

        .empty-logs h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-700);
            margin-bottom: var(--space-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .empty-logs p {
            font-size: 1rem;
            font-weight: 500;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: var(--space-8);
            gap: var(--space-4);
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--primary-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--space-2);
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 var(--space-3);
            background-color: white;
            color: var(--primary-800);
            text-decoration: none;
            transition: var(--transition-all);
            font-size: 0.875rem;
            font-weight: 600;
            border: 2px solid var(--primary-300);
        }

        .pagination a:hover, .pagination a.active {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-500));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-600);
        }

        .pagination span {
            cursor: not-allowed;
            color: var(--primary-400);
            background-color: var(--primary-50);
            border-color: var(--primary-200);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 500px;
            overflow: hidden;
            animation: modalSlideIn 0.3s forwards;
            border: 1px solid var(--primary-200);
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-800), var(--primary-700));
            color: white;
            padding: var(--space-5) var(--space-6);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition-all);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: white;
            transform: scale(1.2) rotate(90deg);
        }

        .modal-body {
            padding: var(--space-6);
        }

        .modal-footer {
            padding: var(--space-5) var(--space-6);
            border-top: 2px solid var(--primary-200);
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
            background: var(--primary-50);
        }

        /* Notification */
        .notification {
            position: fixed;
            top: var(--space-5);
            right: var(--space-5);
            background: linear-gradient(135deg, var(--success-500), var(--success-dark));
            color: white;
            padding: var(--space-4) var(--space-6);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            transform: translateX(120%);
            transition: transform 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification i {
            font-size: 1.25rem;
        }

        /* Enhanced Animation Effects */
        .btn:hover {
            transform: translateY(-3px);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        /* Focus States */
        .btn:focus,
        .filter-select:focus,
        .filter-input:focus {
            outline: 2px solid var(--accent-500);
            outline-offset: 2px;
        }

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--primary-200);
            border-left-color: var(--accent-500);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Ripple Effect */
        @keyframes ripple {
            to {
                transform: scale(3);
                opacity: 0;
            }
        }

        .ripple {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            pointer-events: none;
            animation: ripple 0.6s linear;
            z-index: 1;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 100%;
                height: var(--header-height);
                transform: translateY(0);
                transition: var(--transition-slow);
            }

            .sidebar.menu-active {
                height: 100vh;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: var(--accent-500) var(--primary-800);
            }

            .sidebar-header {
                position: sticky;
                top: 0;
                z-index: 20;
                padding: var(--spacing-3) var(--spacing-4);
            }

            .header-logo img {
                width: 40px;
                height: 40px;
            }

            .sidebar-toggler {
                display: none;
            }

            .menu-toggler {
                display: flex;
                width: 35px;
                height: 35px;
            }

            .nav-list {
                padding: var(--spacing-3);
            }

            .nav-link {
                padding: var(--spacing-3);
                font-size: var(--font-size-xs);
                gap: var(--spacing-2);
            }

            .nav-icon {
                font-size: var(--font-size-lg);
            }

            .nav-tooltip {
                display: none !important;
            }

            .secondary-nav {
                position: relative;
                margin: var(--spacing-6) 0 var(--spacing-4);
            }

            .main {
                margin-left: 0;
                padding: calc(var(--header-height) + var(--spacing-4)) var(--spacing-4) var(--spacing-4);
            }

            .sidebar.collapsed + .main {
                margin-left: 0;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main {
                padding: calc(var(--header-height) + var(--spacing-3)) var(--spacing-3) var(--spacing-3);
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: var(--space-4);
                padding: var(--space-5) var(--space-6);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
            }
            
            .logs-header {
                flex-direction: column;
                gap: var(--space-3);
                text-align: center;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
            
            .modal-content {
                width: 95%;
                margin: var(--space-4);
            }

            .sidebar-header {
                padding: var(--spacing-2) var(--spacing-3);
            }

            .header-logo img {
                width: 36px;
                height: 36px;
            }

            .nav-link {
                padding: var(--spacing-2);
                font-size: var(--font-size-xs);
            }

            .nav-icon {
                font-size: var(--font-size-base);
            }
        }

        @media (max-width: 576px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                gap: var(--space-3);
            }
            
            .filter-container,
            .logs-container {
                padding: var(--space-5);
            }
            
            .logs-table th,
            .logs-table td {
                padding: var(--space-3);
                font-size: 0.75rem;
            }
            
            .logs-table-container {
                font-size: 0.75rem;
            }
        }

        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: var(--primary-800);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--accent-500), var(--accent-600));
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--accent-400), var(--accent-500));
        }

        /* Loading Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-link {
            animation: slideIn 0.3s ease-out;
        }

        .nav-link:nth-child(1) { animation-delay: 0.1s; }
        .nav-link:nth-child(2) { animation-delay: 0.2s; }
        .nav-link:nth-child(3) { animation-delay: 0.3s; }
        .nav-link:nth-child(4) { animation-delay: 0.4s; }
        .nav-link:nth-child(5) { animation-delay: 0.5s; }


        /* Sidebar Styles - ADD THIS BLOCK */
.activity-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-right: 1px solid var(--primary-300);
    box-shadow: var(--shadow-xl);
    transform: translateX(0);
    transition: var(--transition-slow);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

.activity-sidebar.collapsed {
    transform: translateX(-100%);
}

.activity-sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid var(--primary-300);
    background: linear-gradient(135deg, var(--accent-600), var(--primary-700));
    color: white;
}

.activity-sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    font-weight: 700;
}

.activity-sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.activity-sidebar-avatar {
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

.activity-sidebar-user-info {
    flex: 1;
}

.activity-sidebar-user-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 2px;
}

.activity-sidebar-user-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

.activity-sidebar-nav {
    flex: 1;
    padding: 1.5rem 0;
    overflow-y: auto;
}

.activity-sidebar-section {
    margin-bottom: 2rem;
}

.activity-sidebar-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--primary-500);
    padding: 0 1.5rem;
    margin-bottom: 1rem;
}

.activity-sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 1.5rem;
    color: var(--primary-700);
    text-decoration: none;
    transition: var(--transition-base);
    position: relative;
    margin: 2px 12px;
    border-radius: 12px;
}

.activity-sidebar-item:hover {
    background: linear-gradient(135deg, var(--accent-600), var(--primary-700));
    color: white;
    transform: translateX(4px);
    text-decoration: none;
}

.activity-sidebar-item.active {
    background: linear-gradient(135deg, var(--accent-600), var(--primary-700));
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.activity-sidebar-item i {
    width: 20px;
    text-align: center;
}

.activity-sidebar-item.text-danger:hover {
    background: var(--danger);
    color: white;
}

.activity-sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--primary-300);
}

.activity-sidebar-toggle-btn {
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
    box-shadow: var(--shadow-xl);
    backdrop-filter: blur(10px);
    transition: var(--transition-base);
    color: var(--accent-600);
}

.activity-sidebar-toggle-btn:hover {
    transform: scale(1.1);
}

/* Update main content margin */
.main {
    margin-left: 280px; /* Change this line */
    padding: var(--space-6);
    transition: var(--transition-slow);
    min-height: 100vh;
}

.activity-sidebar.collapsed + .main {
    margin-left: 0;
}

/* Mobile responsive updates */
@media (max-width: 1024px) {
    .activity-sidebar {
        transform: translateX(-100%);
    }
    
    .activity-sidebar.open {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0 !important;
        padding: calc(var(--header-height) + var(--spacing-4)) var(--spacing-4) var(--spacing-4);
    }
    
    .activity-sidebar-toggle-btn {
        display: flex;
    }
}

    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
<button class="activity-sidebar-toggle-btn" onclick="toggleActivitySidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside class="activity-sidebar" id="activitySidebar">
    <div class="activity-sidebar-header">
        <div class="activity-sidebar-logo">
            <i class="fas fa-cogs"></i>
            <span>VDart PMT</span>
        </div>
        <div class="activity-sidebar-user">
            <div class="activity-sidebar-avatar">
                <?php echo substr($userName, 0, 1); ?>
            </div>
            <div class="activity-sidebar-user-info">
                <div class="activity-sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="activity-sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>
    </div>
    
    <nav class="activity-sidebar-nav">
        <div class="activity-sidebar-section">
            <div class="activity-sidebar-section-title">Management</div>
            <a href="index.php" class="activity-sidebar-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="usermanagement.php" class="activity-sidebar-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="paperworkallrecords.php" class="activity-sidebar-item">
                <i class="fas fa-file-alt"></i>
                <span>Paperwork</span>
            </a>

            <a href="activitylogs.php" class="activity-sidebar-item">
                <i class="fas fa-file-alt"></i>
                <span>Activity Logs</span>
            </a>

        </div>
        
        <!-- <div class="activity-sidebar-section">
            <div class="activity-sidebar-section-title">Quick Actions</div>
            <a href="paperwork.php" class="activity-sidebar-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add Paperwork</span>
            </a>
            <a href="activity_logs.php" class="activity-sidebar-item active">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>
        </div>
        
        <div class="activity-sidebar-section">
            <div class="activity-sidebar-section-title">Reports</div>
            <a href="#" class="activity-sidebar-item">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="#" class="activity-sidebar-item">
                <i class="fas fa-download"></i>
                <span>Export Data</span>
            </a>
        </div> -->
        
        <div class="activity-sidebar-section">
            <div class="activity-sidebar-section-title">Account</div>
            <a href="profile.php" class="activity-sidebar-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="settings.php" class="activity-sidebar-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>
    
    <div class="activity-sidebar-footer">
        <a href="logout.php" class="activity-sidebar-item text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

    <div class="main">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Activity Logs
            </h1>
            <div class="header-actions">
                <a href="export_logs.php?date=<?php echo urlencode($date); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&record_id=<?php echo urlencode($recordIdFilter); ?>" class="btn btn-primary">
                    <i class="fas fa-download"></i> Export Logs
                </a>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <button id="clear-logs-btn" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Clear Logs
                </button>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $logger->getActionCount('view', $date); ?></div>
                    <div class="stat-label">Page Views</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $logger->getActionCount('create', $date); ?></div>
                    <div class="stat-label">Records Created</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $logger->getActionCount('update', $date); ?></div>
                    <div class="stat-label">Updates Made</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $logger->getActionCount('status', $date); ?></div>
                    <div class="stat-label">Status Changes</div>
                </div>
            </div>
        </div>
        
        <div class="filter-container">
            <h2 class="filter-title">
                <i class="fas fa-filter"></i> Filter Logs
            </h2>
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label class="filter-label">Date</label>
                    <select name="date" class="filter-select">
                        <?php foreach ($availableDates as $availableDate): ?>
                            <option value="<?php echo $availableDate; ?>" <?php if ($date === $availableDate) echo 'selected'; ?>>
                                <?php echo date('F j, Y', strtotime($availableDate)); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($availableDates)): ?>
                            <option value="<?php echo date('Y-m-d'); ?>" selected>
                                <?php echo date('F j, Y'); ?> (Today)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">User</label>
                    <select name="user" class="filter-select">
                        <option value="">All Users</option>
                        <?php foreach ($uniqueUsers as $user): ?>
                            <option value="<?php echo $user; ?>" <?php if ($userFilter === $user) echo 'selected'; ?>>
                                <?php echo $user; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Action</label>
                    <select name="action" class="filter-select">
                        <option value="">All Actions</option>
                        <?php foreach ($uniqueActions as $action): ?>
                            <option value="<?php echo $action; ?>" <?php if ($actionFilter === $action) echo 'selected'; ?>>
                                <?php echo ucfirst($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Record ID</label>
                    <input type="text" name="record_id" class="filter-input" placeholder="Enter record ID" value="<?php echo htmlspecialchars($recordIdFilter); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Entries Per Page</label>
                    <select name="limit" class="filter-select">
                        <option value="25" <?php if ($limit === 25) echo 'selected'; ?>>25 entries</option>
                        <option value="50" <?php if ($limit === 50) echo 'selected'; ?>>50 entries</option>
                        <option value="100" <?php if ($limit === 100) echo 'selected'; ?>>100 entries</option>
                        <option value="200" <?php if ($limit === 200) echo 'selected'; ?>>200 entries</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i class="fas fa-undo-alt"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>
        
        <div class="logs-container">
            <div class="logs-header">
                <div class="logs-title">Activity Records</div>
                <div class="logs-count"><?php echo $totalCount; ?> entries</div>
            </div>
            
            <?php if (empty($logs)): ?>
                <div class="empty-logs">
                    <i class="fas fa-search"></i>
                    <h3>No logs found</h3>
                    <p>No activity logs match your current filters. Try adjusting your search criteria or selecting a different date.</p>
                </div>
            <?php else: ?>
                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th class="timestamp">Timestamp</th>
                                <th class="user">User</th>
                                <th class="action">Action</th>
                                <th class="record-id">Record ID</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="timestamp"><?php echo $log['timestamp']; ?></td>
                                    <td class="user">
                                        <div class="user-name"><?php echo htmlspecialchars($log['user']); ?></div>
                                        <div class="ip-address"><?php echo htmlspecialchars($log['ip']); ?></div>
                                    </td>
                                    <td class="action">
                                        <span class="action-tag action-<?php echo strtolower($log['action']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($log['action'])); ?>
                                        </span>
                                    </td>
                                    <td class="record-id">
                                        <?php if (!empty($log['record_id'])): ?>
                                            <a href="paperworkedit.php?id=<?php echo htmlspecialchars($log['record_id']); ?>" 
                                               title="View record" class="record-link">
                                                #<?php echo htmlspecialchars($log['record_id']); ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-details"><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo min(($page - 1) * $limit + 1, $totalCount); ?> to 
                            <?php echo min($page * $limit, $totalCount); ?> of 
                            <?php echo $totalCount; ?> entries
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?date=<?php echo urlencode($date); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&record_id=<?php echo urlencode($recordIdFilter); ?>&limit=<?php echo $limit; ?>&page=1" title="First Page">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?date=<?php echo urlencode($date); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&record_id=<?php echo urlencode($recordIdFilter); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" title="Previous Page">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php else: ?>
                                <span><i class="fas fa-angle-double-left"></i></span>
                                <span><i class="fas fa-angle-left"></i></span>
                            <?php endif; ?>
                            
                            <?php
                            // Determine range of pages to show
                            $startPage = max(1, min($page - 2, $totalPages - 4));
                            $endPage = min($totalPages, max($page + 2, 5));
                            
                            // Show first page if not in range
                            if ($startPage > 1) {
                                echo '<a href="?date=' . urlencode($date) . '&user=' . urlencode($userFilter) . '&action=' . urlencode($actionFilter) . '&record_id=' . urlencode($recordIdFilter) . '&limit=' . $limit . '&page=1">1</a>';
                                if ($startPage > 2) {
                                    echo '<span>...</span>';
                                }
                            }
                            
                            // Show pages in range
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                if ($i == $page) {
                                    echo '<a href="#" class="active">' . $i . '</a>';
                                } else {
                                    echo '<a href="?date=' . urlencode($date) . '&user=' . urlencode($userFilter) . '&action=' . urlencode($actionFilter) . '&record_id=' . urlencode($recordIdFilter) . '&limit=' . $limit . '&page=' . $i . '">' . $i . '</a>';
                                }
                            }
                            
                            // Show last page if not in range
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<span>...</span>';
                                }
                                echo '<a href="?date=' . urlencode($date) . '&user=' . urlencode($userFilter) . '&action=' . urlencode($actionFilter) . '&record_id=' . urlencode($recordIdFilter) . '&limit=' . $limit . '&page=' . $totalPages . '">' . $totalPages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?date=<?php echo urlencode($date); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&record_id=<?php echo urlencode($recordIdFilter); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" title="Next Page">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?date=<?php echo urlencode($date); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&record_id=<?php echo urlencode($recordIdFilter); ?>&limit=<?php echo $limit; ?>&page=<?php echo $totalPages; ?>" title="Last Page">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php else: ?>
                                <span><i class="fas fa-angle-right"></i></span>
                                <span><i class="fas fa-angle-double-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clear Logs Modal -->
    <div id="clear-logs-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Clear Activity Logs</div>
                <div class="modal-close">&times;</div>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: var(--space-5); color: var(--primary-600); font-weight: 500;">Are you sure you want to clear the activity logs? This action cannot be undone.</p>
                <form id="clear-logs-form" method="POST" action="">
                    <input type="hidden" name="clear_logs" value="confirm">
                    <div class="filter-group">
                        <label class="filter-label">Select Date to Clear</label>
                        <select name="clear_date" class="filter-select">
                            <option value="<?php echo $date; ?>" selected>Current Date (<?php echo date('F j, Y', strtotime($date)); ?>)</option>
                            <option value="all">All Dates</option>
                            <?php foreach ($availableDates as $availableDate): ?>
                                <?php if ($availableDate !== $date): ?>
                                    <option value="<?php echo $availableDate; ?>">
                                        <?php echo date('F j, Y', strtotime($availableDate)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="button" class="btn btn-danger confirm-clear-btn">Clear Logs</button>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <i class="fas fa-check-circle"></i>
        <span id="notification-message">Logs cleared successfully!</span>
    </div>

    <script>
        /**
         * Enhanced Sidebar JavaScript
         * Handles sidebar functionality with smooth animations and responsive behavior
         */
        class EnhancedSidebar {
            constructor() {
                this.sidebar = document.querySelector('.sidebar');
                this.sidebarToggler = document.querySelector('.sidebar-toggler');
                this.menuToggler = document.querySelector('.menu-toggler');
                this.main = document.querySelector('.main');
                
                // Configuration
                this.config = {
                    sidebarWidth: 280,
                    collapsedWidth: 80,
                    mobileBreakpoint: 1024,
                    animationDuration: 350
                };
                
                // State
                this.isCollapsed = this.sidebar?.classList.contains('collapsed') || false;
                this.isMobileMenuActive = false;
                this.isInitialized = false;
                
                this.init();
            }
            
            init() {
                if (this.isInitialized) return;
                
                this.bindEvents();
                this.setInitialState();
                this.addKeyboardSupport();
                this.addTouchSupport();
                
                this.isInitialized = true;
                
                // Add loaded class for animations
                setTimeout(() => {
                    this.sidebar?.classList.add('loaded');
                }, 100);
            }
            
            bindEvents() {
                // Sidebar toggle button
                this.sidebarToggler?.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('Sidebar toggle button clicked');
                    this.toggleSidebar();
                });
                
                // Mobile menu toggle button
                this.menuToggler?.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('Mobile menu toggle button clicked');
                    this.toggleMobileMenu();
                });
                
                // Window resize handler
                window.addEventListener('resize', this.debounce(() => {
                    this.handleResize();
                }, 250));
                
                // Click outside to close mobile menu
                document.addEventListener('click', (e) => {
                    if (this.isMobile() && this.isMobileMenuActive && !this.sidebar?.contains(e.target)) {
                        this.closeMobileMenu();
                    }
                });
                
                // Navigation link interactions
                this.enhanceNavLinks();
            }
            
            toggleSidebar() {
                if (this.isMobile()) return;
                
                console.log('Toggle sidebar called. Current collapsed state:', this.isCollapsed);
                
                this.isCollapsed = !this.isCollapsed;
                this.sidebar?.classList.toggle('collapsed', this.isCollapsed);
                
                console.log('New collapsed state:', this.isCollapsed);
                console.log('Sidebar classes:', this.sidebar?.className);
                
                this.updateToggleIcon();
                
                // Update main margin immediately
                this.updateMainMargin();
                
                // Dispatch custom event
                this.dispatchEvent('sidebarToggle', { collapsed: this.isCollapsed });
            }
            
            updateMainMargin() {
                if (!this.main) return;
                
                console.log('Updating main margin. Is mobile:', this.isMobile(), 'Is collapsed:', this.isCollapsed);
                
                if (this.isMobile()) {
                    this.main.style.marginLeft = '0';
                } else {
                    const margin = this.isCollapsed ? `${this.config.collapsedWidth}px` : `${this.config.sidebarWidth}px`;
                    this.main.style.marginLeft = margin;
                    console.log('Set main margin to:', margin);
                }
            }
            
            toggleMobileMenu() {
                if (!this.isMobile()) return;
                
                this.isMobileMenuActive = !this.isMobileMenuActive;
                this.sidebar?.classList.toggle('menu-active', this.isMobileMenuActive);
                
                this.updateMobileMenuIcon();
                
                // Prevent body scroll when menu is open
                document.body.style.overflow = this.isMobileMenuActive ? 'hidden' : '';
                
                // Dispatch custom event
                this.dispatchEvent('mobileMenuToggle', { active: this.isMobileMenuActive });
            }
            
            closeMobileMenu() {
                if (!this.isMobileMenuActive) return;
                
                this.isMobileMenuActive = false;
                this.sidebar?.classList.remove('menu-active');
                this.updateMobileMenuIcon();
                document.body.style.overflow = '';
            }
            
            updateToggleIcon() {
                const icon = this.sidebarToggler?.querySelector('span');
                if (!icon) return;
                
                if (this.isCollapsed) {
                    icon.textContent = 'chevron_right';
                } else {
                    icon.textContent = 'chevron_left';
                }
            }
            
            updateMobileMenuIcon() {
                const icon = this.menuToggler?.querySelector('span');
                if (!icon) return;
                
                if (this.isMobileMenuActive) {
                    icon.textContent = 'close';
                } else {
                    icon.textContent = 'menu';
                }
            }
            
            handleResize() {
                // Reset mobile menu state when switching to desktop
                if (!this.isMobile() && this.isMobileMenuActive) {
                    this.closeMobileMenu();
                }
                
                // Reset collapsed state when switching to mobile
                if (this.isMobile() && this.isCollapsed) {
                    this.sidebar?.classList.remove('collapsed');
                    this.isCollapsed = false;
                }
                
                this.setInitialState();
                
                // Dispatch resize event
                this.dispatchEvent('sidebarResize', { 
                    isMobile: this.isMobile(),
                    collapsed: this.isCollapsed 
                });
            }
            
            setInitialState() {
                console.log('Setting initial state. Is mobile:', this.isMobile());
                
                if (this.isMobile()) {
                    this.sidebar?.classList.remove('collapsed');
                    this.isCollapsed = false;
                } else {
                    this.sidebar?.classList.remove('menu-active');
                    this.updateToggleIcon();
                    this.updateMainMargin();
                }
            }
            
            enhanceNavLinks() {
                const navLinks = document.querySelectorAll('.nav-link');
                
                navLinks.forEach(link => {
                    // Add ripple effect on click
                    link.addEventListener('click', (e) => {
                        this.createRipple(e, link);
                        
                        // Close mobile menu when navigation link is clicked
                        if (this.isMobile() && this.isMobileMenuActive) {
                            setTimeout(() => this.closeMobileMenu(), 150);
                        }
                    });
                });
            }
            
            createRipple(event, element) {
                const ripple = document.createElement('span');
                const rect = element.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = event.clientX - rect.left - size / 2;
                const y = event.clientY - rect.top - size / 2;
                
                ripple.className = 'ripple';
                ripple.style.cssText = `
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                `;
                
                element.style.position = 'relative';
                element.style.overflow = 'hidden';
                element.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    if (ripple.parentNode) {
                        ripple.parentNode.removeChild(ripple);
                    }
                }, 600);
            }
            
            addKeyboardSupport() {
                document.addEventListener('keydown', (e) => {
                    // Alt + S to toggle sidebar
                    if (e.altKey && e.key === 's') {
                        e.preventDefault();
                        if (this.isMobile()) {
                            this.toggleMobileMenu();
                        } else {
                            this.toggleSidebar();
                        }
                    }
                    
                    // Escape to close mobile menu
                    if (e.key === 'Escape' && this.isMobileMenuActive) {
                        this.closeMobileMenu();
                    }
                });
            }
            
            addTouchSupport() {
                if (!('ontouchstart' in window)) return;
                
                let startX = 0;
                let startY = 0;
                let isSwipeGesture = false;
                
                document.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                    isSwipeGesture = false;
                });
                
                document.addEventListener('touchmove', (e) => {
                    if (!this.isMobile()) return;
                    
                    const deltaX = e.touches[0].clientX - startX;
                    const deltaY = Math.abs(e.touches[0].clientY - startY);
                    
                    // Detect horizontal swipe
                    if (Math.abs(deltaX) > 50 && deltaY < 50) {
                        isSwipeGesture = true;
                        
                        // Swipe right to open menu
                        if (deltaX > 0 && !this.isMobileMenuActive && startX < 50) {
                            this.toggleMobileMenu();
                        }
                        // Swipe left to close menu
                        else if (deltaX < 0 && this.isMobileMenuActive) {
                            this.closeMobileMenu();
                        }
                    }
                });
            }
            
            isMobile() {
                return window.innerWidth <= this.config.mobileBreakpoint;
            }
            
            dispatchEvent(eventName, detail = {}) {
                const event = new CustomEvent(`sidebar:${eventName}`, { detail });
                document.dispatchEvent(event);
            }
            
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Public API methods
            collapse() {
                if (!this.isMobile() && !this.isCollapsed) {
                    this.toggleSidebar();
                }
            }
            
            expand() {
                if (!this.isMobile() && this.isCollapsed) {
                    this.toggleSidebar();
                }
            }
            
            destroy() {
                // Clean up event listeners and state
                document.body.style.overflow = '';
                window.removeEventListener('resize', this.handleResize);
                this.isInitialized = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the sidebar
            const sidebar = new EnhancedSidebar();
            
            // Make sidebar instance globally available
            window.enhancedSidebar = sidebar;
            
            // Debug: Log sidebar initialization
            console.log('Sidebar initialized:', sidebar);
            console.log('Sidebar element:', sidebar.sidebar);
            console.log('Toggle button:', sidebar.sidebarToggler);
            
            // Check for cleared notification
            <?php if (isset($_GET['cleared']) && $_GET['cleared'] == '1'): ?>
                showNotification('Logs cleared successfully!');
            <?php endif; ?>

            // Fallback click handler for sidebar toggle
            const sidebarToggleBtn = document.querySelector('.sidebar-toggler');
            if (sidebarToggleBtn) {
                sidebarToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Fallback toggle handler triggered');
                    
                    const sidebar = document.querySelector('.sidebar');
                    const main = document.querySelector('.main');
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    
                    if (window.innerWidth > 1024) {
                        sidebar.classList.toggle('collapsed');
                        
                        if (isCollapsed) {
                            // Expanding
                            main.style.marginLeft = '280px';
                            sidebarToggleBtn.querySelector('span').textContent = 'chevron_left';
                        } else {
                            // Collapsing
                            main.style.marginLeft = '80px';
                            sidebarToggleBtn.querySelector('span').textContent = 'chevron_right';
                        }
                    }
                });
            }

            // Show/hide clear logs modal
            const clearLogsBtn = document.getElementById('clear-logs-btn');
            const clearLogsModal = document.getElementById('clear-logs-modal');
            const modalClose = clearLogsModal.querySelector('.modal-close');
            const cancelBtn = clearLogsModal.querySelector('.cancel-btn');
            const confirmClearBtn = clearLogsModal.querySelector('.confirm-clear-btn');
            
            clearLogsBtn.addEventListener('click', function() {
                clearLogsModal.style.display = 'flex';
            });
            
            modalClose.addEventListener('click', function() {
                clearLogsModal.style.display = 'none';
            });
            
            cancelBtn.addEventListener('click', function() {
                clearLogsModal.style.display = 'none';
            });
            
            confirmClearBtn.addEventListener('click', function() {
                document.getElementById('clear-logs-form').submit();
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === clearLogsModal) {
                    clearLogsModal.style.display = 'none';
                }
            });
            
            // Show notification function
            function showNotification(message) {
                const notification = document.getElementById('notification');
                const notificationMessage = document.getElementById('notification-message');
                
                notificationMessage.textContent = message;
                notification.classList.add('show');
                
                setTimeout(function() {
                    notification.classList.remove('show');
                }, 5000);
            }

            // Enhanced table interactions
            const tableRows = document.querySelectorAll('.logs-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.002)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Enhanced button interactions
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Custom event listeners (optional)
            document.addEventListener('sidebar:sidebarToggle', (e) => {
                console.log('Sidebar toggled:', e.detail);
            });
            
            document.addEventListener('sidebar:mobileMenuToggle', (e) => {
                console.log('Mobile menu toggled:', e.detail);
            });
        });

        // Expose utility functions
        window.sidebarUtils = {
            toggle: () => window.enhancedSidebar?.toggleSidebar(),
            collapse: () => window.enhancedSidebar?.collapse(),
            expand: () => window.enhancedSidebar?.expand(),
            isMobile: () => window.enhancedSidebar?.isMobile(),
            isCollapsed: () => window.enhancedSidebar?.isCollapsed
        };
    </script>

    <script>
        // Activity Sidebar functionality - ADD THIS BLOCK
function toggleActivitySidebar() {
    const sidebar = document.getElementById('activitySidebar');
    const mainContent = document.querySelector('.main');
    
    if (window.innerWidth <= 1024) {
        sidebar.classList.toggle('open');
    } else {
        sidebar.classList.toggle('collapsed');
    }
}

// Handle window resize for activity sidebar
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('activitySidebar');
    const mainContent = document.querySelector('.main');
    
    if (window.innerWidth > 1024) {
        sidebar.classList.remove('open');
    } else {
        sidebar.classList.remove('collapsed');
    }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('activitySidebar');
    const toggleBtn = document.querySelector('.activity-sidebar-toggle-btn');
    
    if (window.innerWidth <= 1024 && 
        sidebar.classList.contains('open') && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>