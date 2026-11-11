<?php

namespace Richie314\SimpleMvc\Controllers\Attributes;
use Richie314\SimpleMvc\Controllers\Controller;

interface Attribute
{
    /**
     * Run an action filter before the router invokes the action.
     *
     * Implementations may inspect the request, enforce access rules, or throw an
     * exception. Parameter-array mutations are not passed back to the router.
     *
     * @param Controller $controller Controller instance that will execute the action.
     * @param string $action Action method name.
     * @param array<array-key, mixed> $parameters Parsed named action arguments.
     * @return void
     */
    public function DoWork(Controller $controller, string $action, array $parameters): void;
}