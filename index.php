<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Home</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
</head>

<body>

<header>
  <a href="index.php" class="logo">CINEMADLE</a>
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
  <h1 id="greeting">Hello!</h1>

  <div class="button-group">
    <button class="game-btn" id="startDailyBtn">📅 Daily Game</button>
    <button class="game-btn" id="startRandomBtn">🎬 Random Game</button>
  </div>
</main>

<footer>
  © 2025 Cinemadle. All Rights Reserved.
</footer>

<script>
window.onload = () => {
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random()*16|0;
            const v = c==='x' ? r : (r&0x3|0x8);
            return v.toString(16);
        });
    }

    const greeting = document.getElementById("greeting");
    const userEmail = localStorage.getItem("userEmail");

    greeting.textContent = userEmail ? `Hello, ${userEmail}!` : "Hello, Guest!";

    let sessionID = localStorage.getItem("cinemadleSessionID");
    if (!sessionID) {
        sessionID = generateUUID();
        localStorage.setItem("cinemadleSessionID", sessionID);
    }

    async function startGame(flag) {
        const payload = {
            Flag: flag,
            Payload: {
                SessionID: sessionID,
                Username: userEmail || "guest"
            }
        };

        const res = await fetch("start_game.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const out = await res.json();

        if (!out.success) {
            alert("Failed to start game: " + out.message);
            return;
        }

        const data = out.data;

        // Store poster URL for display when game ends
        if (data.Poster) {
            sessionStorage.setItem("gamePosterURL", data.Poster);
        }

        if (data.Flag === "daily_already_played") {
            // Store the message from backend
            sessionStorage.setItem("dailyAlreadyPlayedMessage", data.Message || "You already played the daily for today.");
            sessionStorage.setItem("dailyAlreadyPlayedDate", data.Date || "");
            window.location.href = "daily.php?already=1";
            return;
        }

        if (data.Flag === "daily_game_started") {
            window.location.href = "game.php?mode=daily";
            return;
        }

        if (data.Flag === "random_game_started") {
            window.location.href = "game.php?mode=random";
            return;
        }

        alert("Unexpected backend response");
    }

    const dailyBtn = document.getElementById("startDailyBtn");
    const randomBtn = document.getElementById("startRandomBtn");

    dailyBtn.addEventListener("click", () => {
        startGame("start_daily_game");
    });

    randomBtn.addEventListener("click", () => {
        startGame("start_random_game");
    });
};
</script>

</body>
</html>
