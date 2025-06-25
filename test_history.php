<?php
session_start();
require 'db.php';

echo "<h2>Testing direct record_history insertion</h2>";

// Try both table names to see which one works
$tables = ['record_history', 'recordhistory'];

foreach ($tables as $table) {
    echo "<h3>Testing table: $table</h3>";
    
    $sql = "INSERT INTO $table (record_id, modified_by, modified_date, modification_details, old_value, new_value) 
            VALUES (1, 'test@example.com', NOW(), 'Test Entry', 'Old Value', 'New Value')";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>Successfully inserted into $table</p>";
        $inserted_id = $conn->insert_id;
        echo "<p>Inserted ID: $inserted_id</p>";
        
        // Verify we can retrieve the data
        $verify = $conn->query("SELECT * FROM $table WHERE id = $inserted_id");
        if ($verify && $verify->num_rows > 0) {
            $data = $verify->fetch_assoc();
            echo "<pre>" . print_r($data, true) . "</pre>";
        } else {
            echo "<p style='color:red'>Could not verify insertion (this is unusual)</p>";
        }
    } else {
        echo "<p style='color:red'>Failed to insert into $table: " . $conn->error . "</p>";
    }
}

// Show all tables in the database
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<h3>All tables in database:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_row()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
}