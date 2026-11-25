<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller of the system.
 *
 * Every other controller (UserController, RoleController, LoginController and
 * the MSWDO controllers) extends this class. It has no pages of its own; it
 * only shares Laravel's built-in helpers with all controllers:
 *  - AuthorizesRequests: permission checks such as $this->authorize(...)
 *  - ValidatesRequests:  form validation helpers
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
