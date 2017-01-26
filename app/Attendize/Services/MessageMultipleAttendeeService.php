<?php

namespace App\Attendize\Services;

use App\Http\Requests\Request;
use App\Jobs\SendMessageToAttendees;
use App\Models\Message;

class MessageMultipleAttendeeService
{
    public function make(Request $request, $eventId)
    {
        $message = Message::createNew();
        $message->message = $request->get('message');
        $message->subject = $request->get('subject');
        $message->recipients = ($request->get('recipients') == 'all') ? 'all' : $request->get('recipients');
        $message->event_id = $eventId;
        $message->save();

        dispatch(new SendMessageToAttendees($message));
    }
}