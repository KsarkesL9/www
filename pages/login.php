<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie – Edux</title>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <script src="/assets/js/theme.js?v=<?= time() ?>"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../includes/auth.php';
    redirectIfLoggedIn();

    $msg = $_GET['msg'] ?? '';
    ?>

    <div class="auth-wrapper">

        <!-- Brand header - powyżej karty -->
        <div class="brand-header">
            <a href="/" class="brand-logo">Edu<span>x</span></a>
            <p class="brand-tagline">Platforma edukacyjna</p>
        </div>

        <!-- Karta formularza -->
        <div class="auth-card">

            <?php if ($msg === 'session_expired'): ?>
                <div class="alert alert-info show">Sesja wygasła. Zaloguj się ponownie.</div>
            <?php elseif ($msg === 'logged_out'): ?>
                <div class="alert alert-success show" style="display:flex; align-items:center; gap:0.6rem;">
                    <span style="font-size:1.2rem;">✓</span>
                    Wylogowano pomyślnie. Do zobaczenia!
                </div>
            <?php endif; ?>

            <!-- Alert -->
            <div id="alert" class="alert"></div>

            <!-- Form -->
            <form id="loginForm" novalidate>
                <div class="form-group">
                    <label>Login <span class="required">*</span></label>
                    <input type="text" id="login" name="login" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label>Hasło <span class="required">*</span></label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" onclick="togglePw('password')"
                            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.4rem;">
                            👁
                        </button>
                    </div>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        Zaloguj się
                    </button>
                </div>
            </form>

            <div class="divider">lub</div>

            <div style="text-align:center; display:flex; flex-direction:column; gap:0.6rem;">
                <a href="/pages/forgot_password.php"
                    style="color:var(--gold); font-size:1.05rem; text-decoration:none;">
                    Zapomniałem hasła
                </a>
                <a href="/pages/register.php" style="color:var(--text-muted); font-size:1.05rem; text-decoration:none;">
                    Nie masz konta?
                    <span style="color:var(--gold);">Zarejestruj się</span>
                </a>
            </div>
        </div>
    </div>

    <script src="/assets/js/alerts.js?v=<?= time() ?>"></script>
    <script src="/assets/js/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/api.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Logowanie…';

            try {
                const data = await apiPost('/api/login.php', {
                    login: document.getElementById('login').value.trim(),
                    password: document.getElementById('password').value
                });

                if (data.success) {
                    showAlert('success', 'Zalogowano! Przekierowuję…');
                    setTimeout(() => { window.location.href = data.redirect || '/pages/dashboard.php'; }, 600);
                } else {
                    showAlert('error', data.message);
                    btn.disabled = false;
                    btn.textContent = 'Zaloguj się';
                }
            } catch {
                showAlert('error', 'Błąd połączenia z serwerem.');
                btn.disabled = false;
                btn.textContent = 'Zaloguj się';
            }
        });
    </script>
</body>

</html>