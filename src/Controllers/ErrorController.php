<?php

namespace Richie314\SimpleMvc\Controllers;

use Richie314\SimpleMvc\Http\StatusCode;

class ErrorController extends Controller
{
    public function NotFoundHandler(): StatusCode
    {
        return $this->NotFound(
            message: 'The request resource "' . $this->RequestPath . '" was not found',
        );
    }
    public function InternalErrorHandler(?\Throwable $ex = null): StatusCode
    {
        return $this->InternalError(ex: $ex);
    }
}