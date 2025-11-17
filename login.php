<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cinemadle | Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>

<header>
    <a href="index.php" class="logo">Cinemadle</a>
    <nav>
        <a href="register.php">Register</a>
    </nav>
</header>

<main class="form-container">
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

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const status = document.getElementById("status");
    status.textContent = "Logging in...";
    status.className = "";

    const username = document.querySelector('[name="username"]').value.trim();
    const password = document.querySelector('[name="password"]').value;

    try {
        const res = await fetch("send_user_data.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                Flag: "Login_Request",
                username,
                password
            })
        });

        const text = await res.text();
        console.log("Raw Response:", text);

        let result = JSON.parse(text);

        status.textContent = result.message;
        status.className = result.success ? "success-msg" : "error-msg";

        if (result.success) {
            localStorage.setItem("userEmail", result.username);

            setTimeout(() => {
                window.location.href = "index.php";
            }, 1500);
        }

    } catch (err) {
        console.error(err);
        status.textContent = "Network error.";
        status.className = "error-msg";
    }
});
</script>

</body>
</html>
