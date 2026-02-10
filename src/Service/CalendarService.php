<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\RDVRepository;

class CalendarService
{
    public function __construct(private readonly RDVRepository $rdvRepository)
    {
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function getAvailableSlots(User $professionnel, ?\DateTimeInterface $start = null, int $days = 7, int $slotMinutes = 60): array
    {
        $slots = [];
        $current = $start ? \DateTimeImmutable::createFromInterface($start) : new \DateTimeImmutable('tomorrow');
        $endDate = $current->modify('+' . $days . ' days');

        while ($current <= $endDate) {
            if ((int) $current->format('N') <= 5) {
                $dayStart = $current->setTime(9, 0);
                $dayEnd = $current->setTime(17, 0);

                $slot = $dayStart;
                while ($slot <= $dayEnd) {
                    if (!$this->rdvRepository->isSlotTaken($professionnel->getId(), $slot)) {
                        $slots[] = $slot;
                    }
                    $slot = $slot->modify('+' . $slotMinutes . ' minutes');
                }
            }

            $current = $current->modify('+1 day');
        }

        return $slots;
    }
}
