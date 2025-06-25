<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Recruiter profile script started");

// Check database connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Connection object is null"));
    die("Database connection failed. Please try again later.");
}
error_log("Database connection successful");

// Authentication check
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Helper function for safe query execution
function safeExecuteQuery($conn, $query, $types = '', $params = []) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error . " in query: " . $query);
        return false;
    }
    
    if (!empty($params) && !empty($types)) {
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("Bind failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    if ($result === false && $stmt->errno !== 0) {
        error_log("Get result failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $data = [
        'stmt' => $stmt,
        'result' => $result,
        'affected_rows' => $stmt->affected_rows,
        'insert_id' => $stmt->insert_id
    ];
    
    return $data;
}

// Helper function to free resources
function freeResources($data) {
    if (isset($data['result']) && $data['result'] instanceof mysqli_result) {
        $data['result']->free();
    }
    if (isset($data['stmt']) && $data['stmt'] instanceof mysqli_stmt) {
        $data['stmt']->close();
    }
}

// Get logged-in user's info
$userEmail = $_SESSION['email'] ?? '';
$userResult = safeExecuteQuery($conn, "SELECT id, name, role, userwithempid, profile_image FROM users WHERE email = ?", 's', [$userEmail]);

if ($userResult === false) {
    error_log("Failed to get user data");
    die("Failed to retrieve user information. Please try again.");
}

$userData = $userResult['result']->fetch_assoc();
$userId = $userData['id'];
$userName = $userData['name'];
$userRole = $userData['role'];
$userWithEmpId = $userData['userwithempid'];
$userProfileImage = $userData['profile_image'] ?? 'assets/images/default-profile.png';

// Free the result resources
freeResources($userResult);

// Get recruiter ID from URL parameter, or default to current user
$recruiterId = $_GET['id'] ?? $userId;

// Fetch recruiter data
$recruiterResult = safeExecuteQuery($conn, 
    "SELECT u.*, IFNULL((SELECT COUNT(*) FROM paperworkdetails WHERE submittedby = u.email), 0) as submission_count 
     FROM users u WHERE u.id = ?", 
    'i', [$recruiterId]);

if ($recruiterResult === false || $recruiterResult['result']->num_rows === 0) {
    error_log("Failed to get recruiter data for ID: " . $recruiterId);
    die("Failed to retrieve recruiter information. Please try again.");
}

$recruiterData = $recruiterResult['result']->fetch_assoc();
freeResources($recruiterResult);

// Get recruiter's initials for avatar placeholder
$recruiterInitials = '';
$nameParts = explode(' ', $recruiterData['name']);
if (count($nameParts) >= 2) {
    $recruiterInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $recruiterInitials = strtoupper(substr($recruiterData['name'], 0, 2));
}

// Calculate performance metrics
// 1. Get total submissions count - already captured in query above in $recruiterData['submission_count']

// 2. Success rate (hired candidates / total submissions)
$successRateResult = safeExecuteQuery($conn, 
    "SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'active' OR status = 'hired' THEN 1 ELSE 0 END) as successful_submissions
     FROM paperworkdetails 
     WHERE submittedby = ?", 
    's', [$recruiterData['email']]);

$successRateData = $successRateResult['result']->fetch_assoc();
$totalSubmissions = $successRateData['total_submissions'] ?: 0;
$successfulSubmissions = $successRateData['successful_submissions'] ?: 0;
$successRate = $totalSubmissions > 0 ? round(($successfulSubmissions / $totalSubmissions) * 100, 1) : 0;
freeResources($successRateResult);

// 3. Average margin on placements
$marginResult = safeExecuteQuery($conn, 
    "SELECT AVG(margin) as avg_margin
     FROM paperworkdetails 
     WHERE submittedby = ? AND margin IS NOT NULL AND margin > 0", 
    's', [$recruiterData['email']]);

$marginData = $marginResult['result']->fetch_assoc();
$avgMargin = $marginData['avg_margin'] ? round($marginData['avg_margin'], 1) : 0;
freeResources($marginResult);

// 4. Ratecard adherence
$ratecardResult = safeExecuteQuery($conn, 
    "SELECT 
        COUNT(*) as total_with_ratecard,
        SUM(CASE WHEN ratecard_adherence = 'yes' THEN 1 ELSE 0 END) as adherent_count
     FROM paperworkdetails 
     WHERE submittedby = ? AND ratecard_adherence IS NOT NULL", 
    's', [$recruiterData['email']]);

$ratecardData = $ratecardResult['result']->fetch_assoc();
$totalWithRatecard = $ratecardData['total_with_ratecard'] ?: 0;
$adherentCount = $ratecardData['adherent_count'] ?: 0;
$ratecardAdherence = $totalWithRatecard > 0 ? round(($adherentCount / $totalWithRatecard) * 100, 1) : 0;
freeResources($ratecardResult);

// 5. Active placements
$activePlacementsResult = safeExecuteQuery($conn, 
    "SELECT COUNT(*) as active_placements
     FROM paperworkdetails 
     WHERE submittedby = ? AND status = 'active'", 
    's', [$recruiterData['email']]);

$activePlacementsData = $activePlacementsResult['result']->fetch_assoc();
$activePlacements = $activePlacementsData['active_placements'] ?: 0;
freeResources($activePlacementsResult);

// 6. Revenue generated
$revenueResult = safeExecuteQuery($conn, 
    "SELECT SUM(clientrate * duration) as total_revenue
     FROM paperworkdetails 
     WHERE submittedby = ? AND clientrate IS NOT NULL AND clientrate > 0 AND duration IS NOT NULL AND duration > 0", 
    's', [$recruiterData['email']]);

$revenueData = $revenueResult['result']->fetch_assoc();
$totalRevenue = $revenueData['total_revenue'] ?: 0;
$formattedRevenue = '$' . number_format($totalRevenue, 2);
freeResources($revenueResult);

// 7. YoY Growth
// This would require historical data comparison, simplified version:
$yoyGrowth = 18.3; // Placeholder value - would need to calculate from historical data

// 8. Average time to fill (days)
$timeToFillResult = safeExecuteQuery($conn, 
    "SELECT AVG(DATEDIFF(start_date, created_at)) as avg_days_to_fill
     FROM paperworkdetails 
     WHERE submittedby = ? AND start_date IS NOT NULL AND created_at IS NOT NULL 
     AND status IN ('active', 'hired')", 
    's', [$recruiterData['email']]);

$timeToFillData = $timeToFillResult['result']->fetch_assoc();
$avgTimeToFill = $timeToFillData['avg_days_to_fill'] ? round($timeToFillData['avg_days_to_fill'], 1) : 0;
freeResources($timeToFillResult);

// Fetch recent submissions
$recentSubmissionsResult = safeExecuteQuery($conn, 
    "SELECT 
        cfirstname, 
        clastname, 
        job_title, 
        client, 
        DATE_FORMAT(created_at, '%b %d, %Y') as submission_date, 
        status,
        cresume_attached,
        pwcode
     FROM paperworkdetails 
     WHERE submittedby = ? 
     ORDER BY created_at DESC 
     LIMIT 10", 
    's', [$recruiterData['email']]);

if ($recentSubmissionsResult !== false) {
    $recentSubmissions = [];
    while ($row = $recentSubmissionsResult['result']->fetch_assoc()) {
        $recentSubmissions[] = $row;
    }
    freeResources($recentSubmissionsResult);
} else {
    $recentSubmissions = [];
}

// Get monthly submission data for chart (last 12 months)
$monthlyDataResult = safeExecuteQuery($conn, 
    "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as submission_count,
        SUM(CASE WHEN status = 'active' OR status = 'hired' THEN 1 ELSE 0 END) as hired_count
     FROM paperworkdetails 
     WHERE submittedby = ? 
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC", 
    's', [$recruiterData['email']]);

if ($monthlyDataResult !== false) {
    $monthlyData = [];
    $monthLabels = [];
    $submissionCounts = [];
    $hireCounts = [];
    
    while ($row = $monthlyDataResult['result']->fetch_assoc()) {
        $monthLabels[] = date('M', strtotime($row['month'] . '-01'));
        $submissionCounts[] = (int)$row['submission_count'];
        $hireCounts[] = (int)$row['hired_count'];
    }
    
    // Fill in missing months with zeros to ensure 12 months of data
    if (count($monthLabels) < 12) {
        $existingMonths = count($monthLabels);
        for ($i = $existingMonths; $i < 12; $i++) {
            $monthLabels[] = date('M', strtotime("-" . (12 - $i) . " month"));
            $submissionCounts[] = 0;
            $hireCounts[] = 0;
        }
    }
    
    freeResources($monthlyDataResult);
} else {
    // Default empty data for chart
    $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $submissionCounts = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    $hireCounts = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
}

// Get skills distribution data
$skillsResult = safeExecuteQuery($conn, 
    "SELECT 
        primary_skill,
        COUNT(*) as count
     FROM paperworkdetails 
     WHERE submittedby = ? AND primary_skill IS NOT NULL AND primary_skill != ''
     GROUP BY primary_skill
     ORDER BY count DESC
     LIMIT 6", 
    's', [$recruiterData['email']]);

if ($skillsResult !== false) {
    $skillLabels = [];
    $skillCounts = [];
    
    while ($row = $skillsResult['result']->fetch_assoc()) {
        $skillLabels[] = $row['primary_skill'];
        $skillCounts[] = (int)$row['count'];
    }
    
    freeResources($skillsResult);
} else {
    // Default empty data for chart
    $skillLabels = ['Java', 'DevOps', 'Data Science', 'Frontend', 'UX/UI', 'Other'];
    $skillCounts = [0, 0, 0, 0, 0, 0];
}

// Get status breakdown data
$statusResult = safeExecuteQuery($conn, 
    "SELECT 
        status,
        COUNT(*) as count
     FROM paperworkdetails 
     WHERE submittedby = ? AND status IS NOT NULL AND status != ''
     GROUP BY status
     ORDER BY count DESC", 
    's', [$recruiterData['email']]);

if ($statusResult !== false) {
    $statusLabels = [];
    $statusCounts = [];
    
    while ($row = $statusResult['result']->fetch_assoc()) {
        $statusLabels[] = ucfirst($row['status']);
        $statusCounts[] = (int)$row['count'];
    }
    
    freeResources($statusResult);
} else {
    // Default empty data for chart
    $statusLabels = ['Active', 'Pending', 'Rejected', 'Withdrawn'];
    $statusCounts = [0, 0, 0, 0];
}

// Get industry distribution data
$industryResult = safeExecuteQuery($conn, 
    "SELECT 
        industry,
        COUNT(*) as count
     FROM paperworkdetails 
     WHERE submittedby = ? AND industry IS NOT NULL AND industry != ''
     GROUP BY industry
     ORDER BY count DESC
     LIMIT 5", 
    's', [$recruiterData['email']]);

if ($industryResult !== false) {
    $industryLabels = [];
    $industryCounts = [];
    
    while ($row = $industryResult['result']->fetch_assoc()) {
        $industryLabels[] = $row['industry'];
        $industryCounts[] = (int)$row['count'];
    }
    
    freeResources($industryResult);
} else {
    // Default empty data for chart
    $industryLabels = ['Technology', 'Financial Services', 'Healthcare', 'Retail', 'Manufacturing'];
    $industryCounts = [0, 0, 0, 0, 0];
}

// Get client data
$clientResult = safeExecuteQuery($conn, 
    "SELECT 
        client,
        COUNT(*) as count
     FROM paperworkdetails 
     WHERE submittedby = ? AND client IS NOT NULL AND client != ''
     GROUP BY client
     ORDER BY count DESC
     LIMIT 8", 
    's', [$recruiterData['email']]);

if ($clientResult !== false) {
    $clientNames = [];
    
    while ($row = $clientResult['result']->fetch_assoc()) {
        $clientNames[] = $row['client'];
    }
    
    freeResources($clientResult);
} else {
    $clientNames = [];
}

// Get business units
$businessUnitResult = safeExecuteQuery($conn, 
    "SELECT DISTINCT business_unit
     FROM paperworkdetails 
     WHERE submittedby = ? AND business_unit IS NOT NULL AND business_unit != ''
     ORDER BY business_unit ASC", 
    's', [$recruiterData['email']]);

if ($businessUnitResult !== false) {
    $businessUnits = [];
    
    while ($row = $businessUnitResult['result']->fetch_assoc()) {
        $businessUnits[] = $row['business_unit'];
    }
    
    freeResources($businessUnitResult);
} else {
    $businessUnits = [];
}

// Get team members
$teamResult = safeExecuteQuery($conn, 
    "SELECT DISTINCT 
        delivery_manager as name, 
        'Delivery Manager' as role
     FROM paperworkdetails 
     WHERE submittedby = ? AND delivery_manager IS NOT NULL AND delivery_manager != ''
     UNION
     SELECT DISTINCT 
        team_lead as name, 
        'Team Lead' as role
     FROM paperworkdetails 
     WHERE submittedby = ? AND team_lead IS NOT NULL AND team_lead != ''
     UNION
     SELECT DISTINCT 
        associate_team_lead as name, 
        'Associate Team Lead' as role
     FROM paperworkdetails 
     WHERE submittedby = ? AND associate_team_lead IS NOT NULL AND associate_team_lead != ''
     UNION
     SELECT DISTINCT 
        pt_support as name, 
        'PT Support' as role
     FROM paperworkdetails 
     WHERE submittedby = ? AND pt_support IS NOT NULL AND pt_support != ''
     LIMIT 8", 
    'ssss', [$recruiterData['email'], $recruiterData['email'], $recruiterData['email'], $recruiterData['email']]);

if ($teamResult !== false) {
    $teamMembers = [];
    
    while ($row = $teamResult['result']->fetch_assoc()) {
        if (!empty($row['name'])) {
            $initials = '';
            $nameParts = explode(' ', $row['name']);
            if (count($nameParts) >= 2) {
                $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($row['name'], 0, 2));
            }
            
            $row['initials'] = $initials;
            $teamMembers[] = $row;
        }
    }
    
    freeResources($teamResult);
} else {
    $teamMembers = [];
}

