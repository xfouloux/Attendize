<?php

namespace App\Attendize\Repositories;

use App\Models\Event;

class EventRepository extends Repository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Event);
    }

    public function getAttendees($sortBy = null, $sortOrder = self::DEFAULT_SORT_ORDER)
    {
        return (new AttendeeRepository())
            ->getAttendeesByEvent($this->model->id, $sortBy, $sortOrder);
    }

    public function getAttendeesByTerm($searchQuery, $sortBy = null, $sortOrder = self::DEFAULT_SORT_ORDER)
    {
        return (new AttendeeRepository())
            ->getAttendeesByEventTerm(
                $searchQuery,
                $this->model->id,
                $sortBy,
                $sortOrder
            );
    }
}