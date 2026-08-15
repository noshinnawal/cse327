<?php
require 'auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$notice = '';
if (isset($_GET['registered'])) {
    $notice = 'Registration submitted. Your institution will be active once approved by an administrator.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['institution'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = authenticate($name, $password);
    if ($result === 'pending') {
        $notice = 'This institution is registered but awaiting admin approval. Please try again later.';
    } elseif ($result !== null) {
        $_SESSION['institution'] = $result;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid institution name or password. Please try again.';
    }
}

$institutions = active_institutions();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Login | Block327</title>
    <link rel="stylesheet" href="style.css?v=2.0">
    <script>
        // Prevent flash of wrong theme
        (function() {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = saved || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <nav class="topbar">
        <div class="topbar-inner">
            <a href="index.php" class="brand">Block327</a>
            <div class="nav-actions">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/>
                        <line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>
                <a href="index.php" class="link">Verification Portal</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Institution Login</h1>
        <p class="subtitle">Sign in to issue or manage certificates in the secure ledger.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px; vertical-align: middle;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px; vertical-align: middle;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <?php if (count($institutions) === 0): ?>
            <div class="empty">
                <p>No active institutions yet.<br>New institutions register below and are activated after admin approval.</p>
            </div>
        <?php else: ?>
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="institution">Institution</label>
                <select name="institution" id="institution" class="input" required>
                    <option value="" disabled selected>Select your institution</option>
                    <?php foreach ($institutions as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Sign In
            </button>
        </form>
        <?php endif; ?>

        <p class="tool-help">
            <strong>New here?</strong><br>
            <a href="register.php" class="link">Register your institution</a>
        </p>

        <p class="tool-help">
            <strong>Demo Credentials</strong><br>
            North South University: <code>nosh327</code><br>
            Brac University: <code>brac327</code>
        </p>
    </div>
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Remove no-transition class after page load
        window.addEventListener('load', () => {
            document.body.classList.remove('no-transition');
        });
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
</body>
</html>