<?php
session_start();
$username = $_SESSION['email'] ?? "guest";

$alreadyPlayed = isset($_GET['already']) || (
    isset($_COOKIE['daily_played']) &&
    $_COOKIE['daily_played'] === date('Y-m-d')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Daily Game</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/game.css">
</head>

<body>

<header>
    <a href="index.php" class="logo">CINEMADLE</a>
    <nav>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
            <a href="profile.php">👤 Profile</a>
            <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main class="game-container">
    
<?php if ($alreadyPlayed): ?>

<h1 class="page-title">Daily Game</h1>

<div class="summary-box">
    <h2>You Already Played Today!</h2>
    <p id="alreadyPlayedMessage">Come back tomorrow for a new challenge.</p>
    <button id="playRandomBtn" class="primary-btn">Play Random Mode</button>
</div>

<script>
const backendMessage = sessionStorage.getItem("dailyAlreadyPlayedMessage");
if (backendMessage) {
    document.getElementById("alreadyPlayedMessage").textContent = backendMessage;
    sessionStorage.removeItem("dailyAlreadyPlayedMessage");
}

document.getElementById("playRandomBtn").addEventListener("click", async () => {
    let sessionID = localStorage.getItem("cinemadleSessionID");
    if (!sessionID) {
        sessionID = crypto.randomUUID();
        localStorage.setItem("cinemadleSessionID", sessionID);
    }

    const payload = {
        Flag: "start_random_game",
        Payload: {
            SessionID: sessionID,
            Username: "<?php echo htmlspecialchars($username); ?>"
        }
    };

    const res = await fetch("start_game.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    const out = await res.json();

    if (out.success && out.data && out.data.Poster) {
        sessionStorage.setItem("gamePosterURL", out.data.Poster);
    }

    if (out.success && out.data && out.data.Flag === "random_game_started") {
        window.location.href = "game.php?mode=random";
    }
});
</script>

<?php else: ?>

<h1 class="page-title">Daily Game</h1>
<p class="subtitle">1 movie per day • 5 guesses</p>

<img id="posterImage" class="poster" style="display:none;">

<form id="guessForm" class="guess-form" autocomplete="off">
    <div class="guess-wrapper">
        <input type="text" id="guessInput" placeholder="Enter your guess..." required>
        <ul id="suggestions"></ul>
    </div>
    <button type="submit" class="primary-btn">Submit Guess</button>
</form>

<div id="responseArea" class="response-box"></div>

<div class="panel">
    <h3>Your Guess History</h3>
    <div id="historyList"></div>
</div>

<div class="panel">
    <h3>Hints</h3>
    <div id="hintContent">Make a guess to see hints!</div>
</div>

<div id="playAgainBox" style="display:none;">
    <a href="index.php" class="primary-btn">Back to Home</a>
</div>

<?php endif; ?>

</main>

<footer>© 2025 Cinemadle. All Rights Reserved.</footer>

<?php if (!$alreadyPlayed): ?>
<script>
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
    sessionID = crypto.randomUUID();
    localStorage.setItem("cinemadleSessionID", sessionID);
}

document.cookie = "daily_played=" + new Date().toISOString().split("T")[0] + "; path=/";

const username = "<?php echo $username; ?>";
let movies = [];

(async () => {
    const r = await fetch("movies.json?t=" + Date.now());
    movies = await r.json();
})();

const input = document.getElementById("guessInput");
const suggestions = document.getElementById("suggestions");
let selectedMovieId = null;

input.addEventListener("input", () => {
    const t = input.value.toLowerCase();
    suggestions.innerHTML = "";
    selectedMovieId = null;
    if (!t) return;

    movies.filter(m => m.title.toLowerCase().includes(t))
        .slice(0, 10)
        .forEach(m => {
            const li = document.createElement("li");
            li.textContent = m.title;
            li.onclick = () => {
                input.value = m.title;
                selectedMovieId = m.id;
                suggestions.innerHTML = "";
            };
            suggestions.appendChild(li);
        });
});

document.addEventListener("click", e => {
    if (!document.querySelector(".guess-wrapper").contains(e.target)) {
        suggestions.innerHTML = "";
    }
});

async function pollGameState(timeout = 5000) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        const r = await fetch("response_status.json?ts=" + Date.now());
        const data = await r.json();
        if (data && data.SessionID === sessionID) {
            return data;
        }
        await new Promise(res => setTimeout(res, 200));
    }
    return null;
}

function showEndScreen() {
    input.disabled = true;
    document.querySelector(".primary-btn").style.display = "none";
    document.getElementById("playAgainBox").style.display = "block";
}

document.getElementById("guessForm").addEventListener("submit", async e => {
    e.preventDefault();

    const txt = input.value.trim();
    if (!selectedMovieId) {
        const found = movies.find(m => m.title.toLowerCase() === txt.toLowerCase());
        if (found) selectedMovieId = found.id;
    }
    if (!selectedMovieId) return;

    const body = {
        Flag: "guess",
        Payload: {
            SessionID: sessionID,
            Username: username,
            GuessIndex: selectedMovieId,
            Mode: "daily"
        }
    };

    const r = await fetch("send_guess.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(body)
    });

    const out = await r.json();
    if (!out.success) return;

    const g = await pollGameState();
    if (!g) return;

    const responseDiv = document.getElementById("responseArea");
    const historyList = document.getElementById("historyList");
    const hintContent = document.getElementById("hintContent");

    let msg = "";

    if (g.Flag === "game_over") {
        msg = `🎉 Correct! The answer was "${g.Answer}".`;
        showEndScreen();
    }

    if (g.Flag === "game_lost") {
        msg = `❌ Out of guesses! The answer was "${g.Answer}".`;
        showEndScreen();
    }

    responseDiv.textContent = msg;

    const entry = document.createElement("div");
    entry.className = "history-item";
    entry.textContent = txt + " — " + msg;
    historyList.appendChild(entry);

    let hintHTML = "";
    if (g.Hint || g.NextHint) {
        hintHTML += `<p><strong>Hint:</strong> ${g.Hint || g.NextHint}</p>`;
    }

    if (g.Overlaps) {
        hintHTML += "<ul>";
        hintHTML += `<li>Director Match: ${g.Overlaps.DirectorMatch}</li>`;
        hintHTML += `<li>Shared Actors: ${(g.Overlaps.SharedActors || []).join(", ")}</li>`;
        hintHTML += `<li>Shared Genres: ${(g.Overlaps.SharedGenres || []).join(", ")}</li>`;
        hintHTML += "</ul>";
    }

    hintContent.innerHTML = hintHTML;

    const posterURL = g.Poster || sessionStorage.getItem("gamePosterURL");
    if (posterURL) {
        const posterImg = document.getElementById("posterImage");
        posterImg.src = posterURL;
        posterImg.style.display = "block";
    }

    input.value = "";
    selectedMovieId = null;
});
</script>
<?php endif; ?>

</body>
</html>
