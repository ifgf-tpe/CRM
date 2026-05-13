<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($churchName, ENT_QUOTES) ?> — Check In</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background: #f0f4f8; min-height: 100vh; }
        .checkin-card { max-width: 400px; border-radius: 1rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2.5rem; }
        .icon-success { background: #d1fae5; }
        .icon-error   { background: #fee2e2; }
        .icon-info    { background: #dbeafe; }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center p-3">

    <div class="checkin-card card w-100 p-4 p-sm-5 text-center">

        <h1 class="fs-4 fw-bold text-primary mb-4"><?= htmlspecialchars($churchName, ENT_QUOTES) ?></h1>

        <?php if ($status === 'success'): ?>
            <div class="icon-circle icon-success">✅</div>
            <h2 class="fs-3 fw-bold text-success mb-2">You're checked in!</h2>
            <?php if ($event !== null): ?>
                <p class="text-muted mb-1">
                    <strong><?= htmlspecialchars($event->getTitle(), ENT_QUOTES) ?></strong>
                </p>
                <p class="text-muted small mb-3">
                    <?= htmlspecialchars($event->getStartDate()->format('l, F j, Y'), ENT_QUOTES) ?>
                </p>
            <?php endif; ?>
            <p class="text-body"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>

        <?php elseif ($status === 'no_event'): ?>
            <div class="icon-circle icon-info">📅</div>
            <h2 class="fs-4 fw-bold text-info mb-2">No event today</h2>
            <p class="text-muted"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>

        <?php else: ?>
            <div class="icon-circle icon-error">❌</div>
            <h2 class="fs-4 fw-bold text-danger mb-2">Check-in failed</h2>
            <p class="text-muted"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
        <?php endif; ?>

        <a href="<?= htmlspecialchars($sRootPath . '/external/member-portal', ENT_QUOTES) ?>" class="btn btn-outline-primary mt-3">
            Back to Member Portal
        </a>
    </div>

</body>
</html>
