<?php
namespace Richie314\SimpleMvc\Routers;

use Richie314\SimpleMvc\Controllers\Controller;
use Richie314\SimpleMvc\Controllers\ErrorController;
use Richie314\SimpleMvc\Controllers\Attributes\Attribute;
use Richie314\SimpleMvc\Users\User;
use Richie314\SimpleMvc\Http\Method;
use Richie314\SimpleMvc\Http\StatusCode;

class Router {

    private ?\mysqli $connection = null;
    private ?User $user = null;
    private array $defaultViewBag = [];
    private array $routes = [];

    protected Method $RequestMethod;

    /**
     * If this application is not installed in the root of your webserver.
     * 
     * I.e: app is exposed at https://example.com/mysite/
     * 
     * This variable will be empty in every other case.
     * @var string
     */
    protected string $PathPrefix;

    /**
     * Physical path of the root of this app.
     * 
     * It's the folder that usually contains index.php and MUST have 
     * the Views and Views/Shared subdirectories.
     * 
     * The default values is `null` (but evaluated at `$_SERVER['DOCUMENT_ROOT']` during execution). 
     * You won't need to explicitly set it if this app is the only project in the server
     * as the default value will most likely work.
     * @var string
     */
    protected ?string $ApplicationInstallationPath;

    public function __construct(
        string $pathPrefix = '',
        ?string $applicationInstallationPath = null,
    ) {
        if (!array_key_exists(key: 'REQUEST_METHOD', array: $_SERVER))
            throw new \BadMethodCallException(
                message: 
                    '$_SERVER["REQUEST_METHOD"] is missing. ' . 
                    'This class can only be used inside HTTP requests'
            );
        
        $this->RequestMethod = Method::from(value: strtoupper(string: $_SERVER["REQUEST_METHOD"]));

        $this->PathPrefix = $pathPrefix;
        if (strlen(string: $pathPrefix) > 0 && !str_starts_with(needle: '/', haystack: $pathPrefix))
            throw new \InvalidArgumentException(
                message: 
                    "A custom installation path is detected but does not start with '/'. " . 
                    "Given: '$pathPrefix'"
            );

        $this->ApplicationInstallationPath = $applicationInstallationPath;
    }

    public function AddRouteAction(
        string $route, 
        string $controller, 
        string $action,
    ): void {
        $this->routes[$this->PathPrefix . $route] = [
            'controller' => $controller, 
            'action' => $action,
        ];
    }

    public function AddController(
        string $controller,
        string $route_base,
    ): void {
        $dummy_instance = new $controller(
            requestPath: '',
            requestMethod: $this->RequestMethod,
        );
        if (!$dummy_instance instanceof Controller)
        {
            throw new \Exception(message: "Invalid class '$controller'");
        }

        if (!str_starts_with(haystack: $route_base, needle: '/'))
        {
            $route_base = '/' . $route_base;
        }

        $methods = get_class_methods(object_or_class: $dummy_instance);
        foreach ($methods as $method)
        {
            if (str_starts_with(haystack: $method, needle: '_') || 
                str_starts_with(haystack: $method, needle: '#')
            ) {
                continue;
            }
            $this->AddRouteAction(
                route: $route_base === '/' ? "/$method" : "$route_base/$method", 
                controller: $controller, 
                action: $method
            );
        }

        // Default '/' to method index()
        $this->AddRouteAction(
            route: "$route_base", 
            controller: $controller, 
            action: 'index'
        );
    }

    private function ParseParameters(string $uri): array
    {
        $method_params = [];

        switch ($this->RequestMethod)
        {
            case Method::Post:
                $method_params = $_POST;
                break;
            case Method::Get:
                $query = parse_url(url: $uri, component: PHP_URL_QUERY);
                if (is_string(value: $query) && !empty($query)) {
                    parse_str(string: $query, result: $method_params);
                }
                break;
        }

        $filtered_parameters = [];
        foreach ($method_params as $name => $value)
        {
            $filtered_parameters[
                str_replace(search: '-', replace: '_', subject: $name)
            ] = $value;
        }

        return $filtered_parameters;
    }

    public function Dispatch(string $uri): StatusCode
    {
        $path = parse_url(url: $uri, component: PHP_URL_PATH);
        if (!$path || empty($path)) {
            throw new \Exception(message: "Could not extract path from uri '$uri'.");
        }

        $method_params = $this->ParseParameters(uri: $uri);

        if (!array_key_exists(key: $path, array: $this->routes)) {
            $controller = ErrorController::class;
            $action = 'NotFoundHandler';
        } else {
            $controller = $this->routes[$path]['controller'];
            $action = $this->routes[$path]['action'];
        }

        $controller_instance = new $controller(
            requestPath: $uri,
            requestMethod: $this->RequestMethod,

            pathPrefix: $this->PathPrefix,
            applicationInstallationPath: $this->ApplicationInstallationPath,

            connection: $this->connection, 
            user: $this->user,
        );

        $method_attributes = \ReflectionMethod::createFromMethodName(method: "$controller::$action")
            ->getAttributes(
                name: Attribute::class, 
                flags: \ReflectionAttribute::IS_INSTANCEOF,
            );
        foreach ($method_attributes as $attribute)
        {
            $attribute->newInstance()->DoWork($controller_instance, $action, $method_params);
        }

        try {
            $status_code = call_user_func_array(
                callback: [$controller_instance, $action], 
                args: $method_params,
            );
            if (!($status_code instanceof StatusCode))
                $status_code = StatusCode::from(value: $status_code);
            return $status_code;
        } catch (\Throwable $ex) {
            if (headers_sent()) {
                // Cannot send an other HTTP status code, just print the error
                // It will be handled by the general handler 
                throw $ex;
            }

            // "Manually" handle the exception
            $error_controller = new ErrorController(
                requestPath: $uri,
                requestMethod: $this->RequestMethod,

                pathPrefix: $this->PathPrefix,
                applicationInstallationPath: $this->ApplicationInstallationPath,
            );
            return $error_controller->InternalErrorHandler(ex: $ex);
        }
    }

    public function SetDbConnection(\mysqli $connection): bool {
        $this->connection = $connection;
        return 
            $this->connection->set_charset(charset: "utf8mb4");
    }

    public function SetUser(User $user): void {
        $this->user = $user;
    }

    public function SetDefaultVariable(string $key, mixed $value): void
    {
        if (strlen(string: $key) === 0)
            return;

        $this->defaultViewBag[$key] = $value;
    }

    public function ClearDefaultVariable(string $key): void
    {
        if (strlen(string: $key) === 0)
            return;

        if (!array_key_exists(key: $key, array: $this->defaultViewBag))
            return;

        unset($this->defaultViewBag[$key]);
    }
}