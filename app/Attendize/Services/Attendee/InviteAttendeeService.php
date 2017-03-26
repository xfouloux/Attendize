<?php

namespace App\Attendize\Services\Attendee;

use App\Http\Requests\Request;
use App\Jobs\SendAttendeeInvite;
use App\Models\Attendee;
use App\Models\EventStats;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InviteAttendeeService
{
    /**
     * @param Request $request
     * @return bool
     */
    public function handle(Request $request)
    {
        $ticketId = $request->get('ticket_id');
        $ticketPrice = 0;
        $attendeeFirstName = $request->get('first_name');
        $eventId = $request->event_id;
        $attendeeLastName = $request->get('last_name');
        $attendeeEmail = $request->get('email');
        $sendEmailToAttendee = $request->get('email_ticket');

        DB::beginTransaction();

        try {
            $order = $this->createOrder($attendeeFirstName, $attendeeLastName, $attendeeEmail, $ticketPrice,
                $eventId);
            $ticket = Ticket::scope()->find($ticketId);

            $this->updateTicketStatistics($ticket, $ticketPrice);
            $this->createOrderItem($ticket, $order, $ticketPrice);
            $this->updateEventStatistics($eventId, $ticketId, $ticketPrice);

            $attendee = $this->createAttendee($attendeeFirstName, $attendeeLastName, $attendeeEmail, $eventId,
                $order, $ticketId);


            if ($sendEmailToAttendee == '1') {
                dispatch(new SendAttendeeInvite($attendee));
            }

            DB::commit();
            return true;
        } catch (Exception $e) {

            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    /**
     * @param $attendeeFirstName
     * @param $attendeeLastName
     * @param $attendeeEmail
     * @param $ticketPrice
     * @param $eventId
     * @return Order
     */
    private function createOrder($attendeeFirstName, $attendeeLastName, $attendeeEmail, $ticketPrice, $eventId)
    {
        $order = new Order();
        $order->save([
            'first_name' => $attendeeFirstName
        ]);
        $order->first_name = $attendeeFirstName;
        $order->last_name = $attendeeLastName;
        $order->email = $attendeeEmail;
        $order->order_status_id = 1;
        $order->amount = $ticketPrice;
        $order->account_id = Auth::user()->account_id;
        $order->event_id = $eventId;
        $order->save();
        return $order;
    }

    /**
     * @param $ticket
     * @param $ticketPrice
     */
    private function updateTicketStatistics($ticket, $ticketPrice)
    {
        $ticket->increment('quantity_sold');
        $ticket->increment('sales_volume', $ticketPrice);
        $ticket->event->increment('sales_volume', $ticketPrice);
    }

    /**
     * @param $ticket
     * @param $order
     * @param $ticketPrice
     */
    private function createOrderItem($ticket, $order, $ticketPrice)
    {
        $orderItem = new OrderItem();
        $orderItem->title = $ticket->title;
        $orderItem->quantity = 1;
        $orderItem->order_id = $order->id;
        $orderItem->unit_price = $ticketPrice;
        $orderItem->save();
    }

    /**
     * @param $eventId
     * @param $ticketId
     * @param $ticketPrice
     */
    private function updateEventStatistics($eventId, $ticketId, $ticketPrice)
    {
        $event_stats = new EventStats();
        $event_stats->updateTicketsSoldCount($eventId, 1);
        $event_stats->updateTicketRevenue($ticketId, $ticketPrice);
    }

    /**
     * @param $attendeeFirstName
     * @param $attendeeLastName
     * @param $attendeeEmail
     * @param $eventId
     * @param $order
     * @param $ticketId
     * @return Attendee
     */
    private function createAttendee($attendeeFirstName, $attendeeLastName, $attendeeEmail, $eventId, $order, $ticketId)
    {
        $attendee = new Attendee();
        $attendee->first_name = $attendeeFirstName;
        $attendee->last_name = $attendeeLastName;
        $attendee->email = $attendeeEmail;
        $attendee->event_id = $eventId;
        $attendee->order_id = $order->id;
        $attendee->ticket_id = $ticketId;
        $attendee->account_id = Auth::user()->account_id;
        $attendee->reference_index = 1;
        $attendee->save();
        return $attendee;
    }
}