<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css?t=<?=time()?>">

</head>

<body>

<header>
    <a href="index.php" class="logo">CINEMADLE</a>
    <nav><a href="register.php">Register</a></nav>
</header>

<main>
    <div class="form-box">
        <h1>Login</h1>

        <form id="loginForm">
            <div class="form-row">
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <div class="form-row">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div id="status"></div>
    </div>
</main>

<footer>© 2025 Cinemadle. All Rights Reserved.</footer>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const status = document.getElementById("status");
    status.textContent = "Logging in...";

    const username = document.querySelector('[name="username"]').value.trim();
    const password = document.querySelector('[name="password"]').value;

    try {
        const res = await fetch("send_user_data.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                Flag: "Login_Request",
                username,
                password
            })
        });

        const raw = await res.text();
        const result = JSON.parse(raw);

        status.textContent = result.message;

        if (result.success) {
            localStorage.setItem("userEmail", result.username);
            setTimeout(() => window.location.href = "index.php", 1200);
        }
    } catch {
        status.textContent = "Network error.";
    }
});
</script>

</body>
</html>
