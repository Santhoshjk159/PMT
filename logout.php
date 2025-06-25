<?php

// Start the session

session_start();



// Destroy all session data

session_destroy();



// Optionally, you can clear the session array to ensure all data is removed

$_SESSION = array();



// Redirect the user to the login page or homepage

header("Location: paperworklogin.php");

exit();

?>

