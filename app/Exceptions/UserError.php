<?php

namespace App\Exceptions;

use RuntimeException;

/** A problem the user can fix. Its message is shown on screen and nothing is saved. */
class UserError extends RuntimeException
{
}
