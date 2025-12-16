<?php
session_start();
$username = $_SESSION['email'] ?? "guest";
$mode = $_GET['mode'] ?? "random";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Game</title>
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
        <?php if (!empty($_SESSION["logged_in"])): ?>
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

<?php if ($mode === "daily"): ?>
    <h1>Daily Game</h1>
<?php elseif ($mode === "daily_summary"): ?>
    <h1>Daily Summary</h1>
<?php else: ?>
    <h1>Guess the Movie!</h1>
<?php endif; ?>

<img id="moviePoster" class="poster" style="display:none;" alt="Movie Poster">

<form id="guessForm" autocomplete="off">
    <div class="guess-wrapper">
        <input type="text" id="guessInput" placeholder="Enter your guess..." required>
        <ul id="suggestions"></ul>
    </div>
    <button type="submit" class="primary-btn">Submit Guess</button>
</form>

<div id="responseArea"></div>

<div id="historyBox">
    <h3>Your Guess History</h3>
    <div id="historyList"></div>
</div>

<div id="hintBox">
    <h3>Hints</h3>
    <div id="hintContent">Make a guess to see hints!</div>
</div>

<div id="playAgainBox" style="display:none; text-align:center; margin-top:30px;">
    <button id="playAgainBtn" class="primary-btn"></button>
</div>

</main>

<script>
const gameMode = "<?php echo $mode; ?>";
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
    sessionID = crypto.randomUUID();
    localStorage.setItem("cinemadleSessionID", sessionID);
}

const username = "<?php echo $username; ?>";

function addPosterToCarousel(posterURL) {
    try {
        let carouselPosters = JSON.parse(localStorage.getItem("carouselPosters")) || [];
        if (!carouselPosters.includes(posterURL)) {
            carouselPosters.push(posterURL);
            if (carouselPosters.length > 20) {
                carouselPosters = carouselPosters.slice(-20);
            }
            localStorage.setItem("carouselPosters", JSON.stringify(carouselPosters));
        }
    } catch (e) {}
}

let movies = [];
async function loadMovies() {
    const r = await fetch("movies.json?t=" + Date.now());
    movies = await r.json();
}
loadMovies();

const input = document.getElementById("guessInput");
const suggestions = document.getElementById("suggestions");
let selectedMovieId = null;

input.addEventListener("input", () => {
    const txt = input.value.toLowerCase();
    suggestions.innerHTML = "";
    selectedMovieId = null;
    if (!txt) return;

    const results = movies.filter(m => m.title.toLowerCase().includes(txt)).slice(0, 10);
    for (const m of results) {
        const li = document.createElement("li");
        li.textContent = m.title;
        li.onclick = () => {
            input.value = m.title;
            selectedMovieId = m.id;
            suggestions.innerHTML = "";
        };
        suggestions.appendChild(li);
    }
});

document.addEventListener("click", e => {
    if (!document.querySelector(".guess-wrapper").contains(e.target)) {
        suggestions.innerHTML = "";
    }
});

const responseDiv = document.getElementById("responseArea");
const historyList = document.getElementById("historyList");
const hintContent = document.getElementById("hintContent");

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

document.getElementById("guessForm").addEventListener("submit", async e => {
    e.preventDefault();

    const guessText = input.value.trim();
    if (!guessText) return;

    if (!selectedMovieId) {
        const found = movies.find(m => m.title.toLowerCase() === guessText.toLowerCase());
        if (found) selectedMovieId = found.id;
    }

    if (!selectedMovieId) {
        responseDiv.textContent = "Please choose a valid movie.";
        return;
    }

    const body = {
        Flag: "guess",
        Payload: {
            SessionID: sessionID,
            Username: username,
            GuessIndex: selectedMovieId
        }
    };

    const r = await fetch("send_guess.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
    });

    const out = await r.json();
    if (!out.success) return;

    const g = await pollGameState();
    if (!g) return;

    if (g.Flag === "guess_feedback") {
        responseDiv.textContent = "❌ Incorrect Guess";

        const item = document.createElement("div");
        item.className = "history-item";
        item.textContent = guessText + " — Incorrect";
        historyList.appendChild(item);

        let hintHTML = "";
        if (g.Hint) hintHTML += "<p>" + g.Hint + "</p>";

        if (g.Overlaps) {
            hintHTML += "<ul>";
            hintHTML += "<li>Director Match: " + g.Overlaps.DirectorMatch + "</li>";
            hintHTML += "<li>Shared Actors: " + (g.Overlaps.SharedActors.join(', ') || "None") + "</li>";
            hintHTML += "<li>Shared Genres: " + (g.Overlaps.SharedGenres.join(', ') || "None") + "</li>";
            hintHTML += "</ul>";
        }

        hintContent.innerHTML = hintHTML;
    }

    if (g.Flag === "game_over" || g.Flag === "game_lost") {
        responseDiv.textContent =
            g.Flag === "game_over"
                ? "🎉 Correct! The movie was: " + g.Answer
                : "❌ Game Over — The answer was: " + g.Answer;

        const item = document.createElement("div");
        item.className = "history-item";
        item.textContent = guessText;
        historyList.appendChild(item);

        hintContent.innerHTML = "<p>Attempts: " + g.Attempts + "</p>";

        const posterURL = g.Poster || sessionStorage.getItem("gamePosterURL");
        if (posterURL) {
            const posterImg = document.getElementById("moviePoster");
            posterImg.src = posterURL;
            posterImg.style.display = "block";
            addPosterToCarousel(posterURL);
        }

        document.getElementById("guessForm").style.display = "none";
        showPlayAgainButton();
    }

    input.value = "";
    selectedMovieId = null;
    suggestions.innerHTML = "";
});

function showPlayAgainButton() {
    const playAgainBox = document.getElementById("playAgainBox");
    const playAgainBtn = document.getElementById("playAgainBtn");

    playAgainBtn.textContent = gameMode === "daily" ? "Play Random Mode" : "Play Again!";
    playAgainBox.style.display = "block";

    playAgainBtn.addEventListener("click", async () => {
        const payload = {
            Flag: "start_random_game",
            Payload: {
                SessionID: sessionID,
                Username: username
            }
        };

        const res = await fetch("start_game.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const out = await res.json();

        if (out.success && out.data.Poster) {
            sessionStorage.setItem("gamePosterURL", out.data.Poster);
        }

        if (out.success && out.data.Flag === "random_game_started") {
            window.location.href = "game.php?mode=random";
        }
    });
}
</script>

</body>
</html>
