<?php declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    throw new \Exception('This script supports command line usage only. Please check your command.');
}

if (file_exists(__DIR__ . '/config.php')) {
	require_once(__DIR__ . '/config.php');
} else {
    echo 'Please create demo/config.php file from demo/example.config.php file.' . PHP_EOL;
    return;
}

$client = new Adbros\Microsoft\Client(
    $tenantId,
    $clientId,
    $clientSecret
);

$client->createOrUpdateEvent($demoUserId, $demoSubject, $demoStart, $demoEnd, false, $demoDescription, null, $demoRequiredAttendeesEmails, $demoOptionalAttendeesEmails, $demoResourceAttendeesEmails);
echo 'Now you can find event in your DemoUser – ' . $demoUserId . ' calendar.' . PHP_EOL;

?>