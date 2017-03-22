<?php

namespace App\Attendize\Requests\Attendee;

class MessageAttendeeRequest extends BaseRequest
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
        ];
    }
}