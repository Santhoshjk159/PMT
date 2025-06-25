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
$uniqueId = 'table_' . $recordId . '_' . time();
?>

<div id="table-details-<?php echo $recordId; ?>" class="expanded-details-container">
    <!-- Tab buttons with proper IDs -->
    <div class="tabs">
        <button type="button" id="table-tab-basic-<?php echo $recordId; ?>" class="tab active" 
                onclick="window.tableTabFunctions.switchTab(<?php echo $recordId; ?>, 'basic')">
            Basic Info
        </button>
        <button type="button" id="table-tab-employment-<?php echo $recordId; ?>" class="tab" 
                onclick="window.tableTabFunctions.switchTab(<?php echo $recordId; ?>, 'employment')">
            Employment Details
        </button>
        <button type="button" id="table-tab-plc-<?php echo $recordId; ?>" class="tab" 
                onclick="window.tableTabFunctions.switchTab(<?php echo $recordId; ?>, 'plc')">
            PLC Code
        </button>
    </div>

    <!-- Tab contents with matching IDs -->
    <div id="table-content-basic-<?php echo $recordId; ?>" class="tab-content active">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($row['cfirstname'] . ' ' . $row['clastname']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($row['cemail']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Phone Number</span>
                <span class="info-value"><?php echo htmlspecialchars($row['cmobilenumber'] ?? 'Not available'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Address</span>
                <span class="info-value"><?php echo htmlspecialchars($row['chomeaddress'] ?? 'Not available'); ?></span>
            </div>
        </div>
    </div>

    <div id="table-content-employment-<?php echo $recordId; ?>" class="tab-content">
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
                <span class="info-label">Start Date</span>
                <span class="info-value"><?php echo htmlspecialchars($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pay Rate</span>
                <span class="info-value"><?php echo !empty($row['pay_rate']) ? '$' . htmlspecialchars($row['pay_rate']) : 'Not specified'; ?></span>
            </div>
        </div>
    </div>

    <div id="table-content-plc-<?php echo $recordId; ?>" class="tab-content">
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
            <form class="plc-form" id="table-plc-form-<?php echo htmlspecialchars($recordId); ?>">
                <input type="hidden" name="paperwork_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                <input type="text" name="plc_code" class="plc-input" placeholder="Enter PLC code" value="<?php echo htmlspecialchars($plcCode); ?>">
                <button type="submit" class="btn btn-primary save-plc"><i class="fas fa-save"></i> Save</button>
            </form>
            <div class="save-status" style="margin-top: 8px; display: none;"></div>
            <?php else: ?>
            <p>PLC code information is only available to administrators.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="record-meta" style="margin-top: 16px; font-size: 12px; color: var(--gray-500);">
        Submitted by: <?php echo htmlspecialchars($row['submittedby']); ?> on <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
    </div>
</div>

<script>
// Create a global namespace if it doesn't exist
if (!window.tableTabFunctions) {
    window.tableTabFunctions = {};
}

// Add the tab switching function to the global namespace
window.tableTabFunctions.switchTab = function(recordId, tabType) {
    console.log(`Table tab switch called for record ${recordId}, tab ${tabType}`);
    
    // Get the container
    const expandedRow = document.getElementById(`expanded-${recordId}`);
    if (!expandedRow) {
        console.error('Expanded row not found:', 'expanded-' + recordId);
        return false;
    }
    
    const container = expandedRow.querySelector('.expanded-details-container');
    if (!container) {
        console.error('Container not found in expanded row');
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
    const clickedTab = document.getElementById(`table-tab-${tabType}-${recordId}`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    } else {
        console.error(`Tab element not found: table-tab-${tabType}-${recordId}`);
    }
    
    // Activate the corresponding content
    const targetContent = document.getElementById(`table-content-${tabType}-${recordId}`);
    if (targetContent) {
        targetContent.classList.add('active');
        console.log(`Activated content: table-content-${tabType}-${recordId}`);
    } else {
        console.error(`Content element not found: table-content-${tabType}-${recordId}`);
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
    const plcForm = document.getElementById('table-plc-form-<?php echo htmlspecialchars($recordId); ?>');
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
                    const plcCurrentElement = document.querySelector('#table-content-plc-<?php echo htmlspecialchars($recordId); ?> .plc-current');
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
console.log('Table details loaded for record <?php echo $recordId; ?>');
</script>