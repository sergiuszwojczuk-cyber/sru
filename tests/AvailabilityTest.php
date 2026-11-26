<?php

require_once __DIR__ . '/../src/models/Availability.php';

$assertions = 0;
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
};

$date = '2099-01-02';
$assertSame(
    ['08:00:00', '08:30:00', '09:00:00', '09:30:00'],
    generateSlots($date, '08:00:00', '10:00:00', 30),
    'Half-hour appointments should fit inside the availability window.'
);
$assertSame(
    ['08:00:00', '08:30:00', '09:00:00'],
    generateSlots($date, '08:00:00', '10:00:00', 60),
    'Generated slots must account for the service duration.'
);
$assertSame(
    [],
    generateSlots('2000-01-01', '08:00:00', '10:00:00', 30),
    'Slots in the past must not be offered.'
);

$appointment = [
    'data_wizyty' => $date,
    'godzina_wizyty' => '09:30:00',
    'czas_trwania_minut' => 60,
];
$assertSame(
    true,
    hasAppointmentOverlap(
        new DateTimeImmutable($date . ' 10:00:00'),
        new DateTimeImmutable($date . ' 10:30:00'),
        [$appointment]
    ),
    'Partially overlapping appointments must conflict.'
);
$assertSame(
    false,
    hasAppointmentOverlap(
        new DateTimeImmutable($date . ' 10:30:00'),
        new DateTimeImmutable($date . ' 11:00:00'),
        [$appointment]
    ),
    'Back-to-back appointments must not conflict.'
);
$assertSame(
    false,
    hasAppointmentOverlap(
        new DateTimeImmutable('2099-01-03 10:00:00'),
        new DateTimeImmutable('2099-01-03 10:30:00'),
        [$appointment]
    ),
    'Appointments on different dates must not conflict.'
);

echo "Availability tests passed ($assertions assertions).\n";
