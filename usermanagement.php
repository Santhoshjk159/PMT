<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Script started"); // Debugging marker

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

// Check if user has admin privileges
if ($userRole !== 'Admin') {
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
            background-color: rgba(0, 0, 0, 0.85);
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
        
        .sidebar.access-denied-blur {
            filter: blur(4px);
            pointer-events: none;
        }
        
        /* Custom access denied popup styling */
        .access-denied-popup {
            background: linear-gradient(135deg, white 0%, var(--gray-50) 100%);
            padding: 3rem;
            border-radius: 0;
            box-shadow: var(--shadow-lg);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--primary-light);
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
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }
        
        .access-denied-icon {
            font-size: 4rem;
            color: var(--danger-color);
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
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .access-denied-message {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .access-denied-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 0;
            box-shadow: var(--shadow-lg);
        }
        
        .access-denied-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
        }
    </style>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Add blur effect to main content and sidebar
            document.body.classList.add("access-denied");
            const mainContent = document.querySelector(".main");
            const sidebar = document.querySelector(".sidebar");
            
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
                        You do not have permission to manage users. 
                        Only administrators can access user management features.
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

// Process actions (add, edit, delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    
    // Add new user
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? '';
        $empId = $_POST['empid'] ?? '';
        $password = password_hash($_POST['password'] ?? 'default123', PASSWORD_DEFAULT);
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        // Check if email already exists
        $checkResult = safeExecuteQuery(
            $conn, 
            "SELECT id FROM users WHERE email = ?", 
            's', 
            [$email]
        );
        
        if ($checkResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Database error checking email'
            ];
            echo json_encode($response);
            exit;
        }
        
        if ($checkResult['result']->num_rows > 0) {
            freeResources($checkResult);
            $response = [
                'status' => 'error',
                'message' => 'Email already exists in the system'
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($checkResult);
        
        // Insert new user
        $insertResult = safeExecuteQuery(
            $conn,
            "INSERT INTO users (name, email, role, userwithempid, password, department, position, status, created_at, last_login) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)",
            'ssssssss',
            [$name, $email, $role, $empId, $password, $department, $position, $status]
        );
        
        if ($insertResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to add user: ' . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
        
        $newUserId = $insertResult['insert_id'];
        freeResources($insertResult);
        
        // Log the action
        $details = json_encode(['name' => $name, 'email' => $email, 'role' => $role]);
        $logResult = safeExecuteQuery(
            $conn,
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'create', 'user', ?, ?, NOW())",
            'iis',
            [$userId, $newUserId, $details]
        );
        
        if ($logResult !== false) {
            freeResources($logResult);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'User added successfully'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Edit user
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $editId = $_POST['user_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? '';
        $empId = $_POST['empid'] ?? '';
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        // Check if email already exists for a different user
        $checkResult = safeExecuteQuery(
            $conn,
            "SELECT id FROM users WHERE email = ? AND id != ?",
            'si',
            [$email, $editId]
        );
        
        if ($checkResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Database error checking email'
            ];
            echo json_encode($response);
            exit;
        }
        
        if ($checkResult['result']->num_rows > 0) {
            freeResources($checkResult);
            $response = [
                'status' => 'error',
                'message' => 'Email already exists for another user'
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($checkResult);
        
        // Update user information
        $updateResult = safeExecuteQuery(
            $conn,
            "UPDATE users SET name = ?, email = ?, role = ?, userwithempid = ?, 
            department = ?, position = ?, status = ?, updated_at = NOW() WHERE id = ?",
            'sssssssi',
            [$name, $email, $role, $empId, $department, $position, $status, $editId]
        );
        
        if ($updateResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to update user: ' . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($updateResult);
        
        // Log the action
        $details = json_encode(['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status]);
        $logResult = safeExecuteQuery(
            $conn,
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'update', 'user', ?, ?, NOW())",
            'iis',
            [$userId, $editId, $details]
        );
        
        if ($logResult !== false) {
            freeResources($logResult);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'User updated successfully'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Reset password
    if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $targetUserId = $_POST['user_id'] ?? 0;
        $newPassword = password_hash($_POST['new_password'] ?? 'reset123', PASSWORD_DEFAULT);
        
        $resetResult = safeExecuteQuery(
            $conn,
            "UPDATE users SET password = ?, force_password_change = 1, updated_at = NOW() WHERE id = ?",
            'si',
            [$newPassword, $targetUserId]
        );
        
        if ($resetResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to reset password: ' . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($resetResult);
        
        // Log the action
        $details = json_encode(['action' => 'password reset']);
        $logResult = safeExecuteQuery(
            $conn,
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'reset_password', 'user', ?, ?, NOW())",
            'iis',
            [$userId, $targetUserId, $details]
        );
        
        if ($logResult !== false) {
            freeResources($logResult);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'Password reset successfully'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Delete user (soft delete)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $deleteId = $_POST['user_id'] ?? 0;
        
        // Get user info for logging
        $userInfoResult = safeExecuteQuery(
            $conn,
            "SELECT name, email FROM users WHERE id = ?",
            'i',
            [$deleteId]
        );
        
        if ($userInfoResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to get user information'
            ];
            echo json_encode($response);
            exit;
        }
        
        $userInfo = $userInfoResult['result']->fetch_assoc();
        freeResources($userInfoResult);
        
        // Soft delete
        $deleteResult = safeExecuteQuery(
            $conn,
            "UPDATE users SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?",
            'i',
            [$deleteId]
        );
        
        if ($deleteResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to delete user: ' . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($deleteResult);
        
        // Log the action
        $details = json_encode(['name' => $userInfo['name'], 'email' => $userInfo['email']]);
        $logResult = safeExecuteQuery(
            $conn,
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'delete', 'user', ?, ?, NOW())",
            'iis',
            [$userId, $deleteId, $details]
        );
        
        if ($logResult !== false) {
            freeResources($logResult);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'User deleted successfully'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Change user status (activate/deactivate)
    if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
        $targetUserId = $_POST['user_id'] ?? 0;
        $newStatus = $_POST['status'] ?? 'active';
        
        $statusResult = safeExecuteQuery(
            $conn,
            "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?",
            'si',
            [$newStatus, $targetUserId]
        );
        
        if ($statusResult === false) {
            $response = [
                'status' => 'error',
                'message' => 'Failed to update user status: ' . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
        
        freeResources($statusResult);
        
        // Log the action
        $details = json_encode(['new_status' => $newStatus]);
        $logResult = safeExecuteQuery(
            $conn,
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'status_change', 'user', ?, ?, NOW())",
            'iis',
            [$userId, $targetUserId, $details]
        );
        
        if ($logResult !== false) {
            freeResources($logResult);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'User status updated successfully'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Bulk action (delete, activate, deactivate)
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_action') {
        $userIds = json_decode($_POST['user_ids'], true);
        $bulkAction = $_POST['bulk_action'] ?? '';
        
        if (empty($userIds)) {
            $response = [
                'status' => 'error',
                'message' => 'No users selected'
            ];
            echo json_encode($response);
            exit;
        }
        
        $success = true;
        $affectedCount = 0;

        foreach ($userIds as $bulkUserId) {
            if ($bulkAction === 'delete') {
                $bulkQuery = "UPDATE users SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
                $actionType = 'delete';
            } elseif ($bulkAction === 'activate') {
                $bulkQuery = "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?";
                $actionType = 'status_change';
            } elseif ($bulkAction === 'deactivate') {
                $bulkQuery = "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?";
                $actionType = 'status_change';
            } else {
                continue;
            }
            
            $bulkResult = safeExecuteQuery($conn, $bulkQuery, 'i', [$bulkUserId]);
            
            if ($bulkResult !== false) {
                if ($bulkResult['affected_rows'] > 0) {
                    $affectedCount++;
                    
                    // Log each action
                    $details = json_encode(['bulk_action' => $bulkAction]);
                    $logResult = safeExecuteQuery(
                        $conn,
                        "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, created_at) 
                        VALUES (?, ?, 'user', ?, ?, NOW())",
                        'isis',
                        [$userId, $actionType, $bulkUserId, $details]
                    );
                    
                    if ($logResult !== false) {
                        freeResources($logResult);
                    }
                }
                
                freeResources($bulkResult);
            } else {
                $success = false;
            }
        }
        
        if ($success && $affectedCount > 0) {
            $response = [
                'status' => 'success',
                'message' => "Bulk action completed successfully. {$affectedCount} user(s) affected."
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Failed to complete bulk action or no users were affected.'
            ];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // If we got here, no valid action was found
    echo json_encode($response);
    exit;
}

// Fetch user data for display
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Build the WHERE clause using direct string interpolation (safer than prepared statements in this case)
$whereClauseSimple = "1=1";
if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $whereClauseSimple .= " AND (u.name LIKE '%$searchEscaped%' OR u.email LIKE '%$searchEscaped%' OR u.userwithempid LIKE '%$searchEscaped%')";
}
if (!empty($role)) {
    $roleEscaped = $conn->real_escape_string($role);
    $whereClauseSimple .= " AND u.role = '$roleEscaped'";
}
if (!empty($status)) {
    if ($status === 'all_active') {
        $whereClauseSimple .= " AND u.status != 'deleted'";
    } else if ($status !== '') {
        $statusEscaped = $conn->real_escape_string($status);
        $whereClauseSimple .= " AND u.status = '$statusEscaped'";
    }
} else {
    $whereClauseSimple .= " AND u.status != 'deleted'";
}
if (!empty($department)) {
    $departmentEscaped = $conn->real_escape_string($department);
    $whereClauseSimple .= " AND u.department = '$departmentEscaped'";
}

// Count total for pagination - use direct query
$countSimpleQuery = "SELECT COUNT(*) as total_count FROM users u WHERE $whereClauseSimple";
$countResult = $conn->query($countSimpleQuery);
if ($countResult === false) {
    error_log("Count query failed: " . $conn->error . " in query: " . $countSimpleQuery);
    die("Count query failed: " . $conn->error);
}

$countData = $countResult->fetch_assoc();
$totalRecords = $countData['total_count'] ?? 0;
$totalPages = ceil($totalRecords / $recordsPerPage);

// IMPORTANT: Free the result from count query
$countResult->free();

// Main query for user data - use direct query since we're not using parameters
$simpleQuery = "SELECT u.*, IFNULL((SELECT COUNT(*) FROM paperworkdetails WHERE submittedby = u.email), 0) as paperwork_count 
                FROM users u 
                WHERE $whereClauseSimple
                ORDER BY u.created_at DESC 
                LIMIT $offset, $recordsPerPage";

$users = $conn->query($simpleQuery);
if ($users === false) {
    error_log("User query failed: " . $conn->error . " in query: " . $simpleQuery);
    die("Database query failed: " . $conn->error);
}

// Get all departments for filter dropdown - use direct query
$deptQuery = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$deptResult = $conn->query($deptQuery);
if ($deptResult === false) {
    error_log("Department query failed: " . $conn->error);
    $departments = [];
} else {
    $departments = [];
    while ($dept = $deptResult->fetch_assoc()) {
        $departments[] = $dept['department'];
    }
    // IMPORTANT: Free the result
    $deptResult->free();
}

// Recent activity logs for user management - use direct query
$activityQuery = "SELECT a.*, u.name as actor_name, u2.name as target_name 
                 FROM activity_log a
                 LEFT JOIN users u ON a.user_id = u.id
                 LEFT JOIN users u2 ON a.entity_id = u2.id AND a.entity_type = 'user'
                 WHERE a.entity_type = 'user'
                 ORDER BY a.created_at DESC
                 LIMIT 10";
$activityResult = $conn->query($activityQuery);
if ($activityResult === false) {
    error_log("Activity query failed: " . $conn->error);
    // We'll handle this in the HTML section
}

// Note: Remember to free $users and $activityResult after you're done using them in HTML
// Best practice would be to add at the very end of the script:
// if ($users instanceof mysqli_result) $users->free();
// if ($activityResult instanceof mysqli_result) $activityResult->free();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>VDart PMT - User Management</title>
    <link rel="icon" href="images.png" type="image/png">
    <!-- Google Fonts - Poppins -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- SweetAlert2 for better alerts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Root variables for consistent theming */
        :root {
            --primary-color: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --success-color: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --font-sans: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 0.375rem;
            --transition: all 0.2s ease;
        }

        /* Base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-sans);
        }

        body {
            background-color: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
        }

        .main {
            padding: 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .dashboard-heading {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            position: relative;
            display: inline-block;
        }

        .dashboard-heading::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background-color: var(--primary-light);
            border-radius: 2px;
        }

        /* Main Layout */
        .dashboard-container {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 350px);
            gap: 24px;
            overflow: hidden; /* Prevent overflow from child elements */
        }

        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .side-panel {
                margin-top: 24px;
            }
        }

        /* Dashboard cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            gap: 12px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.users {
            background-color: var(--primary-light);
        }

        .stat-icon.admin {
            background-color: var(--danger-color);
        }

        .stat-icon.active {
            background-color: var(--success-color);
        }

        .stat-icon.inactive {
            background-color: var(--warning-color);
        }

        .stat-title {
            font-size: 16px;
            font-weight: 500;
            color: var(--gray-600);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .stat-footer {
            margin-top: auto;
            font-size: 14px;
            color: var(--gray-500);
        }

        /* Table styles */
        .main-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .filter-panel {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .filter-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--primary-light);
        }

        .filter-title {
            display: flex;
            align-items: center;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 16px;
        }

        .filter-title i {
            margin-right: 8px;
            color: var(--primary-light);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-group {
            margin-bottom: 16px;
        }

        .filter-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .filter-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
        }

        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background-color: var(--gray-300);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }

        .table-title i {
            margin-right: 10px;
            color: var(--primary-light);
        }

        .table-actions {
            display: flex;
            gap: 12px;
        }

        .bulk-actions {
            display: none;
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 10;
            padding: 12px 24px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 16px;
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: space-between;
        }

        .bulk-count {
            font-weight: 500;
            color: var(--primary-color);
        }

        .bulk-buttons {
            display: flex;
            gap: 8px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            position: relative;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .user-table th {
            padding: 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .user-table td {
            padding: 16px;
            font-size: 14px;
            color: var(--gray-800);
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }
        
        .user-table tbody tr {
            transition: var(--transition);
        }
        
        .user-table tbody tr:hover {
            background-color: var(--gray-50);
        }
        
        /* User avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            margin-right: 12px;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .user-email {
            font-size: 13px;
            color: var(--gray-500);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            text-align: center;
        }
        
        .status-active {
            background-color: var(--success-color);
        }
        
        .status-inactive {
            background-color: var(--warning-color);
        }
        
        .status-deleted {
            background-color: var(--danger-color);
        }
        
        .status-pending {
            background-color: var(--info-color);
        }
        
        /* Role badges */
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-800);
            text-align: center;
            background-color: var(--gray-200);
        }
        
        .role-admin {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        .role-contracts {
            background-color: #bfdbfe;
            color: #1e40af;
        }
        
        .role-manager {
            background-color: #bbf7d0;
            color: #166534;
        }
        
        .role-user {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        /* Actions menu */
        .actions-cell {
            text-align: right;
            white-space: nowrap;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            margin-left: 4px;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        .action-view { background-color: var(--primary-color); }
        .action-edit { background-color: var(--info-color); }
        .action-password { background-color: var(--warning-color); }
        .action-delete { background-color: var(--danger-color); }
        
        /* Dropdown menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-btn {
            background-color: var(--gray-200);
            color: var(--gray-600);
            border: none;
            padding: 10px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .dropdown-btn:hover {
            background-color: var(--gray-300);
        }
        
        .dropdown-btn i {
            margin-right: 6px;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: var(--shadow-md);
            border-radius: var(--border-radius);
            z-index: 100;
            overflow: hidden;
        }
        
        .dropdown-item {
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            color: var(--gray-700);
            font-size: 14px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .dropdown-item:hover {
            background-color: var(--gray-100);
        }
        
        .dropdown-item.text-danger {
            color: var(--danger-color);
        }
        
        .dropdown-item.text-warning {
            color: var(--warning-color);
        }
        
        .dropdown.show .dropdown-content {
            display: block;
        }
        
        /* Side panel */
        .side-panel {
            height: fit-content;
            max-height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .side-panel-section {
            margin-bottom: 24px;
        }
        
        .side-panel-title {
            display: flex;
            align-items: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 16px;
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .side-panel-title i {
            margin-right: 8px;
            color: var(--primary-light);
        }
        
        .side-panel-content {
            padding: 0 16px 16px;
        }
        
        /* Activity log */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }
        
        .activity-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .activity-content, .activity-user {
            white-space: normal;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        
        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            background-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }
        
        .activity-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 17px;
            top: 12px;
            width: 2px;
            height: calc(100% - 12px);
            background-color: var(--gray-300);
        }
        
        .activity-header {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .activity-user {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .activity-time {
            margin-left: auto;
            font-size: 12px;
            color: var(--gray-500);
        }
        
        .activity-content {
            font-size: 13px;
            color: var(--gray-600);
        }
        
        .activity-action {
            font-weight: 500;
        }
        
        .activity-create {
            color: var(--success-color);
        }
        
        .activity-update {
            color: var(--info-color);
        }
        
        .activity-delete {
            color: var(--danger-color);
        }
        
        .activity-reset {
            color: var(--warning-color);
        }
        
        /* User statistics */
        .chart-container {
            width: 100%;
            height: 250px;
            max-width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 600px;
            animation: modal-appear 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes modal-appear {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .modal-title i {
            margin-right: 10px;
            color: var(--primary-light);
        }
        
        .modal-close {
            font-size: 24px;
            color: var(--gray-500);
            background: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .modal-close:hover {
            color: var(--danger-color);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Form styles */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 10px) center;
            padding-right: 30px;
            transition: var(--transition);
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        
        .required:after {
            content: '*';
            color: var(--danger-color);
            margin-left: 4px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 24px;
            gap: 8px;
        }
        
        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            text-decoration: none;
            transition: var(--transition);
            background-color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .pagination a:hover {
            background-color: var(--gray-100);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .toast {
            padding: 16px;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toast-in 0.3s ease forwards;
            max-width: 400px;
        }
        
        .toast-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .toast-success .toast-icon {
            background-color: var(--success-color);
        }
        
        .toast-error .toast-icon {
            background-color: var(--danger-color);
        }
        
        .toast-warning .toast-icon {
            background-color: var(--warning-color);
        }
        
        .toast-info .toast-icon {
            background-color: var(--info-color);
        }
        
        .toast-content {
            flex-grow: 1;
        }
        
        .toast-title {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .toast-message {
            font-size: 14px;
            color: var(--gray-600);
        }
        
        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toast-close:hover {
            color: var(--gray-700);
        }
        
        @keyframes toast-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast.removing {
            animation: toast-out 0.3s ease forwards;
        }
        
        @keyframes toast-out {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* User profile view */
        .profile-header {
            display: flex;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 600;
            margin-right: 24px;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex-grow: 1;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }
        
        .profile-email {
            font-size: 16px;
            color: var(--gray-600);
            margin-bottom: 12px;
        }
        
        .profile-meta {
            display: flex;
            gap: 16px;
        }
        
        .profile-role {
            margin-right: 16px;
        }
        
        .profile-content {
            padding: 24px;
        }
        
        .profile-section {
            margin-bottom: 24px;
        }
        
        .profile-section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .profile-item {
            display: flex;
            flex-direction: column;
        }
        
        .profile-label {
            font-size: 14px;
            color: var(--gray-500);
            margin-bottom: 4px;
        }
        
        .profile-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--gray-800);
        }
        

        @media (max-width: 992px) {
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-actions {
        margin-top: 12px;
        width: 100%;
        justify-content: space-between;
    }
    .user-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .actions-cell {
        position: sticky;
        right: 0;
        background-color: rgba(255, 255, 255, 0.9);
    }
}


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 16px;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .modal-content {
                width: 95%;
                margin: 10px auto;
            }
            
            .modal-body {
                max-height: 70vh;
                overflow-y: auto;
            }
            .side-panel-content {
                padding: 0 12px 12px;
            }
            
            .chart-container {
                height: 200px;
            }
        }
        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 80px; /* Changed from 60px to 40px */
            font-weight: 500;
            color: var(--gray-500);
        }
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-actions .btn {
                width: 100%;
            }

            .table-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .table-actions .btn,
            .table-actions .dropdown {
                width: 100%;
                margin-bottom: 8px;
            }
            
            .modal-footer {
                flex-direction: column-reverse;
            }
            
            .modal-footer .btn {
                width: 100%;
                margin-bottom: 8px;
            }
        }

        /* Fixed height with scrollbar for activity list */
        .side-panel-section:first-of-type .side-panel-content {
            max-height: 400px; /* Adjust this value based on your needs */
            overflow-y: auto;
            scrollbar-width: thin; /* For Firefox */
            scrollbar-color: var(--gray-300) var(--gray-100); /* For Firefox */
        }

        /* Custom scrollbar styling for Webkit browsers (Chrome, Safari, etc.) */
        .side-panel-section:first-of-type .side-panel-content::-webkit-scrollbar {
            width: 6px;
        }

        .side-panel-section:first-of-type .side-panel-content::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 8px;
        }

        .side-panel-section:first-of-type .side-panel-content::-webkit-scrollbar-thumb {
            background-color: var(--gray-300);
            border-radius: 8px;
        }

        .side-panel-section:first-of-type .side-panel-content::-webkit-scrollbar-thumb:hover {
            background-color: var(--gray-400);
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
    border-right: 1px solid var(--gray-200);
    box-shadow: var(--shadow-lg);
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
    border-bottom: 1px solid var(--gray-200);
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
    color: var(--gray-500);
    padding: 0 1.5rem;
    margin-bottom: 1rem;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 1.5rem;
    color: var(--gray-700);
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    margin: 2px 12px;
    border-radius: 12px;
}

.sidebar-item:hover {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    transform: translateX(4px);
}

.sidebar-item.active {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
}

.sidebar-item.active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    background: var(--primary-color);
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
    border-top: 1px solid var(--gray-200);
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
    transition: all 0.2s ease;
    color: var(--primary-color);
}

.sidebar-toggle-btn:hover {
    transform: scale(1.1);
}

/* Adjust main content for sidebar */
.main {
    margin-left: 280px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.main.expanded {
    margin-left: 0;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0;
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
    background: var(--gray-300);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--gray-400);
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
            <a href="usermanagement.php" class="sidebar-item active">
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


    <!-- Main content -->
    <div class="main" id="mainContent">
        <div class="topbar">
            <h1 class="dashboard-heading">User Management</h1>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container" class="toast-container"></div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <?php
                // Get user statistics
                $statsQuery = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'Admin' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
                FROM users WHERE status != 'deleted'";
                $statsResult = $conn->query($statsQuery);
                $stats = $statsResult->fetch_assoc();
            ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-title">Total Users</div>
                </div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-footer">All registered users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon admin">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-title">Administrators</div>
                </div>
                <div class="stat-value"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-footer">Users with admin access</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-title">Active Users</div>
                </div>
                <div class="stat-value"><?php echo $stats['active_count']; ?></div>
                <div class="stat-footer">Currently active accounts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon inactive">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-title">Inactive Users</div>
                </div>
                <div class="stat-value"><?php echo $stats['inactive_count']; ?></div>
                <div class="stat-footer">Suspended or inactive accounts</div>
            </div>
        </div>
        
        <div class="dashboard-container">
            <!-- Main Panel -->
            <div class="dashboard-main">
                <!-- Filter Panel -->
                <div class="filter-panel">
                    <h2 class="filter-title"><i class="fas fa-filter"></i> Filter Users</h2>
                    <form id="filter-form" method="GET" action="usermanagement.php">
                        <div class="filter-form">
                            <div class="filter-group">
                                <label class="filter-label" for="search">Search</label>
                                <input type="text" id="search" name="search" class="filter-input" placeholder="Name, email, ID" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label class="filter-label" for="role">Role</label>
                                <select id="role" name="role" class="filter-input">
                                    <option value="">All Roles</option>
                                    <option value="Admin" <?php if ($role === 'Admin') echo 'selected'; ?>>Administrator</option>
                                    <option value="Contracts" <?php if ($role === 'Contracts') echo 'selected'; ?>>Contracts</option>
                                    <option value="Manager" <?php if ($role === 'Manager') echo 'selected'; ?>>Manager</option>
                                    <option value="User" <?php if ($role === 'User') echo 'selected'; ?>>Standard User</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label" for="status">Status</label>
                                <select id="status" name="status" class="filter-input">
                                    <option value="all_active" <?php if ($status === 'all_active' || empty($status)) echo 'selected'; ?>>All Active</option>
                                    <option value="active" <?php if ($status === 'active') echo 'selected'; ?>>Active Only</option>
                                    <option value="inactive" <?php if ($status === 'inactive') echo 'selected'; ?>>Inactive Only</option>
                                    <option value="deleted" <?php if ($status === 'deleted') echo 'selected'; ?>>Deleted</option>
                                    <option value="" <?php if ($status === '') echo 'selected'; ?>>All (Including Deleted)</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label" for="department">Department</label>
                                <select id="department" name="department" class="filter-input">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php if ($department === $dept) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                <button type="button" id="reset-filters" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Main Content -->
                <div class="main-content">
                    <div class="table-header">
                        <h2 class="table-title">
                            <i class="fas fa-user-friends"></i> User Directory
                        </h2>
                        <div class="table-actions">
                            <div class="dropdown">
                                <button class="dropdown-btn" onclick="toggleDropdown('bulk-dropdown')">
                                    <i class="fas fa-tasks"></i> Bulk Actions <i class="fas fa-chevron-down" style="margin-left: 6px;"></i>
                                </button>
                                <div id="bulk-dropdown" class="dropdown-content">
                                    <div class="dropdown-item" onclick="bulkAction('activate')">
                                        <i class="fas fa-check-circle"></i> Activate Selected
                                    </div>
                                    <div class="dropdown-item" onclick="bulkAction('deactivate')">
                                        <i class="fas fa-ban"></i> Deactivate Selected
                                    </div>
                                    <div class="dropdown-item text-danger" onclick="bulkAction('delete')">
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-primary" onclick="openAddUserModal()">
                                <i class="fas fa-user-plus"></i> Add New User
                            </button>
                        </div>
                    </div>
                    
                    <!-- Bulk Actions Bar (appears when checkboxes are selected) -->
                    <div id="bulk-actions-bar" class="bulk-actions">
                        <div class="bulk-count">
                            <span id="selected-count">0</span> users selected
                        </div>
                        <div class="bulk-buttons">
                            <button class="btn btn-success" onclick="bulkAction('activate')">
                                <i class="fas fa-check-circle"></i> Activate
                            </button>
                            <button class="btn btn-secondary" onclick="bulkAction('deactivate')">
                                <i class="fas fa-ban"></i> Deactivate
                            </button>
                            <button class="btn btn-danger" onclick="bulkAction('delete')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button class="btn btn-secondary" onclick="clearSelection()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th>User</th>
                                    <th>Employee ID</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Records</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="user-checkbox" data-id="<?php echo $user['id']; ?>" onchange="updateSelectedCount()">
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php 
                                                        if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                                                            echo '<img src="' . htmlspecialchars($user['profile_image']) . '" alt="Profile">';
                                                        } else {
                                                            echo substr(htmlspecialchars($user['name']), 0, 1);
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo !empty($user['userwithempid']) ? htmlspecialchars($user['userwithempid']) : '<span style="color: var(--gray-400); font-style: italic;">Not assigned</span>'; ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                                    <?php echo htmlspecialchars($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo !empty($user['department']) ? htmlspecialchars($user['department']) : '<span style="color: var(--gray-400); font-style: italic;">Not assigned</span>'; ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($user['status']) {
                                                    case 'active':
                                                        $statusClass = 'status-active';
                                                        break;
                                                    case 'inactive':
                                                        $statusClass = 'status-inactive';
                                                        break;
                                                    case 'deleted':
                                                        $statusClass = 'status-deleted';
                                                        break;
                                                    default:
                                                        $statusClass = 'status-pending';
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($user['last_login'])) {
                                                    echo date('M d, Y H:i', strtotime($user['last_login']));
                                                } else {
                                                    echo '<span style="color: var(--gray-400); font-style: italic;">Never</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="paperwork.php?submitted_by=<?php echo urlencode($user['email']); ?>" title="View user's records">
                                                    <?php echo (int)$user['paperwork_count']; ?>
                                                </a>
                                            </td>
                                            <td class="actions-cell">
                                                <button class="action-btn action-view" onclick="viewUser(<?php echo $user['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn action-edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>', '<?php echo addslashes(htmlspecialchars($user['email'])); ?>', '<?php echo addslashes(htmlspecialchars($user['role'])); ?>', '<?php echo addslashes(htmlspecialchars($user['userwithempid'])); ?>', '<?php echo addslashes(htmlspecialchars($user['department'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($user['position'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($user['status'])); ?>')" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn action-password" onclick="resetPasswordModal(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>')" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($user['id'] != $userId): // Prevent self-deletion ?>
                                                <button class="action-btn action-delete" onclick="deleteUserConfirm(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>')" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-users" style="font-size: 48px; color: var(--gray-300); margin-bottom: 16px; display: block;"></i>
                                            <h3 style="color: var(--gray-600); margin-bottom: 8px;">No Users Found</h3>
                                            <p style="color: var(--gray-500);">Try adjusting your filters or add a new user.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="usermanagement.php?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&department=<?php echo urlencode($department); ?>" title="Previous Page">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                                // Calculate pagination range
                                $startPage = max(1, $page - 2);
                                $endPage = min($startPage + 4, $totalPages);
                                
                                // Adjust start page if we're near the end
                                if ($endPage - $startPage < 4 && $startPage > 1) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                
                                // First page link if not in range
                                if ($startPage > 1): 
                            ?>
                                <a href="usermanagement.php?page=1&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&department=<?php echo urlencode($department); ?>">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="usermanagement.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&department=<?php echo urlencode($department); ?>" 
                                class="<?php if ($i == $page) echo 'active'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="usermanagement.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&department=<?php echo urlencode($department); ?>">
                                    <?php echo $totalPages; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="usermanagement.php?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&department=<?php echo urlencode($department); ?>" title="Next Page">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Side Panel -->
            <div class="side-panel">
                <!-- User Activity Section -->
                <div class="side-panel-section">
                    <h3 class="side-panel-title">
                        <i class="fas fa-history"></i> Recent Activity
                    </h3>
                    <div class="side-panel-content">
                        <div class="activity-list">
                            <?php if (is_object($activityResult) && $activityResult->num_rows > 0): ?>
                                <?php while ($activity = $activityResult->fetch_assoc()): ?>
                                    <div class="activity-item">
                                        <div class="activity-header">
                                            <span class="activity-user"><?php echo htmlspecialchars($activity['actor_name']); ?></span>
                                            <span class="activity-time"><?php echo date('M d, g:i a', strtotime($activity['created_at'])); ?></span>
                                        </div>
                                        <div class="activity-content">
                                            <?php
                                            $actionText = '';
                                            $actionClass = '';
                                            switch ($activity['action']) {
                                                case 'create':
                                                    $actionText = 'created';
                                                    $actionClass = 'activity-create';
                                                    break;
                                                case 'update':
                                                    $actionText = 'updated';
                                                    $actionClass = 'activity-update';
                                                    break;
                                                case 'delete':
                                                    $actionText = 'deleted';
                                                    $actionClass = 'activity-delete';
                                                    break;
                                                case 'reset_password':
                                                    $actionText = 'reset password for';
                                                    $actionClass = 'activity-reset';
                                                    break;
                                                case 'status_change':
                                                    $actionText = 'changed status of';
                                                    $actionClass = 'activity-update';
                                                    break;
                                                default:
                                                    $actionText = $activity['action'];
                                            }
                                            ?>
                                            <span class="activity-action <?php echo $actionClass; ?>"><?php echo $actionText; ?></span>
                                            user <?php echo htmlspecialchars($activity['target_name'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; color: var(--gray-500);">
                                    <i class="fas fa-info-circle"></i> No recent activity found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- User Statistics Section -->
                <div class="side-panel-section">
                    <h3 class="side-panel-title">
                        <i class="fas fa-chart-pie"></i> User Statistics
                    </h3>
                    <div class="side-panel-content">
                        <div class="chart-container">
                            <canvas id="userRoleChart"></canvas>
                        </div>
                        
                        <div class="chart-container" style="margin-top: 24px;">
                            <canvas id="userStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-user-plus"></i> Add New User
                </h2>
                <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_name" class="form-label required">Full Name</label>
                            <input type="text" id="add_name" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="add_email" class="form-label required">Email Address</label>
                            <input type="email" id="add_email" name="email" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_role" class="form-label required">Role</label>
                            <select id="add_role" name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="Admin">Administrator</option>
                                <option value="Contracts">Contracts</option>
                                <option value="Manager">Manager</option>
                                <option value="User">Standard User</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_empid" class="form-label">Employee ID</label>
                            <input type="text" id="add_empid" name="empid" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_department" class="form-label">Department</label>
                            <input type="text" id="add_department" name="department" class="form-input" list="departments">
                            <datalist id="departments">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label for="add_position" class="form-label">Position</label>
                            <input type="text" id="add_position" name="position" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_password" class="form-label required">Password</label>
                            <input type="password" id="add_password" name="password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="add_status" class="form-label required">Status</label>
                            <select id="add_status" name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitAddUser()">Add User</button>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-user-edit"></i> Edit User
                </h2>
                <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_name" class="form-label required">Full Name</label>
                            <input type="text" id="edit_name" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email" class="form-label required">Email Address</label>
                            <input type="email" id="edit_email" name="email" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_role" class="form-label required">Role</label>
                            <select id="edit_role" name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="Admin">Administrator</option>
                                <option value="Contracts">Contracts</option>
                                <option value="Manager">Manager</option>
                                <option value="User">Standard User</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_empid" class="form-label">Employee ID</label>
                            <input type="text" id="edit_empid" name="empid" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_department" class="form-label">Department</label>
                            <input type="text" id="edit_department" name="department" class="form-input" list="edit_departments">
                            <datalist id="edit_departments">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label for="edit_position" class="form-label">Position</label>
                            <input type="text" id="edit_position" name="position" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status" class="form-label required">Status</label>
                        <select id="edit_status" name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitEditUser()">Update User</button>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-key"></i> Reset Password
                </h2>
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="resetPasswordForm">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" id="reset_user_id" name="user_id">
                    
                    <p style="margin-bottom: 20px;">You are about to reset the password for <strong id="reset_user_name"></strong>.</p>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label required">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button class="btn btn-warning" onclick="submitResetPassword()">Reset Password</button>
            </div>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-user"></i> User Details
                </h2>
                <button class="modal-close" onclick="closeModal('viewUserModal')">&times;</button>
            </div>
            <div id="user-profile-container">
                <div class="spinner" style="margin: 50px auto; display: block;"></div>
                <p style="text-align: center;">Loading user details...</p>
            </div>
        </div>
    </div>
    
    
    <script>
        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Role distribution chart
            <?php
            $roleChartQuery = "SELECT role, COUNT(*) as count FROM users WHERE status != 'deleted' GROUP BY role";
            $roleChartResult = $conn->query($roleChartQuery);
            $roleLabels = [];
            $roleCounts = [];
            $roleColors = [];
            
            $colorMap = [
                'Admin' => '#fecaca', // Light red
                'Contracts' => '#bfdbfe', // Light blue
                'Manager' => '#bbf7d0', // Light green
                'User' => '#e5e7eb', // Light gray
            ];
            
            while ($roleData = $roleChartResult->fetch_assoc()) {
                $roleLabels[] = $roleData['role'];
                $roleCounts[] = $roleData['count'];
                $roleColors[] = $colorMap[$roleData['role']] ?? '#d1d5db';
            }
            ?>
            
            const roleCtx = document.getElementById('userRoleChart').getContext('2d');
            const roleChart = new Chart(roleCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($roleLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($roleCounts); ?>,
                        backgroundColor: <?php echo json_encode($roleColors); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        title: {
                            display: true,
                            text: 'Users by Role',
                            padding: {
                                bottom: 15
                            }
                        }
                    }
                }
            });
            
            // Status distribution chart
            <?php
            $statusChartQuery = "SELECT status, COUNT(*) as count FROM users WHERE status != 'deleted' GROUP BY status";
            $statusChartResult = $conn->query($statusChartQuery);
            $statusLabels = [];
            $statusCounts = [];
            $statusColors = [];
            
            $statusColorMap = [
                'active' => '#10b981', // Green
                'inactive' => '#f59e0b', // Amber
                'pending' => '#3b82f6', // Blue
            ];
            
            while ($statusData = $statusChartResult->fetch_assoc()) {
                $statusLabels[] = ucfirst($statusData['status']);
                $statusCounts[] = $statusData['count'];
                $statusColors[] = $statusColorMap[$statusData['status']] ?? '#d1d5db';
            }
            ?>
            
            const statusCtx = document.getElementById('userStatusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($statusLabels); ?>,
                    datasets: [{
                        label: 'Users',
                        data: <?php echo json_encode($statusCounts); ?>,
                        backgroundColor: <?php echo json_encode($statusColors); ?>,
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Users by Status',
                            padding: {
                                bottom: 15
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
        
        // Toggle dropdown
        function toggleDropdown(id) {
            document.getElementById(id).classList.toggle('show');
            // Close other dropdowns
            const dropdowns = document.getElementsByClassName('dropdown-content');
            for (let i = 0; i < dropdowns.length; i++) {
                const openDropdown = dropdowns[i];
                if (openDropdown.id !== id && openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
        
        // Close dropdowns when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-btn') && !event.target.closest('.dropdown-content')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        };
        
        // Modal functions
        function openModal(id) {
            // Close any open modals first if opening edit or reset password modals from view modal
            if ((id === 'editUserModal' || id === 'resetPasswordModal') && 
                document.getElementById('viewUserModal').style.display === 'block') {
                document.getElementById('viewUserModal').style.display = 'none';
            }
            
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            
            // If closing edit or reset password modals that were opened from view modal, 
            // reopen the view modal
            if ((id === 'editUserModal' || id === 'resetPasswordModal') && 
                document.getElementById('viewUserModal').getAttribute('data-reopen') === 'true') {
                document.getElementById('viewUserModal').style.display = 'block';
                document.getElementById('viewUserModal').removeAttribute('data-reopen');
            } else {
                document.body.style.overflow = 'auto'; // Re-enable scrolling
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    modals[i].style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
        });
        
        // Toggle all checkboxes
        function toggleAllCheckboxes() {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateSelectedCount();
        }
        
        // Update selected count and show/hide bulk actions bar
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            
            document.getElementById('selected-count').textContent = count;
            
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            if (count > 0) {
                bulkActionsBar.style.display = 'flex';
            } else {
                bulkActionsBar.style.display = 'none';
            }
        }
        
        // Clear selection
        function clearSelection() {
            document.getElementById('select-all').checked = false;
            
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            updateSelectedCount();
        }
        
        // Add user modal
        function openAddUserModal() {
            document.getElementById('addUserForm').reset();
            openModal('addUserModal');
        }
        
        // Submit add user form
        function submitAddUser() {
            const form = document.getElementById('addUserForm');
            
            // Simple validation
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger-color)';
                    valid = false;
                } else {
                    field.style.borderColor = 'var(--gray-300)';
                }
            });
            
            if (!valid) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Check if passwords match for add user
            const password = document.getElementById('add_password').value;
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch('usermanagement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Raw response status:', response.status);
                return response.text(); // Use text() instead of json() first
            })
            .then(text => {
                console.log('Response text:', text);
                
                // Try to parse as JSON only if it looks like JSON
                try {
                    if (text.trim().startsWith('{')) {
                        const data = JSON.parse(text);
                        
                        if (data.status === 'success') {
                            showToast(data.message, 'success');
                            closeModal('addUserModal');
                            
                            // Reload page to show new user
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showToast(data.message || 'Unknown error', 'error');
                        }
                    } else {
                        // Not JSON, probably an error page
                        showToast('Server returned an error. Check console for details.', 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showToast('Failed to process server response', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }
        
        function editUserFromView(id, name, email, role, empId, department, position, status) {
            document.getElementById('viewUserModal').setAttribute('data-reopen', 'true');
            editUser(id, name, email, role, empId, department, position, status);
        }

        function resetPasswordModalFromView(id, name) {
            document.getElementById('viewUserModal').setAttribute('data-reopen', 'true');
            resetPasswordModal(id, name);
        }

        // Edit user modal
        function editUser(id, name, email, role, empId, department, position, status) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_empid').value = empId;
            document.getElementById('edit_department').value = department;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_status').value = status;
            
            openModal('editUserModal');
        }
        
        // Submit edit user form
        function submitEditUser() {
            const form = document.getElementById('editUserForm');
            
            // Simple validation
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger-color)';
                    valid = false;
                } else {
                    field.style.borderColor = 'var(--gray-300)';
                }
            });
            
            if (!valid) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch('usermanagement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('editUserModal');
                    
                    // Check if we should reopen view modal
                    if (document.getElementById('viewUserModal').getAttribute('data-reopen') === 'true') {
                        // Refresh the view modal content
                        const userId = document.getElementById('edit_user_id').value;
                        viewUser(userId);
                    } else {
                        // Reload page to show updated user
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        // Reset password modal
        function resetPasswordModal(id, name) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_user_name').textContent = name;
            document.getElementById('resetPasswordForm').reset();
            
            openModal('resetPasswordModal');
        }
        
        // Submit reset password form
        function submitResetPassword() {
            const form = document.getElementById('resetPasswordForm');
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate passwords
            if (!password) {
                showToast('Please enter a new password', 'error');
                document.getElementById('new_password').style.borderColor = 'var(--danger-color)';
                return;
            } else {
                document.getElementById('new_password').style.borderColor = 'var(--gray-300)';
            }
            
            if (password !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                document.getElementById('confirm_password').style.borderColor = 'var(--danger-color)';
                return;
            } else {
                document.getElementById('confirm_password').style.borderColor = 'var(--gray-300)';
            }
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch('usermanagement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('resetPasswordModal');
                    
                    // Check if we should reopen view modal
                    if (document.getElementById('viewUserModal').getAttribute('data-reopen') === 'true') {
                        // No need to refresh the view modal content for password reset
                        document.getElementById('viewUserModal').style.display = 'block';
                        document.getElementById('viewUserModal').removeAttribute('data-reopen');
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        // Delete user confirmation
        function deleteUserConfirm(id, name) {
            Swal.fire({
                title: 'Delete User?',
                html: `Are you sure you want to delete <strong>${name}</strong>?<br>This action can be reversed by an administrator.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--danger-color)',
                cancelButtonColor: 'var(--gray-500)',
                confirmButtonText: 'Yes, delete user',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteUser(id);
                }
            });
        }
        
        // Delete user
        function deleteUser(id) {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', id);
            
            fetch('usermanagement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        // View user profile
        function viewUser(id) {
            openModal('viewUserModal');
            
            const container = document.getElementById('user-profile-container');
            container.innerHTML = `
                <div style="display: flex; justify-content: center; align-items: center; padding: 40px;">
                    <div class="spinner" style="margin-right: 16px;"></div>
                    <p>Loading user details...</p>
                </div>
            `;
            
            // Fetch user details
            fetch(`fetch_user.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load user details');
                    }
                    return response.text();
                })
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: var(--danger-color);">
                            <i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
                            <h3>Error Loading User</h3>
                            <p>${error.message}</p>
                        </div>
                    `;
                });
        }
        
        // Bulk actions
        function bulkAction(action) {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            
            if (checkboxes.length === 0) {
                showToast('Please select at least one user', 'warning');
                return;
            }
            
            // Get selected user IDs
            const userIds = Array.from(checkboxes).map(checkbox => checkbox.getAttribute('data-id'));
            
            // Confirmation based on action
            let title, text, confirmButtonText, icon;
            
            switch (action) {
                case 'activate':
                    title = 'Activate Users?';
                    text = `Are you sure you want to activate ${checkboxes.length} selected user(s)?`;
                    confirmButtonText = 'Yes, activate users';
                    icon = 'question';
                    break;
                case 'deactivate':
                    title = 'Deactivate Users?';
                    text = `Are you sure you want to deactivate ${checkboxes.length} selected user(s)?`;
                    confirmButtonText = 'Yes, deactivate users';
                    icon = 'warning';
                    break;
                case 'delete':
                    title = 'Delete Users?';
                    text = `Are you sure you want to delete ${checkboxes.length} selected user(s)?`;
                    confirmButtonText = 'Yes, delete users';
                    icon = 'warning';
                    break;
                default:
                    return;
            }
            
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: action === 'delete' ? 'var(--danger-color)' : 'var(--primary-color)',
                cancelButtonColor: 'var(--gray-500)',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeBulkAction(action, userIds);
                }
            });
        }
        
        // Execute bulk action
        function executeBulkAction(action, userIds) {
            const formData = new FormData();
            formData.append('action', 'bulk_action');
            formData.append('bulk_action', action);
            formData.append('user_ids', JSON.stringify(userIds));
            
            fetch('usermanagement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        // Reset filters
        document.getElementById('reset-filters').addEventListener('click', function() {
            // Clear all input fields
            document.getElementById('search').value = '';
            document.getElementById('role').value = '';
            document.getElementById('status').value = 'all_active';
            document.getElementById('department').value = '';
            
            // Navigate to the base URL without parameters
            window.location.href = 'usermanagement.php';
        });
        
        // Toast notifications
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon;
            let title;
            
            switch (type) {
                case 'success':
                    icon = 'check-circle';
                    title = 'Success';
                    break;
                case 'error':
                    icon = 'times-circle';
                    title = 'Error';
                    break;
                case 'warning':
                    icon = 'exclamation-triangle';
                    title = 'Warning';
                    break;
                default:
                    icon = 'info-circle';
                    title = 'Information';
            }
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="dismissToast(this.parentElement)">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                dismissToast(toast);
            }, 5000);
        }
        
        function dismissToast(toast) {
            if (toast.parentElement) {
                toast.classList.add('removing');
                
                toast.addEventListener('animationend', () => {
                    if (toast.parentElement) {
                        toast.parentElement.removeChild(toast);
                    }
                });
            }
        }
    </script>

    <script>
        // Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (window.innerWidth <= 768) {
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
    
    if (window.innerWidth > 768) {
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
    
    if (window.innerWidth <= 768 && 
        sidebar.classList.contains('open') && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>