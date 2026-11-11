<?php
namespace Richie314\SimpleMvc\Controllers;

use Richie314\SimpleMvc\Http\Method;
use Richie314\SimpleMvc\Http\StatusCode;
use Richie314\SimpleMvc\Utils\Cookie;
use Richie314\SimpleMvc\Utils\File;
use Richie314\SimpleMvc\Users\User;

class Controller {

    /**
     * The requested resource path
     * 
     * ```php
     * // when request is https://example.com/path
     * $RequestPath = '/path';
     * ```
     * 
     * @var string
     */
    protected string $RequestPath;

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
     * If this app is the only project in the server, this path will most likely be
     * set to `$_SERVER['DOCUMENT_ROOT']`
     * @var string
     */
    private string $ApplicationInstallationPath;

    /**
     * Build candidate view paths in lookup order.
     *
     * @param string $viewName View name relative to Views/, with an optional extension.
     * @param bool $checkShared Whether to also search Views/Shared/.
     * @return list<string> Candidate file paths; files may not exist.
     */
    private function ViewPossibleFileNames(
        string $viewName, 
        bool $checkShared = true,
    ): array
    {
        $paths = [];

        if (str_contains(haystack: $viewName, needle: '.'))
        {
            $paths[] = $this->ApplicationInstallationPath . '/Views/' . $viewName;
        } else {
            $paths[] = $this->ApplicationInstallationPath . '/Views/' . $viewName . '.php';
            $paths[] = $this->ApplicationInstallationPath . '/Views/' . $viewName . '.html';
            $paths[] = $this->ApplicationInstallationPath . '/Views/' . $viewName . '.htm';
        }

        if ($checkShared)
        {
            $shared_paths = self::ViewPossibleFileNames(viewName: 'Shared/' . $viewName, checkShared: false);
            $paths = array_merge($paths, $shared_paths);
        }

        return $paths;
    }

    /**
     * Find the first existing view file in Views/ or Views/Shared/.
     *
     * @param string $viewName View name, for example Home/index or Shared/Error.
     * @return string|null Matching file path, or null when no candidate exists.
     */
    protected function ViewFileName(string $viewName): ?string
    {
        $possible_paths = $this->ViewPossibleFileNames(viewName: $viewName);
        foreach ($possible_paths as $path)
        {
            if (File::Exists(file_path: $path))
                return $path;
        }
        return null;
    }

    /**
     * Get the default layout path without checking whether it exists.
     *
     * @return string Path to Views/Shared/Layout.php.
     */
    private function DefaultLayoutFileName(): string
    {
        return $this->ApplicationInstallationPath . '/Views/Shared/Layout.php';
    }

    protected \mysqli|false $DB = false;


    /**
     * The current user, if logged in
     * @var User|null
     */
    protected ?User $User;

    protected Method $RequestMethod;

    /**
     * Associative array of variables that will be passed to Render()
     * @var array strings as keys
     */
    protected array $ViewBag = [];

    /**
     * Initialize request context, optional services, and default view variables.
     *
     * @param string $requestPath Request URI, optionally including its query string.
     * @param Method $requestMethod HTTP method of the current request.
     * @param string $pathPrefix Application URL prefix, such as /myapp, or an empty string.
     * @param string|null $applicationInstallationPath App directory; null or empty uses DOCUMENT_ROOT, then the working directory.
     * @param User|null $user Authenticated application user, if available.
     * @param \mysqli|null $connection Optional database connection, exposed as $this->DB.
     * @param array<string, mixed> $defaultViewBag Default view variables; non-string keys are ignored.
     */
    public function __construct(
        string $requestPath,
        Method $requestMethod,

        string $pathPrefix = '',
        ?string $applicationInstallationPath = null,

        ?User $user = null,
        ?\mysqli $connection = null,

        array $defaultViewBag = [],
    ) {
        $this->RequestPath = $requestPath;
        $this->RequestMethod = $requestMethod;

        $this->PathPrefix = $pathPrefix;

        $this->ApplicationInstallationPath = empty($applicationInstallationPath) ? 
            ($_SERVER['DOCUMENT_ROOT'] ?? getcwd()) :
            $applicationInstallationPath;

        while (str_ends_with(haystack: $this->ApplicationInstallationPath, needle: '/'))
            $this->ApplicationInstallationPath = substr(
                string: $this->ApplicationInstallationPath, 
                offset: 0, 
                length: strlen(string: $this->ApplicationInstallationPath) - 1,
            );

        if ($connection !== null)
            $this->DB = $connection;
        $this->User = $user;

        foreach ($defaultViewBag as $key => $value)
        {
            if (!is_string(value: $key))
                continue;

            $this->ViewBag[$key] = $value;
        }
    }

