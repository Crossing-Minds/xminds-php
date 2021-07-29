<?php
/*
xminds.api.exceptions
~~~~~~~~~~~~~~~~~~~~~

This module defines all custom API exceptions.
All exceptions inherit from ``XMindsError``.
*/

class XMindsError extends Exception
{
	public $CODE = null;

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

// === Server Errors ===

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
        if (isset($data['retry_after']))
            $this->retry_after = $data['retry_after'];
	}
}

// === Authentication Errors ====

class AuthError extends XMindsError
{
    public $HTTP_STATUS = 401;
    public $CODE = 21;

	function __construct($data=['error'=>null])
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

// === Request Errors ===


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

	function __construct($data=['type'=>null, 'key'=>null])
	{
		parent::__construct($data);
	    $this->message = "The {$data['type']} {$data['key']} is duplicated";
	}
}

class ForbiddenError extends XMindsError
{
    public $HTTP_STATUS = 403;
    public $CODE = 50;

	function __construct($data=['error'=>null])
	{
		parent::__construct($data);
	    $this->message = "Do not have enough permissions to access this resource: {$data['error']}";

	}
}

// === Resource Errors ===


class NotFoundError extends XMindsError
{
    public $HTTP_STATUS = 404;
    public $CODE = 60;

	function __construct($data=['type'=>null, 'key'=>null])
	{
		parent::__construct($data);
	    $this->message = "The {$data['type']} {$data['key']} does not exist";

	}
}

class MethodNotAllowed extends XMindsError
{
    public $HTTP_STATUS = 405;
    public $CODE = 70;

	function __construct($data=['method'=>null])
	{
		parent::__construct($data);
	    $this->message = "Method \"{$data['method']}\" not allowed";

	}
}

class NotImplementedError extends XMindsError
{
	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = "Not implemented error";
	}
}

class XMindsTypeError extends XMindsError
{
	function __construct($data=null)
	{
		parent::__construct($data);
	    $this->message = "Type error";
	}
}

// === Utils to build exception from code ===

function getSubclassesOf($parent) {
	// https://stackoverflow.com/a/3470032/4288232
    $result = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, $parent))
            $result[] = $class;
    }
    return $result;
}

$_ERROR_CLASSES = [];
foreach (getSubclassesOf('XMindsError') as $EC)
{
	array_push($_ERROR_CLASSES, new $EC());
}

function XMinds_Error_from_code($code, $data=[])
{
	global $_ERROR_CLASSES;
	$c = null;
	foreach ($_ERROR_CLASSES as $EC)
	{	
		//print($EC." ".$EC->CODE."\n");
		if($EC->CODE == $code)
		{
			$c = get_class($EC);
			break;
		}
	}
	if($c == null)
	{
        print("unknown error code {$code}\n");
        $c = 'ServerError';
	}
    return new $c($data);
}

?>
