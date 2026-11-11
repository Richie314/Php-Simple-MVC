<?php

namespace Richie314\SimpleMvc\Controllers\Attributes;

use Richie314\SimpleMvc\Controllers\Controller;

#[\Attribute]
class RequireLogin implements Attribute
{
    /**
     * Configure the authentication requirements for an attributed action.
     *
     * @param bool $requireAdmin Whether the authenticated user must also be an administrator.
     * @param string $loginPath Login path relative to the application URL prefix.
     */
    public function __construct(
        private bool $requireAdmin = false, 
        private string $loginPath = '/login',
    ) {}

    /**
     * Enforce login and optional administrator access before invoking an action.
     *
     * Delegates to Controller::RequireLogin(); failed checks terminate the request.
     *
     * @param Controller $controller Controller instance that will execute the action.
     * @param string $action Action method name.
     * @param array<array-key, mixed> $parameters Parsed named action arguments.
     * @return void
     */
    public function DoWork(Controller $controller, string $action, array $parameters): void
    {
        Controller::RequireLogin(
            controller: $controller, 
            requireAdmin: $this->requireAdmin,
            loginPath: $this->loginPath,
        );
    }
}