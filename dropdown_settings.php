<?php
require 'db.php'; // Include your database connection
session_start(); // Ensure the session is started

// Verify user is logged in and has admin privileges
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php"); // Redirect if not logged in
    exit();
}


// Check user role
$userEmail = $_SESSION['email'] ?? '';
$roleQuery = "SELECT role, name FROM users WHERE email = ?";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$userData = [];
if ($result && $result->num_rows > 0) {
    $userData = $result->fetch_assoc();
}

$displayName = !empty($userData['name']) ? $userData['name'] : $userEmail;

// Get initials for avatar
$initials = '';
if (!empty($userData['name'])) {
    $nameParts = explode(' ', trim($userData['name']));
    if (count($nameParts) >= 2) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
    } else {
        $initials = strtoupper(substr($userData['name'], 0, 2));
    }
} else {
    $initials = strtoupper(substr($userEmail, 0, 1));
}

$isAdmin = ($userData['role'] === 'Admin');
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
            background: linear-gradient(135deg, white 0%, var(--grey-50) 100%);
            padding: 3rem;
            border-radius: 0;
            box-shadow: var(--shadow-lg);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--blue-500);
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
            background: linear-gradient(90deg, var(--blue-600), var(--grey-600));
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
            color: var(--blue-600);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .access-denied-message {
            font-size: 1.1rem;
            color: var(--grey-600);
            margin-bottom: 2rem;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .access-denied-btn {
            background: linear-gradient(135deg, var(--blue-600), var(--grey-600));
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
            background: linear-gradient(135deg, var(--blue-700), var(--grey-700));
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
                        You do not have permission to access system settings. 
                        Only administrators can manage dropdown configurations and custom field settings.
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

// Process AJAX requests for dropdown management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_categories':
            // Fetch all dropdown categories
            $query = "SELECT id, category_key, name, description FROM dropdown_categories ORDER BY name";
            $result = $conn->query($query);
            
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            exit;
            
        case 'get_options':
            // Validate input
            if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                exit;
            }
            
            $categoryId = (int)$_POST['category_id'];
            
            // Fetch options for the specified category
            $query = "SELECT id, value, label, display_order, is_active FROM dropdown_options 
                      WHERE category_id = ? ORDER BY display_order";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $options = [];
            while ($row = $result->fetch_assoc()) {
                $options[] = $row;
            }
            
            echo json_encode(['success' => true, 'options' => $options]);
            exit;
            
        case 'add_option':
            // Validate input
            if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id']) || 
                !isset($_POST['value']) || trim($_POST['value']) === '') {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $categoryId = (int)$_POST['category_id'];
            $value = trim($_POST['value']);
            $label = isset($_POST['label']) && trim($_POST['label']) !== '' ? trim($_POST['label']) : $value;
            
            // Get the next display order
            $orderQuery = "SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM dropdown_options WHERE category_id = ?";
            $orderStmt = $conn->prepare($orderQuery);
            $orderStmt->bind_param("i", $categoryId);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();
            $orderRow = $orderResult->fetch_assoc();
            $displayOrder = $orderRow['next_order'];
            
            // Insert new option
            $query = "INSERT INTO dropdown_options (category_id, value, label, display_order, is_active) 
                      VALUES (?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issi", $categoryId, $value, $label, $displayOrder);
            
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Option added successfully',
                    'option' => [
                        'id' => $newId,
                        'value' => $value,
                        'label' => $label,
                        'display_order' => $displayOrder,
                        'is_active' => 1
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding option: ' . $conn->error]);
            }
            exit;
            
        case 'update_option':
            // Validate input
            if (!isset($_POST['option_id']) || !is_numeric($_POST['option_id']) || 
                !isset($_POST['value']) || trim($_POST['value']) === '') {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $optionId = (int)$_POST['option_id'];
            $value = trim($_POST['value']);
            $label = isset($_POST['label']) && trim($_POST['label']) !== '' ? trim($_POST['label']) : $value;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            
            // Update option
            $query = "UPDATE dropdown_options SET value = ?, label = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $value, $label, $isActive, $optionId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Option updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating option: ' . $conn->error]);
            }
            exit;
            
        case 'delete_option':
            // Validate input
            if (!isset($_POST['option_id']) || !is_numeric($_POST['option_id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid option ID']);
                exit;
            }
            
            $optionId = (int)$_POST['option_id'];
            
            // Delete option
            $query = "DELETE FROM dropdown_options WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $optionId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Option deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting option: ' . $conn->error]);
            }
            exit;
            
        case 'reorder_options':
            // Validate input
            if (!isset($_POST['options']) || !is_array($_POST['options'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid options data']);
                exit;
            }
            
            $options = $_POST['options'];
            $success = true;
            
            // Begin transaction for reordering
            $conn->begin_transaction();
            
            try {
                $query = "UPDATE dropdown_options SET display_order = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                
                foreach ($options as $index => $optionId) {
                    $displayOrder = $index + 1;
                    $stmt->bind_param("ii", $displayOrder, $optionId);
                    $stmt->execute();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Options reordered successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error reordering options: ' . $e->getMessage()]);
            }
            exit;
            
        case 'add_category':
            // Validate input
            if (!isset($_POST['key']) || trim($_POST['key']) === '' || 
                !isset($_POST['name']) || trim($_POST['name']) === '') {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $key = trim($_POST['key']);
            $name = trim($_POST['name']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            // Check if key already exists
            $checkQuery = "SELECT id FROM dropdown_categories WHERE category_key = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $key);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'A category with this key already exists']);
                exit;
            }
            
            // Insert new category
            $query = "INSERT INTO dropdown_categories (category_key, name, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $key, $name, $description);
            
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Category added successfully',
                    'category' => [
                        'id' => $newId,
                        'category_key' => $key,
                        'name' => $name,
                        'description' => $description
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $conn->error]);
            }
            exit;
            
        case 'delete_category':
            // Validate input
            if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                exit;
            }
            
            $categoryId = (int)$_POST['category_id'];
            
            // Check if there are options for this category
            $checkQuery = "SELECT COUNT(*) as option_count FROM dropdown_options WHERE category_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("i", $categoryId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $row = $checkResult->fetch_assoc();
            
            if ($row['option_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete category with existing options. Delete the options first.']);
                exit;
            }
            
            // Delete the category
            $query = "DELETE FROM dropdown_categories WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $categoryId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

// Bulk import functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_submit'])) {
    $categoryId = isset($_POST['import_category_id']) ? (int)$_POST['import_category_id'] : 0;
    
    if ($categoryId <= 0) {
        $importError = "Please select a valid category.";
    } else if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importError = "Error uploading file. Please try again.";
    } else {
        $file = $_FILES['import_file']['tmp_name'];
        $options = [];
        
        // Read CSV file
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Skip header row if checkbox is checked
            if (isset($_POST['has_header']) && $_POST['has_header'] == '1') {
                fgetcsv($handle, 1000, ",");
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Get the next display order
                $orderQuery = "SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM dropdown_options WHERE category_id = ?";
                $orderStmt = $conn->prepare($orderQuery);
                $orderStmt->bind_param("i", $categoryId);
                $orderStmt->execute();
                $orderResult = $orderStmt->get_result();
                $orderRow = $orderResult->fetch_assoc();
                $displayOrder = $orderRow['next_order'];
                
                // Prepare insert statement
                $query = "INSERT INTO dropdown_options (category_id, value, label, display_order, is_active) VALUES (?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($query);
                
                $importCount = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (isset($data[0]) && trim($data[0]) !== '') {
                        $value = trim($data[0]);
                        $label = isset($data[1]) && trim($data[1]) !== '' ? trim($data[1]) : $value;
                        
                        $stmt->bind_param("issi", $categoryId, $value, $label, $displayOrder);
                        $stmt->execute();
                        $displayOrder++;
                        $importCount++;
                    }
                }
                
                $conn->commit();
                $importSuccess = "Successfully imported $importCount options.";
            } catch (Exception $e) {
                $conn->rollback();
                $importError = "Error importing options: " . $e->getMessage();
            }
            
            fclose($handle);
        } else {
            $importError = "Could not open the file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropdown Settings | Paperwork System</title>
    
    <!-- Google Fonts - Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    
    
    <!-- jQuery UI for sortable functionality -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/ui-lightness/jquery-ui.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            margin-left: 0;
            transition: var(--transition-all);
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

        .heading-container h1 {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: var(--space-1);
            letter-spacing: -0.025em;
        }

        .heading-container p {
            color: var(--grey-300);
            font-weight: 500;
            font-size: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
        }

        .btn i {
            margin-right: var(--space-2);
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
            background: linear-gradient(135deg, var(--grey-500), var(--grey-400));
            color: white;
            border-color: var(--grey-500);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--grey-600), var(--grey-500));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--grey-600);
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

        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            border-color: var(--success);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, var(--success-dark), #047857);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--success-dark);
        }

        /* Alerts */
        .alert {
            padding: var(--space-4) var(--space-6);
            margin-bottom: var(--space-6);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border: 2px solid;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Container Layout */
        .container {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: var(--space-8);
            margin-bottom: var(--space-8);
        }

        .categories-container, .options-container {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-xl);
            padding: var(--space-8);
            transition: var(--transition-all);
            position: relative;
        }

        .categories-container:hover, .options-container:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        /* Section Titles */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: var(--space-6);
            color: var(--grey-800);
            position: relative;
            padding-bottom: var(--space-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--blue-500), var(--blue-400));
        }

        .section-title i {
            margin-right: var(--space-3);
            color: var(--blue-500);
        }

        /* Lists */
        .category-list, .option-list {
            max-height: 600px;
            overflow-y: auto;
            margin-bottom: var(--space-6);
            border: 2px solid var(--grey-200);
            background: var(--grey-50);
        }

        .category-item, .option-item {
            padding: var(--space-4) var(--space-5);
            border-bottom: 1px solid var(--grey-200);
            cursor: pointer;
            transition: var(--transition-all);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            margin-bottom: 1px;
        }

        .category-item:last-child, .option-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .category-item:hover, .option-item:hover {
            background: linear-gradient(135deg, var(--blue-50), var(--grey-50));
            transform: translateX(8px);
            box-shadow: var(--shadow-md);
        }

        .category-item.active {
            background: linear-gradient(135deg, var(--grey-800), var(--grey-700));
            color: white;
            transform: translateX(8px);
            box-shadow: var(--shadow-lg);
        }

        .category-content {
            flex-grow: 1;
            cursor: pointer;
        }

        .category-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: var(--space-1);
        }

        .category-key {
            font-size: 0.875rem;
            color: var(--grey-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .category-item.active .category-key {
            color: var(--grey-300);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--space-2);
            color: var(--grey-700);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            width: 100%;
            padding: var(--space-4) var(--space-5);
            border: 2px solid var(--grey-300);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition-all);
            background-color: white;
            color: var(--grey-800);
            border-radius: 0;
            font-family: var(--font-family);
        }

        .form-control:focus {
            border-color: var(--blue-500);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
            background-color: var(--blue-50);
        }

        .form-control:hover {
            border-color: var(--blue-400);
            background-color: var(--grey-50);
        }

        .required::after {
            content: '*';
            color: var(--danger);
            margin-left: var(--space-1);
            font-weight: 700;
        }

        .form-actions {
            display: flex;
            gap: var(--space-3);
            justify-content: flex-end;
            margin-top: var(--space-6);
        }

        /* Option Items */
        .option-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4) var(--space-5);
        }

        .option-text {
            flex-grow: 1;
            margin-left: var(--space-3);
        }

        .option-actions {
            display: flex;
            gap: var(--space-2);
        }

        .option-value {
            font-weight: 600;
            font-size: 1rem;
            color: var(--grey-800);
        }

        .option-label {
            font-size: 0.875rem;
            color: var(--grey-500);
            margin-top: var(--space-1);
            font-weight: 500;
        }

        .inactive .option-value,
        .inactive .option-label {
            text-decoration: line-through;
            opacity: 0.6;
            color: var(--grey-400);
        }

        .handle {
            cursor: move;
            color: var(--grey-400);
            font-size: 1.25rem;
            transition: var(--transition-all);
            padding: var(--space-2);
        }

        .handle:hover {
            color: var(--blue-500);
            transform: scale(1.2);
        }

        .action-btn {
            background: none;
            border: none;
            font-size: 1.125rem;
            cursor: pointer;
            padding: var(--space-2);
            color: var(--grey-500);
            transition: var(--transition-all);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0;
        }

        .action-btn:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .edit-btn:hover {
            color: var(--blue-500);
            background: var(--blue-50);
        }

        .delete-btn:hover {
            color: var(--danger);
            background: #fef2f2;
        }

        .status-btn:hover {
            color: var(--success);
            background: #dcfce7;
        }

        /* Import Container */
        .import-container {
            background: white;
            border: 1px solid var(--grey-200);
            box-shadow: var(--shadow-xl);
            padding: var(--space-8);
            transition: var(--transition-all);
        }

        .import-container:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            margin-top: var(--space-4);
            gap: var(--space-3);
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--blue-500);
            cursor: pointer;
        }

        .checkbox-container label {
            font-weight: 500;
            cursor: pointer;
            margin: 0;
            text-transform: none;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 700px;
            border: 1px solid var(--grey-200);
            animation: modalAppear 0.3s ease;
            border-radius: 0;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--grey-800);
            margin-bottom: var(--space-6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .close {
            color: var(--grey-500);
            float: right;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition-all);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
        }

        .close:hover {
            color: var(--danger);
            transform: scale(1.1) rotate(90deg);
        }

        .category-management-list {
            margin: var(--space-6) 0;
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid var(--grey-200);
            background: var(--grey-50);
        }

        .category-item-manage {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-4) var(--space-5);
            border-bottom: 1px solid var(--grey-200);
            transition: var(--transition-all);
            background: white;
            margin-bottom: 1px;
        }

        .category-item-manage:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .category-item-manage:hover {
            background: linear-gradient(135deg, var(--grey-50), var(--blue-50));
            transform: translateX(4px);
        }

        .delete-category-btn {
            color: var(--grey-400);
            transition: var(--transition-all);
        }

        .delete-category-btn:hover {
            color: var(--danger);
            transform: scale(1.2);
        }

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 120px;
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

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: var(--space-12);
            color: var(--grey-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: var(--space-4);
            opacity: 0.3;
            color: var(--grey-400);
        }

        .empty-state p {
            font-size: 1.125rem;
            font-weight: 500;
        }

        /* Sortable UI */
        .sortable-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ui-sortable-helper {
            background: white !important;
            box-shadow: var(--shadow-xl) !important;
            transform: rotate(2deg) !important;
            border: 2px solid var(--blue-500) !important;
        }

        .ui-sortable-placeholder {
            background: var(--blue-50) !important;
            height: 60px !important;
            border: 2px dashed var(--blue-300) !important;
            margin-bottom: 1px !important;
        }

        /* Custom Scrollbar */
        .category-list::-webkit-scrollbar,
        .option-list::-webkit-scrollbar,
        .category-management-list::-webkit-scrollbar {
            width: 8px;
        }

        .category-list::-webkit-scrollbar-track,
        .option-list::-webkit-scrollbar-track,
        .category-management-list::-webkit-scrollbar-track {
            background: var(--grey-100);
        }

        .category-list::-webkit-scrollbar-thumb,
        .option-list::-webkit-scrollbar-thumb,
        .category-management-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--grey-400), var(--grey-500));
        }

        .category-list::-webkit-scrollbar-thumb:hover,
        .option-list::-webkit-scrollbar-thumb:hover,
        .category-management-list::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--blue-400), var(--blue-500));
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }
            
            .categories-container {
                order: 1;
            }
            
            .options-container {
                order: 2;
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
            
            .heading-container h1 {
                font-size: 1.75rem;
            }
            
            .categories-container, 
            .options-container, 
            .import-container {
                padding: var(--space-6);
            }
            
            .section-title {
                font-size: 1.375rem;
            }
            
            .category-list, 
            .option-list {
                max-height: 400px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: var(--space-6);
            }
            
            .form-actions {
                flex-direction: column;
                gap: var(--space-3);
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .topbar {
                padding: var(--space-4) var(--space-5);
            }
            
            .heading-container h1 {
                font-size: 1.5rem;
            }
            
            .categories-container, 
            .options-container, 
            .import-container {
                padding: var(--space-5);
            }
            
            .category-item, 
            .option-item {
                padding: var(--space-3) var(--space-4);
            }
            
            .option-actions {
                gap: var(--space-1);
            }
            
            .action-btn {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
            
            .modal-content {
                padding: var(--space-5);
            }
            
            .container {
                grid-template-columns: 1fr;
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
        .form-control:focus {
            outline: 2px solid var(--blue-500);
            outline-offset: 2px;
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .uppercase { text-transform: uppercase; }
        .lowercase { text-transform: lowercase; }

        /* Enhanced visual feedback */
        .form-control.valid {
            border-color: var(--success);
            background-color: #f0fdf4;
        }

        .form-control.invalid {
            border-color: var(--danger);
            background-color: #fef2f2;
        }

        /* Progress indicators */
        .progress-bar {
            width: 100%;
            height: 4px;
            background: var(--grey-200);
            overflow: hidden;
            margin-top: var(--space-2);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue-500), var(--blue-400));
            width: 0%;
            transition: width 0.3s ease;
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

/* Main content adjustment */
.main {
    margin-left: 280px;
    transition: var(--transition-all);
}

.main.expanded {
    margin-left: 0;
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
    <?php echo $initials; ?>
</div>
<div class="sidebar-user-info">
    <div class="sidebar-user-name"><?php echo htmlspecialchars($displayName); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($userData['role'] ?? 'User'); ?></div>
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
            <a href="profile.php" class="sidebar-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="dropdown_settings.php" class="sidebar-item active">
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

    <div class="main">
        <div class="topbar">
            <div class="heading-container">
                <h1><i class="fas fa-cogs"></i> Custom Field Settings</h1>
                <p>Manage dropdown options for form fields</p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-secondary" id="categoryManagementBtn">
                    <i class="fas fa-layer-group"></i> Category Management
                </button>
            </div>
        </div>

        <!-- Display import messages -->
        <?php if (isset($importSuccess)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $importSuccess; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($importError)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $importError; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <!-- Categories section -->
            <div class="categories-container">
                <h2 class="section-title">
                    <i class="fas fa-folder-open"></i>
                    Dropdown Categories
                </h2>
                <div class="category-list" id="categoryList">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
            
            <!-- Options section -->
            <div class="options-container">
                <h2 class="section-title">
                    <i class="fas fa-list-ul"></i>
                    Options for <span id="selectedCategoryTitle">Select a category</span>
                </h2>
                
                <div class="option-list" id="optionList">
                    <div class="empty-state">
                        <i class="fas fa-mouse-pointer"></i>
                        <p>Select a category to view and manage its options</p>
                    </div>
                </div>
                
                <div id="optionForm" style="display: none;">
                    <h3 style="margin-top: var(--space-6); margin-bottom: var(--space-5); font-size: 1.25rem; font-weight: 700; color: var(--grey-800); text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fas fa-plus-circle"></i> Add New Option
                    </h3>
                    <div class="form-group">
                        <label class="required">Value</label>
                        <input type="text" class="form-control" id="optionValue" placeholder="Option value (internal)">
                    </div>
                    <div class="form-group">
                        <label>Display Label</label>
                        <input type="text" class="form-control" id="optionLabel" placeholder="Option label (displayed to users)">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" id="addOptionBtn">
                            <i class="fas fa-plus"></i> Add Option
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bulk import section -->
        <div class="import-container">
            <h2 class="section-title">
                <i class="fas fa-file-import"></i>
                Bulk Import Options
            </h2>
            <p style="margin-bottom: var(--space-6); color: var(--grey-600); font-weight: 500;">Import multiple options at once from a CSV file.</p>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="required">Select Category</label>
                    <select class="form-control" name="import_category_id" required>
                        <option value="">Select a category</option>
                        <!-- Will be populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">CSV File</label>
                    <input type="file" class="form-control" name="import_file" accept=".csv" required>
                    <small style="display: block; margin-top: var(--space-2); color: var(--grey-500); font-weight: 500;">
                        <i class="fas fa-info-circle"></i> File should have columns: Value, Label (optional)
                    </small>
                </div>
                
                <div class="checkbox-container">
                    <input type="checkbox" id="hasHeader" name="has_header" value="1">
                    <label for="hasHeader">File has header row (skip first line)</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="import_submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Options
                    </button>
                </div>
            </form>
        </div>

        <!-- Category Management Modal -->
        <div id="categoryManagementModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><i class="fas fa-layer-group"></i> Category Management</h2>
                <div class="category-management-list">
                    <!-- Categories will be listed here -->
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" id="addNewCategoryBtn">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    
    <script>
        // Global variables
        let selectedCategoryId = null;
        let categories = [];

        // Document ready
        $(document).ready(function() {
            // Load categories
            loadCategories();
            
            // Event handlers
            $('#addOptionBtn').click(function() {
                addOption();
            });
            
            $('#categoryManagementBtn').click(function() {
                showCategoryManagementModal();
            });
            
            $('#addNewCategoryBtn').click(function() {
                showAddCategoryDialog();
            });
            
            $('.close').click(function() {
                $('#categoryManagementModal').hide();
            });
            
            $(window).click(function(event) {
                if ($(event.target).is('#categoryManagementModal')) {
                    $('#categoryManagementModal').hide();
                }
            });

            // Enhanced form validation
            $('#optionValue, #optionLabel').on('input', function() {
                validateFormField($(this));
            });
        });

        // Enhanced form field validation
        function validateFormField($field) {
            const value = $field.val().trim();
            const isRequired = $field.attr('required') !== undefined || $field.attr('id') === 'optionValue';
            
            if (isRequired && !value) {
                $field.removeClass('valid').addClass('invalid');
            } else if (value) {
                $field.removeClass('invalid').addClass('valid');
            } else {
                $field.removeClass('valid invalid');
            }
        }

        // Load dropdown categories
        function loadCategories() {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: { action: 'get_categories' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        categories = response.categories;
                        renderCategories();
                        populateCategoryDropdown();
                        
                        // Auto-select first category or work authorization
                        const workAuthCategory = categories.find(cat => 
                            cat.category_key === 'work_authorization' || 
                            cat.name.toLowerCase().includes('work authorization')
                        );
                        
                        if (workAuthCategory) {
                            selectCategory(workAuthCategory.id);
                        } else if (categories.length > 0) {
                            selectCategory(categories[0].id);
                        }
                    } else {
                        showAlert('Error', response.message || 'Failed to load categories', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        function selectCategory(categoryId) {
            const category = categories.find(cat => cat.id == categoryId);
            if (!category) return;
            
            $('.category-item').removeClass('active');
            $(`.category-item[data-id="${categoryId}"]`).addClass('active');
            
            selectedCategoryId = categoryId;
            $('#selectedCategoryTitle').text(category.name);
            loadOptions(categoryId);
            restoreDraft(categoryId);
        }

        // Render categories in the list
        function renderCategories() {
            const categoryList = $('#categoryList');
            categoryList.empty();
            
            if (categories.length === 0) {
                categoryList.html(`
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No categories found</p>
                    </div>
                `);
                return;
            }
            
            $.each(categories, function(index, category) {
                const categoryItem = $(`<div class="category-item" data-id="${category.id}"></div>`);
                categoryItem.html(`
                    <div class="category-content">
                        <div class="category-name">${category.name}</div>
                        <div class="category-key">${category.category_key}</div>
                    </div>
                `);
                
                categoryItem.click(function() {
                    selectCategory(category.id);
                });
                
                categoryList.append(categoryItem);
            });
        }

        // Show category management modal
        function showCategoryManagementModal() {
            const categoryManagementList = $('.category-management-list');
            categoryManagementList.empty();
            
            if (categories.length === 0) {
                categoryManagementList.html(`
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No categories found</p>
                    </div>
                `);
            } else {
                $.each(categories, function(index, category) {
                    const categoryItem = $('<div class="category-item-manage"></div>');
                    categoryItem.html(`
                        <div>
                            <div style="font-weight: 600; font-size: 1rem; margin-bottom: var(--space-1);">${category.name}</div>
                            <div style="font-size: 0.875rem; color: var(--grey-500); font-weight: 500;">${category.category_key}</div>
                        </div>
                        <div class="category-actions">
                            <button type="button" class="action-btn delete-category-btn" title="Delete Category">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `);
                    
                    categoryItem.find('.delete-category-btn').click(function() {
                        showDeleteCategoryConfirmation(category.id, category.name);
                    });
                    
                    categoryManagementList.append(categoryItem);
                });
            }
            
            $('#categoryManagementModal').show();
        }

        // Populate category dropdown for import
        function populateCategoryDropdown() {
            const dropdown = $('select[name="import_category_id"]');
            dropdown.find('option:not(:first)').remove();
            
            $.each(categories, function(index, category) {
                dropdown.append(`<option value="${category.id}">${category.name} (${category.category_key})</option>`);
            });
        }
        
        // Load options for a category
        function loadOptions(categoryId) {
            const optionList = $('#optionList');
            optionList.html('<div class="loading"><div class="spinner"></div></div>');
            $('#optionForm').show();
            
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'get_options',
                    category_id: categoryId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderOptions(response.options);
                    } else {
                        optionList.html(`
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>${response.message || 'Failed to load options'}</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    optionList.html(`
                        <div class="empty-state">
                            <i class="fas fa-wifi"></i>
                            <p>Failed to connect to the server</p>
                        </div>
                    `);
                }
            });
        }
        
        // Render options in the list
        function renderOptions(options) {
            const optionList = $('#optionList');
            optionList.empty();
            
            if (options.length === 0) {
                optionList.html(`
                    <div class="empty-state">
                        <i class="fas fa-list-ul"></i>
                        <p>No options found for this category</p>
                    </div>
                `);
                return;
            }
            
            const ul = $('<ul id="sortableOptions" class="sortable-list"></ul>');
            
            $.each(options, function(index, option) {
                const li = $(`<li class="option-item ${parseInt(option.is_active) === 1 ? '' : 'inactive'}" data-id="${option.id}"></li>`);
                
                const isActive = parseInt(option.is_active) === 1;
                
                li.html(`
                    <div class="handle"><i class="fas fa-grip-vertical"></i></div>
                    <div class="option-text">
                        <div class="option-value">${option.value}</div>
                        ${option.label !== option.value ? `<div class="option-label">${option.label}</div>` : ''}
                    </div>
                    <div class="option-actions">
                        <button type="button" class="action-btn edit-btn" title="Edit Option">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="action-btn status-btn" title="${isActive ? 'Deactivate' : 'Activate'}">
                            <i class="fas ${isActive ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>
                        </button>
                        <button type="button" class="action-btn delete-btn" title="Delete Option">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                `);
                
                // Event handlers
                li.find('.edit-btn').click(function(e) {
                    e.stopPropagation();
                    showEditOptionDialog(option);
                });
                
                li.find('.status-btn').click(function(e) {
                    e.stopPropagation();
                    toggleOptionStatus(option.id, !isActive);
                });
                
                li.find('.delete-btn').click(function(e) {
                    e.stopPropagation();
                    showDeleteOptionConfirmation(option.id);
                });
                
                ul.append(li);
            });
            
            optionList.html(ul);
            
            // Initialize sortable with enhanced UI
            $('#sortableOptions').sortable({
                handle: '.handle',
                helper: 'clone',
                cursor: 'grabbing',
                placeholder: 'ui-sortable-placeholder',
                update: function(event, ui) {
                    const newOrder = $(this).sortable('toArray', { attribute: 'data-id' });
                    updateOptionOrder(newOrder);
                }
            });
        }
        
        // Add a new option
        function addOption() {
            const value = $('#optionValue').val().trim();
            const label = $('#optionLabel').val().trim();
            
            if (!value) {
                showAlert('Error', 'Value is required', 'error');
                $('#optionValue').focus();
                validateFormField($('#optionValue'));
                return;
            }
            
            if (!selectedCategoryId) {
                showAlert('Error', 'Please select a category first', 'error');
                return;
            }
            
            // Show loading state
            const $btn = $('#addOptionBtn');
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Adding...').prop('disabled', true);
            
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'add_option',
                    category_id: selectedCategoryId,
                    value: value,
                    label: label
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#optionValue, #optionLabel').val('').removeClass('valid invalid');
                        loadOptions(selectedCategoryId);
                        showAlert('Success', response.message, 'success');
                        clearDraft();
                    } else {
                        showAlert('Error', response.message || 'Failed to add option', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        }
        
        // Toggle option status
        function toggleOptionStatus(optionId, newStatus) {
            const optionValue = $(`li[data-id="${optionId}"] .option-value`).text().trim();
            const optionLabel = $(`li[data-id="${optionId}"] .option-label`).text().trim() || optionValue;
            
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'update_option',
                    option_id: optionId,
                    is_active: newStatus ? 1 : 0,
                    value: optionValue,
                    label: optionLabel
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const li = $(`li[data-id="${optionId}"]`);
                        if (newStatus) {
                            li.removeClass('inactive');
                            li.find('.status-btn i').removeClass('fa-toggle-off').addClass('fa-toggle-on');
                            li.find('.status-btn').attr('title', 'Deactivate');
                        } else {
                            li.addClass('inactive');
                            li.find('.status-btn i').removeClass('fa-toggle-on').addClass('fa-toggle-off');
                            li.find('.status-btn').attr('title', 'Activate');
                        }
                        showAlert('Success', response.message, 'success');
                    } else {
                        showAlert('Error', response.message || 'Failed to update option status', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        // Update option order
        function updateOptionOrder(newOrder) {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'reorder_options',
                    options: newOrder
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Success', 'Options reordered successfully', 'success');
                    } else {
                        showAlert('Error', response.message || 'Failed to update option order', 'error');
                        loadOptions(selectedCategoryId);
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                    loadOptions(selectedCategoryId);
                }
            });
        }
        
        // Delete an option
        function deleteOption(optionId) {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'delete_option',
                    option_id: optionId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(`li[data-id="${optionId}"]`).slideUp(300, function() {
                            $(this).remove();
                            if ($('#sortableOptions li').length === 0) {
                                $('#optionList').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-list-ul"></i>
                                        <p>No options found for this category</p>
                                    </div>
                                `);
                            }
                        });
                        showAlert('Success', response.message, 'success');
                    } else {
                        showAlert('Error', response.message || 'Failed to delete option', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        // Add new category
        function addCategory(key, name, description) {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'add_category',
                    key: key,
                    name: name,
                    description: description
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        categories.push(response.category);
                        renderCategories();
                        populateCategoryDropdown();
                        showAlert('Success', response.message, 'success');
                        $('#categoryManagementModal').hide();
                    } else {
                        showAlert('Error', response.message || 'Failed to add category', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        // Delete category
        function deleteCategory(categoryId) {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'delete_category',
                    category_id: categoryId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        categories = categories.filter(cat => cat.id != categoryId);
                        renderCategories();
                        populateCategoryDropdown();
                        showAlert('Success', response.message, 'success');
                        
                        // If deleted category was selected, clear selection
                        if (selectedCategoryId == categoryId) {
                            selectedCategoryId = null;
                            $('#selectedCategoryTitle').text('Select a category');
                            $('#optionList').html(`
                                <div class="empty-state">
                                    <i class="fas fa-mouse-pointer"></i>
                                    <p>Select a category to view and manage its options</p>
                                </div>
                            `);
                            $('#optionForm').hide();
                        }
                        
                        // Refresh category management modal
                        showCategoryManagementModal();
                    } else {
                        showAlert('Error', response.message || 'Failed to delete category', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        // Show edit option dialog
        function showEditOptionDialog(option) {
            Swal.fire({
                title: 'Edit Option',
                html: `
                    <div style="text-align: left;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--grey-700); text-transform: uppercase; letter-spacing: 0.05em;">Value <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="editOptionValue" class="swal2-input" value="${option.value}" style="margin: 0; width: 100%; border: 2px solid var(--grey-300); border-radius: 0; font-family: var(--font-family);">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--grey-700); text-transform: uppercase; letter-spacing: 0.05em;">Display Label</label>
                            <input type="text" id="editOptionLabel" class="swal2-input" value="${option.label}" style="margin: 0; width: 100%; border: 2px solid var(--grey-300); border-radius: 0; font-family: var(--font-family);">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Update Option',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                customClass: {
                    popup: 'custom-swal-popup',
                    confirmButton: 'custom-swal-button',
                    cancelButton: 'custom-swal-button'
                },
                didOpen: () => {
                    document.getElementById('editOptionValue').focus();
                },
                preConfirm: () => {
                    const value = document.getElementById('editOptionValue').value.trim();
                    const label = document.getElementById('editOptionLabel').value.trim();
                    
                    if (!value) {
                        Swal.showValidationMessage('Value is required');
                        return false;
                    }
                    
                    return { value, label };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateOption(option.id, result.value.value, result.value.label);
                }
            });
        }
        
        // Update option
        function updateOption(optionId, value, label) {
            $.ajax({
                url: 'dropdown_settings.php',
                type: 'POST',
                data: {
                    action: 'update_option',
                    option_id: optionId,
                    value: value,
                    label: label,
                    is_active: 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadOptions(selectedCategoryId);
                        showAlert('Success', response.message, 'success');
                    } else {
                        showAlert('Error', response.message || 'Failed to update option', 'error');
                    }
                },
                error: function() {
                    showAlert('Error', 'Failed to connect to the server', 'error');
                }
            });
        }
        
        // Show delete option confirmation
        function showDeleteOptionConfirmation(optionId) {
            const optionValue = $(`li[data-id="${optionId}"] .option-value`).text().trim();
            
            Swal.fire({
                title: 'Delete Option',
                text: `Are you sure you want to delete the option "${optionValue}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'custom-swal-popup',
                    confirmButton: 'custom-swal-button',
                    cancelButton: 'custom-swal-button'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteOption(optionId);
                }
            });
        }
        
        // Show delete category confirmation
        function showDeleteCategoryConfirmation(categoryId, categoryName) {
            Swal.fire({
                title: 'Delete Category',
                text: `Are you sure you want to delete the category "${categoryName}"? This will only work if there are no options in this category.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'custom-swal-popup',
                    confirmButton: 'custom-swal-button',
                    cancelButton: 'custom-swal-button'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteCategory(categoryId);
                }
            });
        }
        
        // Show add category dialog
        function showAddCategoryDialog() {
            Swal.fire({
                title: 'Add New Category',
                html: `
                    <div style="text-align: left;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--grey-700); text-transform: uppercase; letter-spacing: 0.05em;">Category Key <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="categoryKey" class="swal2-input" placeholder="e.g., job_status" style="margin: 0; width: 100%; border: 2px solid var(--grey-300); border-radius: 0; font-family: var(--font-family);">
                            <small style="display: block; margin-top: 0.25rem; color: var(--grey-500); font-size: 0.75rem;">Unique identifier (lowercase, underscores only)</small>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--grey-700); text-transform: uppercase; letter-spacing: 0.05em;">Category Name <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="categoryName" class="swal2-input" placeholder="e.g., Job Status" style="margin: 0; width: 100%; border: 2px solid var(--grey-300); border-radius: 0; font-family: var(--font-family);">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--grey-700); text-transform: uppercase; letter-spacing: 0.05em;">Description</label>
                            <textarea id="categoryDescription" class="swal2-textarea" placeholder="Optional description" style="margin: 0; width: 100%; min-height: 80px; border: 2px solid var(--grey-300); border-radius: 0; font-family: var(--font-family);"></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Add Category',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                width: '600px',
                customClass: {
                    popup: 'custom-swal-popup',
                    confirmButton: 'custom-swal-button',
                    cancelButton: 'custom-swal-button'
                },
                didOpen: () => {
                    document.getElementById('categoryKey').focus();
                },
                preConfirm: () => {
                    const key = document.getElementById('categoryKey').value.trim();
                    const name = document.getElementById('categoryName').value.trim();
                    const description = document.getElementById('categoryDescription').value.trim();
                    
                    if (!key) {
                        Swal.showValidationMessage('Category key is required');
                        return false;
                    }
                    
                    if (!name) {
                        Swal.showValidationMessage('Category name is required');
                        return false;
                    }
                    
                    // Validate key format
                    if (!/^[a-z0-9_]+$/.test(key)) {
                        Swal.showValidationMessage('Category key can only contain lowercase letters, numbers, and underscores');
                        return false;
                    }
                    
                    return { key, name, description };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    addCategory(result.value.key, result.value.name, result.value.description);
                }
            });
        }
        
        // Enhanced alert function using SweetAlert2
        function showAlert(title, message, type = 'info') {
            const config = {
                title: title,
                text: message,
                confirmButtonColor: '#2563eb',
                timer: type === 'success' ? 3000 : null,
                timerProgressBar: type === 'success',
                customClass: {
                    popup: 'custom-swal-popup',
                    confirmButton: 'custom-swal-button'
                }
            };
            
            switch (type) {
                case 'success':
                    config.icon = 'success';
                    config.confirmButtonColor = '#10b981';
                    break;
                case 'error':
                    config.icon = 'error';
                    config.confirmButtonColor = '#ef4444';
                    break;
                case 'warning':
                    config.icon = 'warning';
                    config.confirmButtonColor = '#f59e0b';
                    break;
                default:
                    config.icon = 'info';
            }
            
            Swal.fire(config);
        }
        
        // Enhanced keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl/Cmd + S to save current option form
            if ((e.ctrlKey || e.metaKey) && e.which === 83) {
                e.preventDefault();
                if ($('#optionForm').is(':visible') && selectedCategoryId) {
                    addOption();
                }
            }
            
            // Escape to close modals
            if (e.which === 27) {
                $('#categoryManagementModal').hide();
            }
            
            // Ctrl/Cmd + N to add new category
            if ((e.ctrlKey || e.metaKey) && e.which === 78) {
                e.preventDefault();
                showAddCategoryDialog();
            }
        });
        
        // Auto-save draft functionality for option form
        let draftTimer;
        $('#optionValue, #optionLabel').on('input', function() {
            clearTimeout(draftTimer);
            draftTimer = setTimeout(function() {
                const value = $('#optionValue').val().trim();
                const label = $('#optionLabel').val().trim();
                if (value || label) {
                    sessionStorage.setItem('optionDraft', JSON.stringify({
                        value: value,
                        label: label,
                        categoryId: selectedCategoryId
                    }));
                }
            }, 1000);
        });
        
        // Restore draft when category is selected
        function restoreDraft(categoryId) {
            const draft = sessionStorage.getItem('optionDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                if (draftData.categoryId == categoryId) {
                    $('#optionValue').val(draftData.value);
                    $('#optionLabel').val(draftData.label);
                }
            }
        }
        
        // Clear draft after successful submission
        function clearDraft() {
            sessionStorage.removeItem('optionDraft');
        }
    </script>

    <style>
        /* Custom SweetAlert2 styles */
        .custom-swal-popup {
            border-radius: 0 !important;
            font-family: var(--font-family) !important;
        }
        
        .custom-swal-button {
            border-radius: 0 !important;
            font-family: var(--font-family) !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 12px 24px !important;
            border: 2px solid transparent !important;
            transition: var(--transition-all) !important;
        }
        
        .custom-swal-button:hover {
            transform: translateY(-2px) !important;
            box-shadow: var(--shadow-md) !important;
        }
        
        /* Enhanced SweetAlert2 Input Styling */
        .swal2-input, .swal2-textarea {
            border-radius: 0 !important;
            font-family: var(--font-family) !important;
            font-size: 0.875rem !important;
            padding: var(--space-4) var(--space-5) !important;
            border: 2px solid var(--grey-300) !important;
            transition: var(--transition-all) !important;
        }
        
        .swal2-input:focus, .swal2-textarea:focus {
            border-color: var(--blue-500) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
            outline: none !important;
            background-color: var(--blue-50) !important;
        }
        
        .swal2-validation-message {
            background-color: var(--danger) !important;
            color: white !important;
            border-radius: 0 !important;
            font-family: var(--font-family) !important;
            font-weight: 500 !important;
        }
        
        /* Enhanced Modal Animations */
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .swal2-popup {
            animation: modalSlideIn 0.3s ease-out !important;
        }
        
        /* Custom scrollbar for SweetAlert2 */
        .swal2-popup::-webkit-scrollbar {
            width: 6px;
        }
        
        .swal2-popup::-webkit-scrollbar-track {
            background: var(--grey-100);
        }
        
        .swal2-popup::-webkit-scrollbar-thumb {
            background: var(--grey-400);
            border-radius: 0;
        }
        
        .swal2-popup::-webkit-scrollbar-thumb:hover {
            background: var(--blue-500);
        }
    </style>

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