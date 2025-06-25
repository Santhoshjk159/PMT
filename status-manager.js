/**
 * Status Manager - Handles status dropdown interactions and updates
 * This standalone file manages all status-related functionality
 */

console.log('Status manager loaded - Version 2.0');

// Main event handler for status dropdowns
document.addEventListener('DOMContentLoaded', function() {
    console.log('Setting up status dropdown handlers');
    
    // Use event delegation for all status dropdowns
    document.addEventListener('change', function(event) {
        // Check if the changed element is a status dropdown
        if (event.target.classList.contains('status-dropdown')) {
            console.log('Status dropdown change detected');
            
            const dropdown = event.target;
            const recordId = dropdown.getAttribute('data-id');
            const currentStatus = dropdown.getAttribute('data-current');
            const newStatus = dropdown.value;
            
            console.log(`Status change detected: ${currentStatus} -> ${newStatus} for record ID ${recordId}`);
            
            // Skip if status hasn't changed
            if (currentStatus === newStatus) return;
            
            // Different handling based on status
            if (newStatus === 'client_hold' || newStatus === 'client_dropped') {
                promptForReason(recordId, newStatus, currentStatus, dropdown);
            } else if (newStatus === 'started') {
                promptForStartDate(recordId, newStatus, currentStatus, dropdown);
            } else {
                updateStatus(recordId, newStatus, '', dropdown);
            }
        }
    });

    // Log all status dropdowns on the page for debugging
    const allDropdowns = document.querySelectorAll('.status-dropdown');
    console.log(`Found ${allDropdowns.length} status dropdowns on page.`);
    allDropdowns.forEach((dropdown, index) => {
        console.log(`Dropdown #${index+1}: ID=${dropdown.getAttribute('data-id')}, Current=${dropdown.getAttribute('data-current')}`);
    });
});

// Helper function to prompt for reason when changing to hold/dropped statuses
function promptForReason(recordId, newStatus, currentStatus, dropdown) {
    console.log(`Prompting for reason - Record ID: ${recordId}, New Status: ${newStatus}`);
    
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
            console.log(`Reason provided: "${reason}"`);
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            console.log(`Confirmation received with reason: ${result.value}`);
            updateStatus(recordId, newStatus, result.value, dropdown);
        } else {
            console.log('Reason prompt canceled. Resetting dropdown.');
            // Reset dropdown if canceled
            dropdown.value = currentStatus;
        }
    });
}

// Helper function to prompt for start date when changing to 'started' status
function promptForStartDate(recordId, newStatus, currentStatus, dropdown) {
    console.log(`Prompting for start date - Record ID: ${recordId}`);
    
    Swal.fire({
        title: 'Enter Start Date',
        html: '<input type="date" id="swal-start-date" class="swal2-input" required>',
        showCancelButton: true,
        confirmButtonText: 'Update Status',
        focusConfirm: false,
        preConfirm: () => {
            const startDate = document.getElementById('swal-start-date').value;
            if (!startDate) {
                Swal.showValidationMessage('Please select a date');
                return false;
            }
            console.log(`Start date selected: "${startDate}"`);
            return startDate;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            console.log(`Confirmation received with start date: ${result.value}`);
            updateStatus(recordId, newStatus, result.value, dropdown, true);
        } else {
            console.log('Date prompt canceled. Resetting dropdown.');
            // Reset dropdown if canceled
            dropdown.value = currentStatus;
        }
    });
}

