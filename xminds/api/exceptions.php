<?php
/*
xminds.api.exceptions
~~~~~~~~~~~~~~~~~~~~~

This module defines all custom API exceptions.
All exceptions inherit from ``XMindsError``.
*/

class XMindsError extends Exception
{
    // Base class for all Crossing Minds Exceptions
	function __construct($data=null)
	{
		parent::__construct();
		$this->retry_after = null;
        $this->message = null;
        $this->data = $data;
	}

	public function __toString()
	{
        $msg = $this->message;
        if ($this->data)
            $msg .= ' ' . (string)($this->data);
        return $msg;
	}
}
/*

# === Server Errors ===

*/
class ServerError extends XMindsError
{
    public $CODE = 0;
    public $HTTP_STATUS = 500;

	function __construct($data=null)
	{
		parent::__construct($data);
        $this->message = 'Unknown error from server';
	}
}

class ServerUnavailable extends XMindsError
{
    public $CODE = 1;
    public $HTTP_STATUS = 503;

	function __construct($data=null)
	{
		parent::__construct($data);
		$this->message = 'The server is currently unavailable, please try again later';
		$this->retry_after = 1;
	}
}

class TooManyRequests extends XMindsError
{
    public $CODE = 2;
    public $HTTP_STATUS = 429;

	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = 'The amount of requests exceeds the limit of your subscription';

	    $this->retry_after = 1;  # should be passed in constructor instead
        if ($data['retry_after'])
            $this->retry_after = $data['retry_after'];
	}
}

# === Authentication Errors ====


class AuthError extends XMindsError
{
    public $HTTP_STATUS = 401;
    public $CODE = 21;

	function __construct($data=null)
	{
		parent::__construct($data);
    	$this->message = "Cannot perform authentication:{$data['error']}";
	}
}

class JwtTokenExpired extends XMindsError
{
    public $CODE = 22;

	function __construct($data=null)
	{
		parent::__construct($data);
    	$this->message = 'The JWT token has expired';

	}
}

class RefreshTokenExpired extends XMindsError
{
    public $CODE = 28;

	function __construct($data=null)
	{
		parent::__construct($data);
   		$this->message = 'The refresh token has expired';

	}
}

# === Request Errors ===


class RequestError extends XMindsError
{
    public $HTTP_STATUS = 400;

	function __construct($data=null)
	{
		parent::__construct($data);
		$this->message = "Request error";
	}
}

class WrongData extends XMindsError
{
    public $CODE = 40;

	function __construct($data=null)
	{
		parent::__construct($data);
    	$this->message = 'There is an error in the submitted data';

	}
}

class DuplicatedError extends XMindsError
{
    public $CODE = 42;

	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = "The {$data['type']} {$data['key']} is duplicated";
	}
}

class ForbiddenError extends XMindsError
{
    public $HTTP_STATUS = 403;
    public $CODE = 50;

	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = "Do not have enough permissions to access this resource: {$data['error']}";

	}
}

# === Resource Errors ===


class NotFoundError extends XMindsError
{
    public $HTTP_STATUS = 404;
    public $CODE = 60;

	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = "The {$data['type']} {$data['key']} does not exist";

	}
}

class MethodNotAllowed extends XMindsError
{
    public $HTTP_STATUS = 405;
    public $CODE = 70;

	function __construct($data=null)
	{
	    $this->message = "Method \"{$data['method']}\" not allowed";

	}
}
/*

# === Utils to build exception from code ===


def _get_all_subclasses(cls):
    return set(cls.__subclasses__()).union(
        {s for c in cls.__subclasses__() for s in _get_all_subclasses(c)})


_ERROR_CLASSES = _get_all_subclasses(XMindsError)


@classmethod
def from_code(cls, code, data=None):
    if data is None:
        data = {}
    try:
        c = next(c for c in _ERROR_CLASSES if getattr(c, 'CODE', -1) == code)
    except StopIteration:
        print(f'unknown error code {code}')
        c = ServerError
    exc = c.__new__(c)
    XMindsError.__init__(exc, data)
    return exc


XMindsError.from_code = from_code

*/
?>
