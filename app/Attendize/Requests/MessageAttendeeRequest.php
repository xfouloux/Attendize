<?php

namespace App\Attendize\Requests;

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