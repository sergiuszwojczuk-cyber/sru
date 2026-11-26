<?php

function generateSlots(string $date, string $from, string $to, int $duration): array
{
    $slots = [];
    $current = new DateTimeImmutable($date . ' ' . substr($from, 0, 8));
    $end = new DateTimeImmutable($date . ' ' . substr($to, 0, 8));
    $now = new DateTimeImmutable('now');

    while ($current->modify('+' . $duration . ' minutes') <= $end) {
        if ($current >= $now) {
            $slots[] = $current->format('H:i:s');
        }
        $current = $current->modify('+30 minutes');
    }

    return $slots;
}

function hasAppointmentOverlap(DateTimeImmutable $start, DateTimeImmutable $end, array $appointments): bool
{
    foreach ($appointments as $appointment) {
        $busyStart = new DateTimeImmutable(
            $appointment['data_wizyty'] . ' ' . $appointment['godzina_wizyty']
        );
        $busyEnd = $busyStart->modify('+' . (int)$appointment['czas_trwania_minut'] . ' minutes');

        if ($start < $busyEnd && $end > $busyStart) {
            return true;
        }
    }

    return false;
}
