<?php

namespace App\Attendize\Repositories;

use App\Models\Attendee;
use App\Models\Event;

class AttendeeRepository extends Repository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Attendee);
    }

    /**
     * @param Event $event
     * @param null $sortBy
     * @param string $sortOrder
     * @return mixed
     */
    public function getAttendeesByEvent(Event $event, $sortBy = null, $sortOrder = self::DEFAULT_SORT_ORDER)
    {
        $attendees = $this->model
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->withoutCancelled()
            ->orderBy(($sortBy == 'order_reference' ? 'orders.' : 'attendees.') . $sortBy, $sortOrder)
            ->select('attendees.*', 'orders.order_reference')
            ->where('attendees.event_id', $event->id)
            ->paginate();

        return $attendees;
    }

    /**
     * @param Event $event
     * @param $searchQuery
     * @param null $sortBy
     * @param string $sortOrder
     * @return mixed
     */
    public function getAttendeesByEventTerm(
        Event $event,
        $searchQuery,
        $sortBy = null,
        $sortOrder = self::DEFAULT_SORT_ORDER
    ) {
        $attendees = $this->model
            ->withoutCancelled()
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->where(function ($query) use ($searchQuery) {
                $query->where('orders.order_reference', 'like', $searchQuery . '%')
                    ->orWhere('attendees.first_name', 'like', $searchQuery . '%')
                    ->orWhere('attendees.email', 'like', $searchQuery . '%')
                    ->orWhere('attendees.last_name', 'like', $searchQuery . '%');
            })
            ->orderBy(($sortBy == 'order_reference' ? 'orders.' : 'attendees.') . $sortBy, $sortOrder)
            ->select('attendees.*', 'orders.order_reference')
            ->where('attendees.event_id', $event->id)
            ->paginate();

        return $attendees;
    }
}