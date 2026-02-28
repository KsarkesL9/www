<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nowe hasło – Edux</title>

    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme.js"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../includes/bootstrap.php';
    redirectIfLoggedIn();
    $tokenFromUrl = htmlspecialchars($_GET['token'] ?? '');
    ?>

    <div class="auth-wrapper">

        <!-- Brand header - Above the card -->
        <div class="brand-header">
            <a href="/" class="brand-logo">Edu<span>x</span></a>
            <p class="brand-tagline">Ustaw nowe hasło</p>
        </div>

        <!-- Form card -->
        <div class="auth-card">

            <div id="alert" class="alert"></div>

            <div id="formSection">
                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem; line-height:1.6;">
                    Wprowadź token resetowania hasła oraz ustaw nowe hasło dla swojego konta.
                    Wszystkie aktywne sesje zostaną natychmiast zamknięte.
                </p>

                <form id="resetForm" novalidate>
                    <div class="form-group">
                        <label>Token resetowania <span class="required">*</span></label>
                        <input type="text" id="token" value="<?= $tokenFromUrl ?>"
                            placeholder="Wklej token z poprzedniego kroku" required
                            style="font-family:'Courier New',monospace; font-size:0.82rem;">
                    </div>

                    <div class="form-group">
                        <label>Nowe hasło <span class="required">*</span> <span
                                style="font-size:0.72rem; color:var(--text-muted); text-transform:none; letter-spacing:0;">(min.
                                8 znaków)</span></label>
                        <div style="position:relative;">
                            <input type="password" id="new_password" required autocomplete="new-password"
                                oninput="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePw('new_password')"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">👁</button>
                        </div>
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="pwFill"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Potwierdź nowe hasło <span class="required">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="confirm_password" required autocomplete="new-password">
                            <button type="button" onclick="togglePw('confirm_password')"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">👁</button>
                        </div>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            Zmień hasło
                        </button>
                    </div>
                </form>
            </div>

            <!-- Success state -->
            <div id="successSection" style="display:none; text-align:center; padding:1rem 0;">
                <div style="font-size:3rem; margin-bottom:1rem;">✅</div>
                <h3 style="font-family:'Playfair Display',serif; color:var(--success); margin:0 0 0.75rem;">
                    Hasło zmienione!
                </h3>
                <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:1.5rem; line-height:1.6;">
                    Twoje hasło zostało zaktualizowane. Wszystkie poprzednie sesje zostały zamknięte.
                    Zaloguj się nowym hasłem.
                </p>
                <a href="/pages/login.php" class="btn-primary"
                    style="display:inline-block; text-decoration:none; padding:0.75rem 2.5rem; width:auto;">
                    Przejdź do logowania
                </a>
            </div>

            <div style="text-align:center; margin-top:1.5rem;" id="backLink">
                <a href="/pages/forgot_password.php"
                    style="color:var(--text-muted); font-size:0.85rem; text-decoration:none;">
                    Nie masz tokenu? Wygeneruj tutaj
                </a>
            </div>
        </div>
    </div>

    <script src="/assets/js/alerts.js?v=<?= time() ?>"></script>
    <script src="/assets/js/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/api.js?v=<?= time() ?>"></script>
    <script src="/assets/js/password.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('resetForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const token = document.getElementById('token').value.trim();
            const pw = document.getElementById('new_password').value;
            const cpw = document.getElementById('confirm_password').value;
            const btn = document.getElementById('submitBtn');

            if (!token) { showAlert('error', 'Wklej token resetowania hasła.'); return; }
            if (pw.length < 8) { showAlert('error', 'Hasło musi mieć minimum 8 znaków.'); return; }
            if (pw !== cpw) { showAlert('error', 'Hasła nie są identyczne.'); return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Zmienianie hasła…';

            try {
                const data = await apiPost('/api/reset_password.php', {
                    token, new_password: pw, confirm_password: cpw
                });

                if (data.success) {
                    document.getElementById('formSection').style.display = 'none';
                    document.getElementById('successSection').style.display = 'block';
                    document.getElementById('backLink').style.display = 'none';
                    clearAlert();
                } else {
                    showAlert('error', data.message);
                    btn.disabled = false;
                    btn.textContent = 'Zmień hasło';
                }
            } catch {
                showAlert('error', 'Błąd połączenia z serwerem.');
                btn.disabled = false;
                btn.textContent = 'Zmień hasło';
            }
        });
    </script>
</body>

</html>