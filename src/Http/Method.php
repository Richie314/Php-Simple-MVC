<?php

declare(strict_types=1);

namespace Richie314\SimpleMvc\Http;

enum Method : string
{
    case Post = 'POST';
    case Get = 'GET';
    case Put = 'PUT';
    case Head = 'HEAD';
    case Trace = 'TRACE';
    case Delete = 'DELETE';
}