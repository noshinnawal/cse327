<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'core.php';
require_login();
$institution = current_institution();
$pdo = DbConnection::getInstance()->getPdo();

$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$certificates = ledger_search($pdo, $institution, $q, $sort);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issued Certificates | Checkr</title>
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
                <a href="dashboard.php" class="btn btn-primary sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Issue New
                </a>
                <a href="logout.php" class="btn btn-ghost">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="container wide">
        <h1>Issued Certificates</h1>
        <p class="subtitle">Ledger entries for <strong><?= htmlspecialchars($institution) ?></strong> — search, sort, and manage.</p>

        <form method="get" action="view_certs.php" class="toolbar">
            <div class="form-group search-group">
                <input type="text" name="q" id="q" class="input" placeholder="Search by student name or degree..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="form-group sort-group">
                <select name="sort" id="sort" class="input" onchange="this.form.submit()">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>By Student Name</option>
                    <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>By Issue Date</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Search
            </button>
            <?php if ($q !== ''): ?>
                <a href="view_certs.php" class="btn btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <?php if (count($certificates) === 0): ?>
                <div class="empty">
                    <?php if ($q !== ''): ?>
                        <p>No certificates match "<strong><?= htmlspecialchars($q) ?></strong>".</p>
                    <?php else: ?>
                        <p>No certificates issued yet.<br><a href="dashboard.php">Issue your first certificate</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Degree</th>
                            <th>Issue Date</th>
                            <th>Hash</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td><span class="badge">#<?= (int)$cert['id'] ?></span></td>
                                <td class="strong"><?= htmlspecialchars($cert['student_name']) ?></td>
                                <td><?= htmlspecialchars($cert['degree']) ?></td>
                                <td><?= htmlspecialchars($cert['issuance_date']) ?></td>
                                <td><span class="mono"><?= htmlspecialchars(substr($cert['hash'], 0, 16)) ?>…</span></td>
                                <td class="actions-cell">
                                    <button type="button" class="btn btn-danger sm delete-btn" data-id="<?= (int)$cert['id'] ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <h3 id="modalTitle">Delete this certificate?</h3>
            <p id="modalBody">This will permanently remove the certificate from the ledger. It will no longer verify for future employer checks, and the same PDF can be re-registered afterwards.</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" id="confirmCancel">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete Certificate
                </button>
            </div>
        </div>
    </div>

    <script>
        const overlay = document.getElementById('modalOverlay');
        let deleteId = null;

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                deleteId = btn.dataset.id;
                overlay.classList.add('show');
            });
        });

        document.getElementById('confirmCancel').addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        function closeModal() {
            overlay.classList.remove('show');
            deleteId = null;
        }

        document.getElementById('confirmDelete').addEventListener('click', async () => {
            if (!deleteId) return;
            const btn = document.getElementById('confirmDelete');
            btn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                    <line x1="12" y1="2" x2="12" y2="6"/>
                    <line x1="12" y1="18" x2="12" y2="22"/>
                    <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/>
                    <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                    <line x1="2" y1="12" x2="6" y2="12"/>
                    <line x1="18" y1="12" x2="22" y2="12"/>
                    <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/>
                    <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
                </svg>
                Deleting...
            `;
            btn.disabled = true;
            
            const response = await fetch('delete_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: 'id=' + encodeURIComponent(deleteId)
            });
            const data = await response.json();
            closeModal();
            
            if (data.status === 'success') {
                window.location.reload();
            } else {
                btn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete Certificate
                `;
                btn.disabled = false;
                alert(data.message || 'Delete failed.');
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('show')) {
                closeModal();
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