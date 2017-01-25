<?php

namespace App\Attendize\Services;

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
    public function make(Request $request)
    {
        $ticket_id = $request->get('ticket_id');
        $ticket_price = 0;
        $attendee_first_name = $request->get('first_name');
        $event_id = $request->event_id;
        $attendee_last_name = $request->get('last_name');
        $attendee_email = $request->get('email');
        $email_attendee = $request->get('email_ticket');

        DB::beginTransaction();

        try {

            /*
             * Create the order
             */
            $order = new Order();
            $order->save([
                'first_name' => $attendee_first_name
            ]);
            $order->first_name = $attendee_first_name;
            $order->last_name = $attendee_last_name;
            $order->email = $attendee_email;
            $order->order_status_id = config('attendize.order_complete');
            $order->amount = $ticket_price;
            $order->account_id = Auth::user()->account_id;
            $order->event_id = $event_id;
            $order->save();

            /*
             * Update qty sold
             */
            $ticket = Ticket::scope()->find($ticket_id);
            $ticket->increment('quantity_sold');
            $ticket->increment('sales_volume', $ticket_price);
            $ticket->event->increment('sales_volume', $ticket_price);

            /*
             * Insert order item
             */
            $orderItem = new OrderItem();
            $orderItem->title = $ticket->title;
            $orderItem->quantity = 1;
            $orderItem->order_id = $order->id;
            $orderItem->unit_price = $ticket_price;
            $orderItem->save();

            /*
             * Update the event stats
             */
            $event_stats = new EventStats();
            $event_stats->updateTicketsSoldCount($event_id, 1);
            $event_stats->updateTicketRevenue($ticket_id, $ticket_price);

            /*
             * Create the attendee
             */
            $attendee = new Attendee();
            $attendee->first_name = $attendee_first_name;
            $attendee->last_name = $attendee_last_name;
            $attendee->email = $attendee_email;
            $attendee->event_id = $event_id;
            $attendee->order_id = $order->id;
            $attendee->ticket_id = $ticket_id;
            $attendee->account_id = Auth::user()->account_id;
            $attendee->reference_index = 1;
            $attendee->save();


            if ($email_attendee == '1') {
                dispatch(new SendAttendeeInvite($attendee));
            }

            DB::commit();

            return true;

        } catch (Exception $e) {

            Log::error($e);
            DB::rollBack();

            return false;
        }
    }
}