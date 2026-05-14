<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-xl">

    <?php if (empty($meetings)): ?>
    <div class="empty py-5">
        <div class="empty-icon"><i class="fa-regular fa-calendar-xmark fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No meetings recorded yet') ?></p>
        <p class="empty-subtitle text-muted"><?= gettext('Start recording attendance to build your history.') ?></p>
        <a href="<?= InputUtils::escapeAttribute($sRootPath . '/v2/icare/groups/' . $groupId) ?>"
           class="btn btn-primary mt-2">
            <i class="fa-solid fa-circle-check me-1"></i><?= gettext('Record Attendance') ?>
        </a>
    </div>
    <?php else: ?>
    <div class="row row-cards">
        <?php foreach ($meetings as $meeting): ?>
        <div class="col-12 col-sm-6 col-md-4">
            <a href="<?= InputUtils::escapeAttribute($sRootPath . '/v2/icare/meeting/' . $meeting['id']) ?>"
               class="card card-link text-decoration-none h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="avatar bg-primary-lt text-primary rounded">
                            <i class="fa-solid fa-calendar-day"></i>
                        </span>
                        <div>
                            <div class="fw-bold"><?= InputUtils::escapeHTML($meeting['meetingDate']) ?></div>
                            <?php if (!empty($meeting['location'])): ?>
                            <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= InputUtils::escapeHTML($meeting['location']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($meeting['hasPhoto']): ?>
                        <span class="ms-auto badge bg-secondary-lt text-secondary"><i class="fa-solid fa-camera"></i></span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-3 mt-3">
                        <div class="text-center">
                            <div class="h3 mb-0 text-primary"><?= (int) $meeting['memberCount'] ?></div>
                            <div class="text-muted small"><?= gettext('Members') ?></div>
                        </div>
                        <?php if ($meeting['visitorCount'] > 0): ?>
                        <div class="text-center">
                            <div class="h3 mb-0 text-warning"><?= (int) $meeting['visitorCount'] ?></div>
                            <div class="text-muted small"><?= gettext('Visitors') ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="text-center ms-auto">
                            <div class="h3 mb-0"><?= (int) $meeting['totalCount'] ?></div>
                            <div class="text-muted small"><?= gettext('Total') ?></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
