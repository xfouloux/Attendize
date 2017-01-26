<?php

namespace App\Http\Controllers;

use App\Attendize\Repositories\AttendeeRepository;
use App\Attendize\Repositories\EventRepository;
use App\Attendize\Requests\ImportAttendeeRequest;
use App\Attendize\Requests\InviteAttendeeRequest;
use App\Attendize\Requests\MessageAttendeeRequest;
use App\Attendize\Requests\MessageMultipleAttendeeRequest;
use App\Attendize\Services\ImportAttendeeService;
use App\Attendize\Services\InviteAttendeeService;
use App\Attendize\Services\MessageAttendeeService;
use App\Attendize\Services\MessageMultipleAttendeeService;
use App\Jobs\GenerateTicket;
use App\Jobs\SendAttendeeTicket;
use App\Jobs\SendMessageToAttendees;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventStats;
use App\Models\Message;
use Auth;
use Config;
use DB;
use Excel;
use Illuminate\Http\Request;
use Log;
use Mail;
use Omnipay\Omnipay;
use PDF;
use Validator;

class EventAttendeesController extends MyBaseController
{
    protected $attendeeRepository;
    protected $eventRepository;
    protected $attendeeService;

    public function __construct(
        AttendeeRepository $attendeeRepository,
        EventRepository $eventRepository
    ) {
        $this->attendeeRepository = $attendeeRepository;
        $this->eventRepository = $eventRepository;

        parent::__construct();
    }

    /**
     * Show the attendees list
     *
     * @param Request $request
     * @param $event_id
     * @return View
     */
    public function showAttendees(Request $request, $event_id)
    {
        $allowed_sorts = ['first_name', 'email', 'ticket_id', 'order_reference'];

        $searchQuery = $request->get('q');
        $sort_order = $request->get('sort_order') == 'asc' ? 'asc' : 'desc';
        $sort_by = (in_array($request->get('sort_by'), $allowed_sorts) ? $request->get('sort_by') : 'created_at');

        $event = $this->eventRepository->find($event_id);

        if ($searchQuery) {
            $attendees = $this->eventRepository->getAttendeesByTerm($searchQuery, $sort_by, $sort_order);
        } else {
            $attendees = $this->eventRepository->getAttendees($sort_by, $sort_order);
        }

        $data = [
            'attendees' => $attendees,
            'event' => $event,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'q' => $searchQuery ? $searchQuery : '',
        ];

        return view('ManageEvent.Attendees', $data);
    }

    /**
     * Show the 'Invite Attendee' modal
     *
     * @param Request $request
     * @param $eventId
     * @return string|View
     */
    public function showInviteAttendee(Request $request, $eventId)
    {
        $event = $this->eventRepository->find($eventId);

        /*
         * If there are no tickets then we can't create an attendee
         * @todo This is a bit hackish
         */
        if ($event->tickets->count() === 0) {
            return '<script>showMessage("You need to create a ticket before you can invite an attendee.");</script>';
        }

        return view('ManageEvent.Modals.InviteAttendee', [
            'event' => $event,
            'tickets' => $event->tickets()->pluck('title', 'id'),
        ]);
    }

    /**
     * Invite an attendee
     *
     * @param InviteAttendeeRequest $request
     * @param $eventId
     * @return mixed
     */
    public function postInviteAttendee(InviteAttendeeRequest $request, InviteAttendeeService $action, $eventId)
    {
        if ($action->make($request)) {
            session()->flash('message', 'Attendee Successfully Invited');

            return response()->json([
                'status' => self::RESPONSE_SUCCESS,
                'redirectUrl' => route('showEventAttendees', [
                    'event_id' => $eventId,
                ]),
            ]);
        }

        return response()->json([
            'status' => self::RESPONSE_ERROR,
            'message' => __('There was an error inviting this attendee. Please try again')
        ]);
    }

    /**
     * Show import attendee modal
     *
     * @param $event_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    public function showImportAttendee($event_id)
    {
        $event = $this->eventRepository->find($event_id);

        /*
         * If there are no tickets then we can't create an attendee
         * @todo This is a bit hackish
         */
        if ($event->tickets->count() === 0) {
            return '<script>showMessage("You need to create a ticket before you can add an attendee.");</script>';
        }

