<?php

namespace App\Attendize\Requests\Attendee;

use App\Attendize\Requests\BaseRequest;

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