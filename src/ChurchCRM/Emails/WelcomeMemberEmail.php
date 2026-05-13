<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\QrCodeService;
use ChurchCRM\Utils\LoggerUtils;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Welcome email sent to new members when they are added to ChurchCRM.
 *
 * Includes the member's personal attendance QR code as an inline image
 * inside the standard church email chrome (logo, footer).
 *
 * Enable via System Settings → New Members & Greeting → "Send Welcome Email".
 */
class WelcomeMemberEmail extends BaseEmail
{
    private Person $person;
    private string $checkInUrl;
    private ?string $qrCid = null;

    public function __construct(Person $person)
    {
        parent::__construct([$person->getEmail()]);

        $this->person     = $person;
        $this->checkInUrl = QrCodeService::getPersonCheckInUrl($person);

        // Try to attach the QR code PNG inline. Failures are non-fatal.
        try {
            $pngData     = QrCodeService::fetchQrCodePng($this->checkInUrl, 300);
            $this->qrCid = 'member_qr_' . $person->getId();
            $this->mail->addStringEmbeddedImage(
                $pngData,
                $this->qrCid,
                'attendance-qr.png',
                PHPMailer::ENCODING_BASE64,
                'image/png'
            );
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('WelcomeMemberEmail: QR code fetch failed — email will still be sent without image', [
                'person_id' => $person->getId(),
                'error'     => $e->getMessage(),
            ]);
        }

        $this->mail->Subject = $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        $firstName  = $this->person->getFirstName();
        $churchName = ChurchMetaData::getChurchName() ?: 'ChurchCRM';

        $qrBlock = $this->qrCid
            ? '<div style="text-align:center;margin:24px 0;">'
              . '<img src="cid:' . $this->qrCid . '" alt="Attendance QR Code" width="240" height="240" style="border:1px solid #e0e0e0;border-radius:8px;">'
              . '<p style="margin:8px 0 0;font-size:13px;color:#666;">Scan this QR code to check in to church events</p>'
              . '</div>'
            : '<div style="text-align:center;margin:24px 0;">'
              . '<a href="' . htmlspecialchars($this->checkInUrl, ENT_QUOTES) . '" style="color:#0d6efd;">View your check-in link</a>'
              . '</div>';

        $body = '<p>Hi ' . htmlspecialchars($firstName, ENT_QUOTES) . ',</p>'
            . '<p>Welcome to ' . htmlspecialchars($churchName, ENT_QUOTES) . '! '
            . 'We\'re so glad to have you with us.</p>'
            . '<p>Below is your personal attendance QR code. '
            . 'Scan it each week when you arrive so we can record your attendance.</p>'
            . $qrBlock
            . '<p>See you on Sunday!</p>';

        return array_merge($this->getCommonTokens(), [
            'toName'      => $firstName,
            'body'        => $body,
            'fullURL'     => $this->checkInUrl,
            'buttonText'  => 'Check In Online',
        ]);
    }

    protected function getSubSubject(): string
    {
        $custom = SystemConfig::getValue('sWelcomeEmailSubject');
        if (!empty($custom)) {
            return $custom;
        }
        $churchName = ChurchMetaData::getChurchName() ?: 'ChurchCRM';
        return "Welcome to {$churchName}!";
    }

    protected function getPreheader(): string
    {
        return 'Your personal attendance QR code is inside — scan it each Sunday when you arrive.';
    }

    protected function getFullURL(): string
    {
        return $this->checkInUrl;
    }

    protected function getButtonText(): string
    {
        return 'Check In Online';
    }

    /**
     * Sends the welcome email if the feature is enabled and the person has an email address.
     * Failures are logged but never thrown — a misconfigured SMTP must not block person creation.
     */
    public static function sendIfEnabled(Person $person): void
    {
        if (!SystemConfig::getBooleanValue('bSendWelcomeEmail')) {
            return;
        }
        if (empty($person->getEmail())) {
            LoggerUtils::getAppLogger()->debug('WelcomeMemberEmail: skipped — person has no email address', [
                'person_id' => $person->getId(),
            ]);
            return;
        }

        try {
            $email = new self($person);
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->warning('WelcomeMemberEmail: send failed', [
                    'person_id'    => $person->getId(),
                    'mailer_error' => $email->getError(),
                ]);
            } else {
                LoggerUtils::getAppLogger()->info('WelcomeMemberEmail: sent successfully', [
                    'person_id' => $person->getId(),
                    'email'     => $person->getEmail(),
                ]);
            }
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('WelcomeMemberEmail: exception during send', [
                'person_id' => $person->getId(),
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
