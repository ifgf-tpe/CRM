<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($churchName, ENT_QUOTES) ?> — Member Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background: #f0f4f8; min-height: 100vh; }
        .portal-card { max-width: 480px; border-radius: 1rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .church-title { font-weight: 800; letter-spacing: -.5px; }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center p-3">

    <div class="portal-card card w-100 p-4 p-sm-5">

        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="church-title fs-3 text-primary"><?= htmlspecialchars($churchName, ENT_QUOTES) ?></h1>
            <p class="text-muted mb-0">Member Portal</p>
        </div>

        <!-- Action buttons -->
        <div class="d-grid gap-3 mb-4">
            <?php if ($bSelfReg): ?>
            <a href="<?= htmlspecialchars($selfRegUrl, ENT_QUOTES) ?>" class="btn btn-primary btn-lg fw-semibold">
                Register as a New Member
            </a>
            <?php endif; ?>

            <button id="showQrBtn" class="btn btn-outline-primary btn-lg fw-semibold">
                Get / Resend My Attendance QR Code
            </button>
        </div>

        <!-- QR code resend form (hidden by default) -->
        <div id="qrSection" class="d-none border-top pt-4">
            <p class="text-muted small text-center mb-3">
                Enter the email address you used when you registered. We'll email your personal QR code.
            </p>
            <form id="qrForm" novalidate>
                <div class="mb-3">
                    <label for="emailInput" class="visually-hidden">Email address</label>
                    <input id="emailInput" type="email" class="form-control form-control-lg text-center"
                           placeholder="your@email.com" autocomplete="email" required>
                </div>
                <div class="d-grid">
                    <button id="qrSubmitBtn" type="submit" class="btn btn-primary btn-lg fw-semibold">
                        Send My QR Code
                    </button>
                </div>
            </form>
            <div id="qrMessage" class="mt-3 text-center small fw-semibold" role="alert" aria-live="polite"></div>
        </div>

    </div>

    <!-- Social footer (customise links as needed) -->
    <footer class="text-center text-muted small mt-4 pb-3">
        <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($churchName, ENT_QUOTES) ?></p>
    </footer>

    <script>
    (function () {
        const showQrBtn  = document.getElementById('showQrBtn');
        const qrSection  = document.getElementById('qrSection');
        const qrForm     = document.getElementById('qrForm');
        const emailInput = document.getElementById('emailInput');
        const submitBtn  = document.getElementById('qrSubmitBtn');
        const msgArea    = document.getElementById('qrMessage');
        const rootPath   = <?= json_encode($sRootPath) ?>;

        showQrBtn.addEventListener('click', () => {
            qrSection.classList.toggle('d-none');
            if (!qrSection.classList.contains('d-none')) {
                emailInput.focus();
            }
        });

        qrForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = emailInput.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showMessage('Please enter a valid email address.', 'text-danger');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending…';
            msgArea.textContent  = '';

            try {
                const res  = await fetch(rootPath + '/external/member-portal/resend-qr', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email }),
                });
                const data = await res.json();
                showMessage(data.message || 'Done.', data.status === 'success' ? 'text-success' : 'text-danger');
                if (data.status === 'success') emailInput.value = '';
            } catch (_) {
                showMessage('Something went wrong. Please try again.', 'text-danger');
            } finally {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Send My QR Code';
            }
        });

        function showMessage(text, cls) {
            msgArea.className = 'mt-3 text-center small fw-semibold ' + cls;
            msgArea.textContent = text;
        }
    })();
    </script>
</body>
</html>
