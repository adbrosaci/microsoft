<?php declare(strict_types = 1);

namespace Adbros\Microsoft;

use Adbros\Microsoft\Exception\InvalidStateException;
use Adbros\Microsoft\Exception\NotFoundException;
use DateTimeImmutable;
use Microsoft\Graph\Generated\Models\Attendee;
use Microsoft\Graph\Generated\Models\AttendeeType;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;

class Client
{

	private GraphServiceClient $graphServiceClient;

	private string $tenantId;

	private string $clientId;

	private string $clientSecret;

	public function __construct(
		string $tenantId,
		string $clientId,
		string $clientSecret
	)
	{
		$this->tenantId = $tenantId;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
	}

	/**
	 * @param string[] $requiredAttendeesEmails
	 * @param string[] $optionalAttendeesEmails
	 * @param string[] $resourceAttendeesEmails
	 */
	public function createOrUpdateEvent(
		string $userId,
		string $subject,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		bool $allDay = false,
		?string $content = null,
		?string $eventId = null,
		array $requiredAttendeesEmails = [],
		array $optionalAttendeesEmails = [],
		array $resourceAttendeesEmails = [],
		bool $onlineMeeting = false
	): string
	{
		$event = $this->createEventModel(
			$subject,
			$start,
			$end,
			$allDay,
			$content,
			$requiredAttendeesEmails,
			$optionalAttendeesEmails,
			$resourceAttendeesEmails,
			$onlineMeeting
		);

		$calendar = $this->getGraphServiceClient()->usersById($userId)->calendar();

		try {
			/** @var Event $response */
			$response = $eventId !== null
				? $calendar->eventsById($eventId)->patch($event)->wait()
				: $calendar->events()->post($event)->wait();
		} catch (ODataError $e) {
			if ($e->getResponse() !== null && $e->getResponse()->getStatusCode() === 404) {
				throw new NotFoundException($e->getResponse()->getReasonPhrase(), 404);
			}

			throw $e;
		}

		if ($response->getId() === null) {
			throw new InvalidStateException('Event id is empty!', 500);
		}

		return $response->getId();
	}

	public function deleteEvent(
		string $userId,
		string $eventId
	): void
	{
		try {
			$this->getGraphServiceClient()->usersById($userId)->calendar()->eventsById($eventId)->delete()->wait();
		} catch (ODataError $e) {
			if ($e->getResponse() !== null && $e->getResponse()->getStatusCode() === 404) {
				throw new NotFoundException($e->getResponse()->getReasonPhrase(), 404);
			}

			throw $e;
		}
	}

	/**
	 * @param string[] $requiredAttendeesEmails
	 * @param string[] $optionalAttendeesEmails
	 * @param string[] $resourceAttendeesEmails
	 */
	private function createEventModel(
		string $subject,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		bool $allDay = false,
		?string $content = null,
		array $requiredAttendeesEmails = [],
		array $optionalAttendeesEmails = [],
		array $resourceAttendeesEmails = [],
		bool $onlineMeeting = false
	): Event
	{
		$event = new Event();
		$event->setSubject($subject);

		$body = new ItemBody();

		if ($content !== null) {
			$body->setContent($content);
		}

		$event->setBody($body);

		$attendees = [];

		foreach ($requiredAttendeesEmails as $email) {
			$attendees[] = $this->createAttendeeModel($email, AttendeeType::REQUIRED);
		}

		foreach ($optionalAttendeesEmails as $email) {
			$attendees[] = $this->createAttendeeModel($email, AttendeeType::OPTIONAL);
		}

		foreach ($resourceAttendeesEmails as $email) {
			$attendees[] = $this->createAttendeeModel($email, AttendeeType::RESOURCE);
		}

		$event->setAttendees($attendees);

		$event->setIsOnlineMeeting($onlineMeeting);

		$dateFormat = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i';

		$date = new DateTimeTimeZone();
		$date->setDateTime($start->format($dateFormat));
		$date->setTimeZone($start->format('e'));
		$event->setStart($date);

		$date = new DateTimeTimeZone();
		$date->setDateTime($end->format($dateFormat));
		$date->setTimeZone($end->format('e'));
		$event->setEnd($date);

		$event->setIsAllDay($allDay);

		return $event;
	}

	private function createAttendeeModel(string $email, string $type): Attendee
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new InvalidStateException(sprintf('Invalid email address "%s"!', $email), 500);
		}

		$attendeeEmail = new EmailAddress();
		$attendeeEmail->setAddress($email);

		$attendee = new Attendee();
		$attendee->setEmailAddress($attendeeEmail);

		$attendeeType = new AttendeeType($type);
		$attendee->setType($attendeeType);

		return $attendee;
	}

	private function getGraphServiceClient(): GraphServiceClient
	{
		if (!isset($this->graphServiceClient)) {
			$tokenRequestContext = new ClientCredentialContext(
				$this->tenantId,
				$this->clientId,
				$this->clientSecret,
			);

			$scopes = ['https://graph.microsoft.com/.default'];
			$authProvider = new PhpLeagueAuthenticationProvider($tokenRequestContext, $scopes);
			$requestAdapter = new GraphRequestAdapter($authProvider);

			$this->graphServiceClient = new GraphServiceClient($requestAdapter);
		}

		return $this->graphServiceClient;
	}

}
