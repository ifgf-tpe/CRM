<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ICareMeetingQuery;
use ChurchCRM\Service\ICareService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * GET /v2/icare
 * List all iCare groups the current user belongs to.
 * Redirects directly to the attendance page if the user is in exactly one group.
 */
$app->get('/icare', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer('templates/icare/');
    $service  = new ICareService();
    $userId   = (int) AuthenticationManager::getCurrentUser()->getId();
    $groups   = $service->getGroupsForUser($userId);

    if (count($groups) === 1) {
        return $response
            ->withStatus(302)
            ->withHeader('Location', SystemURLs::getRootPath() . '/v2/icare/groups/' . $groups[0]['id']);
    }

    return $renderer->render($response, 'groups.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('iCare'),
        'sPageSubtitle'      => gettext('Select your cell group'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Dashboard'), '/v2/dashboard'],
            [gettext('iCare')],
        ]),
        'groups' => $groups,
    ]);
});

/**
 * GET /v2/icare/groups/{groupId}
 * Attendance form for today's meeting.
 */
$app->get('/icare/groups/{groupId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $renderer = new PhpRenderer('templates/icare/');
    $groupId  = (int) $args['groupId'];

    $group = GroupQuery::create()->findOneById($groupId);
    if ($group === null) {
        return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/icare');
    }

    $service = new ICareService();
    $members = $service->getGroupMembers($groupId);

    return $renderer->render($response, 'attendance.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => $group->getName(),
        'sPageSubtitle'      => gettext('Record attendance'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Dashboard'), '/v2/dashboard'],
            [gettext('iCare'), '/v2/icare'],
            [$group->getName()],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Meeting History'), 'url' => '/v2/icare/groups/' . $groupId . '/history', 'icon' => 'fa-clock-rotate-left'],
        ]),
        'groupId'   => $groupId,
        'groupName' => $group->getName(),
        'members'   => $members,
        'today'     => date('Y-m-d'),
    ]);
});

/**
 * GET /v2/icare/groups/{groupId}/history
 * Past meetings list for a group.
 */
$app->get('/icare/groups/{groupId:[0-9]+}/history', function (Request $request, Response $response, array $args): Response {
    $renderer = new PhpRenderer('templates/icare/');
    $groupId  = (int) $args['groupId'];

    $group = GroupQuery::create()->findOneById($groupId);
    if ($group === null) {
        return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/icare');
    }

    $service  = new ICareService();
    $meetings = $service->getMeetingsForGroup($groupId, 52);

    return $renderer->render($response, 'history.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => $group->getName() . ' — ' . gettext('History'),
        'sPageSubtitle'      => gettext('Past iCare meetings'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Dashboard'), '/v2/dashboard'],
            [gettext('iCare'), '/v2/icare'],
            [$group->getName(), '/v2/icare/groups/' . $groupId],
            [gettext('History')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Record Attendance'), 'url' => '/v2/icare/groups/' . $groupId, 'icon' => 'fa-circle-check'],
        ]),
        'groupId'   => $groupId,
        'groupName' => $group->getName(),
        'meetings'  => $meetings,
    ]);
});

/**
 * GET /v2/icare/meeting/{meetingId}
 * Detail view for a past meeting.
 */
$app->get('/icare/meeting/{meetingId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $renderer  = new PhpRenderer('templates/icare/');
    $meetingId = (int) $args['meetingId'];

    $service = new ICareService();
    try {
        $detail = $service->getMeetingDetail($meetingId);
    } catch (\InvalidArgumentException) {
        return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/icare');
    }

    $group = GroupQuery::create()->findOneById($detail['groupId']);

    return $renderer->render($response, 'meeting-detail.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => ($group ? $group->getName() : gettext('iCare')) . ' — ' . $detail['meetingDate'],
        'sPageSubtitle' => gettext('Meeting details'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Dashboard'), '/v2/dashboard'],
            [gettext('iCare'), '/v2/icare'],
            [$group ? $group->getName() : '', '/v2/icare/groups/' . $detail['groupId']],
            [gettext('History'), '/v2/icare/groups/' . $detail['groupId'] . '/history'],
            [$detail['meetingDate']],
        ]),
        'meeting' => $detail,
        'group'   => $group,
    ]);
});
