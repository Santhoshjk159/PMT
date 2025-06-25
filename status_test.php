<?php
// Status Update Test Script
require_once 'db.php';
session_start();

// Set a test session email
$_SESSION['email'] = 'test@vdartinc.com';

echo "<h2>Status Update Test</h2>";

// Test 1: Check if paperworkdetails table exists and has data
$query = "SELECT id, cfirstname, clastname, status FROM paperworkdetails LIMIT 3";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>✅ Available Records for Testing:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Current Status</th><th>Test Update</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $testUrl = "test_status_update.php?id=" . $row['id'] . "&new_status=test_status";
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cfirstname'] . " " . $row['clastname'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td><a href='$testUrl' target='_blank'>Test Update</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No records found in paperworkdetails table.";
}

// Test 2: Create a direct test form
echo "<h3>Direct Status Update Test</h3>";
echo "<form id='statusTestForm'>";
echo "Record ID: <input type='number' id='testRecordId' value='1' required><br><br>";
echo "New Status: <input type='text' id='testStatus' value='test_status' required><br><br>";
echo "Reason: <input type='text' id='testReason' value='Testing status update'><br><br>";
echo "<button type='button' onclick='testStatusUpdate()'>Test Status Update</button>";
echo "</form>";

echo "<div id='testResult'></div>";

echo "<script>
function testStatusUpdate() {
    const recordId = document.getElementById('testRecordId').value;
    const status = document.getElementById('testStatus').value;
    const reason = document.getElementById('testReason').value;
    
    const formData = new FormData();
    formData.append('id', recordId);
    formData.append('status', status);
    formData.append('reason', reason);
    
    fetch('paperworkstatus.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        document.getElementById('testResult').innerHTML = '<h4>Response:</h4><pre>' + text + '</pre>';
        try {
            const data = JSON.parse(text);
            if (data.success) {
                document.getElementById('testResult').innerHTML += '<p style=\"color: green;\">✅ Status update successful!</p>';
            } else {
                document.getElementById('testResult').innerHTML += '<p style=\"color: red;\">❌ Status update failed: ' + data.message + '</p>';
            }
        } catch (e) {
            document.getElementById('testResult').innerHTML += '<p style=\"color: orange;\">⚠ Response is not valid JSON</p>';
        }
    })
    .catch(error => {
        document.getElementById('testResult').innerHTML = '<p style=\"color: red;\">❌ Error: ' + error.message + '</p>';
    });
}
</script>";

// Test 3: Check if reason column exists
$query = "SHOW COLUMNS FROM paperworkdetails LIKE 'reason'";
$result = $conn->query($query);
if ($result->num_rows == 0) {
    echo "<h3>⚠ Adding missing 'reason' column to paperworkdetails table...</h3>";
    $alterQuery = "ALTER TABLE paperworkdetails ADD COLUMN reason TEXT DEFAULT NULL";
    if ($conn->query($alterQuery)) {
        echo "✅ Reason column added successfully.";
    } else {
        echo "❌ Failed to add reason column: " . $conn->error;
    }
} else {
    echo "<h3>✅ Reason column exists in paperworkdetails table.</h3>";
}

?>
