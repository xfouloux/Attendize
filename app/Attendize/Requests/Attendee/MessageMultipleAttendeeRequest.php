<?php

namespace App\Attendize\Requests\Attendee;

class MessageMultipleAttendeeRequest extends BaseRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'subject' => 'required',
            'message' => 'required',
            'recipients' => 'required',
        ];
    }
}