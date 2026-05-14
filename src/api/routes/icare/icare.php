<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ICareMeetingQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\ICareService;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/icare', function (RouteCollectorProxy $group): void {

    /**
     * GET /icare/groups
     * Return all groups the current user belongs to (admins see all groups).
     */
    $group->get('/groups', function (Request $request, Response $response): Response {
        $service = new ICareService();
        $userId  = (int) AuthenticationManager::getCurrentUser()->getId();
        return SlimUtils::renderJSON($response, $service->getGroupsForUser($userId));
    });

    /**
     * GET /icare/groups/{groupId}/members
     * Members of a specific group (for the attendance checklist).
     */
    $group->get('/groups/{groupId:[0-9]+}/members', function (Request $request, Response $response, array $args): Response {
        $groupId = (int) $args['groupId'];
        $group   = GroupQuery::create()->findOneById($groupId);
        if ($group === null) {
            throw new HttpNotFoundException($request, gettext('Group not found'));
        }
        $service = new ICareService();
        return SlimUtils::renderJSON($response, $service->getGroupMembers($groupId));
    });

    /**
     * GET /icare/groups/{groupId}/meetings
     * Past meetings for a group (history list).
     */
    $group->get('/groups/{groupId:[0-9]+}/meetings', function (Request $request, Response $response, array $args): Response {
        $groupId = (int) $args['groupId'];
        $group   = GroupQuery::create()->findOneById($groupId);
        if ($group === null) {
            throw new HttpNotFoundException($request, gettext('Group not found'));
        }
        $service = new ICareService();
        return SlimUtils::renderJSON($response, $service->getMeetingsForGroup($groupId));
    });

    /**
     * POST /icare/groups/{groupId}/meeting
     * Create a meeting + batch attendance for a group.
     *
     * Body:
     * {
     *   "meeting_date": "2026-05-13",        // required
     *   "location":     "Living Room",        // optional
     *   "notes":        "...",                // optional
     *   "member_ids":   [1, 2, 3],            // existing person IDs
     *   "visitors": [                          // new walk-in visitors
     *     { "full_name": "John Doe", "phone": "0912...", "instagram": "@john", "address": "Taipei" }
     *   ]
     * }
     */
    $group->post('/groups/{groupId:[0-9]+}/meeting', function (Request $request, Response $response, array $args): Response {
        try {
            $groupId = (int) $args['groupId'];
            $group   = GroupQuery::create()->findOneById($groupId);
            if ($group === null) {
                throw new HttpNotFoundException($request, gettext('Group not found'));
            }

            $body = $request->getParsedBody() ?? [];

            if (empty($body['meeting_date'])) {
                return SlimUtils::renderErrorJSON($response, gettext('meeting_date is required'), [], 400);
            }

            // Sanitize visitor names
            $visitors = [];
            foreach ($body['visitors'] ?? [] as $v) {
                $name = InputUtils::sanitizeText((string) ($v['full_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $visitors[] = [
                    'full_name' => $name,
                    'phone'     => InputUtils::sanitizeText((string) ($v['phone'] ?? '')),
                    'instagram' => InputUtils::sanitizeText((string) ($v['instagram'] ?? '')),
                    'address'   => InputUtils::sanitizeText((string) ($v['address'] ?? '')),
                ];
            }

            $memberIds = array_map('intval', $body['member_ids'] ?? []);

            $service = new ICareService();
            $userId  = (int) AuthenticationManager::getCurrentUser()->getId();
            $meeting = $service->createMeeting($groupId, $userId, [
                'meeting_date' => InputUtils::sanitizeText((string) $body['meeting_date']),
                'location'     => InputUtils::sanitizeText((string) ($body['location'] ?? '')),
                'notes'        => InputUtils::sanitizeText((string) ($body['notes'] ?? '')),
                'member_ids'   => $memberIds,
                'visitors'     => $visitors,
            ]);

            return SlimUtils::renderJSON($response, ['meetingId' => $meeting->getId()], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to save attendance'), [], 500, $e, $request);
        }
    });

    /**
     * GET /icare/meeting/{meetingId}
     * Full detail for a meeting (members + visitors).
     */
    $group->get('/meeting/{meetingId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $meetingId = (int) $args['meetingId'];
        try {
            $service = new ICareService();
            return SlimUtils::renderJSON($response, $service->getMeetingDetail($meetingId));
        } catch (\InvalidArgumentException) {
            throw new HttpNotFoundException($request, gettext('Meeting not found'));
        }
    });

    /**
     * POST /icare/meeting/{meetingId}/photo
     * Upload (or replace) the group photo for a meeting.
     * Body: { "imgBase64": "data:image/jpeg;base64,..." }
     */
    $group->post('/meeting/{meetingId:[0-9]+}/photo', function (Request $request, Response $response, array $args): Response {
        try {
            $meetingId = (int) $args['meetingId'];
            $body      = $request->getParsedBody() ?? [];
            $base64    = (string) ($body['imgBase64'] ?? '');

            if ($base64 === '') {
                return SlimUtils::renderErrorJSON($response, gettext('imgBase64 is required'), [], 400);
            }

            $service  = new ICareService();
            $filename = $service->saveMeetingPhoto($meetingId, $base64);
            return SlimUtils::renderJSON($response, ['filename' => $filename]);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to save photo'), [], 500, $e, $request);
        }
    });

    /**
     * GET /icare/meeting/{meetingId}/photo
     * Stream the group photo for a meeting.
     */
    $group->get('/meeting/{meetingId:[0-9]+}/photo', function (Request $request, Response $response, array $args): Response {
        $meetingId = (int) $args['meetingId'];
        $service   = new ICareService();
        $path      = $service->getMeetingPhotoPath($meetingId);

        if ($path === null) {
            throw new HttpNotFoundException($request, gettext('Photo not found'));
        }

        $response = $response->withHeader('Content-Type', 'image/jpeg')
                              ->withHeader('Cache-Control', 'max-age=3600');
        $response->getBody()->write((string) file_get_contents($path));
        return $response;
    });

    /**
     * DELETE /icare/meeting/{meetingId}
     * Delete a meeting and all its attendance records (admin only).
     */
    $group->delete('/meeting/{meetingId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $meetingId = (int) $args['meetingId'];
        $service   = new ICareService();
        $service->deleteMeeting($meetingId);
        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * GET /icare/persons/search?q=<name>
     * Search existing church members by name (for the "visiting member" picker).
     * Returns lightweight results suitable for a dropdown.
     */
    $group->get('/persons/search', function (Request $request, Response $response): Response {
        $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
        if (strlen($q) < 2) {
            return SlimUtils::renderJSON($response, []);
        }

        $q       = InputUtils::sanitizeText($q);
        $persons = PersonQuery::create()
            ->filterByFirstName('%' . $q . '%', Criteria::LIKE)
            ->_or()
            ->filterByLastName('%' . $q . '%', Criteria::LIKE)
            ->orderByLastName()
            ->orderByFirstName()
            ->limit(20)
            ->find();

        $result = [];
        foreach ($persons as $p) {
            $result[] = [
                'id'        => (int) $p->getId(),
                'firstName' => $p->getFirstName(),
                'lastName'  => $p->getLastName(),
                'phone'     => (string) $p->getCellPhone(),
            ];
        }

        return SlimUtils::renderJSON($response, $result);
    });
})->add(EditRecordsRoleAuthMiddleware::class);
