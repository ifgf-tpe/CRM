<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-xl">
    <div class="row row-cards justify-content-center">
        <?php if (empty($groups)): ?>
        <div class="col-12 text-center py-5">
            <div class="empty">
                <div class="empty-icon"><i class="fa-solid fa-people-group fa-2x text-muted"></i></div>
                <p class="empty-title"><?= gettext('No iCare groups found') ?></p>
                <p class="empty-subtitle text-muted"><?= gettext('You are not currently assigned to any iCare group.') ?></p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($groups as $g): ?>
        <div class="col-12 col-sm-6 col-md-4">
            <a href="<?= InputUtils::escapeAttribute($sRootPath . '/v2/icare/groups/' . $g['id']) ?>"
               class="card card-link text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar avatar-lg rounded bg-primary text-white">
                        <i class="fa-solid fa-people-group"></i>
                    </span>
                    <div>
                        <div class="fw-bold fs-4"><?= InputUtils::escapeHTML($g['name']) ?></div>
                        <div class="text-muted small"><?= gettext('Tap to record attendance') ?></div>
                    </div>
                    <div class="ms-auto"><i class="fa-solid fa-chevron-right text-muted"></i></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
