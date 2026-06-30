<?php

declare(strict_types=1);

/**
 * Runnable demo for the signed ICS calendar feed.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/SecretStore.php';
require __DIR__ . '/../src/CalendarTokenSigner.php';
require __DIR__ . '/../src/IcsFeedBuilder.php';

use Portfolio\IcsCalendarFeed\CalendarTokenSigner;
use Portfolio\IcsCalendarFeed\IcsFeedBuilder;
use Portfolio\IcsCalendarFeed\InMemorySecretStore;

$store  = new InMemorySecretStore();
$signer = new CalendarTokenSigner($store);

$userId = 42;

echo "== Token issuance & validation ==\n";
$token = $signer->issueToken($userId);
echo "Issued token : {$token}\n";
echo "Validates to : " . var_export($signer->validateToken($token), true) . " (expected 42)\n";

echo "\n== Tampered token is rejected ==\n";
$tampered = $token . 'x';
echo "Tampered validates to: " . var_export($signer->validateToken($tampered), true) . " (expected NULL)\n";

echo "\n== Forged id without the secret is rejected ==\n";
$forged = rtrim(strtr(base64_encode('99'), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode('not-a-real-signature'), '+/', '-_'), '=');
echo "Forged validates to: " . var_export($signer->validateToken($forged), true) . " (expected NULL)\n";

echo "\n== Rotating the secret revokes the old token ==\n";
$signer->rotateSecret($userId);
echo "Old token now validates to: " . var_export($signer->validateToken($token), true) . " (expected NULL)\n";
$newToken = $signer->issueToken($userId);
echo "New token validates to    : " . var_export($signer->validateToken($newToken), true) . " (expected 42)\n";

echo "\n== Sample ICS feed ==\n";
$tz     = new DateTimeZone('Europe/Amsterdam');
$events = [
    [
        'uid'         => 'evt-1001@example.com',
        'summary'     => 'Intake meeting; client A',
        'start'       => new DateTimeImmutable('2026-07-01 09:30', $tz),
        'end'         => new DateTimeImmutable('2026-07-01 10:15', $tz),
        'location'    => 'Room 2, Main office',
        'description' => 'First appointment.',
    ],
    [
        'uid'     => 'evt-1002@example.com',
        'summary' => 'Team sync',
        'start'   => new DateTimeImmutable('2026-07-01 14:00', $tz),
        'end'     => new DateTimeImmutable('2026-07-01 14:30', $tz),
    ],
];

echo (new IcsFeedBuilder('Europe/Amsterdam', 'Agenda of user 42'))->build($events);
