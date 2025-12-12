<?php
session_start();
$username = $_SESSION['email'] ?? "guest";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Random Game</title>
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
      <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>

<main class="game-container">

  <h1 class="page-title">Random Game</h1>
  <p class="subtitle">Unlimited guesses</p>

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
    <a href="random.php" class="primary-btn">Play Again</a>
  </div>

</main>

<footer>© 2025 Cinemadle. All Rights Reserved.</footer>

<script>
let sessionID = localStorage.getItem("cinemadleSessionID");
if (!sessionID) {
  sessionID = crypto.randomUUID();
  localStorage.setItem("cinemadleSessionID", sessionID);
}

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
  const txt = input.value.toLowerCase();
  suggestions.innerHTML = "";
  selectedMovieId = null;
  if (!txt) return;

  movies.filter(m => m.title.toLowerCase().includes(txt))
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

function showPlayAgain() {
  document.getElementById("playAgainBox").style.display = "block";
  document.querySelector(".primary-btn").style.display = "none";
  input.disabled = true;
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
      GuessIndex: selectedMovieId
    }
  };

  const res = await fetch("send_guess.php", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify(body)
  });

  const out = await res.json();
  if (!out.success) return;

  const g = out.data;
  const responseDiv = document.getElementById("responseArea");
  const historyList = document.getElementById("historyList");
  const hintContent = document.getElementById("hintContent");

  let msg = g.Message || "";

  if (g.Flag === "game_over") {
    msg = `🎉 Correct! The answer was "${g.Answer}".`;
    showPlayAgain();
  }
  if (g.Flag === "game_lost") {
    msg = `❌ Game over! The answer was "${g.Answer}".`;
    showPlayAgain();
  }

  responseDiv.textContent = msg;

  const entry = document.createElement("div");
  entry.className = "history-item";
  entry.textContent = txt + " — " + msg;
  historyList.appendChild(entry);

  let hintHTML = "";
  if (g.Hint || g.NextHint) hintHTML += `<p><strong>Hint:</strong> ${g.Hint || g.NextHint}</p>`;

  if (g.Overlaps) {
    hintHTML += "<ul>";
    hintHTML += `<li>Director Match: ${g.Overlaps.DirectorMatch}</li>`;
    hintHTML += `<li>Shared Actors: ${(g.Overlaps.SharedActors || []).join(", ")}</li>`;
    hintHTML += `<li>Shared Genres: ${(g.Overlaps.SharedGenres || []).join(", ")}</li>`;
    hintHTML += "</ul>";
  }

  hintContent.innerHTML = hintHTML;
  input.value = "";
  selectedMovieId = null;
});
</script>

</body>
</html>
