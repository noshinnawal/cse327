<?php
require_once 'auth.php';
require_login();
$institution = current_institution();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issuance Dashboard | Checkr</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
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
            <a href="index.php" class="brand">Checkr</a>
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
                <a href="view_certs.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Certificates
                </a>
                <a href="logout.php" class="btn btn-ghost">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Issue Certificate</h1>
        <p class="subtitle">Register a new certificate for <strong><?= htmlspecialchars($institution) ?></strong> into the secure ledger.</p>

        <form id="issueForm">
            <div class="form-group">
                <label for="student_name">Student Name</label>
                <input type="text" id="student_name" name="student_name" class="input" required placeholder="Enter student's full name">
            </div>

            <div class="form-group">
                <label for="degree">Degree / Qualification</label>
                <input type="text" id="degree" name="degree" class="input" required placeholder="e.g., Bachelor of Science in Computer Science">
            </div>

            <div class="form-group">
                <label for="issuance_date">Issuance Date</label>
                <input type="date" id="issuance_date" name="issuance_date" class="input" required>
            </div>

            <div class="form-group">
                <label>Certificate File</label>
                <div class="drop-zone" id="dropZone">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="12" x2="12" y2="18"/>
                    <polyline points="9 15 12 18 15 15"/>
                </svg>
                    <span id="fileName">Drag and drop the certificate PDF here or click to browse</span>
                    <input type="file" id="certificate" name="certificate" accept="application/pdf" required>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Issue Certificate
            </button>
        </form>

        <div id="resultCard" class="result-card">
            <!-- Notifications injected via JS -->
        </div>
        
        <p class="tool-help">The certificate will be fingerprinted and permanently recorded in the secure ledger.</p>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('certificate');
        const fileNameSpan = document.getElementById('fileName');
        const form = document.getElementById('issueForm');
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
                fileNameSpan.textContent = 'Drag and drop the certificate PDF here or click to browse';
                fileNameSpan.style.color = '';
                fileNameSpan.style.fontWeight = '';
            }
        }

        // Set default date to today
        document.getElementById('issuance_date').valueAsDate = new Date();

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
                Issuing...
            `;
            submitBtn.disabled = true;
            resultCard.style.display = 'none';

            const formData = new FormData(form);

            try {
                const response = await fetch('issue_handler.php', {
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
                            Certificate Issued
                        </h3>
                        <div class="result-item">${data.message || 'Certificate has been securely registered in the ledger.'}</div>
                        ${data.document_hash ? `<div class="result-item"><span>Hash:</span> <code class="mono">${data.document_hash}</code></div>` : ''}
                    `;
                    form.reset();
                    document.getElementById('issuance_date').valueAsDate = new Date();
                    updateFileName();
                } else {
                    resultCard.innerHTML = `
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Error
                        </h3>
                        <div class="result-item">${data.message || 'Failed to register certificate.'}</div>
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
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Issue Certificate
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