// Function to update status via AJAX
function updateStatus(recordId, status, additionalInfo, dropdown, isDate = false) {
    console.log(`Updating status - Record ID: ${recordId}, Status: ${status}, IsDate: ${isDate}`);
    
    // Show loading state
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait while we update the status.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Prepare request data
    const data = {
        id: recordId,
        status: status
    };
    
    // Add appropriate data based on the type
    if (additionalInfo) {
        if (isDate) {
            data.start_date = additionalInfo;
            console.log(`Using start_date parameter: ${additionalInfo}`);
        } else {
            data.reason = additionalInfo;
            console.log(`Using reason parameter: ${additionalInfo}`);
        }
    }
    
    console.log('Request data:', JSON.stringify(data));
    
    // Send update request
    fetch('paperworkstatus.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log(`Response status: ${response.status}`);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        console.log('Response data:', result);
        
        if (result.status === 'success') {
            // Update dropdown's data-current attribute
            if (dropdown) {
                dropdown.setAttribute('data-current', status);
            }
            
            // Update status text and class in UI
            updateStatusUI(recordId, status);
            
            Swal.fire({
                title: 'Success!',
                text: 'Status updated successfully.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            fetch('log_status_change.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `record_id=${recordId}&old_status=${currentStatus}&new_status=${newStatus}`
            });
            
        } else {
            throw new Error(result.message || 'Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        
        Swal.fire({
            title: 'Error!',
            text: 'Failed to update the status. Please try again.',
            icon: 'error'
        });
        
        // Reset dropdown to previous value if it exists
        if (dropdown) {
            const currentStatus = dropdown.getAttribute('data-current');
            dropdown.value = currentStatus;
        }
    });
}

// Helper function to update status in UI
function updateStatusUI(recordId, status) {
    console.log(`Updating UI for record ${recordId} with status ${status}`);
    
    // Get status text and color
    const statusText = getStatusText(status);
    const statusColor = getStatusColor(status);
    
    try {
        // 1. Update the badge in the main table row
        const expandBtn = document.querySelector(`button.expand-row-btn[data-id="${recordId}"]`);
        if (expandBtn) {
            const tableRow = expandBtn.closest('tr');
            if (tableRow) {
                const statusBadge = tableRow.querySelector('.status-badge');
                if (statusBadge) {
                    console.log('Updating table status badge');
                    statusBadge.className = 'status-badge status-' + status;
                    statusBadge.textContent = statusText;
                }
            }
        }
        
        // 2. Update in card view if present
        const cardCheckbox = document.querySelector(`.record-checkbox[data-id="${recordId}"]`);
        if (cardCheckbox) {
            const card = cardCheckbox.closest('.record-card');
            if (card) {
                const statusBadge = card.querySelector('.record-status');
                if (statusBadge) {
                    console.log('Updating card status badge');
                    statusBadge.className = 'record-status status-' + status;
                    statusBadge.textContent = statusText;
                }
            }
        }
        
        // 3. Update any status dropdowns with this record ID
        const statusDropdowns = document.querySelectorAll(`.status-dropdown[data-id="${recordId}"]`);
        statusDropdowns.forEach(dropdown => {
            console.log('Updating status dropdown value and data attribute');
            dropdown.value = status;
            dropdown.setAttribute('data-current', status);
        });
        
        // 4. Update any status display elements in expanded views
        const expandedRow = document.getElementById(`expanded-${recordId}`);
        if (expandedRow && expandedRow.style.display !== 'none') {
            const statusDisplay = expandedRow.querySelector('div[style*="display: inline-block"]');
            if (statusDisplay) {
                console.log('Updating status display in expanded row');
                statusDisplay.textContent = statusText;
                statusDisplay.style.backgroundColor = statusColor;
            }
        }
        
        console.log('Status UI update complete');
    } catch (error) {
        console.error('Error updating status UI:', error);
    }
}

// Helper function to get human-readable status text
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

// Helper function to get status color
function getStatusColor(status) {
    switch(status) {
        case 'paperwork_created': return '#3b82f6'; // blue
        case 'initiated_agreement_bgv': return '#3b82f6'; // blue
        case 'paperwork_closed': return '#10b981'; // green
        case 'started': return '#10b981'; // green
        case 'client_hold': return '#f59e0b'; // amber
        case 'client_dropped': return '#ef4444'; // red
        case 'backout': return '#6b7280'; // gray
        default: return '#3b82f6'; // default blue
    }
}