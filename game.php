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
<link rel="stylesheet" href="css/styles.css">

<style>
.game-container { max-width: 750px; margin: 2rem auto; text-align: center; }
.guess-wrapper { position: relative; width: 100%; max-width: 420px; margin: 0 auto; }
#guessInput { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 6px; }
#suggestions { list-style: none; padding: 0; margin: 0; border: 1px solid #ccc; border-top: none; background: #ffffff; width: 100%; position: absolute; z-index: 8000; max-height: 200px; overflow-y: auto; }
#suggestions li { padding: 10px; cursor: pointer; }
#suggestions li:hover { background: #148B18; color: white; }
#historyBox { margin-top: 25px; padding: 15px; background: #f1f1f1; border: 1px solid #ccc; border-radius: 8px; max-height: 260px; overflow-y: auto; text-align: left; }
.history-item { padding: 10px; margin-bottom: 8px; background: #ffffff; border-left: 5px solid #148B18; border-radius: 6px; }
#hintBox { margin-top: 25px; padding: 15px; background: #e7f5e9; border: 1px solid #148B18; border-radius: 8px; text-align: left; }
button { margin-top: 15px; padding: 10px 18px; background: #148B18; border: none; border-radius: 6px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; }
button:hover { background: #0f6611; }
#responseArea { margin-top: 18px; font-size: 18px; font-weight: bold; min-height: 32px; }
</style>
</head>

<body>

<header>
    <a href="index.php" class="logo">CINEMADLE</a>
    <nav>
        <?php if (!empty($_SESSION["logged_in"])): ?>
            <span><?php echo htmlspecialchars($username); ?></span>
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

    <form id="guessForm" autocomplete="off">
        <div class="guess-wrapper">
            <input type="text" id="guessInput" placeholder="Enter your guess..." required>
            <ul id="suggestions"></ul>
        </div>
        <button type="submit">Submit Guess</button>
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
</main>

<script>
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
    sessionID = crypto.randomUUID();
    localStorage.setItem("cinemadleSessionID", sessionID);
}

const username = "<?php echo $username; ?>";

let movies = [];
async function loadMovies() {
    const r = await fetch("movies.json");
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
    const g = out.data;

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

    if (g.Flag === "game_win") {
        responseDiv.textContent = "🎉 Correct! The movie was: " + g.Answer;

        const item = document.createElement("div");
        item.className = "history-item";
        item.textContent = guessText + " — Correct!";
        historyList.appendChild(item);

        hintContent.innerHTML = "<p>You won in " + g.Attempts + " attempts!</p>";
    }

    if (g.Flag === "game_lost") {
        responseDiv.textContent = "❌ Game Over — The answer was: " + g.Answer;

        const item = document.createElement("div");
        item.className = "history-item";
        item.textContent = guessText + " — Incorrect";
        historyList.appendChild(item);

        hintContent.innerHTML = "<p>You used all attempts (" + g.Attempts + ").</p>";
    }

    input.value = "";
    selectedMovieId = null;
    suggestions.innerHTML = "";
});
</script>

</body>
</html>
