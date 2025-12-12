<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cinemadle | Register</title>
<link rel="stylesheet" href="css/styles.css?t=<?=time()?>">
</head>

<body>

<header>
    <a href="index.php" class="logo">CINEMADLE</a>
    <nav><a href="login.php">Login</a></nav>
</header>

<main>
    <div class="form-box">
        <h1>Register</h1>

        <form id="registerForm">
            <div class="form-row"><input name="username" placeholder="Choose username" required></div>
            <div class="form-row"><input name="email" type="email" placeholder="Email" required></div>
            <div class="form-row"><input name="password" type="password" placeholder="Password" required></div>
            <div class="form-row"><input name="confirm_password" type="password" placeholder="Confirm password" required></div>

            <input type="submit" value="Register">
        </form>

        <div id="formMessage"></div>
    </div>
</main>

<footer>© 2025 Cinemadle. All Rights Reserved.</footer>

<script>
function validPassword(p) {
  return p.length >= 8 && /[A-Z]/.test(p) && /[a-z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p);
}

document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const msgBox = document.getElementById('formMessage');
    msgBox.textContent = 'Registering...';

    const username = document.querySelector('[name="username"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const password = document.querySelector('[name="password"]').value;
    const confirm = document.querySelector('[name="confirm_password"]').value;

    if (password !== confirm) {
        msgBox.textContent = "Passwords do not match.";
        return;
    }

    if (!validPassword(password)) {
        msgBox.textContent = "Password must contain uppercase, lowercase, number, symbol, and be 8+ chars.";
        return;
    }

    try {
        const res = await fetch("send_user_data.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                Flag: "Register_User",
                username,
                email,
                password
            })
        });

        const raw = await res.text();
        const result = JSON.parse(raw);

        msgBox.textContent = result.message;

        if (result.success) setTimeout(() => window.location.href = "login.php", 1500);

    } catch {
        msgBox.textContent = "Network error.";
    }
});
</script>

</body>
</html>
