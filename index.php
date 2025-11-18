<?php
session_start();

// If this file exists, frontend1 should be passive, redirect to frontend2
if (file_exists(__DIR__ . '/.passive')) {
    header("Location: http://34.31.203.117" . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cinemadle | Home</title>
  <link rel="stylesheet" href="css/styles.css">

  <script>
  document.addEventListener("DOMContentLoaded", () => {

    //
    // ----- FIXED: Secure UUID generator that works on HTTP -----
    //
    function generateUUID() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
      });
    }


    //
    // ----- GREETING LOGIC -----
    //
    const greeting = document.getElementById("greeting");
    const userEmail = localStorage.getItem("userEmail");

    if (userEmail) {
      greeting.textContent = `Hello, ${userEmail}!`;
    } else {
      greeting.textContent = "Hello, Guest!";
    }


    //
    // ----- SESSION ID HANDLING -----
    //
    let sessionID = localStorage.getItem("cinemadleSessionID");
    if (!sessionID) {
      sessionID = generateUUID();
      localStorage.setItem("cinemadleSessionID", sessionID);
    }


    //
    // ----- START GAME BUTTON CLICK HANDLER -----
    //
    document.getElementById("startGameBtn").addEventListener("click", async (e) => {
      e.preventDefault();

      const payload = {
        Flag: "start_game",
        Payload: {
          SessionID: sessionID,
          Username: "<?php echo $_SESSION['username'] ?? 'guest'; ?>"
        }
      };

      console.log("Starting game with payload:", payload);

      try {
        const res = await fetch("start_game.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        console.log("Start game response:", data);

      } catch (err) {
        console.error("Error starting game:", err);
      }

      // Redirect after sending the start flag
      window.location.href = "game.php";
    });

  });
  </script>
</head>

<body>
  <header>
    <a href="index.php" class="logo">Cinemadle</a>
    <nav>
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <h1 id="greeting">Hello, Guest!</h1>

    <!-- The Start Game button -->
    <a href="#" id="startGameBtn" style="font-size:20px; text-decoration:none;">
      🎬 Start Cinemadle — Guess the Movie!
    </a>
  </main>

</body>
</html>
