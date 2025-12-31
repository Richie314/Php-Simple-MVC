<?php

namespace Richie314\SimpleMvc\Controllers\Attributes;

use Richie314\SimpleMvc\Controllers\Controller;

#[\Attribute]
class RequireLogin implements Attribute
{
    public function __construct(
        private bool $requireAdmin = false, 
        private string $loginPath = '/login',
    ) {}

    public function DoWork(Controller $controller, string $action, array $parameters): void
    {
        Controller::RequireLogin(
            controller: $controller, 
            requireAdmin: $this->requireAdmin,
            loginPath: $this->loginPath,
        );
    }
}