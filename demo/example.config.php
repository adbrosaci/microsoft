<?php
$tenantId = 'fill your tenant id';
$clientId = 'fill your client id';
$clientSecret = 'fill your client secret';

$demoUserId = 'fill your user id – eq. email';
$demoSubject = 'fill your subject';
$demoDescription = 'fill your description, or null';
$demoStart = (new \DateTimeImmutable())->modify('+5 minutes');
$demoEnd = $demoStart->modify('+10 minutes');
$demoAttendees = ['fill your attendee email' => Microsoft\Graph\Generated\Models\AttendeeType::OPTIONAL, 'fill your attendee2 email' => Microsoft\Graph\Generated\Models\AttendeeType::REQUIRED, 'fill your attendee3 email' => Microsoft\Graph\Generated\Models\AttendeeType::REQUIRED, 'fill your attendeeN email' => Microsoft\Graph\Generated\Models\AttendeeType::REQUIRED];
$demoLocation = 'fill location email';
?>