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
use Maatwebsite\Excel\Facades\Excel;

class ImportAttendeeService
{
    public function handle(Request $request)
    {
        $ticketId = $request->get('ticket_id');
        $sendEmailToAttendee = $request->get('email_ticket');
        $eventId = $request->event_id;
        $ticketPrice = 0;

        DB::beginTransaction();

        try {
            $fileRows = Excel::load($request->file('attendees_list')->getRealPath(), function ($reader) {
            })->get();

            foreach ($fileRows as $currentFileRow) {
                $this->handleAttendeeImport($currentFileRow, $ticketPrice, $eventId, $ticketId,
                    $sendEmailToAttendee);
            };
            DB::commit();
            return true;
        } catch (Exception $exception) {
            logger()->error($exception->getMessage());
            DB::rollback();
            return false;
        }
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
    private function createAttendee(
        $attendeeFirstName,
        $attendeeLastName,
        $attendeeEmail,
        $eventId,
        $order,
        $ticketId
    ) {
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

    /**
     * @param $eventId
     * @param $ticketId
     * @param $ticketPrice
     */
    private function updateEventStats($eventId, $ticketId, $ticketPrice)
    {
        $event_stats = new EventStats();
        $event_stats->updateTicketsSoldCount($eventId, 1);
        $event_stats->updateTicketRevenue($ticketId, $ticketPrice);
    }

    /**
     * @param Ticket $ticket
     * @param Order $order
     * @param $ticketPrice
     */
    private function createOrderItem(Ticket $ticket, Order $order, $ticketPrice)
    {
        $orderItem = new OrderItem();
        $orderItem->title = $ticket->title;
        $orderItem->quantity = 1;
        $orderItem->order_id = $order->id;
        $orderItem->unit_price = $ticketPrice;
        $orderItem->save();
    }

    /**
     * @param Ticket $ticket
     * @param $ticketPrice
     * @return Ticket
     */
    private function updateTicketsQuantitySold(Ticket $ticket, $ticketPrice)
    {
        $ticket->increment('quantity_sold');
        $ticket->increment('sales_volume', $ticketPrice);
        $ticket->event->increment('sales_volume', $ticketPrice);

        return $ticket;
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
        $order->first_name = $attendeeFirstName;
        $order->last_name = $attendeeLastName;
        $order->email = $attendeeEmail;
        $order->order_status_id = config('attendize.order_complete');
        $order->amount = $ticketPrice;
        $order->account_id = Auth::user()->account_id;
        $order->event_id = $eventId;
        $order->save();

        return $order;
    }

    /**
     * @param $csvFileRow
     * @param $ticketPrice
     * @param $eventId
     * @param $ticketId
     * @param $sendEmailToAttendee
     */
    private function handleAttendeeImport($csvFileRow, $ticketPrice, $eventId, $ticketId, $sendEmailToAttendee)
    {
        if ($this->isRowDataValid($csvFileRow)) {
            $attendeeFirstName = $csvFileRow['first_name'];
            $attendeeLastName = $csvFileRow['last_name'];
            $attendeeEmail = $csvFileRow['email'];

            $order = $this->createOrder(
                $attendeeFirstName,
                $attendeeLastName,
                $attendeeEmail,
                $ticketPrice,
                $eventId
            );

            $ticket = Ticket::scope()->find($ticketId);

            $this->updateTicketsQuantitySold($ticket, $ticketPrice);

            $attendee = $this->createAttendee(
                $attendeeFirstName,
                $attendeeLastName,
                $attendeeEmail,
                $eventId,
                $order,
                $ticketId
            );

            $this->createOrderItem($ticket, $order, $ticketPrice);
            $this->updateEventStats($eventId, $ticketId, $ticketPrice);

            if ($sendEmailToAttendee == '1') {
                dispatch(new SendAttendeeInvite($attendee));
            }
        }
    }

    /**
     * @param array $csvFileRow
     * @return bool
     */
    private function isRowDataValid($csvFileRow)
    {
        return !empty($csvFileRow->first_name) && !empty($csvFileRow->last_name) && !empty($csvFileRow->email);
    }
}