<?php
session_start();
require 'db.php';

// Authentication check
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Invalid user ID";
    exit();
}

$userId = (int)$_GET['id'];

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
$userResult = safeExecuteQuery($conn, "SELECT id, role FROM users WHERE email = ?", 's', [$userEmail]);

if ($userResult === false) {
    http_response_code(500);
    echo "Failed to retrieve user information";
    exit();
}

$userData = $userResult['result']->fetch_assoc();
$currentUserRole = $userData['role'];
freeResources($userResult);

// Only admin or the user themselves can view user details
if ($currentUserRole !== 'Admin' && $userData['id'] != $userId) {
    http_response_code(403);
    echo "Access denied";
    exit();
}

// Get user details
$userQuery = "SELECT u.*, 
              IFNULL((SELECT COUNT(*) FROM paperworkdetails WHERE submittedby = u.email), 0) as paperwork_count,
              (SELECT MAX(created_at) FROM activity_log WHERE entity_type = 'user' AND entity_id = u.id) as last_modified,
              (SELECT JSON_OBJECT('action', action, 'user_id', user_id, 'user_name', 
                                 (SELECT name FROM users WHERE id = user_id), 
                                 'created_at', created_at)
               FROM activity_log 
               WHERE entity_type = 'user' AND entity_id = u.id 
               ORDER BY created_at DESC LIMIT 1) as last_action
              FROM users u 
              WHERE u.id = ?";

$userDetailsResult = safeExecuteQuery($conn, $userQuery, 'i', [$userId]);

if ($userDetailsResult === false || $userDetailsResult['result']->num_rows === 0) {
    http_response_code(404);
    echo "User not found";
    exit();
}

$user = $userDetailsResult['result']->fetch_assoc();
freeResources($userDetailsResult);

// Get last 5 activity logs for this user
$activityQuery = "SELECT a.*, u.name as actor_name
                 FROM activity_log a
                 LEFT JOIN users u ON a.user_id = u.id
                 WHERE a.entity_type = 'user' AND a.entity_id = ?
                 ORDER BY a.created_at DESC
                 LIMIT 5";
                 
$activityResult = safeExecuteQuery($conn, $activityQuery, 'i', [$userId]);

// Default profile image if not set
$profileImage = !empty($user['profile_image']) && file_exists($user['profile_image']) 
    ? $user['profile_image'] 
    : 'assets/images/default-profile.png';

// Format the user info for display
$lastAction = json_decode($user['last_action'], true);
?>

<div class="profile-header">
    <div class="profile-avatar">
        <?php if (file_exists($profileImage)): ?>
            <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
        <?php else: ?>
            <?php echo substr(htmlspecialchars($user['name']), 0, 1); ?>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h2 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h2>
        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
        <div class="profile-meta">
            <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                <?php echo htmlspecialchars($user['role']); ?>
            </span>
            <span class="status-badge status-<?php echo $user['status']; ?>">
                <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
            </span>
        </div>
    </div>
</div>

<div class="profile-content">
    <div class="profile-section">
        <h3 class="profile-section-title">User Information</h3>
        <div class="profile-grid">
            <div class="profile-item">
                <div class="profile-label">Employee ID</div>
                <div class="profile-value">
                    <?php echo !empty($user['userwithempid']) ? htmlspecialchars($user['userwithempid']) : '<span style="color: var(--gray-400); font-style: italic;">Not assigned</span>'; ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Department</div>
                <div class="profile-value">
                    <?php echo !empty($user['department']) ? htmlspecialchars($user['department']) : '<span style="color: var(--gray-400); font-style: italic;">Not assigned</span>'; ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Position</div>
                <div class="profile-value">
                    <?php echo !empty($user['position']) ? htmlspecialchars($user['position']) : '<span style="color: var(--gray-400); font-style: italic;">Not assigned</span>'; ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Records Count</div>
                <div class="profile-value">
                    <a href="paperwork.php?submitted_by=<?php echo urlencode($user['email']); ?>" title="View user's records">
                        <?php echo (int)$user['paperwork_count']; ?> records
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="profile-section">
        <h3 class="profile-section-title">Account Details</h3>
        <div class="profile-grid">
            <div class="profile-item">
                <div class="profile-label">Created On</div>
                <div class="profile-value">
                    <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Last Login</div>
                <div class="profile-value">
                    <?php echo !empty($user['last_login']) ? date('F d, Y H:i', strtotime($user['last_login'])) : '<span style="color: var(--gray-400); font-style: italic;">Never</span>'; ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Last Modified</div>
                <div class="profile-value">
                    <?php echo !empty($user['last_modified']) ? date('F d, Y H:i', strtotime($user['last_modified'])) : '<span style="color: var(--gray-400); font-style: italic;">Never</span>'; ?>
                </div>
            </div>
            <div class="profile-item">
                <div class="profile-label">Password Change Required</div>
                <div class="profile-value">
                    <?php echo isset($user['force_password_change']) && $user['force_password_change'] ? 'Yes' : 'No'; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (is_object($activityResult) && $activityResult['result']->num_rows > 0): ?>
    <div class="profile-section">
        <h3 class="profile-section-title">Recent Activity</h3>
        <div class="activity-list">
            <?php while ($activity = $activityResult['result']->fetch_assoc()): ?>
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
                        this user's account
                        
                        <?php if (!empty($activity['details'])): 
                            $details = json_decode($activity['details'], true);
                            if (is_array($details) && !empty($details)):
                                foreach($details as $key => $value):
                                    if ($key != 'name' && $key != 'email' && !empty($value)):
                        ?>
                            <div style="margin-top: 5px; font-size: 12px; color: var(--gray-500);">
                                <?php echo ucfirst($key); ?>: <?php echo htmlspecialchars($value); ?>
                            </div>
                        <?php 
                                    endif;
                                endforeach;
                            endif;
                        endif; 
                        ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($currentUserRole === 'Admin'): ?>
    <div class="profile-section" style="text-align: center; margin-top: 20px;">
        <button class="btn btn-primary" onclick="editUserFromView(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>', '<?php echo addslashes(htmlspecialchars($user['email'])); ?>', '<?php echo addslashes(htmlspecialchars($user['role'])); ?>', '<?php echo addslashes(htmlspecialchars($user['userwithempid'])); ?>', '<?php echo addslashes(htmlspecialchars($user['department'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($user['position'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($user['status'])); ?>')">
            <i class="fas fa-user-edit"></i> Edit User
        </button>
        <button class="btn btn-warning" onclick="resetPasswordModalFromView(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>')">
            <i class="fas fa-key"></i> Reset Password
        </button>
        <?php if ($user['id'] != $userData['id']): // Prevent self-deletion ?>
        <button class="btn btn-danger" onclick="deleteUserConfirm(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>')">
            <i class="fas fa-trash"></i> Delete User
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Free resources
if (isset($activityResult) && is_array($activityResult)) {
    freeResources($activityResult);
}
// Close database connection if needed
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>