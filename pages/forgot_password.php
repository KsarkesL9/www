<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odzyskiwanie hasła – Edux</title>

    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme.js"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../includes/auth.php';
    redirectIfLoggedIn();
    ?>

    <div class="auth-wrapper">

        <!-- Brand header - powyżej karty -->
        <div class="brand-header">
            <a href="/" class="brand-logo">Edu<span>x</span></a>
            <p class="brand-tagline">Odzyskiwanie dostępu</p>
        </div>

        <!-- Karta formularza -->
        <div class="auth-card">

            <div id="alert" class="alert"></div>

            <!-- Step 1: Podaj login i email -->
            <div id="step1">
                <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:1.5rem; line-height:1.6;">
                    Podaj swój login i adres e-mail powiązany z kontem. Otrzymasz token do zresetowania hasła.
                </p>

                <form id="requestForm" novalidate>
                    <div class="form-group">
                        <label>Login <span class="required">*</span></label>
                        <input type="text" id="req_login" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>Adres e-mail <span class="required">*</span></label>
                        <input type="email" id="req_email" required autocomplete="email">
                    </div>
                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="reqBtn">
                            Wygeneruj token
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Token wygenerowany -->
            <div id="step2" style="display:none;">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <div style="font-size:2.5rem;">🔑</div>
                    <h3 style="font-family:'Playfair Display',serif; color:var(--gold); margin:0.5rem 0 0.25rem;">
                        Token wygenerowany
                    </h3>
                    <p style="color:var(--text-muted); font-size:0.85rem;">
                        Skopiuj token poniżej i użyj go na stronie resetowania hasła.
                    </p>
                </div>

                <div class="token-box show" id="tokenDisplay">—</div>

                <div style="background:rgba(248,113,113,0.08); border:1px solid rgba(248,113,113,0.2);
                        border-radius:8px; padding:0.75rem; margin:1rem 0; font-size:0.8rem;
                        color:var(--danger);">
                    ⚠️ Token jest ważny przez <strong>30 minut</strong>. Zachowaj go w bezpiecznym miejscu.
                </div>

                <button onclick="copyToken()" class="btn-ghost" style="width:100%; margin-bottom:0.75rem;">
                    📋 Skopiuj token
                </button>

                <a href="/pages/reset_password.php" class="btn-primary"
                    style="display:block; text-decoration:none; text-align:center; padding:0.75rem;">
                    Zresetuj hasło →
                </a>
            </div>

            <div style="text-align:center; margin-top:1.5rem;">
                <a href="/pages/login.php" style="color:var(--text-muted); font-size:0.85rem; text-decoration:none;">
                    ← Wróć do logowania
                </a>
            </div>
        </div>
    </div>

    <script>
        function showAlert(type, msg) {
            const el = document.getElementById('alert');
            el.className = 'alert alert-' + type + ' show';
            el.textContent = msg;
        }

        let generatedToken = '';

        document.getElementById('requestForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('reqBtn');
            const login = document.getElementById('req_login').value.trim();
            const email = document.getElementById('req_email').value.trim();

            if (!login || !email) { showAlert('error', 'Wypełnij oba pola.'); return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Generowanie…';

            try {
                const res = await fetch('/api/request_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ login, email_address: email })
                });
                const data = await res.json();

                if (data.success && data.token) {
                    generatedToken = data.token;
                    document.getElementById('tokenDisplay').textContent = data.token;
                    document.getElementById('step1').style.display = 'none';
                    document.getElementById('step2').style.display = 'block';
                    document.getElementById('alert').className = 'alert';
                } else {
                    // Even for "not found" we show generic success (security best practice)
                    showAlert('info', data.message);
                    btn.disabled = false;
                    btn.textContent = 'Wygeneruj token';
                }
            } catch {
                showAlert('error', 'Błąd połączenia z serwerem.');
                btn.disabled = false;
                btn.textContent = 'Wygeneruj token';
            }
        });

        async function copyToken() {
            try {
                await navigator.clipboard.writeText(generatedToken);
                const btn = event.target;
                btn.textContent = '✅ Skopiowano!';
                setTimeout(() => btn.textContent = '📋 Skopiuj token', 2000);
            } catch {
                alert('Skopiuj ręcznie: ' + generatedToken);
            }
        }
    </script>
</body>

</html>