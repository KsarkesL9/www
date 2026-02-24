<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja – Edux</title>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <script src="/assets/js/theme.js?v=<?= time() ?>"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../includes/bootstrap.php';
    redirectIfLoggedIn();
    ?>

    <div class="auth-wrapper">

        <!-- Brand header - Above the card -->
        <div class="brand-header">
            <a href="/" class="brand-logo">Edu<span>x</span></a>
            <p class="brand-tagline">Utwórz nowe konto</p>
        </div>

        <!-- Form card -->
        <div class="auth-card auth-card-wide">

            <!-- Step indicator -->
            <div class="steps" id="steps">
                <div class="step-item active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Rola</div>
                </div>
                <div class="step-item" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Dane osobowe</div>
                </div>
                <div class="step-item" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Adres</div>
                </div>
                <div class="step-item" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Hasło</div>
                </div>
            </div>

            <div id="alert" class="alert"></div>

            <form id="registerForm" novalidate>

                <!-- ===== STEP 1: Role ===== -->
                <div class="step-panel" id="panel-1">
                    <div class="form-group">
                        <label>Rola w systemie <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select id="role_id" name="role_id" required>
                                <option value="">— wybierz rolę —</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:1.5rem; display:flex; justify-content:flex-end; gap:0.75rem;">
                        <button type="button" class="btn-primary" style="width:auto; padding:0.65rem 2rem;"
                            onclick="nextStep(1)">
                            Dalej →
                        </button>
                    </div>
                </div>

                <!-- ===== STEP 2: Personal data ===== -->
                <div class="step-panel" id="panel-2" style="display:none;">
                    <div class="grid-3">
                        <div class="form-group">
                            <label>Imię <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Drugie imię</label>
                            <input type="text" id="second_name" name="second_name">
                        </div>
                        <div class="form-group">
                            <label>Nazwisko <span class="required">*</span></label>
                            <input type="text" id="surname" name="surname" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Data urodzenia <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="text" id="date_of_birth_display" class="datepicker-display" readonly
                                    autocomplete="off" style="padding-right:3.2rem; cursor:pointer;"
                                    onclick="openDatePicker()">
                                <button type="button" onclick="openDatePicker()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                           background:none;border:none;cursor:pointer;color:var(--gold);
                                           display:flex;align-items:center;padding:0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                </button>
                            </div>
                            <input type="hidden" id="date_of_birth" name="date_of_birth" required>
                        </div>
                        <div class="form-group">
                            <label>Obywatelstwo <span class="required">*</span></label>
                            <div class="select-wrap">
                                <select id="country_id" name="country_id" required>
                                    <option value="">— ładowanie… —</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Adres e-mail <span class="required">*</span></label>
                            <input type="email" id="email_address" name="email_address" required autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label>Numer telefonu</label>
                            <input type="tel" id="phone_number" name="phone_number">
                        </div>
                    </div>

                    <div style="margin-top:1rem; display:flex; justify-content:space-between; gap:0.75rem;">
                        <button type="button" class="btn-ghost" onclick="prevStep(2)">← Wróć</button>
                        <button type="button" class="btn-primary" style="width:auto; padding:0.65rem 2rem;"
                            onclick="nextStep(2)">Dalej →</button>
                    </div>
                </div>

                <!-- ===== STEP 3: Address ===== -->
                <div class="step-panel" id="panel-3" style="display:none;">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Miasto <span class="required">*</span></label>
                            <input type="text" id="city" name="city" required>
                        </div>
                        <div class="form-group">
                            <label>Ulica <span class="required">*</span></label>
                            <input type="text" id="street" name="street" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Nr budynku <span class="required">*</span></label>
                            <input type="text" id="building_number" name="building_number" required>
                        </div>
                        <div class="form-group">
                            <label>Nr mieszkania</label>
                            <input type="text" id="apartment_number" name="apartment_number">
                        </div>
                    </div>

                    <div style="margin-top:1rem; display:flex; justify-content:space-between; gap:0.75rem;">
                        <button type="button" class="btn-ghost" onclick="prevStep(3)">← Wróć</button>
                        <button type="button" class="btn-primary" style="width:auto; padding:0.65rem 2rem;"
                            onclick="nextStep(3)">Dalej →</button>
                    </div>
                </div>

                <!-- ===== STEP 4: Password ===== -->
                <div class="step-panel" id="panel-4" style="display:none;">
                    <div class="form-group">
                        <label>Hasło <span class="required">*</span> <span
                                style="font-size:0.72rem; color:var(--text-muted); text-transform:none; letter-spacing:0;">(min.
                                8 znaków)</span></label>
                        <div style="position:relative;">
                            <input type="password" id="password" name="password" required autocomplete="new-password"
                                oninput="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePw('password')"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">👁</button>
                        </div>
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="pwFill"></div>
                        </div>
                        <div id="pwLabel" style="font-size:0.72rem; color:var(--text-muted); margin-top:3px;"></div>
                    </div>

                    <div class="form-group">
                        <label>Potwierdź hasło <span class="required">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="password_confirm" name="password_confirm" required
                                autocomplete="new-password">
                            <button type="button" onclick="togglePw('password_confirm')"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">👁</button>
                        </div>
                    </div>

                    <!-- Summary info box -->
                    <div style="background:rgba(255,255,255,0.03); border:1px solid var(--navy-border);
                            border-radius:10px; padding:1rem; margin-bottom:1.2rem; font-size:0.82rem;">
                        <div
                            style="color:var(--text-muted); margin-bottom:0.5rem; font-weight:500; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.06em;">
                            Podsumowanie konta
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.3rem 1rem;">
                            <div><span style="color:var(--text-muted);">Imię: </span><span id="sum-name">—</span></div>
                            <div><span style="color:var(--text-muted);">E-mail: </span><span id="sum-email">—</span>
                            </div>
                            <div><span style="color:var(--text-muted);">Rola: </span><span id="sum-role">—</span></div>
                            <div><span style="color:var(--text-muted);">Data ur.: </span><span id="sum-dob">—</span>
                            </div>
                        </div>
                        <div style="margin-top:0.5rem; color:var(--text-muted); font-size:0.75rem;">
                            Login zostanie wygenerowany automatycznie po rejestracji.
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; gap:0.75rem;">
                        <button type="button" class="btn-ghost" onclick="prevStep(4)">← Wróć</button>
                        <button type="submit" class="btn-primary" style="width:auto; padding:0.65rem 2rem;"
                            id="submitBtn">
                            Zarejestruj się
                        </button>
                    </div>
                </div>

            </form>

            <div style="text-align:center; margin-top:1.5rem;">
                <a href="/pages/login.php" style="color:var(--text-muted); font-size:1rem; text-decoration:none;">
                    Masz już konto? <span style="color:var(--gold);">Zaloguj się</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ===== Custom Date Picker Modal ===== -->
    <div class="datepicker-overlay" id="datepickerOverlay" onclick="overlayClick(event)">
        <div class="datepicker-modal">
            <div class="datepicker-header">
                <button class="datepicker-nav" onclick="dpChangeMonth(-1)">&#8249;</button>
                <div class="datepicker-month-year">
                    <select class="datepicker-select" id="dpMonthSelect" onchange="dpMonthChanged()"></select>
                    <select class="datepicker-select" id="dpYearSelect" onchange="dpYearChanged()"></select>
                </div>
                <button class="datepicker-nav" onclick="dpChangeMonth(1)">&#8250;</button>
            </div>
            <div class="datepicker-weekdays">
                <div class="datepicker-weekday">Pn</div>
                <div class="datepicker-weekday">Wt</div>
                <div class="datepicker-weekday">Śr</div>
                <div class="datepicker-weekday">Cz</div>
                <div class="datepicker-weekday">Pt</div>
                <div class="datepicker-weekday">So</div>
                <div class="datepicker-weekday">Nd</div>
            </div>
            <div class="datepicker-grid" id="dpGrid"></div>
            <div class="datepicker-footer">
                <button class="datepicker-btn-cancel" onclick="closeDatePicker()">Anuluj</button>
                <button class="datepicker-btn-confirm" id="dpConfirmBtn" onclick="confirmDate()"
                    disabled>Zatwierdź</button>
            </div>
        </div>
    </div>

    <script src="/assets/js/datepicker.js?v=<?= time() ?>"></script>

    <!-- Success modal -->
    <div id="successModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6);
     z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
        <div style="background:var(--navy-card); border:1px solid var(--navy-border); border-radius:16px;
                padding:2.5rem 2rem; max-width:420px; width:90%; text-align:center;
                animation:slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);">
            <h2 style="font-family:'Playfair Display',serif; color:var(--gold); margin:0 0 0.5rem;">
                Konto utworzone!
            </h2>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.2rem;">
                Twój wygenerowany login to:
            </p>
            <div style="background:rgba(233,184,74,0.1); border:1px solid rgba(233,184,74,0.3);
                    border-radius:8px; padding:0.75rem; font-size:1.1rem; font-weight:600;
                    color:var(--gold); letter-spacing:0.05em; margin-bottom:1.5rem;" id="generatedLogin">—</div>
            <p style="color:var(--text-muted); font-size:0.8rem; margin-bottom:1.5rem;">
                Zapisz ten login – będzie potrzebny do logowania.
            </p>
            <a href="/pages/login.php" class="btn-primary"
                style="display:inline-block; text-decoration:none; padding:0.75rem 2rem; width:auto;">
                Przejdź do logowania
            </a>
        </div>
    </div>

    <script src="/assets/js/alerts.js?v=<?= time() ?>"></script>
    <script src="/assets/js/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/api.js?v=<?= time() ?>"></script>
    <script src="/assets/js/password.js?v=<?= time() ?>"></script>
    <script>
        // ---- State ----
        let roles = [];
        let currentStep = 1;

        // ---- Init ----
        (async function init() {
            // Load roles from server
            roles = <?php
            $stmt = getDB()->query('SELECT role_id, role_name FROM roles ORDER BY role_name');
            echo json_encode($stmt->fetchAll());
            ?>;

            const sel = document.getElementById('role_id');
            sel.innerHTML = '<option value="">— wybierz rolę —</option>';
            roles.forEach(r => {
                sel.innerHTML += `<option value="${r.role_id}">${capitalize(r.role_name)}</option>`;
            });

            // Load countries
            try {
                const res = await fetch('/api/get_countries.php');
                const data = await res.json();
                const csel = document.getElementById('country_id');
                csel.innerHTML = '<option value="">— wybierz kraj —</option>';
                data.countries.forEach(c => {
                    const selected = c.name === 'Polska' ? ' selected' : '';
                    csel.innerHTML += `<option value="${c.country_id}"${selected}>${c.name}</option>`;
                });
            } catch {
                document.getElementById('country_id').innerHTML = '<option value="">Błąd ładowania</option>';
            }
        })();

        function capitalize(s) {
            return s.charAt(0).toUpperCase() + s.slice(1);
        }

        // ---- Step navigation ----
        function nextStep(from) {
            if (!validateStep(from)) return;
            goToStep(from + 1);
        }

        function prevStep(from) {
            goToStep(from - 1);
        }

        function goToStep(n) {
            document.querySelectorAll('.step-panel').forEach(p => p.style.display = 'none');
            document.getElementById('panel-' + n).style.display = 'block';

            document.querySelectorAll('.step-item').forEach(item => {
                const s = parseInt(item.dataset.step);
                item.classList.remove('active', 'done');
                if (s < n) item.classList.add('done');
                if (s === n) item.classList.add('active');
            });

            if (n === 4) updateSummary();
            currentStep = n;
            clearAlert();
        }

        function validateStep(step) {
            clearAlert();
            if (step === 1) {
                if (!document.getElementById('role_id').value) {
                    showAlert('error', 'Wybierz rolę w systemie.');
                    return false;
                }
            }
            if (step === 2) {
                const fields = ['first_name', 'surname', 'email_address', 'date_of_birth', 'country_id'];
                for (const f of fields) {
                    if (!document.getElementById(f).value.trim()) {
                        showAlert('error', 'Wypełnij wszystkie wymagane pola.');
                        document.getElementById(f).focus();
                        return false;
                    }
                }
                const email = document.getElementById('email_address').value.trim();
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showAlert('error', 'Podaj prawidłowy adres e-mail.');
                    return false;
                }
            }
            if (step === 3) {
                const fields = ['city', 'street', 'building_number'];
                for (const f of fields) {
                    if (!document.getElementById(f).value.trim()) {
                        showAlert('error', 'Wypełnij wszystkie wymagane pola adresu.');
                        document.getElementById(f).focus();
                        return false;
                    }
                }
            }
            return true;
        }

        function updateSummary() {
            const roleEl = document.getElementById('role_id');
            const roleName = roleEl.options[roleEl.selectedIndex]?.text || '—';
            document.getElementById('sum-name').textContent =
                (document.getElementById('first_name').value + ' ' +
                    document.getElementById('surname').value).trim() || '—';
            document.getElementById('sum-email').textContent =
                document.getElementById('email_address').value || '—';
            document.getElementById('sum-role').textContent = roleName;
            document.getElementById('sum-dob').textContent =
                document.getElementById('date_of_birth').value || '—';
        }

        // ---- Submit ----
        document.getElementById('registerForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const pw = document.getElementById('password').value;
            const cpw = document.getElementById('password_confirm').value;
            if (pw.length < 8) { showAlert('error', 'Hasło musi mieć minimum 8 znaków.'); return; }
            if (pw !== cpw) { showAlert('error', 'Hasła nie są identyczne.'); return; }

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Rejestracja…';

            const payload = {
                role_id: document.getElementById('role_id').value,
                first_name: document.getElementById('first_name').value.trim(),
                second_name: document.getElementById('second_name').value.trim(),
                surname: document.getElementById('surname').value.trim(),
                email_address: document.getElementById('email_address').value.trim(),
                password: pw,
                password_confirm: cpw,
                date_of_birth: document.getElementById('date_of_birth').value,
                country_id: document.getElementById('country_id').value,
                city: document.getElementById('city').value.trim(),
                street: document.getElementById('street').value.trim(),
                building_number: document.getElementById('building_number').value.trim(),
                apartment_number: document.getElementById('apartment_number').value.trim(),
                phone_number: document.getElementById('phone_number').value.trim(),
            };

            try {
                const data = await apiPost('/api/register.php', payload);

                if (data.success) {
                    document.getElementById('generatedLogin').textContent = data.login;
                    const modal = document.getElementById('successModal');
                    modal.style.display = 'flex';
                } else {
                    showAlert('error', data.message);
                    btn.disabled = false;
                    btn.textContent = 'Zarejestruj się';
                }
            } catch {
                showAlert('error', 'Błąd połączenia z serwerem.');
                btn.disabled = false;
                btn.textContent = 'Zarejestruj się';
            }
        });
    </script>
</body>

</html>