<?php
$tenantId = 'fill your tenant id';
$clientId = 'fill your client id';
$clientSecret = 'fill your client secret';

$demoUserId = 'fill your user id – eq. email';
$demoSubject = 'fill your subject';
$demoDescription = 'fill your description, or null';
$demoStart = (new \DateTimeImmutable())->modify('+5 minutes');
$demoEnd = $demoStart->modify('+10 minutes');
$demoRequiredAttendeesEmails = ['fill required attendee email'];
$demoOptionalAttendeesEmails = ['fill optional attendee email'];
$demoResourceAttendeesEmails = ['fill resource email, eq. room/location/tools'];
?>