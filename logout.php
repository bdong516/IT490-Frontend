<?php
session_start();

// Destroy PHP session
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging out...</title>
  <script>
    // Clear localStorage too
    localStorage.removeItem('userEmail');

    // Redirect to home after clearing everything
    window.location.href = 'index.php';
  </script>
</head>
<body>
  <p>Logging out...</p>
</body>
</html>
