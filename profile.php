<?php
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile | Cinemadle</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/profile.css">
</head>

<body>

<header>
  <a href="index.php" class="logo">CINEMADLE</a>
  <nav>
    <a href="logout.php">Logout</a>
  </nav>
</header>

<main class="profile-main">
  <div class="profile-container">
    <div class="profile-header">
      <div class="profile-avatar">
        <span class="avatar-initial"><?php echo strtoupper(substr($username, 0, 1)); ?></span>
      </div>
      <h1 class="profile-username"><?php echo htmlspecialchars($username); ?></h1>
    </div>

    <div id="loadingState" class="loading-state">
      <div class="loader"></div>
      <p>Loading profile data...</p>
    </div>

    <div id="errorState" class="error-state" style="display: none;">
      <p class="error-message">Failed to load profile. Please try again.</p>
      <button onclick="loadProfile()" class="retry-btn">Retry</button>
    </div>

    <div id="profileContent" style="display: none;">
      <div class="stats-section">
        <h2 class="section-title">📊 Your Statistics</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-value" id="statWins">0</div>
            <div class="stat-label">Wins</div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">❌</div>
            <div class="stat-value" id="statLosses">0</div>
            <div class="stat-label">Losses</div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">🔥</div>
            <div class="stat-value" id="statStreak">0</div>
            <div class="stat-label">Daily Streak</div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value" id="statWinRate">0%</div>
            <div class="stat-label">Win Rate</div>
          </div>
        </div>
      </div>

      <div class="history-section">
        <h2 class="section-title">🎬 Game History</h2>
        <div id="historyList" class="history-list"></div>
        <div id="noHistory" class="no-history" style="display: none;">
          <p>No games played yet. Start playing to build your history!</p>
          <a href="index.php" class="game-btn">Play Now</a>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  © 2025 Cinemadle. All Rights Reserved.
</footer>

<script>
const username = "<?php echo $username; ?>";
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
    sessionID = crypto.randomUUID();
    localStorage.setItem("cinemadleSessionID", sessionID);
}

async function pollProfileResponse(timeout = 8000) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        const r = await fetch("response_status.json?ts=" + Date.now());
        const data = await r.json();
        if (!data || data.SessionID !== sessionID) {
            await new Promise(res => setTimeout(res, 250));
            continue;
        }

        if (data.Flag === "user_profile_data" || data.Flag === "user_profile_error") {
            return data;
        }

        await new Promise(res => setTimeout(res, 250));
    }
    return null;
}

async function loadProfile() {
    const loadingState = document.getElementById("loadingState");
    const errorState = document.getElementById("errorState");
    const profileContent = document.getElementById("profileContent");

    loadingState.style.display = "flex";
    errorState.style.display = "none";
    profileContent.style.display = "none";

    const payload = {
        Flag: "request_user_profile",
        Payload: {
            SessionID: sessionID,
            Username: username
        }
    };

    try {
        const res = await fetch("request_profile.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const out = await res.json();
        if (!out.success) throw new Error(out.message || "Request failed");

        const data = await pollProfileResponse();
        if (!data) throw new Error("No profile response");

        if (data.Flag === "user_profile_error") {
            throw new Error(data.Message || "Failed to load profile");
        }

        displayProfileData(data.Payload);
        loadingState.style.display = "none";
        profileContent.style.display = "block";

    } catch (error) {
        loadingState.style.display = "none";
        errorState.style.display = "flex";
        errorState.querySelector(".error-message").textContent = error.message;
    }
}

function displayProfileData(data) {
    const stats = data.Stats || {};
    const history = data.History || [];

    document.getElementById("statWins").textContent = stats.Wins || 0;
    document.getElementById("statLosses").textContent = stats.Losses || 0;
    document.getElementById("statStreak").textContent = stats.DailyStreak || 0;

    const totalGames = (stats.Wins || 0) + (stats.Losses || 0);
    const winRate = totalGames > 0 ? Math.round((stats.Wins / totalGames) * 100) : 0;
    document.getElementById("statWinRate").textContent = winRate + "%";

    const historyList = document.getElementById("historyList");
    const noHistory = document.getElementById("noHistory");

    if (history.length === 0) {
        historyList.style.display = "none";
        noHistory.style.display = "flex";
        return;
    }

    historyList.style.display = "block";
    noHistory.style.display = "none";
    historyList.innerHTML = "";

    history.forEach(game => {
        const card = document.createElement("div");
        card.className = "history-card";

        const resultIcon = game.Won ? "🎉" : "❌";
        const resultText = game.Won ? "Won" : "Lost";
        const resultClass = game.Won ? "result-win" : "result-loss";

        const playedDate = new Date(game.PlayedAt);
        const dateStr = playedDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });

        card.innerHTML = `
            <div class="history-result ${resultClass}">
                <span class="result-icon">${resultIcon}</span>
                <span class="result-text">${resultText}</span>
            </div>
            <div class="history-details">
                <div class="history-title">${game.AnswerTitle || 'Unknown Movie'}</div>
                <div class="history-meta">
                    <span class="history-mode">${game.GameMode === 'daily' ? '📅 Daily' : '🎬 Random'}</span>
                    <span class="history-attempts">${game.Attempts} attempt${game.Attempts !== 1 ? 's' : ''}</span>
                    <span class="history-date">${dateStr}</span>
                </div>
            </div>
        `;
        historyList.appendChild(card);
    });
}

window.addEventListener("DOMContentLoaded", loadProfile);
</script>

</body>
</html>
