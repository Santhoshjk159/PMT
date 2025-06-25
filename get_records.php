<!-- 1. First, create a new PHP file called get_records.php -->
<?php
// get_records.php
session_start();
require 'db.php';

// Authentication check
if (!isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Get user info
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT id, role, userwithempid FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$userId = $userData['id'];
$userRole = $userData['role'];
$userWithEmpId = $userData['userwithempid'];

// Determine if admin
$isAdmin = ($userRole === 'Admin' || $userRole === 'Contracts');

// Get filters from request
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$submittedBy = $_GET['submitted_by'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Define columns to check for permissions
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

// Build the query
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

// Apply additional filters
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

// Add pagination
$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$recordsPerPage, $offset]);
$bindTypes .= 'ii';

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($bindTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Format the data
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Count total records (for pagination)
$totalCountSql = "SELECT COUNT(*) as total FROM paperworkdetails WHERE 1";
// Repeat the filter conditions...

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'records' => $records, 
    'totalCount' => count($records),
    'page' => $page,
    'totalPages' => ceil(count($records) / $recordsPerPage)
]);
?>

<!-- 2. Now modify your JavaScript to use AJAX for loading data -->
<script>
// Functions to handle view switching and data loading
function switchToTableView() {
    // Get elements
    const tableView = document.querySelector('.table-view');
    const cardView = document.querySelector('.card-view');
    const tableViewBtn = document.getElementById('table-view-btn');
    const cardViewBtn = document.getElementById('card-view-btn');
    
    // Set display properties directly
    tableView.style.display = 'block';
    cardView.style.display = 'none';
    
    // Set active classes
    tableViewBtn.classList.add('active');
    cardViewBtn.classList.remove('active');
    
    // Save preference
    localStorage.setItem('view', 'table');
    
    // Load data for table view if needed
    loadTableViewData();
    
    // Prevent event bubbling
    return false;
}

function switchToCardView() {
    // Get elements
    const tableView = document.querySelector('.table-view');
    const cardView = document.querySelector('.card-view');
    const tableViewBtn = document.getElementById('table-view-btn');
    const cardViewBtn = document.getElementById('card-view-btn');
    
    // Set display properties directly
    tableView.style.display = 'none';
    cardView.style.display = 'grid';
    
    // Set active classes
    tableViewBtn.classList.remove('active');
    cardViewBtn.classList.add('active');
    
    // Save preference
    localStorage.setItem('view', 'card');
    
    // Load data for card view if needed
    loadCardViewData();
    
    // Prevent event bubbling
    return false;
}

// Function to load data for the table view
function loadTableViewData() {
    // Get current filter parameters
    const search = document.getElementById('search').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const submittedBy = document.getElementById('submitted_by').value;
    const status = document.getElementById('status').value;
    
    // Build query string
    const queryParams = new URLSearchParams({
        search: search,
        start_date: startDate,
        end_date: endDate,
        submitted_by: submittedBy,
        status: status,
        view: 'table'
    });
    
    // Show loading indicator
    const tableBody = document.querySelector('.table tbody');
    tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center;"><div class="spinner"></div></td></tr>';
    
    // Fetch data from server
    fetch(`get_records.php?${queryParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            updateTableView(data.records);
        })
        .catch(error => {
            console.error('Error fetching table data:', error);
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: red;">Error loading data</td></tr>';
        });
}

// Function to load data for the card view
function loadCardViewData() {
    // Get current filter parameters
    const search = document.getElementById('search').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const submittedBy = document.getElementById('submitted_by').value;
    const status = document.getElementById('status').value;
    
    // Build query string
    const queryParams = new URLSearchParams({
        search: search,
        start_date: startDate,
        end_date: endDate,
        submitted_by: submittedBy,
        status: status,
        view: 'card'
    });
    
    // Show loading indicator
    const cardContainer = document.querySelector('.card-view');
    cardContainer.innerHTML = '<div style="text-align: center; grid-column: 1/-1;"><div class="spinner"></div></div>';
    
    // Fetch data from server
    fetch(`get_records.php?${queryParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            updateCardView(data.records);
        })
        .catch(error => {
            console.error('Error fetching card data:', error);
            cardContainer.innerHTML = '<div style="text-align: center; grid-column: 1/-1; color: red;">Error loading data</div>';
        });
}

