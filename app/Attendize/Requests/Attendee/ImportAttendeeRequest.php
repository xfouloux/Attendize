<?php

namespace App\Attendize\Requests\Attendee;

use App\Attendize\Requests\BaseRequest;

class ImportAttendeeRequest extends BaseRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ticket_id' => 'required|exists:tickets,id,account_id,' . \Auth::user()->account_id,
            'attendees_list' => 'required|mimes:csv,txt|max:5000|',
        ];
    }

    public function messages()
    {
        return [
            'ticket_id.exists' => 'The ticket you have selected does not exist',
        ];
    }
}