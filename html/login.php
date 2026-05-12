<?php
require_once(__DIR__ . '/../api/config/auth.php');
$user = authCurrentUser();
if ($user !== null) {
    header('Location: maintenance/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login | HostelHub</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<main class="app-shell">
    <header class="app-header">
        <h1>HostelHub Login</h1>
        <p>Sign in as admin or student to access maintenance requests.</p>
    </header>

    <section class="card" style="max-width:560px;margin-left:auto;margin-right:auto;">
        <h2>Sign In</h2>
        <p class="muted-text">Demo admin: <strong>admin / admin123</strong> · Demo student password: <strong>student123</strong></p>
        <form id="loginForm" class="simple-form">
            <div class="form-row">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="student">Student</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-row">
                <label for="identifier" id="identifierLabel">Student Email</label>
                <input type="text" id="identifier" name="identifier" placeholder="alice.j@example.com" />
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" />
            </div>
            <div>
                <button type="submit" class="btn">Login</button>
            </div>
            <div id="loginMessage" role="status"></div>
        </form>
    </section>
</main>

<script>
const roleSelect = document.getElementById('role');
const identifierLabel = document.getElementById('identifierLabel');
const identifierInput = document.getElementById('identifier');
const loginMessage = document.getElementById('loginMessage');

function setIdentifierMeta() {
    if (roleSelect.value === 'admin') {
        identifierLabel.textContent = 'Admin Username';
        identifierInput.placeholder = 'admin';
    } else {
        identifierLabel.textContent = 'Student Email';
        identifierInput.placeholder = 'alice.j@example.com';
    }
}

function showMessage(type, text) {
    loginMessage.className = 'alert ' + type;
    loginMessage.textContent = text;
}

roleSelect.addEventListener('change', setIdentifierMeta);
setIdentifierMeta();

document.getElementById('loginForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    showMessage('info', 'Signing in...');

    try {
        const resp = await fetch('../api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                role: roleSelect.value,
                identifier: identifierInput.value.trim(),
                password: document.getElementById('password').value
            })
        });
        const json = await resp.json();
        if (json.success) {
            window.location.href = 'maintenance/index.php';
            return;
        }
        showMessage('error', json.errors ? json.errors.join(' ') : 'Login failed.');
    } catch (err) {
        showMessage('error', 'Login request failed.');
    }
});
</script>
</body>
</html>

