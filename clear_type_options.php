<?php
require 'db.php';

// Clear all options for 'type' category
try {
    // First, find the type category ID
    $categoryQuery = "SELECT id FROM dropdown_categories WHERE category_key = 'type'";
    $result = $conn->query($categoryQuery);
    
    if ($result && $result->num_rows > 0) {
        $categoryRow = $result->fetch_assoc();
        $categoryId = $categoryRow['id'];
        
        // Delete all options for this category
        $deleteQuery = "DELETE FROM dropdown_options WHERE category_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $categoryId);
        
        if ($stmt->execute()) {
            echo "SUCCESS: All 'type' dropdown options have been cleared.\n";
            echo "Affected rows: " . $stmt->affected_rows . "\n";
        } else {
            echo "ERROR: Failed to clear options - " . $stmt->error . "\n";
        }
        
        $stmt->close();
    } else {
        echo "INFO: No 'type' category found in database.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$conn->close();
?>
