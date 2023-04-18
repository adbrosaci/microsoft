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
	 * @param array<string, AttendeeType::REQUIRED|AttendeeType::OPTIONAL|AttendeeType::RESOURCE> $attendeesMails
	 */
	public function createOrUpdateEvent(
		string $userId,
		string $subject,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		bool $allDay = false,
		?string $content = null,
		?string $eventId = null,
		array $attendeesMails = [],
		?string $locationId = null
	): string
	{
		$event = $this->createEventModel(
			$subject,
			$start,
			$end,
			$allDay,
			$content,
			$attendeesMails,
			$locationId
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
	 * @param array<string, AttendeeType::REQUIRED|AttendeeType::OPTIONAL|AttendeeType::RESOURCE> $attendeesMails
	 */
	private function createEventModel(
		string $subject,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		bool $allDay = false,
		?string $content = null,
		array $attendeesMails = [],
		?string $locationId = null
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

		foreach ($attendeesMails as $email => $type) {
			$attendeeEmail = new EmailAddress();
			$attendeeEmail->setAddress($email);

			$attendee = new Attendee();
			$attendee->setEmailAddress($attendeeEmail);

			$attendeeType = new AttendeeType($type);
			$attendee->setType($attendeeType);
			$attendees[] = $attendee;
		}

		if ($locationId !== null) {
			$locationEmail = new EmailAddress();
			$locationEmail->setAddress($locationId);

			$location = new Attendee();
			$location->setEmailAddress($locationEmail);

			$attendeeType = new AttendeeType(AttendeeType::RESOURCE);
			$location->setType($attendeeType);
			$attendees[] = $location;
		}

		$event->setAttendees($attendees);

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