// Get quarterly revenue data
$quarterlyRevenueResult = safeExecuteQuery($conn, 
    "SELECT 
        CONCAT('Q', QUARTER(created_at), ' ', YEAR(created_at)) as quarter,
        SUM(clientrate * duration) as revenue,
        AVG(margin) as avg_margin
     FROM paperworkdetails 
     WHERE submittedby = ? 
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 15 MONTH)
     AND clientrate IS NOT NULL AND duration IS NOT NULL AND margin IS NOT NULL
     GROUP BY YEAR(created_at), QUARTER(created_at)
     ORDER BY YEAR(created_at) ASC, QUARTER(created_at) ASC
     LIMIT 5", 
    's', [$recruiterData['email']]);

if ($quarterlyRevenueResult !== false) {
    $quarterLabels = [];
    $revenueValues = [];
    $marginValues = [];
    
    while ($row = $quarterlyRevenueResult['result']->fetch_assoc()) {
        $quarterLabels[] = $row['quarter'];
        $revenueValues[] = round($row['revenue']);
        $marginValues[] = round($row['avg_margin'] * 100) / 100; // Format to 2 decimal places
    }
    
    freeResources($quarterlyRevenueResult);
} else {
    // Default empty data for chart
    $quarterLabels = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024', 'Q1 2025'];
    $revenueValues = [0, 0, 0, 0, 0];
    $marginValues = [0, 0, 0, 0, 0];
}

