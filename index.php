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
      <a href="profile.php">👤 Profile</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>

<div class="carousel-container">
  <div class="film-strip-top"></div>
  <div class="carousel-track carousel-track-left" id="carouselTrack1"></div>
  <div class="carousel-track carousel-track-right" id="carouselTrack2"></div>
  <div class="film-strip-bottom"></div>
</div>

<main>
  <h1 id="greeting">Hello!</h1>

  <div class="button-group">
    <button class="game-btn" id="startDailyBtn">📅 Daily Game</button>
    <button class="game-btn" id="startRandomBtn">🎬 Random Game</button>
  </div>

  <button class="game-btn" id="showLeaderboardBtn" style="margin-top: 20px;">🏆 Show Leaderboard</button>
</main>

<div id="leaderboardModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="modal-close">&times;</span>
    <h2 class="modal-title">🏆 Leaderboard - Most Wins</h2>
    <table id="leaderboardTable">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Player</th>
          <th>Wins</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="3">Loading...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<footer>
  © 2025 Cinemadle. All Rights Reserved.
</footer>

<script>
window.onload = () => {
    const greeting = document.getElementById("greeting");
    const userEmail = localStorage.getItem("userEmail");

    greeting.textContent = userEmail ? `Hello, ${userEmail}!` : "Hello, Guest!";

    let sessionID = localStorage.getItem("cinemadleSessionID");
    if (!sessionID) {
        sessionID = crypto.randomUUID();
        localStorage.setItem("cinemadleSessionID", sessionID);
    }

    function loadCarousel() {
        const posters = JSON.parse(localStorage.getItem("carouselPosters")) || [];
        const t1 = document.getElementById("carouselTrack1");
        const t2 = document.getElementById("carouselTrack2");
        t1.innerHTML = "";
        t2.innerHTML = "";

        if (posters.length === 0) {
            for (let i = 0; i < 8; i++) {
                const p1 = document.createElement("div");
                p1.className = "carousel-placeholder";
                p1.textContent = "Play games to collect posters";
                t1.appendChild(p1);

                const p2 = document.createElement("div");
                p2.className = "carousel-placeholder";
                p2.textContent = "Play games to collect posters";
                t2.appendChild(p2);
            }
        } else {
            const dup = [...posters, ...posters, ...posters];
            dup.forEach(u => {
                const i1 = document.createElement("img");
                i1.src = u;
                i1.className = "carousel-poster";
                t1.appendChild(i1);
            });
            dup.forEach(u => {
                const i2 = document.createElement("img");
                i2.src = u;
                i2.className = "carousel-poster";
                t2.appendChild(i2);
            });
        }
    }

    loadCarousel();

    async function pollLeaderboard(timeout = 6000) {
        const start = Date.now();
        while (Date.now() - start < timeout) {
            const r = await fetch("response_status.json?ts=" + Date.now());
            const j = await r.json();
            if (j && j.Flag === "leaderboard_data") return j;
            await new Promise(res => setTimeout(res, 250));
        }
        return null;
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
            alert("Failed to start game");
            return;
        }

        const data = out.data;

        if (data.Poster) {
            sessionStorage.setItem("gamePosterURL", data.Poster);
        }

        if (data.Flag === "daily_already_played") {
            sessionStorage.setItem("dailyAlreadyPlayedMessage", data.Message || "");
            window.location.href = "daily.php?already=1";
        }

        if (data.Flag === "daily_game_started") {
            window.location.href = "game.php?mode=daily";
        }

        if (data.Flag === "random_game_started") {
            window.location.href = "game.php?mode=random";
        }
    }

    document.getElementById("startDailyBtn").onclick = () => startGame("start_daily_game");
    document.getElementById("startRandomBtn").onclick = () => startGame("start_random_game");

    const leaderboardBtn = document.getElementById("showLeaderboardBtn");
    const modal = document.getElementById("leaderboardModal");
    const close = document.querySelector(".modal-close");

    leaderboardBtn.onclick = async () => {
        modal.style.display = "flex";

        const payload = {
            Flag: "request_leaderboard",
            Payload: {
                Type: "wins",
                Username: userEmail || "guest"
            }
        };

        await fetch("request_leaderboard.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const data = await pollLeaderboard();
        const tbody = document.querySelector("#leaderboardTable tbody");
        tbody.innerHTML = "";

        if (!data || !data.Payload || !data.Payload.Users) {
            tbody.innerHTML = '<tr><td colspan="3">Failed to load leaderboard</td></tr>';
            return;
        }

        data.Payload.Users.forEach((u, i) => {
            tbody.innerHTML += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${u.Username}</td>
                    <td>${u.Wins}</td>
                </tr>
            `;
        });
    };

    close.onclick = () => modal.style.display = "none";
    modal.onclick = e => { if (e.target === modal) modal.style.display = "none"; };
};
</script>

</body>
</html>
