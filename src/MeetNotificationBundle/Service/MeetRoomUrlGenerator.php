<?php

namespace App\MeetNotificationBundle\Service;

use App\Entity\User;

class MeetRoomUrlGenerator
{
    public function __construct(
        private readonly string $jitsiMeetBaseUrl = 'https://meet.jit.si'
    ) {
    }

    public function generateUrgenceRoomUrl(User $patient, User $professional): string
    {
        $base = rtrim($this->jitsiMeetBaseUrl, '/');
        $room = sprintf(
            'chroniccare-urgence-%d-%d-%s',
            (int) $patient->getId(),
            (int) $professional->getId(),
            substr(bin2hex(random_bytes(5)), 0, 10)
        );

        return $base . '/' . $room;
    }
}
