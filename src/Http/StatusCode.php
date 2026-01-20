<?php

declare(strict_types=1);

namespace Richie314\SimpleMvc\Http;

enum StatusCode : int
{
    case Ok = 200;
    
    case MovedPermanently = 301;
    case NotModified = 304;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;

    case BadRequest = 400;
    case NotAuthorized = 401;
    case PaymentRequired = 402;
    case Forbidden = 403;
    case NotFound = 404;
    case Timeout = 408;
    case ContentTooLarge = 413;
    case Teapot = 418;
    case TooManyRequests = 429;
    case CensoredContent = 451;
    
    case ServerError = 500; 
    case NotImplemented = 501;
    case BadGateway = 502;
    case ServiceUnavaible = 503;   

    /**
     * Checks if the status code is in the 4xx or 5xx range
     * @param StatusCode|int $statusCode The number to check
     * @return bool true if the number is in the 4xx or 5xx range
     */
    public static function IsError(StatusCode|int $statusCode): bool
    {
        if ($statusCode instanceof StatusCode)
            $statusCode = $statusCode->value;

        return $statusCode >= 400 && $statusCode < 600;
    }
}