    /**
     * Require an authenticated user, optionally with administrator privileges.
     *
     * Missing users receive a Redirect cookie and a login redirect. Failed admin
     * checks send an unauthorized response. Either failure terminates the request.
     *
     * @param Controller $controller Controller whose request and user are checked.
     * @param bool $requireAdmin Whether the current user must have Admin set to true.
     * @param string $loginPath Login URL path relative to the application prefix.
     * @return User Authenticated user when all checks pass.
     */
    public static function RequireLogin(
        self $controller, 
        bool $requireAdmin = false,
        string $loginPath = '/login',
    ): User
    {
        if (!isset($controller->User)) {
            Cookie::Set(
                name: 'Redirect', 
                value: $controller->RequestPath, 
                exp: 3600,
                path: $controller->PathPrefix,
            );
            $controller->Redirect(url: $controller->PathPrefix . $loginPath);
            exit;
        }

        if ($requireAdmin && !$controller->User->Admin) {
            $controller->NotAuthorized();
            exit;
        }
        
        return $controller->User;
    }

    /**
     * Render a view inside a layout and send the response status.
     *
     * Default ViewBag entries override $data. Built-in variables override both:
     * $title, $status_code, $user, $P, and the $RenderBody callable. Internal local
     * variables are reserved. Templates are responsible for escaping output.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param string $view View name relative to Views/, for example Home/index.
     * @param array<string, mixed> $data Variables made available to the layout and view body.
     * @param string $title Page title, exposed as $title.
     * @param StatusCode|int $statusCode Response status; integers must be supported enum values.
     * @param string|null $cutomLayout Optional layout file path; spelling retained for compatibility.
     * @return StatusCode Response status, or an error status if a view or layout is missing.
     */
    protected function Render(
        string $view, 
        array $data = [], 
        string $title = '',
        StatusCode|int $statusCode = StatusCode::Ok,
        ?string $cutomLayout = null,
    ): StatusCode
    {
        $view_file = $this->ViewFileName(viewName: $view);
        if ($view_file === null)
            return $this->NotFound(message: 'Requested view was not found');

        $layout_file_path = empty($cutomLayout) ?
            $this->DefaultLayoutFileName() :
            $cutomLayout;
        if (!File::Exists(file_path: $layout_file_path))
            return $this->InternalError(render_error: false);

        if (!($statusCode instanceof StatusCode))
            $statusCode = StatusCode::from(value: $statusCode);

        http_response_code(response_code: $statusCode->value);


        $view_variables = array_merge($data, $this->ViewBag, [
            'title' => $title,
            'status_code' => $statusCode->value,
            'user' => $this->User,
            'P' => $this->PathPrefix,
        ]);
        /**
         * Render the selected view with its template variables.
         *
         * @return void
         */
        $view_variables['RenderBody'] = function () use ($view_file, $view_variables): void {
            extract($view_variables, EXTR_SKIP);
            require $view_file;
        };
        extract($view_variables, EXTR_SKIP);

        require $layout_file_path;
        return $statusCode;
    }

    /**
     * Send a Location header using the legacy redirect behavior.
     *
     * PHP normally emits 302 for this header, independently of the returned 301.
     * Use RedirectWithStatus() to keep the HTTP status and return value aligned.
     * This method does not terminate execution.
     *
     * @param string|null $url Destination URL; null or empty selects the application root.
     * @return StatusCode Always MovedPermanently (301), preserving the legacy return value.
     */
    protected function Redirect(?string $url): StatusCode {
        if (empty($url)) {
            $url = $this->PathPrefix . '/';
        }
        // Preserve the original Location header behavior and return value.
        header(header: "Location: $url");
        return StatusCode::MovedPermanently;
    }