// Get recent activity
$activityResult = safeExecuteQuery($conn, 
    "SELECT 
        pwcode,
        cfirstname,
        clastname,
        client,
        job_title,
        status,
        DATE_FORMAT(created_at, '%b %d, %Y') as activity_date
     FROM paperworkdetails 
     WHERE submittedby = ? 
     ORDER BY created_at DESC
     LIMIT 5", 
    's', [$recruiterData['email']]);

if ($activityResult !== false) {
    $activities = [];
    
    while ($row = $activityResult['result']->fetch_assoc()) {
        // Create a descriptive activity based on status
        switch($row['status']) {
            case 'active':
            case 'hired':
                $row['activity_type'] = 'Placement Completed';
                $row['description'] = "{$row['cfirstname']} {$row['clastname']} was successfully placed at {$row['client']} as {$row['job_title']}.";
                break;
            case 'in_process':
            case 'pending':
                $row['activity_type'] = 'New Candidate Submitted';
                $row['description'] = "{$row['cfirstname']} {$row['clastname']} was submitted for the {$row['job_title']} position at {$row['client']}.";
                break;
            case 'rejected':
                $row['activity_type'] = 'Submission Rejected';
                $row['description'] = "{$row['cfirstname']} {$row['clastname']}'s application for {$row['job_title']} at {$row['client']} was rejected.";
                break;
            default:
                $row['activity_type'] = 'Submission Update';
                $row['description'] = "{$row['cfirstname']} {$row['clastname']}'s submission for {$row['job_title']} at {$row['client']} was updated.";
        }
        
        $activities[] = $row;
    }
    
    freeResources($activityResult);
} else {
    $activities = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>VDart PMT - Recruiter Profile</title>
    <link rel="icon" href="images.png" type="image/png">
    
    <!-- Google Fonts - Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            /* Grey Primary Colors */
            --grey-50: #f8fafc;
            --grey-100: #f1f5f9;
            --grey-200: #e2e8f0;
            --grey-300: #cbd5e1;
            --grey-400: #94a3b8;
            --grey-500: #64748b;
            --grey-600: #475569;
            --grey-700: #334155;
            --grey-800: #1e293b;
            --grey-900: #0f172a;
            
            /* Blue Secondary Colors */
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
            --blue-200: #bfdbfe;
            --blue-300: #93c5fd;
            --blue-400: #60a5fa;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --blue-800: #1e40af;
            --blue-900: #1e3a8a;
            
            /* System Colors */
            --success: #10b981;
            --success-dark: #059669;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --info: var(--blue-500);
            
            /* Typography */
            --font-family: 'Montserrat', system-ui, -apple-system, sans-serif;
            
            /* Spacing */
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            /* Transitions */
            --transition-all: all 0.2s ease-in-out;
            --transition-fast: all 0.15s ease-in-out;
            --transition-slow: all 0.3s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, var(--grey-50) 0%, var(--blue-50) 100%);
            color: var(--grey-800);
            line-height: 1.6;
            font-size: 14px;
            min-height: 100vh;
        }

        .main {
            padding: var(--space-6);
            margin-left: 290px; /* Account for sidebar width */
            transition: var(--transition-all);
        }

        /* Sidebar collapsed state */
        .sidebar.collapsed + .main {
            margin-left: 105px; /* Account for collapsed sidebar width */
        }

        /* Header */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-8);
            background: linear-gradient(135deg, var(--grey-800) 0%, var(--grey-700) 100%);
            padding: var(--space-6) var(--space-8);
            color: white;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .topbar::before {
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

        .dashboard-heading {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: var(--space-1);
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .dashboard-heading::after {
            display: none;
        }

        /* Profile Overview */
        .profile-overview {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-xl);
            margin-bottom: var(--space-8);
            overflow: hidden;
            position: relative;
        }

        .profile-header {
            display: flex;
            position: relative;
            padding: 0;
        }

        .profile-cover {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 140px;
            background: linear-gradient(135deg, var(--grey-800) 0%, var(--grey-700) 100%);
            z-index: 1;
        }

        .profile-header-content {
            display: flex;
            z-index: 2;
            width: 100%;
            padding: var(--space-8);
            margin-top: 60px;
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-right: var(--space-6);
            border: 4px solid white;
            box-shadow: var(--shadow-lg);
            flex-shrink: 0;
        }

        .profile-avatar-text {
            font-size: 3rem;
            font-weight: 800;
            color: var(--grey-800);
            text-transform: uppercase;
            letter-spacing: -0.025em;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex-grow: 1;
            color: white;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: var(--space-2);
            letter-spacing: -0.025em;
        }

        .profile-title {
            font-size: 1.125rem;
            color: var(--blue-200);
            margin-bottom: var(--space-4);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-5);
            margin-bottom: var(--space-4);
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .profile-meta-icon {
            color: var(--blue-400);
            font-size: 1rem;
        }

        .profile-meta-item a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition-all);
        }

        .profile-meta-item a:hover {
            color: var(--blue-200);
            text-decoration: underline;
        }

        /* Tabs */
        .tabs-container {
            position: relative;
        }

        .tabs-nav {
            display: flex;
            background: var(--grey-100);
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid var(--grey-200);
        }

        .tab-item {
            padding: var(--space-4) var(--space-6);
            font-weight: 600;
            color: var(--grey-600);
            cursor: pointer;
            position: relative;
            transition: var(--transition-all);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.875rem;
            background: transparent;
            border: none;
        }

        .tab-item:hover {
            color: var(--blue-600);
            background: var(--blue-50);
        }

        .tab-item.active {
            color: var(--blue-600);
            background: white;
        }

        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
        }

        .tab-content {
            display: none;
            padding: var(--space-8);
            background: white;
        }

        .tab-content.active {
            display: block;
        }

        /* Cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .card {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-lg);
            padding: var(--space-6);
            transition: var(--transition-all);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--blue-500);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .card-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--space-4);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .card-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: var(--transition-all);
        }

        .card:hover .card-icon::before {
            transform: translateX(100%);
        }

        .card-icon i {
            font-size: 1.5rem;
            color: white;
            z-index: 1;
            position: relative;
        }

        .card-icon.blue {
            background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
        }

        .card-icon.green {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
        }

        .card-icon.amber {
            background: linear-gradient(135deg, var(--warning), var(--warning-dark));
        }

        .card-icon.red {
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
        }

        .card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--grey-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--grey-800);
            margin-bottom: var(--space-1);
            line-height: 1;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: var(--grey-500);
            font-weight: 500;
        }

        /* Charts */
        .chart-container {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-lg);
            margin-bottom: var(--space-8);
            overflow: hidden;
        }

        .chart-header {
            padding: var(--space-6) var(--space-8);
            border-bottom: 2px solid var(--grey-200);
            background: linear-gradient(135deg, var(--grey-100), var(--blue-50));
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--grey-800);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .chart-title i {
            color: var(--blue-500);
        }

        .chart-body {
            padding: var(--space-6);
            height: 350px;
            position: relative;
        }

        /* Tables */
        .table-container {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-lg);
            margin-bottom: var(--space-8);
            overflow: hidden;
        }

        .table-header {
            padding: var(--space-6) var(--space-8);
            border-bottom: 2px solid var(--grey-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--grey-100), var(--blue-50));
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--grey-800);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table-title i {
            color: var(--blue-500);
        }

        .table-actions {
            display: flex;
            gap: var(--space-3);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: linear-gradient(135deg, var(--grey-800), var(--grey-700));
            color: white;
            padding: var(--space-4) var(--space-5);
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.875rem;
        }

        .data-table td {
            padding: var(--space-4) var(--space-5);
            border-bottom: 1px solid var(--grey-200);
            color: var(--grey-800);
            vertical-align: middle;
            font-weight: 500;
        }

        .data-table tbody tr:nth-child(even) {
            background: var(--grey-50);
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, var(--blue-50), var(--grey-50));
            transform: scale(1.002);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: var(--space-1) var(--space-3);
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-success, .status-active, .status-hired {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
        }

        .status-warning, .status-pending, .status-in_process {
            background: linear-gradient(135deg, var(--warning), var(--warning-dark));
        }

        .status-danger, .status-rejected {
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
        }

        .status-info, .status-interview {
            background: linear-gradient(135deg, var(--info), var(--blue-600));
        }

        .status-default {
            background: linear-gradient(135deg, var(--grey-500), var(--grey-400));
        }

        /* Tag badges */
        .tag-badge {
            display: inline-block;
            padding: var(--space-1) var(--space-3);
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: var(--space-2);
            margin-bottom: var(--space-2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tag-primary {
            background: linear-gradient(135deg, var(--blue-100), var(--blue-50));
            color: var(--blue-700);
            border: 1px solid var(--blue-200);
        }

        .tag-secondary {
            background: linear-gradient(135deg, var(--grey-100), var(--grey-50));
            color: var(--grey-700);
            border: 1px solid var(--grey-200);
        }

        .tag-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            color: var(--success-dark);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Empty State */
        .empty-state {
            padding: var(--space-16) var(--space-8);
            text-align: center;
            color: var(--grey-500);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--grey-300);
            margin-bottom: var(--space-4);
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--grey-700);
            margin-bottom: var(--space-2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .empty-state-text {
            color: var(--grey-500);
            max-width: 500px;
            margin: 0 auto;
            font-weight: 500;
        }

        /* Section Title */
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--grey-800);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-2);
            border-bottom: 2px solid var(--grey-200);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--blue-500), var(--blue-400));
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: var(--space-8);
            margin-bottom: var(--space-8);
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, var(--blue-500), var(--blue-400));
        }

        .timeline-item {
            position: relative;
            padding-bottom: var(--space-6);
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-point {
            position: absolute;
            left: -46px;
            top: 0;
            width: 18px;
            height: 18px;
            background: white;
            border: 4px solid var(--blue-500);
            z-index: 1;
        }

        .timeline-date {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--grey-500);
            margin-bottom: var(--space-2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .timeline-content {
            background: white;
            padding: var(--space-4);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--grey-200);
            position: relative;
        }

        .timeline-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--blue-500);
        }

        .timeline-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--grey-800);
            margin-bottom: var(--space-2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .timeline-text {
            color: var(--grey-700);
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition-all);
            border: 2px solid transparent;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-family: var(--font-family);
            position: relative;
            overflow: hidden;
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
            background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
            color: white;
            border-color: var(--blue-600);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--blue-700), var(--blue-600));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--blue-700);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--grey-200), var(--grey-100));
            color: var(--grey-700);
            border-color: var(--grey-200);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--grey-300), var(--grey-200));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--grey-300);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .profile-header-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: var(--space-4);
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .cards-container {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main {
                padding: var(--space-4);
            }
            
            .topbar {
                flex-direction: column;
                text-align: center;
                gap: var(--space-4);
                padding: var(--space-5) var(--space-6);
            }
            
            .dashboard-heading {
                font-size: 1.75rem;
            }
            
            .cards-container {
                grid-template-columns: 1fr;
            }
            
            .tab-item {
                padding: var(--space-3) var(--space-4);
                font-size: 0.75rem;
            }
            
            .chart-body {
                height: 280px;
            }
            
            .profile-meta {
                flex-direction: column;
                align-items: center;
                gap: var(--space-3);
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-heading {
                font-size: 1.5rem;
            }
            
            .profile-name {
                font-size: 1.75rem;
            }
            
            .profile-title {
                font-size: 1rem;
            }
            
            .tabs-nav {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .tab-item {
                flex-shrink: 0;
            }
            
            .table-header {
                flex-direction: column;
                gap: var(--space-3);
                align-items: flex-start;
            }
            
            .data-table th,
            .data-table td {
                padding: var(--space-3);
                font-size: 0.75rem;
            }
        }

        /* Enhanced Hover Effects */
        .btn:hover {
            transform: translateY(-3px);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        /* Focus States */
        .btn:focus,
        .tab-item:focus {
            outline: 2px solid var(--blue-500);
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
            border: 4px solid var(--grey-200);
            border-left-color: var(--blue-500);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
    border-right: 1px solid var(--grey-200);
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
    border-bottom: 1px solid var(--grey-200);
    background: linear-gradient(135deg, var(--blue-600), var(--grey-600));
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
    color: var(--grey-500);
    padding: 0 1.5rem;
    margin-bottom: 1rem;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 1.5rem;
    color: var(--grey-700);
    text-decoration: none;
    transition: var(--transition-all);
    position: relative;
    margin: 2px 12px;
    border-radius: 0;
}

.sidebar-item:hover {
    background: linear-gradient(135deg, var(--blue-600), var(--grey-600));
    color: white;
    transform: translateX(4px);
}

.sidebar-item.active {
    background: linear-gradient(135deg, var(--blue-600), var(--grey-600));
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.sidebar-item.active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    background: var(--blue-500);
    border-radius: 2px;
}

.sidebar-item i {
    width: 20px;
    text-align: center;
}

.sidebar-item.text-danger:hover {
    background: var(--danger);
    color: white;
}

.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--grey-200);
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
    box-shadow: var(--shadow-lg);
    backdrop-filter: blur(10px);
    transition: var(--transition-all);
    color: var(--blue-500);
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
    background: var(--grey-300);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--grey-500);
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
            <a href="paperworkallrecords.php" class="sidebar-item">
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
            <a href="profile.php" class="sidebar-item active">
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

    <!-- Main content -->
    <div class="main">
        <div class="topbar">
            <h1 class="dashboard-heading">
                <i class="fas fa-user-tie"></i>
                Recruiter Profile
            </h1>
        </div>
        
        <!-- Profile Overview -->
        <div class="profile-overview">
            <div class="profile-header">
                <div class="profile-cover"></div>
                <div class="profile-header-content">
                    <div class="profile-avatar">
                        <?php if (!empty($recruiterData['profile_image']) && file_exists($recruiterData['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($recruiterData['profile_image']); ?>" alt="<?php echo htmlspecialchars($recruiterData['name']); ?>">
                        <?php else: ?>
                            <span class="profile-avatar-text"><?php echo htmlspecialchars($recruiterInitials); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($recruiterData['name']); ?></h1>
                        <p class="profile-title"><?php echo htmlspecialchars($recruiterData['position'] ?? 'Recruiter'); ?></p>
                        <div class="profile-meta">
                            <?php if (!empty($recruiterData['department'])): ?>
                            <div class="profile-meta-item">
                                <i class="fas fa-building profile-meta-icon"></i>
                                <span><?php echo htmlspecialchars($recruiterData['department']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="profile-meta-item">
                                <i class="fas fa-envelope profile-meta-icon"></i>
                                <a href="mailto:<?php echo htmlspecialchars($recruiterData['email']); ?>"><?php echo htmlspecialchars($recruiterData['email']); ?></a>
                            </div>
                            <?php if (!empty($recruiterData['phone'])): ?>
                            <div class="profile-meta-item">
                                <i class="fas fa-phone profile-meta-icon"></i>
                                <a href="tel:<?php echo htmlspecialchars($recruiterData['phone']); ?>"><?php echo htmlspecialchars($recruiterData['phone']); ?></a>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($recruiterData['linkedin_url'])): ?>
                            <div class="profile-meta-item">
                                <i class="fab fa-linkedin profile-meta-icon"></i>
                                <a href="<?php echo htmlspecialchars($recruiterData['linkedin_url']); ?>" target="_blank">LinkedIn Profile</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tabs-container">
                <div class="tabs-nav">
                    <div class="tab-item active" data-tab="performance">Performance</div>
                    <div class="tab-item" data-tab="submissions">Submissions</div>
                    <div class="tab-item" data-tab="team">Team & Accounts</div>
                    <div class="tab-item" data-tab="business">Business Impact</div>
                </div>
                
                <!-- Performance Tab -->
                <div id="performance" class="tab-content active">
                    <div class="cards-container">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-title">Candidates Submitted</div>
                            </div>
                            <div class="card-value"><?php echo $recruiterData['submission_count']; ?></div>
                            <div class="card-subtitle">Total submissions</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon green">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="card-title">Success Rate</div>
                            </div>
                            <div class="card-value"><?php echo $successRate; ?>%</div>
                            <div class="card-subtitle">Candidates placed</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon amber">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="card-title">Average Margin</div>
                            </div>
                            <div class="card-value"><?php echo $avgMargin; ?>%</div>
                            <div class="card-subtitle">On active placements</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon green">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="card-title">Ratecard Adherence</div>
                            </div>
                            <div class="card-value"><?php echo $ratecardAdherence; ?>%</div>
                            <div class="card-subtitle">Compliance with rate standards</div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-chart-line"></i>
                                Monthly Submission Performance
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="submissionsChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-chart-pie"></i>
                                Skill Distribution of Placements
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="skillsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Submissions Tab -->
                <div id="submissions" class="tab-content">
                    <div class="table-container">
                        <div class="table-header">
                            <h3 class="table-title">
                                <i class="fas fa-user-check"></i>
                                Recent Candidate Submissions
                            </h3>
                            <div class="table-actions">
                                <button class="btn btn-primary" onclick="exportData()"><i class="fas fa-download"></i> Export</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Candidate Name</th>
                                        <th>Role</th>
                                        <th>Client</th>
                                        <th>Submission Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recentSubmissions) > 0): ?>
                                        <?php foreach ($recentSubmissions as $submission): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($submission['cfirstname'] . ' ' . $submission['clastname']); ?></td>
                                                <td><?php echo htmlspecialchars($submission['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($submission['client']); ?></td>
                                                <td><?php echo htmlspecialchars($submission['submission_date']); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = 'status-default';
                                                        if (in_array($submission['status'], ['active', 'hired'])) {
                                                            $statusClass = 'status-success';
                                                        } elseif (in_array($submission['status'], ['pending', 'in_process'])) {
                                                            $statusClass = 'status-warning';
                                                        } elseif ($submission['status'] == 'rejected') {
                                                            $statusClass = 'status-danger';
                                                        } elseif ($submission['status'] == 'interview') {
                                                            $statusClass = 'status-info';
                                                        }
                                                    ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="viewpaperwork.php?pwcode=<?php echo urlencode($submission['pwcode']); ?>" class="btn btn-secondary" title="View Details"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="fas fa-file-alt"></i>
                                                    </div>
                                                    <h3 class="empty-state-title">No Submissions Found</h3>
                                                    <p class="empty-state-text">There are no candidate submissions recorded for this recruiter yet.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-chart-pie"></i>
                                Submission Status Breakdown
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Team & Accounts Tab -->
                <div id="team" class="tab-content">
                    <div class="cards-container">
                        <?php if (count($businessUnits) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="card-title">Business Units</div>
                            </div>
                            <div style="margin-top: var(--space-4);">
                                <?php foreach ($businessUnits as $unit): ?>
                                    <span class="tag-badge tag-primary"><?php echo htmlspecialchars($unit); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($clientNames) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon green">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div class="card-title">Managed Clients</div>
                            </div>
                            <div style="margin-top: var(--space-4);">
                                <?php foreach ($clientNames as $client): ?>
                                    <span class="tag-badge tag-success"><?php echo htmlspecialchars($client); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($industryLabels) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon amber">
                                    <i class="fas fa-industry"></i>
                                </div>
                                <div class="card-title">Industry Focus</div>
                            </div>
                            <div style="margin-top: var(--space-4);">
                                <?php foreach ($industryLabels as $industry): ?>
                                    <span class="tag-badge tag-secondary"><?php echo htmlspecialchars($industry); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($businessUnits) == 0 && count($clientNames) == 0 && count($industryLabels) == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="empty-state-title">No Team Data</h3>
                        <p class="empty-state-text">No team members are associated with this recruiter yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Business Impact Tab -->
                <div id="business" class="tab-content">
                    <div class="cards-container">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="card-title">Revenue Generated</div>
                            </div>
                            <div class="card-value"><?php echo $formattedRevenue; ?></div>
                            <div class="card-subtitle">Total from placements</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon green">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="card-title">YoY Growth</div>
                            </div>
                            <div class="card-value"><?php echo $yoyGrowth; ?>%</div>
                            <div class="card-subtitle">Compared to previous year</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon amber">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="card-title">Average Time to Fill</div>
                            </div>
                            <div class="card-value"><?php echo $avgTimeToFill; ?></div>
                            <div class="card-subtitle">Days from submission to hire</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon green">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="card-title">Active Placements</div>
                            </div>
                            <div class="card-value"><?php echo $activePlacements; ?></div>
                            <div class="card-subtitle">Currently working</div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-chart-bar"></i>
                                Revenue & Margin Trends
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-industry"></i>
                                Industry Distribution
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="industryChart"></canvas>
                        </div>
                    </div>
                    
                    <?php if (count($activities) > 0): ?>
                    <div class="section-title">Recent Activity</div>
                    <div class="timeline">
                        <?php foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-point"></div>
                            <div class="timeline-date"><?php echo htmlspecialchars($activity['activity_date']); ?></div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo htmlspecialchars($activity['activity_type']); ?></div>
                                <div class="timeline-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            const tabItems = document.querySelectorAll('.tab-item');
            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabItems.forEach(tab => tab.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab contents
                    const tabContents = document.querySelectorAll('.tab-content');
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Show the selected tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Initialize charts
            initializeCharts();

            // Enhanced interactions
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-6px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Enhanced table row interactions
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.002)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
        
        function initializeCharts() {
            // Chart.js default colors based on our design system
            const chartColors = {
                primary: '#2563eb',
                secondary: '#10b981',
                accent: '#f59e0b',
                danger: '#ef4444',
                grey: '#64748b'
            };

            // Monthly Submissions Chart
            const submissionsCtx = document.getElementById('submissionsChart').getContext('2d');
            new Chart(submissionsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($monthLabels); ?>,
                    datasets: [{
                        label: 'Submissions',
                        data: <?php echo json_encode($submissionCounts); ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderColor: chartColors.primary,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: chartColors.primary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }, {
                        label: 'Hires',
                        data: <?php echo json_encode($hireCounts); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderColor: chartColors.secondary,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: chartColors.secondary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    family: 'Montserrat',
                                    weight: '600'
                                },
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                font: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                font: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        }
                    }
                }
            });
            
            // Skills Distribution Chart - Bar chart
            const skillsCtx = document.getElementById('skillsChart').getContext('2d');
            new Chart(skillsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($skillLabels); ?>,
                    datasets: [{
                        label: 'Candidates',
                        data: <?php echo json_encode($skillCounts); ?>,
                        backgroundColor: [
                            'rgba(37, 99, 235, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(100, 116, 139, 0.8)'
                        ],
                        borderWidth: 0,
                        borderRadius: 0,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + ' candidates';
                                }
                            },
                            titleFont: {
                                family: 'Montserrat',
                                weight: '600'
                            },
                            bodyFont: {
                                family: 'Montserrat',
                                weight: '500'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Candidates',
                                font: {
                                    family: 'Montserrat',
                                    weight: '600'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                font: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Skills',
                                font: {
                                    family: 'Montserrat',
                                    weight: '600'
                                }
                            },
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        }
                    }
                }
            });
            
            // Status Breakdown Chart - Horizontal bar chart
            if (document.getElementById('statusChart')) {
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($statusLabels); ?>,
                        datasets: [{
                            label: 'Submissions',
                            data: <?php echo json_encode($statusCounts); ?>,
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.8)',  // Success/Active/Hired - Green
                                'rgba(245, 158, 11, 0.8)',  // Warning/Pending - Amber
                                'rgba(37, 99, 235, 0.8)',   // Info/Interview - Blue
                                'rgba(239, 68, 68, 0.8)',   // Danger/Rejected - Red
                                'rgba(100, 116, 139, 0.8)'  // Default - Gray
                            ],
                            borderWidth: 0,
                            borderRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const totalCount = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((context.raw / totalCount) * 100);
                                        return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                    }
                                },
                                titleFont: {
                                    family: 'Montserrat',
                                    weight: '600'
                                },
                                bodyFont: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Submissions',
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '500'
                                    }
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Status',
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Revenue Chart
            if (document.getElementById('revenueChart')) {
                const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($quarterLabels); ?>,
                        datasets: [{
                            label: 'Revenue ($)',
                            data: <?php echo json_encode($revenueValues); ?>,
                            backgroundColor: 'rgba(37, 99, 235, 0.8)',
                            borderWidth: 0,
                            borderRadius: 0,
                            yAxisID: 'y'
                        }, {
                            label: 'Margin (%)',
                            data: <?php echo json_encode($marginValues); ?>,
                            type: 'line',
                            borderColor: chartColors.secondary,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: chartColors.secondary,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    },
                                    padding: 20
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                type: 'linear',
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue ($)',
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '500'
                                    }
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                type: 'linear',
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Margin (%)',
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    }
                                },
                                min: 0,
                                max: 30,
                                grid: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Industry Chart - Doughnut chart
            if (document.getElementById('industryChart')) {
                const industryCtx = document.getElementById('industryChart').getContext('2d');
                new Chart(industryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($industryLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($industryCounts); ?>,
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(100, 116, 139, 0.8)'
                            ],
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    font: {
                                        family: 'Montserrat',
                                        weight: '600'
                                    },
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded'
                                }
                            },
                            tooltip: {
                                titleFont: {
                                    family: 'Montserrat',
                                    weight: '600'
                                },
                                bodyFont: {
                                    family: 'Montserrat',
                                    weight: '500'
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Function to export data to CSV
        function exportData() {
            window.location.href = 'export_recruiter_data.php?id=<?php echo $recruiterId; ?>';
        }
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