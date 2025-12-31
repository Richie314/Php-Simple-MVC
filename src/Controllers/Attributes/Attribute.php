<?php

namespace Richie314\SimpleMvc\Controllers\Attributes;
use Richie314\SimpleMvc\Controllers\Controller;

interface Attribute
{
    public function DoWork(Controller $controller, string $action, array $parameters): void;
}