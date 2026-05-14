<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-xl" id="icare-attendance-app">

    <!-- Meeting date + location bar -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold"><?= gettext('Meeting Date') ?></label>
                    <input type="date" id="meetingDate" class="form-control form-control-lg"
                           value="<?= InputUtils::escapeAttribute($today) ?>" max="<?= InputUtils::escapeAttribute($today) ?>">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold"><?= gettext('Location') ?> <span class="text-muted fw-normal">(<?= gettext('optional') ?>)</span></label>
                    <input type="text" id="meetingLocation" class="form-control form-control-lg"
                           placeholder="<?= gettext('e.g. Living Room, Café') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold"><?= gettext('Notes') ?> <span class="text-muted fw-normal">(<?= gettext('optional') ?>)</span></label>
                    <textarea id="meetingNotes" class="form-control" rows="2"
                              placeholder="<?= gettext('Sermon topic, prayer points, etc.') ?>"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Section 1: Group Members ── -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-people-group text-primary"></i>
            <span class="fw-bold"><?= gettext('Group Members') ?></span>
            <span class="badge bg-primary ms-auto" id="memberCheckCount">0</span>
        </div>
        <div class="list-group list-group-flush" id="memberList">
            <?php foreach ($members as $m): ?>
            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 member-item"
                   data-id="<?= (int) $m['id'] ?>">
                <input class="form-check-input flex-shrink-0 member-checkbox" type="checkbox"
                       value="<?= (int) $m['id'] ?>">
                <span class="flex-grow-1">
                    <span class="fw-semibold"><?= InputUtils::escapeHTML($m['firstName'] . ' ' . $m['lastName']) ?></span>
                    <?php if (!empty($m['phone'])): ?>
                    <br><small class="text-muted"><?= InputUtils::escapeHTML($m['phone']) ?></small>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
            <div class="list-group-item text-muted text-center py-3"><?= gettext('No members assigned to this group yet.') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Section 2: Visiting Members ── -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-user-plus text-success"></i>
            <span class="fw-bold"><?= gettext('Visiting Members') ?></span>
            <span class="text-muted small ms-1"><?= gettext('(from other iCare groups)') ?></span>
            <span class="badge bg-success ms-auto" id="visitingCheckCount">0</span>
        </div>
        <div class="card-body pb-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="memberSearch" class="form-control"
                       placeholder="<?= gettext('Search by name…') ?>" autocomplete="off">
            </div>
            <div id="memberSearchResults" class="list-group mt-2 d-none"></div>
        </div>
        <div class="list-group list-group-flush" id="visitingList">
            <!-- Visiting members added here dynamically -->
        </div>
    </div>

    <!-- ── Section 3: New Visitors ── -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-user-tag text-warning"></i>
            <span class="fw-bold"><?= gettext('New Visitors') ?></span>
            <span class="text-muted small ms-1"><?= gettext('(first time / not yet in system)') ?></span>
            <span class="badge bg-warning ms-auto" id="visitorCount">0</span>
        </div>
        <div class="list-group list-group-flush" id="visitorList">
            <!-- New visitor cards added here dynamically -->
        </div>
        <div class="card-footer">
            <button type="button" class="btn btn-outline-warning w-100" id="addVisitorBtn">
                <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New Visitor') ?>
            </button>
        </div>
    </div>

    <!-- ── Photo Upload ── -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-camera text-secondary"></i>
            <span class="fw-bold"><?= gettext('Group Photo') ?></span>
            <span class="text-muted small ms-1"><?= gettext('(optional)') ?></span>
        </div>
        <div class="card-body text-center">
            <div id="photoPreviewWrap" class="d-none mb-3">
                <img id="photoPreview" src="" alt="" class="img-fluid rounded" style="max-height:300px">
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="removePhotoBtn">
                        <i class="fa-solid fa-trash me-1"></i><?= gettext('Remove') ?>
                    </button>
                </div>
            </div>
            <label for="photoInput" class="btn btn-outline-secondary" id="photoLabel">
                <i class="fa-solid fa-camera me-1"></i><?= gettext('Take / Upload Photo') ?>
            </label>
            <input type="file" id="photoInput" class="d-none" accept="image/*" capture="environment">
        </div>
    </div>

    <!-- ── Save Button ── -->
    <div class="sticky-bottom pb-3 pt-2 bg-body">
        <button type="button" class="btn btn-primary btn-lg w-100" id="saveAttendanceBtn">
            <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Save Attendance') ?>
        </button>
        <div id="saveStatus" class="mt-2 text-center d-none"></div>
    </div>

</div><!-- /.container-xl -->

<!-- New Visitor Modal -->
<div class="modal fade" id="newVisitorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Add New Visitor') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= gettext('Full Name') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="visitorName" class="form-control" placeholder="<?= gettext('Full name') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= gettext('WhatsApp / Phone') ?></label>
                    <input type="tel" id="visitorPhone" class="form-control" placeholder="+886...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= gettext('Instagram') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-brands fa-instagram"></i></span>
                        <input type="text" id="visitorInstagram" class="form-control" placeholder="@username">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= gettext('Area / Domicile') ?></label>
                    <input type="text" id="visitorAddress" class="form-control" placeholder="<?= gettext('e.g. Taipei, Zhongli') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="confirmAddVisitor"><?= gettext('Add') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.ICARE = {
    groupId:  <?= (int) $groupId ?>,
    rootPath: <?= json_encode($sRootPath) ?>,
};
</script>
<script src="<?= $sRootPath ?>/skin/v2/icare-attendance.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