    /**
     * Send a Location header with an explicit response status.
     *
     * This method does not terminate execution.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param string|null $url Destination URL; null or empty selects the application root.
     * @param StatusCode|int $statusCode Status to send, typically 301, 302, 303, 307, or 308.
     * @return StatusCode Status sent with the Location header.
     */
    protected function RedirectWithStatus(
        ?string $url,
        StatusCode|int $statusCode = StatusCode::MovedPermanently,
    ): StatusCode {
        if (!($statusCode instanceof StatusCode))
            $statusCode = StatusCode::from($statusCode);
        if (empty($url))
        {
            $url = $this->PathPrefix . '/';
        }
        header(header: "Location: $url", response_code: $statusCode->value);
        return $statusCode;
    }

    /**
     * Write a response body with Content-Type, Content-Length, and status headers.
     *
     * Call before headers have been sent.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param string $type MIME type, optionally including a charset.
     * @param string $content Response body, sent without escaping.
     * @param StatusCode|int $statusCode Response status, defaulting to 200.
     * @return StatusCode Status sent with the response.
     */
    protected function Content(
        string $type, 
        string $content,
        StatusCode|int $statusCode = StatusCode::Ok,
    ): StatusCode
    {
        if (!($statusCode instanceof StatusCode))
            $statusCode = StatusCode::from(value: $statusCode);

        http_response_code(response_code: $statusCode->value);

        header(header: "Content-Type: $type");
        header(header: "Content-length: " . strlen(string: $content));
        
        echo $content;
        return $statusCode;
    }

    /**
     * Encode a value as JSON and send an application/json response.
     *
     * @throws \JsonException If the value cannot be encoded.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param mixed $object Value to encode with json_encode().
     * @param StatusCode|int $statusCode Response status, defaulting to 200.
     * @return StatusCode Status sent with the response.
     */
    protected function Json(
        mixed $object, 
        StatusCode|int $statusCode = StatusCode::Ok,
    ): StatusCode
    {
        return $this->JsonWithOptions($object, $statusCode);
    }

