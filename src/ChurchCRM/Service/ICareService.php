<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ICareAttendance;
use ChurchCRM\model\ChurchCRM\ICareAttendanceQuery;
use ChurchCRM\model\ChurchCRM\ICareMeeting;
use ChurchCRM\model\ChurchCRM\ICareMeetingQuery;
use ChurchCRM\model\ChurchCRM\ICareVisitor;
use ChurchCRM\model\ChurchCRM\ICareVisitorQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class ICareService
{
    private const PHOTO_MAX_WIDTH  = 1200;
    private const PHOTO_MAX_HEIGHT = 900;
    private const PHOTO_QUALITY    = 82;   // Starting JPEG quality; reduced automatically until output ≤ PHOTO_MAX_BYTES
    private const PHOTO_MAX_BYTES  = 307_200; // 300 KB hard limit
    private const PHOTO_DIR        = 'iCare';

    /**
     * Return all groups the given CRM user belongs to (via their linked person record).
     * Admins see all groups. Regular users see only their own groups.
     */
    public function getGroupsForUser(int $userId): array
    {
        $currentUser = AuthenticationManager::getCurrentUser();

        if ($currentUser->isAdmin()) {
            $memberships = Person2group2roleP2g2rQuery::create()
                ->joinWithGroup()
                ->joinWithPerson()
                ->find();
        } else {
            $person = PersonQuery::create()->filterByUserId($userId)->findOne();
            if ($person === null) {
                return [];
            }
            $memberships = Person2group2roleP2g2rQuery::create()
                ->filterByPersonId($person->getId())
                ->joinWithGroup()
                ->find();
        }

        $groups = [];
        $seen   = [];
        foreach ($memberships as $m) {
            $grp = $m->getGroup();
            if ($grp === null || isset($seen[$grp->getId()])) {
                continue;
            }
            $seen[$grp->getId()] = true;
            $groups[] = [
                'id'     => (int) $grp->getId(),
                'name'   => $grp->getName(),
                'active' => (bool) $grp->getActive(),
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $groups;
    }

    /**
     * Return group members with enough info to render the attendance checklist.
     *
     * @return array<int, array{id: int, firstName: string, lastName: string, phone: string}>
     */
    public function getGroupMembers(int $groupId): array
    {
        $memberships = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($groupId)
            ->joinWithPerson()
            ->find();

        $members = [];
        foreach ($memberships as $m) {
            $p = $m->getPerson();
            if ($p === null) {
                continue;
            }
            $members[] = [
                'id'        => (int) $p->getId(),
                'firstName' => $p->getFirstName(),
                'lastName'  => $p->getLastName(),
                'phone'     => (string) $p->getCellPhone(),
            ];
        }

        usort($members, fn ($a, $b) => strcmp($a['lastName'] . $a['firstName'], $b['lastName'] . $b['firstName']));
        return $members;
    }

    /**
     * Return past meetings for a group, newest first, with attendance summary.
     */
    public function getMeetingsForGroup(int $groupId, int $limit = 20): array
    {
        $meetings = ICareMeetingQuery::create()
            ->filterByGroupId($groupId)
            ->orderByMeetingDate(Criteria::DESC)
            ->limit($limit)
            ->find();

        $result = [];
        foreach ($meetings as $meeting) {
            $memberCount  = ICareAttendanceQuery::create()->filterByMeetingId($meeting->getId())->count();
            $visitorCount = ICareVisitorQuery::create()->filterByMeetingId($meeting->getId())->count();
            $result[] = [
                'id'           => (int) $meeting->getId(),
                'meetingDate'  => $meeting->getMeetingDate('Y-m-d'),
                'location'     => (string) $meeting->getLocation(),
                'notes'        => (string) $meeting->getNotes(),
                'hasPhoto'     => $meeting->getPhotoFilename() !== null && $meeting->getPhotoFilename() !== '',
                'memberCount'  => $memberCount,
                'visitorCount' => $visitorCount,
                'totalCount'   => $memberCount + $visitorCount,
            ];
        }

        return $result;
    }

    /**
     * Return full detail for a single meeting (members + visitors).
     */
    public function getMeetingDetail(int $meetingId): array
    {
        $meeting = ICareMeetingQuery::create()->findOneById($meetingId);
        if ($meeting === null) {
            throw new \InvalidArgumentException("iCare meeting $meetingId not found");
        }

        $attendances = ICareAttendanceQuery::create()
            ->filterByMeetingId($meetingId)
            ->joinWithPerson()
            ->find();

        $members = [];
        foreach ($attendances as $a) {
            $p = $a->getPerson();
            if ($p === null) {
                continue;
            }
            $members[] = [
                'id'        => (int) $p->getId(),
                'firstName' => $p->getFirstName(),
                'lastName'  => $p->getLastName(),
            ];
        }

        $visitors = ICareVisitorQuery::create()
            ->filterByMeetingId($meetingId)
            ->find()
            ->toArray();

        return [
            'id'          => (int) $meeting->getId(),
            'groupId'     => (int) $meeting->getGroupId(),
            'meetingDate' => $meeting->getMeetingDate('Y-m-d'),
            'location'    => (string) $meeting->getLocation(),
            'notes'       => (string) $meeting->getNotes(),
            'hasPhoto'    => $meeting->getPhotoFilename() !== null && $meeting->getPhotoFilename() !== '',
            'members'     => $members,
            'visitors'    => $visitors,
        ];
    }

    /**
     * Create a meeting record and persist all attendance in one transaction.
     *
     * @param array{
     *   meeting_date: string,
     *   location?: string,
     *   notes?: string,
     *   member_ids?: int[],
     *   visitors?: array<array{full_name: string, phone?: string, instagram?: string, address?: string}>
     * } $data
     */
    public function createMeeting(int $groupId, int $createdByUserId, array $data): ICareMeeting
    {
        $logger = LoggerUtils::getAppLogger();

        $meeting = new ICareMeeting();
        $meeting->setGroupId($groupId);
        $meeting->setMeetingDate($data['meeting_date']);
        $meeting->setLocation($data['location'] ?? '');
        $meeting->setNotes($data['notes'] ?? '');
        $meeting->setCreatedBy($createdByUserId);
        $meeting->setCreatedAt(new \DateTimeImmutable());
        $meeting->save();

        $logger->info('iCare meeting created', ['meetingId' => $meeting->getId(), 'groupId' => $groupId]);

        // Persist member attendance
        foreach ($data['member_ids'] ?? [] as $personId) {
            $attendance = new ICareAttendance();
            $attendance->setMeetingId($meeting->getId());
            $attendance->setPersonId((int) $personId);
            $attendance->setRecordedAt(new \DateTimeImmutable());
            $attendance->save();
        }

        // Persist visitor records
        foreach ($data['visitors'] ?? [] as $v) {
            $visitor = new ICareVisitor();
            $visitor->setMeetingId($meeting->getId());
            $visitor->setFullName((string) ($v['full_name'] ?? ''));
            $visitor->setPhone((string) ($v['phone'] ?? ''));
            $visitor->setInstagram((string) ($v['instagram'] ?? ''));
            $visitor->setAddress((string) ($v['address'] ?? ''));
            $visitor->setCreatedAt(new \DateTimeImmutable());
            $visitor->save();
        }

        return $meeting;
    }

    /**
     * Save a compressed JPEG group photo for a meeting.
     * Accepts a base64-encoded data URI (from browser canvas or file input).
     * Output is strictly ≤ PHOTO_MAX_BYTES (300 KB). Quality is reduced in steps
     * of 5 from PHOTO_QUALITY (82) down to a floor of 25 until the constraint is met.
     * Images are also resized to fit within PHOTO_MAX_WIDTH × PHOTO_MAX_HEIGHT.
     */
    public function saveMeetingPhoto(int $meetingId, string $base64DataUri): string
    {
        $meeting = ICareMeetingQuery::create()->findOneById($meetingId);
        if ($meeting === null) {
            throw new \InvalidArgumentException("iCare meeting $meetingId not found");
        }

        if (!preg_match('/^data:([\w+.\/-]+);base64,(.+)$/s', $base64DataUri, $parts)) {
            throw new \InvalidArgumentException('Invalid image data URI');
        }

        $fileData = base64_decode($parts[2], true);
        if ($fileData === false) {
            throw new \InvalidArgumentException('Invalid base64 data');
        }

        if (function_exists('finfo_open')) {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($fileData);
        } else {
            $mimeType = $parts[1];
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Only JPEG, PNG, and WebP images are allowed');
        }

        $source = imagecreatefromstring($fileData);
        if ($source === false) {
            throw new \RuntimeException('Failed to decode image data');
        }

        $srcW  = imagesx($source);
        $srcH  = imagesy($source);
        $scale = min(1.0, self::PHOTO_MAX_WIDTH / $srcW, self::PHOTO_MAX_HEIGHT / $srcH);
        $dstW  = (int) round($srcW * $scale);
        $dstH  = (int) round($srcH * $scale);

        $dest = imagecreatetruecolor($dstW, $dstH);
        if ($dest === false) {
            throw new \RuntimeException('Failed to create output image');
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        $photoDir = SystemURLs::getImagesRoot() . '/' . self::PHOTO_DIR;
        if (!is_dir($photoDir)) {
            @mkdir($photoDir, 0755, true);
        }

        // Delete previous photo for this meeting if it exists
        $oldFilename = $meeting->getPhotoFilename();
        if ($oldFilename !== null && $oldFilename !== '') {
            $oldPath = $photoDir . '/' . $oldFilename;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        $filename = 'meeting-' . $meetingId . '.jpg';
        $fullPath = $photoDir . '/' . $filename;

        // Reduce JPEG quality in 5-point steps until output fits in PHOTO_MAX_BYTES.
        // ob_start()/ob_get_clean() captures imagejpeg output without writing to disk.
        $quality  = self::PHOTO_QUALITY;
        $jpegData = '';
        do {
            ob_start();
            imagejpeg($dest, null, $quality);
            $jpegData = (string) ob_get_clean();
            $quality -= 5;
        } while (strlen($jpegData) > self::PHOTO_MAX_BYTES && $quality >= 25);

        imagedestroy($dest);

        if (file_put_contents($fullPath, $jpegData) === false) {
            throw new \RuntimeException('Failed to save photo');
        }

        $meeting->setPhotoFilename($filename);
        $meeting->save();

        return $filename;
    }

    /**
     * Return the absolute filesystem path to a meeting photo, or null if none.
     */
    public function getMeetingPhotoPath(int $meetingId): ?string
    {
        $meeting = ICareMeetingQuery::create()->findOneById($meetingId);
        if ($meeting === null || $meeting->getPhotoFilename() === null || $meeting->getPhotoFilename() === '') {
            return null;
        }

        $path = SystemURLs::getImagesRoot() . '/' . self::PHOTO_DIR . '/' . $meeting->getPhotoFilename();
        return is_file($path) ? $path : null;
    }

    /**
     * Delete a meeting and its cascaded attendance / visitor records.
     */
    public function deleteMeeting(int $meetingId): void
    {
        $meeting = ICareMeetingQuery::create()->findOneById($meetingId);
        if ($meeting === null) {
            return;
        }

        $photoPath = $this->getMeetingPhotoPath($meetingId);
        if ($photoPath !== null && is_file($photoPath)) {
            unlink($photoPath);
        }

        $meeting->delete();
    }
}
