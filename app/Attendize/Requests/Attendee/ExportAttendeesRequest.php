<?php


namespace App\Attendize\Requests\Attendee;


use App\Attendize\Requests\BaseRequest;

class ExportAttendeesRequest extends BaseRequest
{
    public function rules()
    {
        return [];
    }

    public function authorize()
    {
        return true;
    }
}