    /**
     * Encode a value as JSON and send an application/json response.
     *
     * @throws \JsonException If the value cannot be encoded.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param mixed $object Value to encode with json_encode().
     * @param StatusCode|int $statusCode Response status, defaulting to 200.
     * @param int $flags Bitmask of JSON encoding options; JSON_THROW_ON_ERROR is always enabled.
     * @return StatusCode Status sent with the response.
     */
    protected function JsonWithOptions(
        mixed $object,
        StatusCode|int $statusCode = StatusCode::Ok,
        int $flags = 0,
    ): StatusCode
    {
        return $this->Content(
            type: 'application/json', 
            content: json_encode(value: $object, flags: $flags | JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
        );
    }

    /**
     * Read a file and send its contents, or return a not-found response.
     *
     * The entire file is loaded into memory. The MIME type is detected with fileinfo.
     * @throws \ValueError If an integer status has no matching StatusCode case.
     *
     * @param string $file_path Path to the file to read; use a trusted, readable file path.
     * @param bool $additional_headers Whether to add attachment and Last-Modified headers.
     * @param StatusCode|int $statusCode Response status for an existing file, defaulting to 200.
     * @return StatusCode Response status, or NotFound if the path does not exist.
     */
    protected function File(
        string $file_path, 
        bool $additional_headers = true,
        StatusCode|int $statusCode = StatusCode::Ok,
    ): StatusCode
    {
        if (!file_exists(filename: $file_path))
        {
            // File not found.
            return $this->NotFound();
        }

        $mime = File::GetMimeType(filename: $file_path);

        if ($additional_headers)
        {
            $last_modified = gmdate(format: 'D, d M Y H:i:s', timestamp: filemtime(filename: $file_path)) . ' GMT';
            $name_parts = explode(
                separator: "/", 
                string: str_replace(
                    search: "\\", 
                    replace: "/", 
                    subject: $file_path
                )
            );
            $actual_file_name = basename(path: urlencode(string: end(array: $name_parts)));
            
            header(header: 'Content-Description: File Transfer');
            header(header: 'Last-Modified: ' . $last_modified);
            header(header: "Content-Disposition: attachment; filename=\"$actual_file_name\"");
        }
        
        return $this->Content(
            type: $mime,
            content: file_get_contents(filename: $file_path),
            statusCode: $statusCode,
        );
    }

    /**
     * Send a not-found response using Shared/Error when available.
     *
     * Falls back to plain text when the error view is absent.
     *
     * @param string|null $message Custom error banner; null selects the default message.
     * @return StatusCode Error response status (normally 404).
     */
    protected function NotFound(?string $message = null): StatusCode
    {
        $errorLayout = 'Shared/Error';

        if ($this->ViewFileName(viewName: $errorLayout) === null)
        {
            // View file not avaible
            return $this->Content(
                type: 'text/plain',
                content: $message ?? 'The requested resource was not found.',
                statusCode: StatusCode::NotFound,
            );
        }

        return $this->Render(
            view: $errorLayout,
            title: 'Error 404',
            data: [
                'main_banner' => empty($message) ? 
                    'The requested resource was not found' :
                    $message,
            ],
            statusCode: StatusCode::NotFound,
        );
    }

    /**
     * Send a unauthorized response using Shared/Error when available.
     *
     * Falls back to plain text when the error view is absent.
     *
     * @param string|null $message Custom error banner; null selects the default message.
     * @return StatusCode Error response status (normally 401).
     */
    protected function NotAuthorized(?string $message = null): StatusCode {
        if ($this->ViewFileName('Shared/Error') === null) {
            return $this->Content('text/plain', $message ?? 'You are not authorized to view this resource', StatusCode::NotAuthorized);
        }

        return $this->Render(
            view: 'Shared/Error',
            title: 'Error 401',
            data: [
                'main_banner' => empty($message) ? 
                    'You are not authorized to view this resource' :
                    $message,
            ],
            statusCode: StatusCode::NotAuthorized,
        );
    }

    /**
     * Send a bad-request response using Shared/Error when available.
     *
     * Falls back to plain text when the error view is absent.
     *
     * @param string|null $message Custom error banner; null selects the default message.
     * @return StatusCode Error response status (normally 400).
     */
    protected function BadRequest(?string $message = null): StatusCode {
        if ($this->ViewFileName('Shared/Error') === null) {
            return $this->Content('text/plain', $message ?? 'There was a problem in your request', StatusCode::BadRequest);
        }

        return $this->Render(
            view: 'Shared/Error',
            title: 'Error 400',
            data: [
                'main_banner' => empty($message) ? 
                    'The was a problem in your request' :
                    $message,
            ],
            statusCode: StatusCode::BadRequest,
        );
    }

    /**
     * Send a server-error response, optionally using Shared/Error.
     *
     * Falls back to plain text when rendering is disabled or the error view is absent.
     * Exception messages may contain sensitive details; control their exposure in production.
     *
     * @param \Throwable|null $ex Exception exposed to the error view; its message becomes the banner.
     * @param bool $render_error Whether to use the error view if it exists.
     * @return StatusCode ServerError (500).
     */
    protected function InternalError(
        ?\Throwable $ex = null,
        bool $render_error = true,
    ): StatusCode {
        $banner = $ex === null ? 
                        'The server encountered an unexpected error' :
                        $ex->getMessage();
        
        if ($render_error && $this->ViewFileName('Shared/Error') !== null)
        {
            return $this->Render(
                view: 'Shared/Error',
                title: 'Error 500',
                data: [
                    'exception' => $ex,
                    'main_banner' => $banner,
                ],
                statusCode: StatusCode::ServerError,
            );
        }

        
        return $this->Content(
            type: 'text/plain',
            content: $banner,
            statusCode: StatusCode::ServerError,
        );
    }
}