// Update the table view with new data
function updateTableView(records) {
    const tableBody = document.querySelector('.table tbody');
    
    // Clear existing content
    tableBody.innerHTML = '';
    
    if (records.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center;">No records found</td></tr>';
        return;
    }
    
    // Build table rows
    records.forEach(record => {
        // Create table rows with record data
        // (This part will depend on your exact table structure)
        const row = document.createElement('tr');
        
        // Add checkbox column if admin
        const isAdmin = document.querySelector('.table th input[type="checkbox"]') !== null;
        if (isAdmin) {
            row.innerHTML += `
                <td>
                    <input type="checkbox" class="table-checkbox" data-id="${record.id}">
                </td>
            `;
        }
        
        // Add expand button
        row.innerHTML += `
            <td class="expand-cell">
                <button class="expand-row-btn" data-id="${record.id}">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </td>
        `;
        
        // Add other columns
        row.innerHTML += `
            <td>${record.cfirstname} ${record.clastname}</td>
            <td>${record.cemail}</td>
            <td>${record.job_title || 'Not specified'}</td>
            <td>${record.client || 'Not specified'}</td>
            <td>
                <span class="status-badge status-${record.status}">
                    ${getStatusText(record.status)}
                </span>
            </td>
            <td>${formatDate(record.created_at)}</td>
            <td>
                <div class="record-actions">
                    <button class="action-btn action-preview" data-id="${record.id}" title="Preview Details"><i class="fas fa-eye"></i></button>
                    
                    ${record.status === 'paperwork_created' || isAdmin ? 
                    `<a href="paperworkedit.php?id=${record.id}" class="action-btn action-edit" title="Edit Record"><i class="fas fa-edit"></i></a>` : ''}
                    
                    <button class="action-btn action-history" data-id="${record.id}" title="View History"><i class="fas fa-history"></i></button>
                    
                    ${isAdmin ? 
                    `<a href="testexport1.php?id=${record.id}" class="action-btn action-export" title="Export Record"><i class="fas fa-file-export"></i></a>` : ''}
                    
                    ${isAdmin === 'Admin' ? 
                    `<button class="action-btn action-delete" data-id="${record.id}" title="Delete Record"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </td>
        `;
        
        tableBody.appendChild(row);
        
        // Also create the expanded row
        const expandedRow = document.createElement('tr');
        expandedRow.className = 'expanded-content';
        expandedRow.id = `expanded-${record.id}`;
        expandedRow.style.display = 'none';
        
        // The expanded row content would go here
        // This would be quite complex and depend on your exact structure
        
        tableBody.appendChild(expandedRow);
    });
    
    // Reattach event listeners for the new elements
    attachTableEventListeners();
}

// Update the card view with new data
function updateCardView(records) {
    const cardContainer = document.querySelector('.card-view');
    
    // Clear existing content
    cardContainer.innerHTML = '';
    
    if (records.length === 0) {
        cardContainer.innerHTML = `
            <div class="no-records">
                <i class="fas fa-search"></i>
                <h3>No Records Found</h3>
                <p>Try adjusting your search criteria or reset the filters to see more results.</p>
            </div>
        `;
        return;
    }
    
    // Build cards
    records.forEach(record => {
        const card = document.createElement('div');
        card.className = 'record-card';
        
        // Add card content based on your structure
        card.innerHTML = `
            <div class="record-header">
                ${isAdmin ? 
                `<input type="checkbox" class="record-checkbox" data-id="${record.id}">` : ''}
                
                <div class="record-avatar">
                    ${record.cfirstname.charAt(0)}${record.clastname.charAt(0)}
                </div>
                
                <div>
                    <h3 class="record-title">${record.cfirstname} ${record.clastname}</h3>
                    <p class="record-subtitle">${record.cemail}</p>
                </div>
                
                <span class="record-status status-${record.status}">
                    ${getStatusText(record.status)}
                </span>
            </div>
            
            <div class="record-body">
                <div class="record-info-grid">
                    <div class="record-info-item">
                        <span class="record-info-label">Job Title</span>
                        <span class="record-info-value">${record.job_title || 'Not specified'}</span>
                    </div>
                    <div class="record-info-item">
                        <span class="record-info-label">Client</span>
                        <span class="record-info-value">${record.client || 'Not specified'}</span>
                    </div>
                    <div class="record-info-item">
                        <span class="record-info-label">Created</span>
                        <span class="record-info-value">${formatDate(record.created_at)}</span>
                    </div>
                    <div class="record-info-item">
                        <span class="record-info-label">Submitted By</span>
                        <span class="record-info-value">${record.submittedby}</span>
                    </div>
                </div>
                
                <button class="record-expand-btn" data-id="${record.id}">
                    <i class="fas fa-chevron-down"></i> View Details
                </button>
            </div>
            
            <div class="record-footer">
                <div class="record-meta">
                    ID: ${record.id}
                </div>
                <div class="record-actions">
                    <button class="action-btn action-preview" data-id="${record.id}" title="Preview Details"><i class="fas fa-eye"></i></button>
                    
                    ${record.status === 'paperwork_created' || isAdmin ? 
                    `<a href="paperworkedit.php?id=${record.id}" class="action-btn action-edit" title="Edit Record"><i class="fas fa-edit"></i></a>` : ''}
                    
                    <button class="action-btn action-history" data-id="${record.id}" title="View History"><i class="fas fa-history"></i></button>
                    
                    ${isAdmin ? 
                    `<a href="testexport1.php?id=${record.id}" class="action-btn action-export" title="Export Record"><i class="fas fa-file-export"></i></a>` : ''}
                    
                    ${isAdmin === 'Admin' ? 
                    `<button class="action-btn action-delete" data-id="${record.id}" title="Delete Record"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </div>
        `;
        
        cardContainer.appendChild(card);
    });
    
    // Reattach event listeners for the new elements
    attachCardEventListeners();
}

// Helper functions
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

function formatDate(dateString) {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
}

function attachTableEventListeners() {
    // Reattach event listeners for table elements
    // This would include click handlers for expand buttons, checkboxes, etc.
}

function attachCardEventListeners() {
    // Reattach event listeners for card elements
    // This would include click handlers for expand buttons, checkboxes, etc.
}

// Execute on page load to set initial state based on saved preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('view');
    
    // Set initial view based on saved preference
    if (savedView === 'card') {
        switchToCardView();
    } else {
        switchToTableView();
    }
    
    // Add event listeners for filter form submission
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Reload data for the current view
            const currentView = localStorage.getItem('view') || 'table';
            if (currentView === 'table') {
                loadTableViewData();
            } else {
                loadCardViewData();
            }
        });
    }
});
</script>