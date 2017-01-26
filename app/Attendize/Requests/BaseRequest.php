<?php

namespace App\Attendize\Requests;

use App\Http\Requests\Request;

abstract class BaseRequest extends Request
{
    abstract public function rules();

    abstract public function authorize();
}