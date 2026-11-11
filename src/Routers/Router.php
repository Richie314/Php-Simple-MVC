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

    /**
     * Create a router for the current HTTP request.
     *
     * @throws \BadMethodCallException If REQUEST_METHOD is missing from $_SERVER.
     * @throws \InvalidArgumentException If a nonempty prefix does not start with /.
     * @throws \ValueError If the HTTP method has no matching Method case.
     *
     * @param string $pathPrefix Application URL prefix, empty or beginning with /; no slash normalization is performed.
     * @param string|null $applicationInstallationPath App directory passed to dispatched controllers; null uses their default.
     */
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

    /**
     * Register an exact route path for a controller action.
     *
     * The application prefix is prepended verbatim. Re-registering a path replaces it.
     *
     * @param string $route Route path beginning with /, without the application prefix.
     * @param class-string<Controller> $controller Controller class to instantiate when dispatched.
     * @param string $action Public action method name.
     * @return void
     */
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

    /**
     * Register public controller methods and map the base route to index().
     *
     * Methods beginning with _ or # are skipped. The controller is instantiated
     * during registration, so its constructor must accept requestPath and requestMethod.
     * @throws \Exception If the constructed object is not a Controller.
     *
     * @param class-string<Controller> $controller Controller class with a compatible constructor.
     * @param string $route_base Base route, for example /account or /; a missing leading slash is added.
     * @return void
     */
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

    /**
     * Build named action arguments from GET query strings or POST form fields.
     *
     * Other HTTP methods produce an empty argument array; JSON bodies are not parsed.
     *
     * @param string $uri Request URI whose query string is used for GET requests.
     * @return array<array-key, mixed> Parsed fields with hyphens in their names changed to underscores.
     */
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

    /**
     * Dispatch an exact URI path, run action attributes, and return the action status.
     *
     * GET/POST fields are passed as named action arguments. Action and attribute
     * failures are rendered as server errors when response headers remain writable.
     * Actions must return a StatusCode or an integer matching a StatusCode case.
     * @throws \Exception If no usable path can be extracted from the URI.
     * @throws \Throwable If an action fails after headers are sent, or error handling fails.
     *
     * @param string $uri Request URI including its path and optional query string.
     * @return StatusCode Action status, NotFound for unmatched routes, or a handled error status.
     */
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
            $method_params = [];
        } else {
            $controller = $this->routes[$path]['controller'];
            $action = $this->routes[$path]['action'];
        }

        try {
            $controller_instance = new $controller(
                requestPath: $uri,
                requestMethod: $this->RequestMethod,

                pathPrefix: $this->PathPrefix,
                applicationInstallationPath: $this->ApplicationInstallationPath,

                connection: $this->connection,
                user: $this->user,

                defaultViewBag: $this->defaultViewBag,
            );

            $method_attributes = (new \ReflectionMethod($controller, $action))
                ->getAttributes(
                    name: Attribute::class,
                    flags: \ReflectionAttribute::IS_INSTANCEOF,
                );
            foreach ($method_attributes as $attribute)
            {
                $attribute->newInstance()->DoWork($controller_instance, $action, $method_params);
            }

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

                defaultViewBag: $this->defaultViewBag,
            );
            return $error_controller->InternalErrorHandler(ex: $ex);
        }
    }

    /**
     * Store a database connection and configure its character set as utf8mb4.
     *
     * The connection remains stored even if setting the character set fails.
     * @throws \mysqli_sql_exception If mysqli is configured to throw on database errors.
     *
     * @param \mysqli $connection Connection shared with subsequently dispatched controllers.
     * @return bool Whether setting the character set succeeded.
     */
    public function SetDbConnection(\mysqli $connection): bool {
        $this->connection = $connection;
        return 
            $this->connection->set_charset(charset: "utf8mb4");
    }

    /**
     * Set the authenticated user for subsequently dispatched controllers.
     *
     * @param User $user Application user supplied to each controller.
     * @return void
     */
    public function SetUser(User $user): void {
        $this->user = $user;
    }

    /**
     * Set or replace a default variable for controller views.
     *
     * @param string $key Variable name; an empty name is ignored.
     * @param mixed $value Value passed through the controller ViewBag.
     * @return void
     */
    public function SetDefaultVariable(string $key, mixed $value): void
    {
        if (strlen(string: $key) === 0)
            return;

        $this->defaultViewBag[$key] = $value;
    }

    /**
     * Remove a default view variable, if present.
     *
     * @param string $key Variable name; empty or unknown names are ignored.
     * @return void
     */
    public function ClearDefaultVariable(string $key): void
    {
        if (strlen(string: $key) === 0)
            return;

        if (!array_key_exists(key: $key, array: $this->defaultViewBag))
            return;

        unset($this->defaultViewBag[$key]);
    }
}