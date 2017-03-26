<?php

namespace App\Attendize\Repositories;

use App\Models\Event;

class EventRepository extends Repository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Event);
    }

    /**
     * @param Event $event
     * @param null $sortBy
     * @param string $sortOrder
     * @return mixed
     */
    public function getAttendees(Event $event, $sortBy = null, $sortOrder = self::DEFAULT_SORT_ORDER)
    {
        return (new AttendeeRepository())
            ->getAttendeesByEvent($event, $sortBy, $sortOrder);
    }

    /**
     * @param Event $event
     * @param $searchQuery
     * @param null $sortBy
     * @param string $sortOrder
     * @return mixed
     */
    public function getAttendeesByTerm(
        Event $event,
        $searchQuery,
        $sortBy = null,
        $sortOrder = self::DEFAULT_SORT_ORDER
    )
    {
        return (new AttendeeRepository())
            ->getAttendeesByEventTerm(
                $searchQuery,
                $event,
                $sortBy,
                $sortOrder
            );
    }
}