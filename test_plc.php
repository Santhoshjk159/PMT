<?php
// Simple test to check if get_plc_code.php is working
$recordId = 194; // Replace with a valid record ID from your database
$url = "get_plc_code.php?id=" . $recordId;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test PLC Code</title>
</head>
<body>
    <h1>Testing PLC Code Retrieval</h1>
    <p>Testing record ID: <?php echo $recordId; ?></p>
    
    <div id="result">Loading...</div>
    
    <script>
    // Make a fetch request to get_plc_code.php
    fetch('<?php echo $url; ?>')
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            document.getElementById('result').innerHTML = 
                '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = 
                '<p style="color: red;">Error: ' + error.message + '</p>';
        });
    </script>
</body>
</html>