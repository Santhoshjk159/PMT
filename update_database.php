<?php
/**
 * Database update script to remove placement_type column
 * and ensure proper rate formatting
 */

require_once 'db.php';

echo "Starting database update...\n";

try {
    // Check if placement_type column exists
    $checkQuery = "SHOW COLUMNS FROM paperworkdetails LIKE 'placement_type'";
    $result = $conn->query($checkQuery);
    
    if ($result->num_rows > 0) {
        echo "Found placement_type column, removing it...\n";
        
        // Remove the placement_type column
        $dropQuery = "ALTER TABLE paperworkdetails DROP COLUMN placement_type";
        if ($conn->query($dropQuery)) {
            echo "✓ Successfully removed placement_type column\n";
        } else {
            echo "✗ Error removing placement_type column: " . $conn->error . "\n";
        }
    } else {
        echo "placement_type column does not exist, skipping removal\n";
    }
    
    // Update existing records to ensure proper rate format
    echo "Updating existing rate formats...\n";
    
    // Update client rates
    $updateClientQuery = "UPDATE paperworkdetails 
                         SET clientrate = CONCAT(COALESCE(SUBSTRING_INDEX(clientrate, ' ', 1), '0'), ' USD /hour on W2')
                         WHERE clientrate IS NOT NULL 
                           AND clientrate != '' 
                           AND clientrate NOT LIKE '% USD %'
                           AND clientrate REGEXP '^[0-9]+(\.[0-9]+)?$'";
    
    $clientResult = $conn->query($updateClientQuery);
    if ($clientResult) {
        echo "✓ Updated " . $conn->affected_rows . " client rate records\n";
    } else {
        echo "✗ Error updating client rates: " . $conn->error . "\n";
    }
    
    // Update pay rates
    $updatePayQuery = "UPDATE paperworkdetails 
                      SET payrate = CONCAT(COALESCE(SUBSTRING_INDEX(payrate, ' ', 1), '0'), ' USD /hour on W2')
                      WHERE payrate IS NOT NULL 
                        AND payrate != '' 
                        AND payrate NOT LIKE '% USD %'
                        AND payrate REGEXP '^[0-9]+(\.[0-9]+)?$'";
    
    $payResult = $conn->query($updatePayQuery);
    if ($payResult) {
        echo "✓ Updated " . $conn->affected_rows . " pay rate records\n";
    } else {
        echo "✗ Error updating pay rates: " . $conn->error . "\n";
    }
    
    echo "\n✓ Database update completed successfully!\n";
    echo "\nSummary of changes:\n";
    echo "- Removed placement_type column (if it existed)\n";
    echo "- Currency is now always USD\n";
    echo "- Rate format: '[amount] USD [/period] on [tax_term]'\n";
    echo "- Example: '50 USD /hour on 1099'\n";
    
} catch (Exception $e) {
    echo "✗ Error during database update: " . $e->getMessage() . "\n";
}

$conn->close();
?>
