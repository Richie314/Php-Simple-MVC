<?php

namespace Richie314\SimpleMvc\Controllers;

use Richie314\SimpleMvc\Http\StatusCode;

class ErrorController extends Controller
{
    /**
     * Send the router fallback response for an unmatched request URI.
     *
     * @return StatusCode Not-found response status (normally 404).
     */
    public function NotFoundHandler(): StatusCode
    {
        return $this->NotFound(
            message: 'The request resource "' . $this->RequestPath . '" was not found',
        );
    }
    /**
     * Send the router fallback response for an unhandled exception.
     *
     * @param \Throwable|null $ex Exception passed to the error view and used for its banner.
     * @return StatusCode ServerError (500).
     */
    public function InternalErrorHandler(?\Throwable $ex = null): StatusCode
    {
        return $this->InternalError(ex: $ex);
    }
}