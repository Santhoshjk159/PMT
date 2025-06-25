<?php
include 'db.php';
session_start();

// Debug: Show what we received
echo "<h3>Debug Information:</h3>";
echo "POST data received: " . print_r($_POST, true) . "<br>";
echo "Session before: " . print_r($_SESSION, true) . "<br>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: " . htmlspecialchars($password) . "<br>";
    
    // Check database connection
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Query executed. Rows found: " . $result->num_rows . "<br>";
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "User found. Database password: " . htmlspecialchars($row['password']) . "<br>";
        echo "Entered password: " . htmlspecialchars($password) . "<br>";
        echo "Passwords match: " . ($password === $row['password'] ? 'YES' : 'NO') . "<br>";
        
        if ($password === $row['password']) {
            $_SESSION['email'] = $email;
            echo "Session set. Session after: " . print_r($_SESSION, true) . "<br>";
            
            // Try redirect with JavaScript as backup
            echo "<script>
                console.log('Login successful, redirecting...');
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
            
            echo "<p style='color: green;'>Login successful! Redirecting to dashboard in 2 seconds...</p>";
            echo "<p><a href='index.php'>Click here if not redirected automatically</a></p>";
            
            // Also try header redirect
            header("Location: index.php");
            exit();
        } else {
            echo "<p style='color: red;'>Password mismatch!</p>";
        }
    } else {
        echo "<p style='color: red;'>No user found with this email!</p>";
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo "<p style='color: red;'>No POST data received!</p>";
}
?>

<p><a href="paperworklogin.php">Back to Login</a></p>