        return view('ManageEvent.Modals.ImportAttendee', [
            'event' => $event,
            'tickets' => $event->tickets()->pluck('title', 'id'),
        ]);
    }

    /**
     * Imports attendees from CSV file
     *
     * @param ImportAttendeeRequest $request
     * @param ImportAttendeeService $action
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postImportAttendee(ImportAttendeeRequest $request, ImportAttendeeService $action, $event_id)
    {
        if ($action->make($request)) {
            session()->flash('message', __('Attendees Successfully Invited'));
            return response()->json([
                'status' => self::RESPONSE_SUCCESS,
                'redirectUrl' => route('showEventAttendees', [
                    'event_id' => $event_id,
                ]),
            ]);
        }

        return response()->json([
            'status' => self::RESPONSE_ERROR,
            'message' => __('There was an error importing these attendees. Please try again')
        ]);
    }

    /**
     * @param $event_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showPrintAttendees($event_id)
    {
        $data['event'] = $this->eventRepository->find($event_id);
        $data['attendees'] = $data['event']->attendees()->withoutCancelled()->orderBy('first_name')->get();

        return view('ManageEvent.PrintAttendees', $data);
    }

    /**
     * Show the 'Message Attendee' modal
     *
     * @param $attendee_id
     * @return View
     */
    public function showMessageAttendee($attendee_id)
    {
        $attendee = $this->attendeeRepository->find($attendee_id);

        $data = [
            'attendee' => $attendee,
            'event' => $attendee->event,
        ];

        return view('ManageEvent.Modals.MessageAttendee', $data);
    }

    /**
     * Send message to attendee
     *
     * @param MessageAttendeeRequest $request
     * @param MessageAttendeeService $attendeeService
     * @param $attendeeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function postMessageAttendee(
        MessageAttendeeRequest $request,
        MessageAttendeeService $attendeeService,
        $attendeeId
    ) {
        if ($attendeeService->make($request, $attendeeId)) {
            return response()->json([
                'status' => self::RESPONSE_SUCCESS,
                'message' => __('Message Successfully Sent'),
            ]);
        }

        return response()->json([
            'status' => self::RESPONSE_ERROR,
            'message' => __('Message failed to send'),
        ]);
    }

    /**
     * Show message attendee modal
     *
     * @param $event_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showMessageAttendees($event_id)
    {
        $data = [
            'event' => $this->eventRepository->find($event_id),
            'tickets' => $this->eventRepository->find($event_id)->tickets()->pluck('title', 'id')->toArray(),
        ];

        return view('ManageEvent.Modals.MessageAttendees', $data);
    }



    /**
     * Sends a message to attendees
     *
     * @param MessageMultipleAttendeeRequest $request
     * @param MessageMultipleAttendeeService $service
     * @param $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function postMessageAttendees(
        MessageMultipleAttendeeRequest $request,
        MessageMultipleAttendeeService $service,
        $eventId
    ) {
        $service->make($request, $eventId);

        return response()->json([
            'status' => 'success',
            'message' => __('There was an error sending the message'),
        ]);
    }

    /**
     * Downloads the ticket of an attendee as PDF
     *
     * @param $event_id
     * @param $attendee_id
     */
    public function showExportTicket($event_id, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        Config::set('queue.default', 'sync');
        Log::info("*********");
        Log::info($attendee_id);
        Log::info($attendee);


        $this->dispatch(new GenerateTicket($attendee->order->order_reference . "-" . $attendee->reference_index));

        $pdf_file_name = $attendee->order->order_reference . '-' . $attendee->reference_index;
        $pdf_file_path = public_path(config('attendize.event_pdf_tickets_path')) . '/' . $pdf_file_name;
        $pdf_file = $pdf_file_path . '.pdf';


        return response()->download($pdf_file);
    }

    /**
     * Downloads an export of attendees
     *
     * @param $event_id
     * @param string $export_as (xlsx, xls, csv, html)
     */
    public function showExportAttendees($event_id, $export_as = 'xls')
    {

        Excel::create('attendees-as-of-' . date('d-m-Y-g.i.a'), function ($excel) use ($event_id) {

            $excel->setTitle('Attendees List');

            // Chain the setters
            $excel->setCreator(config('attendize.app_name'))
                ->setCompany(config('attendize.app_name'));

            $excel->sheet('attendees_sheet_1', function ($sheet) use ($event_id) {

                DB::connection()->setFetchMode(\PDO::FETCH_ASSOC);
                $data = DB::table('attendees')
                    ->where('attendees.event_id', '=', $event_id)
                    ->where('attendees.is_cancelled', '=', 0)
                    ->where('attendees.account_id', '=', Auth::user()->account_id)
                    ->join('events', 'events.id', '=', 'attendees.event_id')
                    ->join('orders', 'orders.id', '=', 'attendees.order_id')
                    ->join('tickets', 'tickets.id', '=', 'attendees.ticket_id')
                    ->select([
                        'attendees.first_name',
                        'attendees.last_name',
                        'attendees.email',
                        'orders.order_reference',
                        'tickets.title',
                        'orders.created_at',
                        DB::raw("(CASE WHEN attendees.has_arrived THEN 'YES' ELSE 'NO' END) AS has_arrived"),
                        'attendees.arrival_time',
                    ])->get();

                $sheet->fromArray($data);
                $sheet->row(1, [
                    'First Name',
                    'Last Name',
                    'Email',
                    'Order Reference',
                    'Ticket Type',
                    'Purchase Date',
                    'Has Arrived',
                    'Arrival Time',
                ]);

                // Set gray background on first row
                $sheet->row(1, function ($row) {
                    $row->setBackground('#f5f5f5');
                });
            });
        })->export($export_as);
    }

    /**
     * Show the 'Edit Attendee' modal
     *
     * @param Request $request
     * @param $event_id
     * @param $attendee_id
     * @return View
     */
    public function showEditAttendee(Request $request, $event_id, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        $data = [
            'attendee' => $attendee,
            'event' => $attendee->event,
            'tickets' => $attendee->event->tickets->pluck('title', 'id'),
        ];

        return view('ManageEvent.Modals.EditAttendee', $data);
    }

    /**
     * Updates an attendee
     *
     * @param Request $request
     * @param $event_id
     * @param $attendee_id
     * @return mixed
     */
    public function postEditAttendee(Request $request, $event_id, $attendee_id)
    {
        $rules = [
            'first_name' => 'required',
            'ticket_id' => 'required|exists:tickets,id,account_id,' . Auth::user()->account_id,
            'email' => 'required|email',
        ];

        $messages = [
            'ticket_id.exists' => 'The ticket you have selected does not exist',
            'ticket_id.required' => 'The ticket field is required. ',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'messages' => $validator->messages()->toArray(),
            ]);
        }

        $attendee = Attendee::scope()->findOrFail($attendee_id);
        $attendee->update($request->all());

        session()->flash('message', 'Successfully Updated Attendee');

        return response()->json([
            'status' => 'success',
            'id' => $attendee->id,
            'redirectUrl' => '',
        ]);
    }

    /**
     * Shows the 'Cancel Attendee' modal
     *
     * @param Request $request
     * @param $event_id
     * @param $attendee_id
     * @return View
     */
    public function showCancelAttendee(Request $request, $event_id, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        $data = [
            'attendee' => $attendee,
            'event' => $attendee->event,
            'tickets' => $attendee->event->tickets->pluck('title', 'id'),
        ];

        return view('ManageEvent.Modals.CancelAttendee', $data);
    }

    /**
     * Cancels an attendee
     *
     * @param Request $request
     * @param $event_id
     * @param $attendee_id
     * @return mixed
     */
    public function postCancelAttendee(Request $request, $event_id, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);
        $error_message = false; //Prevent "variable doesn't exist" error message

        if ($attendee->is_cancelled) {
            return response()->json([
                'status' => 'success',
                'message' => 'Attendee Already Cancelled',
            ]);
        }

        $attendee->ticket->decrement('quantity_sold');
        $attendee->ticket->decrement('sales_volume', $attendee->ticket->price);
        $attendee->ticket->event->decrement('sales_volume', $attendee->ticket->price);
        $attendee->is_cancelled = 1;
        $attendee->save();

        $eventStats = EventStats::where('event_id', $attendee->event_id)->where('date',
            $attendee->created_at->format('Y-m-d'))->first();
        if ($eventStats) {
            $eventStats->decrement('tickets_sold', 1);
            $eventStats->decrement('sales_volume', $attendee->ticket->price);
        }

        $data = [
            'attendee' => $attendee,
            'email_logo' => $attendee->event->organiser->full_logo_path,
        ];

        if ($request->get('notify_attendee') == '1') {
            Mail::send('Emails.notifyCancelledAttendee', $data, function ($message) use ($attendee) {
                $message->to($attendee->email, $attendee->full_name)
                    ->from(config('attendize.outgoing_email_noreply'), $attendee->event->organiser->name)
                    ->replyTo($attendee->event->organiser->email, $attendee->event->organiser->name)
                    ->subject('You\'re ticket has been cancelled');
            });
        }

        if ($request->get('refund_attendee') == '1') {

            try {
                // This does not account for an increased/decreased ticket price
                // after the original purchase.
                $refund_amount = $attendee->ticket->price;
                $data['refund_amount'] = $refund_amount;

                $gateway = Omnipay::create($attendee->order->payment_gateway->name);

                // Only works for stripe
                $gateway->initialize($attendee->order->account->getGateway($attendee->order->payment_gateway->id)->config);

                $request = $gateway->refund([
                    'transactionReference' => $attendee->order->transaction_id,
                    'amount' => $refund_amount,
                    'refundApplicationFee' => false,
                ]);

                $response = $request->send();

                if ($response->isSuccessful()) {

                    // Update the attendee and their order
                    $attendee->is_refunded = 1;
                    $attendee->order->is_partially_refunded = 1;
                    $attendee->order->amount_refunded += $refund_amount;

                    $attendee->order->save();
                    $attendee->save();

                    // Let the user know that they have received a refund.
                    Mail::send('Emails.notifyRefundedAttendee', $data, function ($message) use ($attendee) {
                        $message->to($attendee->email, $attendee->full_name)
                            ->from(config('attendize.outgoing_email_noreply'), $attendee->event->organiser->name)
                            ->replyTo($attendee->event->organiser->email, $attendee->event->organiser->name)
                            ->subject('You have received a refund from ' . $attendee->event->organiser->name);
                    });
                } else {
                    $error_message = $response->getMessage();
                }

            } catch (\Exception $e) {
                \Log::error($e);
                $error_message = 'There has been a problem processing your refund. Please check your information and try again.';

            }
        }

        if ($error_message) {
            return response()->json([
                'status' => 'error',
                'message' => $error_message,
            ]);
        }

        session()->flash('message', 'Successfully Cancelled Attenddee');

        return response()->json([
            'status' => 'success',
            'id' => $attendee->id,
            'redirectUrl' => '',
        ]);
    }

    /**
     * Show the 'Message Attendee' modal
     *
     * @param Request $request
     * @param $attendee_id
     * @return View
     */
    public function showResendTicketToAttendee(Request $request, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        $data = [
            'attendee' => $attendee,
            'event' => $attendee->event,
        ];

        return view('ManageEvent.Modals.ResendTicketToAttendee', $data);
    }

    /**
     * Send a message to an attendee
     *
     * @param Request $request
     * @param $attendee_id
     * @return mixed
     */
    public function postResendTicketToAttendee(Request $request, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        $this->dispatch(new SendAttendeeTicket($attendee));

        return response()->json([
            'status' => 'success',
            'message' => 'Ticket Successfully Resent',
        ]);
    }


    /**
     * Show an attendee ticket
     *
     * @param Request $request
     * @param $attendee_id
     * @return bool
     */
    public function showAttendeeTicket(Request $request, $attendee_id)
    {
        $attendee = Attendee::scope()->findOrFail($attendee_id);

        $data = [
            'order' => $attendee->order,
            'event' => $attendee->event,
            'tickets' => $attendee->ticket,
            'attendees' => [$attendee],
            'css' => file_get_contents(public_path('assets/stylesheet/ticket.css')),
            'image' => base64_encode(file_get_contents(public_path($attendee->event->organiser->full_logo_path))),

        ];

        if ($request->get('download') == '1') {
            return PDF::html('Public.ViewEvent.Partials.PDFTicket', $data, 'Tickets');
        }
        return view('Public.ViewEvent.Partials.PDFTicket', $data);
    }

}
