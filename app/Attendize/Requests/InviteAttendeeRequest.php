<?php

namespace App\Attendize\Requests;

use Illuminate\Support\Facades\Auth;

class InviteAttendeeRequest extends BaseRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'first_name' => 'required',
            'ticket_id' => 'required|exists:tickets,id,account_id,' . Auth::user()->account_id,
            'email' => 'email|required',
        ];
    }

    public function messages()
    {
        return [
            'ticket_id.exists' => 'The ticket you have selected does not exist',
            'ticket_id.required' => 'The ticket field is required. ',
        ];
    }

}