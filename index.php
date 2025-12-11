<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Home</title>

<link rel="stylesheet" href="css/styles.css">

<style>
body {
    font-family: "Inter", Arial, sans-serif;
    background: #0d0d0d;
    color: #f5f5f5;
    margin: 0;
    display: flex;
    flex-direction: column;
}
header {
    background: #111;
    border-bottom: 2px solid #ffcc00;
    padding: 16px 26px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
header .logo {
    font-size: 26px;
    font-weight: 800;
    color: #ffcc00;
    text-decoration: none;
}
nav a {
    color: white;
    margin-left: 16px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 600;
}
nav a:hover {
    color: #ffcc00;
}
main {
    flex: 1;
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 20px;
    text-align: center;
}
#greeting {
    font-size: 42px;
    font-weight: 700;
    color: #ffcc00;
    margin: 40px 0 32px 0;
}
.button-group {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;
    justify-content: center;
}
.game-btn {
    background: #ffcc00;
    color: #111;
    padding: 16px 28px;
    font-size: 20px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    box-shadow: 0 6px 14px rgba(255, 204, 0, 0.25);
    transition: 0.25s ease;
}
.game-btn:hover {
    background: #e6b800;
    transform: translateY(-3px);
}
footer {
    padding: 16px;
    text-align: center;
    color: #777;
    font-size: 14px;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
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

        try {
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

            if (data.Flag === "daily_already_played") {
                window.location.href = "game.php?mode=daily_summary";
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
        } catch (err) {
            console.error(err);
            alert("Network error starting game.");
        }
    }

    document.getElementById("startDailyBtn").addEventListener("click", () => {
        startGame("start_daily_game");
    });

    document.getElementById("startRandomBtn").addEventListener("click", () => {
        startGame("start_random_game");
    });
});
</script>

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

</body>
</html>
