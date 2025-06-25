<?php
session_start();
require 'db.php';

if (!isset($_SESSION['email'])) {
    echo "Not authenticated";
    exit();
}

// Get the record ID from the request
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recordId <= 0) {
    echo "Invalid record ID";
    exit();
}

// Get user info for permission checks
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT id, role, userwithempid FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$userRole = $userData['role'];
$userWithEmpId = $userData['userwithempid'];

// Check if the user is an admin
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');

// Fetch the record details
$sql = "SELECT * FROM paperworkdetails WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Record not found";
    exit();
}

$row = $result->fetch_assoc();

// Check if PLC code exists in the paperworkdetails table
$plcCode = $row['plc_code'] ?? '';
$plcUpdatedAt = $row['plc_updated_at'] ?? '';
$plcUpdatedBy = $row['plc_updated_by'] ?? '';

// Generate a unique namespace for this instance to avoid conflicts
$uniqueId = 'tab_' . $recordId . '_' . time();
?>

<div id="card-details-<?php echo $recordId; ?>" class="card-details-container">
    <!-- Simple tab buttons with direct inline handlers -->
    <div class="tabs">
        <button type="button" id="tab-basic-<?php echo $recordId; ?>" class="tab active" 
                onclick="window.cardTabFunctions.switchTab(<?php echo $recordId; ?>, 'basic')">
            Basic Info
        </button>
        <button type="button" id="tab-employment-<?php echo $recordId; ?>" class="tab" 
                onclick="window.cardTabFunctions.switchTab(<?php echo $recordId; ?>, 'employment')">
            Employment
        </button>
        <button type="button" id="tab-plc-<?php echo $recordId; ?>" class="tab" 
                onclick="window.cardTabFunctions.switchTab(<?php echo $recordId; ?>, 'plc')">
            PLC Code
        </button>
    </div>

    <!-- Tab contents with clear IDs -->
    <div id="content-basic-<?php echo $recordId; ?>" class="tab-content active">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Mobile Number</span>
                <span class="info-value"><?php echo htmlspecialchars($row['cmobilenumber'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Home Address</span>
                <span class="info-value"><?php echo htmlspecialchars($row['chomeaddress'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Work Authorization</span>
                <span class="info-value"><?php echo htmlspecialchars($row['cwork_authorization_status'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Certifications</span>
                <span class="info-value"><?php echo htmlspecialchars($row['ccertifications'] ?? 'None'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Overall Experience</span>
                <span class="info-value"><?php echo htmlspecialchars($row['coverall_experience'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Recent Job Title</span>
                <span class="info-value"><?php echo htmlspecialchars($row['crecent_job_title'] ?? 'Not specified'); ?></span>
            </div>
        </div>
    </div>

    <div id="content-employment-<?php echo $recordId; ?>" class="tab-content">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Job Title</span>
                <span class="info-value"><?php echo htmlspecialchars($row['job_title'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Client</span>
                <span class="info-value"><?php echo htmlspecialchars($row['client'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Client Manager</span>
                <span class="info-value"><?php echo htmlspecialchars($row['client_manager'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Start Date</span>
                <span class="info-value"><?php echo htmlspecialchars($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">End Date</span>
                <span class="info-value"><?php echo htmlspecialchars($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Location</span>
                <span class="info-value"><?php echo htmlspecialchars($row['project_location'] ?? 'Not specified'); ?></span>
            </div>
        </div>
    </div>

    <div id="content-plc-<?php echo $recordId; ?>" class="tab-content">
        <div class="plc-section">
            <?php if (!empty($plcCode)): ?>
            <div class="plc-current">
                <div style="font-weight: 500; margin-bottom: 8px;">Current PLC Code:</div>
                <div class="plc-code"><?php echo htmlspecialchars($plcCode); ?></div>
                <?php if (!empty($plcUpdatedAt) && !empty($plcUpdatedBy)): ?>
                <div class="plc-meta">
                    Last updated: <?php echo date('M d, Y H:i', strtotime($plcUpdatedAt)); ?> by <?php echo htmlspecialchars($plcUpdatedBy); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="color: var(--gray-600); font-style: italic; margin-bottom: 16px;">No PLC code has been assigned yet.</div>
            <?php endif; ?>
            
            <?php if($isAdmin): ?>
            <form class="plc-form" id="plc-form-<?php echo htmlspecialchars($recordId); ?>">
                <input type="hidden" name="paperwork_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                <input type="text" name="plc_code" class="plc-input" placeholder="Enter PLC code" value="<?php echo htmlspecialchars($plcCode); ?>">
                <button type="submit" class="btn btn-primary save-plc"><i class="fas fa-save"></i> Save</button>
            </form>
            <div class="save-status" style="margin-top: 8px; display: none;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="record-meta" style="margin-top: 16px; font-size: 12px; color: var(--gray-500);">
        Submitted by: <?php echo htmlspecialchars($row['submittedby']); ?> on <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
    </div>
</div>

<script>
// Create a global namespace if it doesn't exist
if (!window.cardTabFunctions) {
    window.cardTabFunctions = {};
}

// Add the tab switching function to the global namespace
window.cardTabFunctions.switchTab = function(recordId, tabType) {
    console.log(`Tab switch called for record ${recordId}, tab ${tabType}`);
    
    // Get the container
    const container = document.getElementById('card-details-' + recordId);
    if (!container) {
        console.error('Container not found:', 'card-details-' + recordId);
        return false;
    }
    
    // Find all tabs and contents within this container
    const tabs = container.querySelectorAll('.tab');
    const contents = container.querySelectorAll('.tab-content');
    
    console.log(`Found ${tabs.length} tabs and ${contents.length} content sections`);
    
    // Deactivate all tabs and contents
    tabs.forEach(tab => tab.classList.remove('active'));
    contents.forEach(content => content.classList.remove('active'));
    
    // Activate the clicked tab
    const clickedTab = document.getElementById(`tab-${tabType}-${recordId}`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    } else {
        console.error(`Tab element not found: tab-${tabType}-${recordId}`);
    }
    
    // Activate the corresponding content
    const targetContent = document.getElementById(`content-${tabType}-${recordId}`);
    if (targetContent) {
        targetContent.classList.add('active');
        console.log(`Activated content: content-${tabType}-${recordId}`);
    } else {
        console.error(`Content element not found: content-${tabType}-${recordId}`);
    }
    
    // Prevent default and stop propagation
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    return false;
};

// Handle PLC form submission
(function() {
    const plcForm = document.getElementById('plc-form-<?php echo htmlspecialchars($recordId); ?>');
    if (plcForm) {
        plcForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const saveStatus = this.nextElementSibling;
            
            fetch('save_plc_code.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                saveStatus.textContent = data.message;
                saveStatus.style.display = 'block';
                saveStatus.className = 'save-status ' + (data.success ? 'success' : 'error');
                
                if (data.success) {
                    // Update the displayed PLC code
                    const plcCurrentElement = document.querySelector('#content-plc-<?php echo htmlspecialchars($recordId); ?> .plc-current');
                    if (plcCurrentElement) {
                        plcCurrentElement.querySelector('.plc-code').textContent = formData.get('plc_code');
                    } else {
                        // Create elements if they don't exist
                        const newPlcCurrent = document.createElement('div');
                        newPlcCurrent.className = 'plc-current';
                        newPlcCurrent.innerHTML = `
                            <div style="font-weight: 500; margin-bottom: 8px;">Current PLC Code:</div>
                            <div class="plc-code">${formData.get('plc_code')}</div>
                            <div class="plc-meta">Last updated: ${new Date().toLocaleString()} by <?php echo htmlspecialchars($_SESSION['email']); ?></div>
                        `;
                        
                        // Insert before the form
                        this.parentNode.insertBefore(newPlcCurrent, this);
                    }
                    
                    // Hide status after 3 seconds
                    setTimeout(() => {
                        saveStatus.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                saveStatus.textContent = 'An error occurred. Please try again.';
                saveStatus.style.display = 'block';
                saveStatus.className = 'save-status error';
            });
        });
    }
})();

// Log initialization for debugging
console.log('Card details loaded for record <?php echo $recordId; ?>');
</script>