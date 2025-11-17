<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cinemadle - Register</title>
  <link rel="stylesheet" href="css/styles.css">

  <style>
    /* Cinemadle Gold Register Button (Option A) */
    input[type="submit"] {
      background: #ffcc00;
      color: black;
      padding: 12px 20px;
      font-size: 18px;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: 0.2s ease-in-out;
      width: 100%;
    }

    input[type="submit"]:hover {
      background: #e6b800;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
  </style>
</head>

<body>
  <header>
    <a href="index.php" class="logo" style="text-decoration:none; color:#ffcc00;">Cinemadle</a>
    <nav>
      <a href="login.php">Login</a>
    </nav>
  </header>

  <main>
    <div class="form-box">
      <h1>Register</h1>
      <form id="registerForm">
        <div class="form-row">
          <input type="text" name="username" placeholder="Choose a username" required>
        </div>

        <div class="form-row">
          <input type="email" name="email" placeholder="Email address" required>
        </div>

        <div class="form-row">
          <input type="password" name="password" placeholder="Password" required>
        </div>

        <div class="form-row">
          <input type="password" name="confirm_password" placeholder="Confirm password" required>
        </div>

        <input type="submit" value="Register">
      </form>

      <div id="formMessage"></div>
    </div>
  </main>

  <script>
  // Password rules: 8+ chars, upper, lower, number, symbol
  function validatePassword(pw) {
    return (
      pw.length >= 8 &&
      /[A-Z]/.test(pw) &&
      /[a-z]/.test(pw) &&
      /\d/.test(pw) &&
      /[^A-Za-z0-9]/.test(pw)
    );
  }

  document.getElementById('registerForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const msgBox = document.getElementById('formMessage');
    msgBox.textContent = 'Registering...';
    msgBox.className = '';

    const username = document.querySelector('[name="username"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const password = document.querySelector('[name="password"]').value;
    const confirm = document.querySelector('[name="confirm_password"]').value;

    // Check passwords match
    if (password !== confirm) {
      msgBox.textContent = '⚠ Passwords do not match.';
      msgBox.className = 'error-msg';
      return;
    }

    // Check password strength
    if (!validatePassword(password)) {
      msgBox.textContent =
        '⚠ Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.';
      msgBox.className = 'error-msg';
      return;
    }

    try {
      const response = await fetch('send_user_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          Flag: 'Register_User',
          username,
          email,
          password
        })
      });

      const text = await response.text();
      console.log('Raw Response:', text);

      let result;
      try {
        result = JSON.parse(text);
      } catch (err) {
        msgBox.textContent = 'Invalid response from server.';
        msgBox.className = 'error-msg';
        console.error('JSON parse error:', err);
        return;
      }

      msgBox.textContent = result.message || 'No message received.';
      msgBox.className = result.success ? 'success-msg' : 'error-msg';

      if (result.success) {
        setTimeout(() => { window.location.href = 'login.php'; }, 2000);
      }

    } catch (error) {
      console.error('Request error:', error);
      msgBox.textContent = 'Network error while registering.';
      msgBox.className = 'error-msg';
    }
  });
  </script>
</body>
</html>
