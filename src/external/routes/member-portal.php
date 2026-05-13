<?php

/**
 * Member Self-Service Portal Routes
 *
 * GET  /external/member-portal        — Public HTML page (register, get QR code)
 * POST /external/member-portal/resend-qr — Email member their attendance QR code
 * GET  /external/checkin              — QR-based self-service check-in
 */

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\WelcomeMemberEmail;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\QrCodeService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// ─── Member Self-Service Portal HTML page ────────────────────────────────────

$app->get('/member-portal', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer('templates/');
    return $renderer->render($response, 'member-portal.php', [
        'sRootPath'   => SystemURLs::getRootPath(),
        'churchName'  => ChurchMetaData::getChurchName() ?: 'ChurchCRM',
        'selfRegUrl'  => SystemURLs::getRootPath() . '/external/register/',
        'bSelfReg'    => SystemConfig::getBooleanValue('bEnableSelfRegistration'),
    ]);
});

// ─── Resend QR code by email ──────────────────────────────────────────────────

$app->post('/member-portal/resend-qr', function (Request $request, Response $response): Response {
    $body  = $request->getParsedBody();
    $email = trim(InputUtils::filterString($body['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return SlimUtils::renderJSON($response, [
            'status'  => 'error',
            'message' => 'Please enter a valid email address.',
        ]);
    }

    $person = PersonQuery::create()
        ->filterByEmail($email)
        ->findOne();

    // Always return success to avoid email enumeration attacks.
    if ($person === null) {
        LoggerUtils::getAppLogger()->info('member-portal: resend-qr — email not found', ['email' => $email]);
        return SlimUtils::renderJSON($response, [
            'status'  => 'success',
            'message' => 'If that email is registered, you will receive your QR code shortly.',
        ]);
    }

    // Always send for an explicit member request, regardless of the global setting.
    try {
        $welcomeEmail = new WelcomeMemberEmail($person);
        $welcomeEmail->send();
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->error('member-portal: resend-qr send failed', [
            'person_id' => $person->getId(),
            'error'     => $e->getMessage(),
        ]);
    }

    LoggerUtils::getAppLogger()->info('member-portal: resend-qr — QR code sent', [
        'person_id' => $person->getId(),
        'email'     => $email,
    ]);

    return SlimUtils::renderJSON($response, [
        'status'  => 'success',
        'message' => 'If that email is registered, you will receive your QR code shortly.',
    ]);
});

// ─── QR Self-Service Check-In ─────────────────────────────────────────────────

$app->get('/checkin', function (Request $request, Response $response): Response {
    $params    = $request->getQueryParams();
    $personId  = (int) ($params['pid'] ?? 0);
    $token     = $params['token'] ?? '';

    $renderer = new PhpRenderer('templates/');

    $renderError = function (string $message) use ($renderer, $response): Response {
        return $renderer->render($response->withStatus(400), 'checkin.php', [
            'sRootPath'  => SystemURLs::getRootPath(),
            'churchName' => ChurchMetaData::getChurchName() ?: 'ChurchCRM',
            'status'     => 'error',
            'message'    => $message,
            'person'     => null,
            'event'      => null,
        ]);
    };

    // Validate token
    if ($personId <= 0 || empty($token) || !QrCodeService::verifyToken($personId, $token)) {
        LoggerUtils::getAppLogger()->warning('checkin: invalid or missing token', [
            'pid' => $personId,
        ]);
        return $renderError('Invalid check-in link. Please scan your personal QR code.');
    }

    // Load person
    $person = PersonQuery::create()->findPk($personId);
    if ($person === null) {
        return $renderError('Member not found. Please contact the church office.');
    }

    // Find the closest upcoming (or ongoing today) Church Service event
    $today = new \DateTime();
    $today->setTime(0, 0, 0);
    $tomorrow = (clone $today)->modify('+1 day');

    $event = EventQuery::create()
        ->filterByStartDate($today, Criteria::GREATER_EQUAL)
        ->filterByStartDate($tomorrow, Criteria::LESS_THAN)
        ->filterByInactive(0)
        ->orderByStartDate()
        ->findOne();

    if ($event === null) {
        // No event today — still show confirmation without recording attendance
        LoggerUtils::getAppLogger()->info('checkin: no event found today — showing confirmation without recording', [
            'person_id' => $personId,
        ]);
        return $renderer->render($response, 'checkin.php', [
            'sRootPath'  => SystemURLs::getRootPath(),
            'churchName' => ChurchMetaData::getChurchName() ?: 'ChurchCRM',
            'status'     => 'no_event',
            'message'    => 'No church event is scheduled for today. We look forward to seeing you next time!',
            'person'     => $person,
            'event'      => null,
        ]);
    }

    // Record attendance
    try {
        $event->checkInPerson($personId);
        LoggerUtils::getAppLogger()->info('checkin: attendance recorded', [
            'person_id' => $personId,
            'event_id'  => $event->getId(),
            'event'     => $event->getTitle(),
        ]);
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->error('checkin: failed to record attendance', [
            'person_id' => $personId,
            'error'     => $e->getMessage(),
        ]);
        return $renderError('Check-in failed. Please try again or see the welcome team.');
    }

    return $renderer->render($response, 'checkin.php', [
        'sRootPath'  => SystemURLs::getRootPath(),
        'churchName' => ChurchMetaData::getChurchName() ?: 'ChurchCRM',
        'status'     => 'success',
        'message'    => 'You\'re checked in! Welcome, ' . htmlspecialchars($person->getFirstName(), ENT_QUOTES) . '!',
        'person'     => $person,
        'event'      => $event,
    ]);
});
