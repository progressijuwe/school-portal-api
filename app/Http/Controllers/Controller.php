<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Gives every controller `$this->authorize()`. Laravel 12's skeleton leaves
     * this out by default; policies are the mechanism this API uses to answer
     * "may this user touch this row", so it belongs on the base class.
     */
    use AuthorizesRequests;
}
