<?php
// Database table checker for debugging status update issues
require_once 'db.php';

echo "<h2>Database Table Checker</h2>";

// Check if paperworkdetails table exists
$query = "SHOW TABLES LIKE 'paperworkdetails'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ paperworkdetails table exists<br>";
    
    // Check if status column exists
    $query = "SHOW COLUMNS FROM paperworkdetails LIKE 'status'";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        echo "✅ status column exists in paperworkdetails<br>";
    } else {
        echo "❌ status column does NOT exist in paperworkdetails<br>";
    }
    
    // Check if reason column exists
    $query = "SHOW COLUMNS FROM paperworkdetails LIKE 'reason'";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        echo "✅ reason column exists in paperworkdetails<br>";
    } else {
        echo "❌ reason column does NOT exist in paperworkdetails<br>";
    }
    
    // Show some sample data
    $query = "SELECT id, cfirstname, clastname, status, reason FROM paperworkdetails LIMIT 5";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        echo "<h3>Sample Records:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Status</th><th>Reason</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['cfirstname'] . "</td>";
            echo "<td>" . $row['clastname'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['reason'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "❌ paperworkdetails table does NOT exist<br>";
}

// Check if status_change_log table exists
$query = "SHOW TABLES LIKE 'status_change_log'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ status_change_log table exists<br>";
} else {
    echo "❌ status_change_log table does NOT exist<br>";
    echo "<h3>Creating status_change_log table...</h3>";
    
    $createTableQuery = "
    CREATE TABLE `status_change_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `record_id` int(11) NOT NULL,
        `old_status` varchar(100) DEFAULT NULL,
        `new_status` varchar(100) NOT NULL,
        `changed_by` varchar(255) NOT NULL,
        `reason` text DEFAULT NULL,
        `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `record_id` (`record_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($createTableQuery)) {
        echo "✅ status_change_log table created successfully<br>";
    } else {
        echo "❌ Failed to create status_change_log table: " . $conn->error . "<br>";
    }
}

// Check current database connection
echo "<h3>Database Connection Info:</h3>";
echo "Host: " . $conn->host_info . "<br>";
echo "Server Info: " . $conn->server_info . "<br>";
echo "Protocol Version: " . $conn->protocol_version . "<br>";

?>
