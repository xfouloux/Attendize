<?php

namespace App\Attendize\Repositories;

use App\Models\Attendee;

class AttendeeRepository extends Repository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Attendee);
    }

    public function getAttendeesByEvent($eventId, $sortBy = null, $sortOrder = self::DEFAULT_SORT_ORDER)
    {
        $attendees = $this->model
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->withoutCancelled()
            ->orderBy(($sortBy == 'order_reference' ? 'orders.' : 'attendees.') . $sortBy, $sortOrder)
            ->select('attendees.*', 'orders.order_reference')
            ->where('attendees.event_id', $eventId)
            ->paginate();

        return $attendees;
    }

    public function getAttendeesByEventTerm(
        $eventId,
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
            ->where('attendees.event_id', $eventId)
            ->paginate();

        return $attendees;
    }
}