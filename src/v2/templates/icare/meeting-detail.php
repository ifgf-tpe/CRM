<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$totalCount = count($meeting['members']) + count($meeting['visitors']);

?>

<div class="container-xl">

    <!-- Stats row -->
    <div class="row row-cards mb-3">
        <div class="col-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h2 mb-0 text-primary"><?= count($meeting['members']) ?></div>
                    <div class="text-muted small"><?= gettext('Members') ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h2 mb-0 text-warning"><?= count($meeting['visitors']) ?></div>
                    <div class="text-muted small"><?= gettext('Visitors') ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h2 mb-0"><?= $totalCount ?></div>
                    <div class="text-muted small"><?= gettext('Total') ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($meeting['location']) || !empty($meeting['notes'])): ?>
    <div class="card mb-3">
        <div class="card-body">
            <?php if (!empty($meeting['location'])): ?>
            <div class="mb-1"><i class="fa-solid fa-location-dot text-muted me-2"></i><?= InputUtils::escapeHTML($meeting['location']) ?></div>
            <?php endif; ?>
            <?php if (!empty($meeting['notes'])): ?>
            <div class="text-muted"><i class="fa-solid fa-note-sticky me-2"></i><?= InputUtils::escapeHTML($meeting['notes']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Group photo -->
    <?php if ($meeting['hasPhoto']): ?>
    <div class="card mb-3">
        <div class="card-body text-center p-2">
            <img src="<?= InputUtils::escapeAttribute($sRootPath . '/api/icare/meeting/' . $meeting['id'] . '/photo') ?>"
                 class="img-fluid rounded" style="max-height:350px"
                 alt="<?= gettext('Group photo') ?>">
        </div>
    </div>
    <?php endif; ?>

    <!-- Members list -->
    <?php if (!empty($meeting['members'])): ?>
    <div class="card mb-3">
        <div class="card-header fw-bold">
            <i class="fa-solid fa-people-group text-primary me-2"></i><?= gettext('Members') ?>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($meeting['members'] as $m): ?>
            <div class="list-group-item d-flex align-items-center gap-3">
                <i class="fa-solid fa-circle-check text-primary"></i>
                <span><?= InputUtils::escapeHTML($m['firstName'] . ' ' . $m['lastName']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Visitors list -->
    <?php if (!empty($meeting['visitors'])): ?>
    <div class="card mb-3">
        <div class="card-header fw-bold">
            <i class="fa-solid fa-user-tag text-warning me-2"></i><?= gettext('New Visitors') ?>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($meeting['visitors'] as $v): ?>
            <div class="list-group-item">
                <div class="fw-semibold"><?= InputUtils::escapeHTML($v['FullName']) ?></div>
                <div class="text-muted small d-flex flex-wrap gap-3 mt-1">
                    <?php if (!empty($v['Phone'])): ?>
                    <span><i class="fa-solid fa-phone me-1"></i><?= InputUtils::escapeHTML($v['Phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($v['Instagram'])): ?>
                    <span><i class="fa-brands fa-instagram me-1"></i><?= InputUtils::escapeHTML($v['Instagram']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($v['Address'])): ?>
                    <span><i class="fa-solid fa-location-dot me-1"></i><?= InputUtils::escapeHTML($v['Address']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
