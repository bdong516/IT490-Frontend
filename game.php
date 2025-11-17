<?php
session_start();
$username = $_SESSION['email'] ?? "guest";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Game</title>
<link rel="stylesheet" href="css/styles.css">

<style>
/* --- Page Layout --- */
.game-container {
    max-width: 750px;
    margin: 2rem auto;
    text-align: center;
}

/* --- Guess & Autocomplete --- */
.guess-wrapper {
    position: relative;
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
}

#guessInput {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 6px;
    color: black;
    background: white;
}

/* --- Autocomplete Dropdown (Readable) --- */
#suggestions {
    list-style: none;
    padding: 0;
    margin: 0;
    border: 1px solid #ccc;
    border-top: none;
    background: #ffffff;
    width: 100%;
    position: absolute;
    z-index: 8000;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

#suggestions li {
    padding: 10px;
    background: white;
    color: black;
    border-bottom: 1px solid #eee;
    font-size: 15px;
    cursor: pointer;
}
#suggestions li:hover {
    background: #148B18;
    color: white;
}

/* --- Guess History Box (Readable) --- */
#historyBox {
    margin-top: 25px;
    padding: 15px;
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-radius: 8px;
    max-height: 260px;
    overflow-y: auto;
    text-align: left;
    color: black !important;
}

.history-item {
    padding: 10px;
    margin-bottom: 8px;
    background: #ffffff;
    border-radius: 6px;
    border-left: 5px solid #148B18;
    color: black !important;
}

/* --- Hint Panel (Readable) --- */
#hintBox {
    margin-top: 25px;
    padding: 15px;
    background: #e7f5e9;
    border: 1px solid #148B18;
    border-radius: 8px;
    text-align: left;
    color: black !important;
}

#hintBox h3, #hintBox p, #hintContent, #hintBox li {
    color: black !important;
}

/* bullets readable */
#hintBox ul {
    padding-left: 18px;
}

/* --- Buttons --- */
button {
    margin-top: 15px;
    padding: 10px 18px;
    background: #148B18;
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}
button:hover {
    background: #0f6611;
}

#responseArea {
    margin-top: 18px;
    font-size: 18px;
    font-weight: bold;
    min-height: 32px;
}
</style>
</head>

<body>

<header>
    <a href="index.php" class="logo">Cinemadle</a>
    <nav>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
            <span style="margin-right:10px;color:#ffcc00;"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main class="game-container">
    <h1>Guess the Movie!</h1>

    <!-- Guess Form -->
    <form id="guessForm" autocomplete="off">
        <div class="guess-wrapper">
            <input type="text" id="guessInput" placeholder="Enter your guess..." required>
            <ul id="suggestions"></ul>
        </div>
        <button type="submit">Submit Guess</button>
    </form>

    <!-- Response Message -->
    <div id="responseArea"></div>

    <!-- Guess History -->
    <div id="historyBox">
        <h3>Your Guess History</h3>
        <div id="historyList"></div>
    </div>

    <!-- Hint Box -->
    <div id="hintBox">
        <h3>Hints</h3>
        <div id="hintContent">Make a guess to see hints!</div>
    </div>
</main>

<script>
// ========================= SESSION ID =========================
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
    sessionID = crypto.randomUUID();
    localStorage.setItem("cinemadleSessionID", sessionID);
}

const username = "<?php echo $username; ?>";

// ========================= LOAD MOVIES =========================
let movies = [];
async function loadMovies() {
    try {
        const r = await fetch("movies.json?t=" + Date.now());
        movies = await r.json();
    } catch (err) {
        console.error("Failed to load movies:", err);
    }
}
loadMovies();

// ========================= AUTOCOMPLETE =========================
const input = document.getElementById("guessInput");
const suggestions = document.getElementById("suggestions");
let selectedMovieId = null;

input.addEventListener("input", () => {
    const txt = input.value.toLowerCase();
    suggestions.innerHTML = "";
    selectedMovieId = null;

    if (!txt) return;

    const results = movies
        .filter(m => m.title.toLowerCase().includes(txt))
        .slice(0, 10);

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

// ========================= SUBMIT GUESS =========================
const responseDiv = document.getElementById("responseArea");
const historyList = document.getElementById("historyList");
const hintContent = document.getElementById("hintContent");

document.getElementById("guessForm").addEventListener("submit", async e => {
    e.preventDefault();

    const guessText = input.value.trim();
    if (!guessText) return;

    // auto-match if the user presses enter
    if (!selectedMovieId) {
        const found = movies.find(m => m.title.toLowerCase() === guessText.toLowerCase());
        if (found) selectedMovieId = found.id;
    }

    if (!selectedMovieId) {
        responseDiv.textContent = "⚠ Please choose a valid movie.";
        return;
    }

    responseDiv.textContent = "Sending guess...";

    const body = {
        Flag: "guess",
        Payload: {
            SessionID: sessionID,
            Username: username,
            GuessIndex: selectedMovieId
        }
    };

    try {
        const r = await fetch("send_guess.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body)
        });

        const out = await r.json();
        console.log("Backend Response:", out);

        if (!out.success) {
            responseDiv.textContent = "⚠ " + out.message;
            return;
        }

        const g = out.data;
        responseDiv.textContent = g.Message;

        // =========== Add Guess to History ===========
        const entry = document.createElement("div");
        entry.className = "history-item";
        entry.textContent = guessText + " — " + g.Message;
        historyList.appendChild(entry);

        // =========== Update Hints ===========
        let hintHTML = "";

        if (g.NextHint)
            hintHTML += `<p><strong>Next Hint:</strong> ${g.NextHint}</p>`;

        if (g.Overlaps) {
            hintHTML += "<p><strong>Matches:</strong></p><ul>";

            hintHTML += `<li>Director Match: ${g.Overlaps.DirectorMatch}</li>`;
            hintHTML += `<li>Shared Actors: ${g.Overlaps.SharedActors.join(", ") || "None"}</li>`;
            hintHTML += `<li>Shared Genres: ${g.Overlaps.SharedGenres.join(", ") || "None"}</li>`;

            hintHTML += "</ul>";
        }

        if (!hintHTML)
            hintHTML = "Make a guess to see hints!";

        hintContent.innerHTML = hintHTML;

        // =========== Win / Lose ===========
        if (g.Flag === "game_over")
            responseDiv.textContent = "🎉 " + g.Message;

        if (g.Flag === "game_lost")
            responseDiv.textContent = "❌ " + g.Message;

    } catch (err) {
        console.error(err);
        responseDiv.textContent = "Network error.";
    }

    // Reset input
    input.value = "";
    selectedMovieId = null;
    suggestions.innerHTML = "";
});
</script>

</body>
</html>

