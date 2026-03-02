<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\RDVRepository;

class CalendarService
{
    public function __construct(
        private readonly RDVRepository $rdvRepository,
        private readonly HolidayService $holidayService
    )
    {
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function getAvailableSlots(User $professionnel, ?\DateTimeInterface $start = null, int $days = 7, int $slotMinutes = 30): array
    {
        $slots = [];
        $current = $start ? \DateTimeImmutable::createFromInterface($start) : new \DateTimeImmutable('today');
        $endDate = $current->modify('+' . $days . ' days');

        while ($current <= $endDate) {
            $isWeekday = (int) $current->format('N') <= 5;
            $isHoliday = $this->holidayService->isHoliday($current);
            if ($isWeekday && !$isHoliday) {
                $dayStart = $current->setTime(8, 0);
                $dayEnd = $current->setTime(17, 0);

                $slot = $dayStart;
                while ($slot < $dayEnd) {
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

    /**
     * @return \DateTimeImmutable[]
     */
    public function getAvailableSlotsForDate(User $professionnel, \DateTimeInterface $date, int $slotMinutes = 30): array
    {
        $day = \DateTimeImmutable::createFromInterface($date);
        $isWeekday = (int) $day->format('N') <= 5;
        if (!$isWeekday || $this->holidayService->isHoliday($day)) {
            return [];
        }

        $dayStart = $day->setTime(8, 0);
        $dayEnd = $day->setTime(17, 0);
        $slot = $dayStart;
        $slots = [];
        while ($slot < $dayEnd) {
            if (!$this->rdvRepository->isSlotTaken($professionnel->getId(), $slot)) {
                $slots[] = $slot;
            }
            $slot = $slot->modify('+' . $slotMinutes . ' minutes');
        }

        return $slots;
    }
}
