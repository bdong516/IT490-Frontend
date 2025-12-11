<?php
session_start();
$username = $_SESSION['username'] ?? "Guest";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Game</title>
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/game.css">
</head>

<body>

<header>
    <a class="logo" href="index.php">CINEMADLE</a>
    <nav>
        <?php if (!empty($_SESSION["logged_in"])): ?>
            <span class="welcome-user">Hello, <?php echo htmlspecialchars($username); ?>!</span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<div class="game-container">

    <h1 class="page-title">Random Game</h1>
    <p class="subtitle">Unlimited guesses</p>

    <img id="posterImage" class="poster" src="" alt="Movie Poster" style="display:none;">

    <div class="guess-form">
        <div class="guess-wrapper">
            <input id="guessInput" type="text" placeholder="Enter your guess..." autocomplete="off">
            <ul id="suggestions"></ul>
        </div>

        <button id="submitGuessBtn" class="primary-btn">Submit Guess</button>
    </div>

    <div id="responseBox" class="response-box"></div>

    <div class="panel" id="historyPanel" style="display:none;">
        <h3>Your Guess History</h3>
        <div id="historyList"></div>
    </div>

    <div class="panel" id="hintPanel" style="display:none;">
        <h3>Hints</h3>
        <div id="hintContent"></div>
    </div>

    <div id="playAgainBox" style="display:none;">
        <button class="primary-btn" onclick="window.location.reload()">Play Again</button>
    </div>

</div>

<footer>
    © 2025 Cinemadle. All Rights Reserved.
</footer>

<script>
// Autocomplete state
let movieList = [];
let guessedTitles = [];

// Fetch list of movies (used for autocomplete)
fetch("movies.json")
    .then(res => res.json())
    .then(data => movieList = data)
    .catch(() => movieList = []);

// Autocomplete logic
const input = document.getElementById("guessInput");
const suggestions = document.getElementById("suggestions");

input.addEventListener("input", () => {
    const query = input.value.toLowerCase();
    suggestions.innerHTML = "";

    if (!query) return;

    const matches = movieList.filter(
        title => title.toLowerCase().startsWith(query)
    ).slice(0, 6);

    matches.forEach(match => {
        const li = document.createElement("li");
        li.textContent = match;
        li.onclick = () => {
            input.value = match;
            suggestions.innerHTML = "";
        };
        suggestions.appendChild(li);
    });
});

// Submit Guess
document.getElementById("submitGuessBtn").addEventListener("click", () => {
    const guess = input.value.trim();
    if (!guess) return;

    suggestions.innerHTML = "";

    fetch("send_guess.php", {
        method: "POST",
        body: JSON.stringify({
            Flag: "guess",
            Payload: {
                Guess: guess,
                SessionID: "<?php echo $_SESSION['session_id'] ?? ''; ?>"
            }
        })
    })
    .then(res => res.json())
    .then(result => handleResponse(result))
    .catch(err => console.error(err));
});

function handleResponse(res) {
    if (!res.success) return;

    const data = res.data;
    const flag = data.Flag;

    document.getElementById("responseBox").textContent = data.Message || "";

    document.getElementById("posterImage").style.display = "block";
    document.getElementById("posterImage").src = data.PosterURL || "";

    document.getElementById("historyPanel").style.display = "block";
    document.getElementById("historyList").innerHTML =
        data.History?.map(h => `<div class='history-item'>${h}</div>`).join("") || "";

    document.getElementById("hintPanel").style.display = "block";
    document.getElementById("hintContent").innerHTML = data.Hints || "";

    // End of game
    if (flag === "game_win" || flag === "game_over") {
        document.getElementById("playAgainBox").style.display = "block";
    }
}
</script>

</body>
</html>
