<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification | Block327</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
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
            <span class="brand">Block327</span>
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
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="btn btn-primary sm">Dashboard</a>
                    <a href="logout.php" class="btn btn-ghost">Sign Out</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary sm">Institution Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Verify Certificate</h1>
        <p class="subtitle">Upload a digital certificate to verify its authenticity and integrity.</p>

        <div id="resultCard" class="result-card">
            <!-- Results injected via JS -->
        </div>

        <form id="verifyForm">
            <div class="drop-zone" id="dropZone">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span id="fileName">Drag and drop your certificate here or click to browse</span>
                <input type="file" id="certificate" name="certificate" accept="application/pdf" required>
            </div>
            <button type="submit" id="submitBtn" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Verify Document
            </button>
        </form>
        
        <p class="tool-help">Your document is checked against the issuer's original record. The file is not stored on our servers.</p>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('certificate');
        const fileNameSpan = document.getElementById('fileName');
        const form = document.getElementById('verifyForm');
        const resultCard = document.getElementById('resultCard');
        const submitBtn = document.getElementById('submitBtn');

        // Drag and drop effects
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('active'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('active'), false);
        });

        dropZone.addEventListener('drop', (e) => {
            let dt = e.dataTransfer;
            let files = dt.files;
            fileInput.files = files;
            updateFileName();
        });

        fileInput.addEventListener('change', updateFileName);

        function updateFileName() {
            if (fileInput.files.length > 0) {
                fileNameSpan.textContent = fileInput.files[0].name;
                fileNameSpan.style.color = 'var(--charcoal-blue)';
                fileNameSpan.style.fontWeight = '500';
            } else {
                fileNameSpan.textContent = 'Drag and drop your certificate here or click to browse';
                fileNameSpan.style.color = '';
                fileNameSpan.style.fontWeight = '';
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (fileInput.files.length === 0) return;

            submitBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                    <line x1="12" y1="2" x2="12" y2="6"/>
                    <line x1="12" y1="18" x2="12" y2="22"/>
                    <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/>
                    <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                    <line x1="2" y1="12" x2="6" y2="12"/>
                    <line x1="18" y1="12" x2="22" y2="12"/>
                    <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/>
                    <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
                </svg>
                Verifying...
            `;
            submitBtn.disabled = true;
            resultCard.style.display = 'none';

            const formData = new FormData(form);

            try {
                const response = await fetch('verify_handler.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                    body: formData
                });

                const data = await response.json();

                resultCard.className = 'result-card ' + (data.status === 'success' ? 'success' : 'error');

                if (data.status === 'success') {
                    resultCard.innerHTML = `
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Authentic Document
                        </h3>
                        <div class="result-item"><span>Student:</span> ${data.data.student_name}</div>
                        <div class="result-item"><span>Degree:</span> ${data.data.degree}</div>
                        <div class="result-item"><span>Institution:</span> ${data.data.institution}</div>
                        <div class="result-item"><span>Issue Date:</span> ${data.data.issuance_date}</div>
                    `;
                } else {
                    resultCard.innerHTML = `
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Document Not Valid
                        </h3>
                        <div class="result-item">${data.message || 'The document could not be verified or has been altered.'}</div>
                    `;
                }
                resultCard.style.display = 'block';
            } catch (error) {
                resultCard.className = 'result-card error';
                resultCard.innerHTML = `
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Error
                    </h3>
                    <div class="result-item">Failed to communicate with the server. Please try again.</div>
                `;
                resultCard.style.display = 'block';
            } finally {
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Verify Document
                `;
                submitBtn.disabled = false;
            }
        });
    </script>
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