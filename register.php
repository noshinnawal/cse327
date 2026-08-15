<?php
require_once 'auth.php';

$pdo = DbConnection::getInstance()->getPdo();

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$submitted = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $rep_name = trim($_POST['rep_name'] ?? '');
        $rep_title = trim($_POST['rep_title'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $location === '' || $email === '' || $website === '' || $rep_name === '' || $rep_title === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!filter_var($website, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid website URL (including https://).';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO institutions (name, password_hash, location, email, website, rep_name, rep_title, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$name, password_hash($password, PASSWORD_BCRYPT), $location, $email, $website, $rep_name, $rep_title]);
                header('Location: login.php?registered=1');
                exit;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'This institution is already registered. Please sign in instead.';
                } else {
                    error_log('register.php: ' . $e->getMessage());
                    $error = 'An internal error occurred. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Institution | Block327</title>
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
                <a href="login.php" class="link">Institution Login</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Register Institution</h1>
        <p class="subtitle">Register your university to issue and manage certificates in the secure ledger. Accounts become active after admin approval.</p>

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

        <form method="post" action="register.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Institution Name</label>
                <input type="text" id="name" name="name" class="input" placeholder="e.g., Independent University Bangladesh" value="<?= htmlspecialchars($submitted['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" class="input" placeholder="e.g., Dhaka, Bangladesh" value="<?= htmlspecialchars($submitted['location'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="input" placeholder="registrar@university.edu" value="<?= htmlspecialchars($submitted['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="website">Website Link</label>
                <input type="url" id="website" name="website" class="input" placeholder="https://www.university.edu" value="<?= htmlspecialchars($submitted['website'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="rep_name">Representative Name</label>
                <input type="text" id="rep_name" name="rep_name" class="input" placeholder="Your full name" value="<?= htmlspecialchars($submitted['rep_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="rep_title">Job Title</label>
                <input type="text" id="rep_title" name="rep_title" class="input" placeholder="e.g., Registrar, Exam Controller" value="<?= htmlspecialchars($submitted['rep_title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" placeholder="At least 8 characters" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Register Institution
            </button>
        </form>

        <p class="tool-help">
            Already registered? <a href="login.php" class="link">Sign in instead</a>
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
