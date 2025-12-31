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

    private function DefaultLayoutFileName(): string
    {
        return $this->ApplicationInstallationPath . '/Views/Shared/Layout.php';
    }

    protected \mysqli|false $DB = false;


    protected ?User $User;

    protected Method $RequestMethod;

    public function __construct(
        string $requestPath,
        Method $requestMethod,

        string $pathPrefix = '',
        ?string $applicationInstallationPath = null,

        ?User $user = null,
        ?\mysqli $connection = null,
    ) {
        $this->RequestPath = $requestPath;
        $this->RequestMethod = $requestMethod;

        $this->PathPrefix = $pathPrefix;

        $this->ApplicationInstallationPath = empty($applicationInstallationPath) ? 
            $_SERVER['DOCUMENT_ROOT'] : 
            $applicationInstallationPath;
        while (str_ends_with(haystack: $this->ApplicationInstallationPath, needle: '/'))
            $this->ApplicationInstallationPath = substr(
                string: $this->ApplicationInstallationPath, 
                offset: 0, 
                length: strlen(string: $this->ApplicationInstallationPath) - 1,
            );

        if ($connection !== null) {
            $this->DB = $connection;
        }
        $this->User = $user;
    }

    /**
     * If the user variable is not set interrupts the flow and redirects to login page
     * @param bool $require_admin Require that the user is also an admin user. Calls NotAuthorized() if not
     * @return User The loaded user
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
     * Renders a particular view.
     * If the associated file in not found calls NotFound()
     * @param string $view View file name
     * @param array $data Variables to pass to the rendering php. Variables $user and $staff are automatically loaded
     * @param string $title Title of the page, is passed also as $tile variable
     * @param StatusCode|int $statusCode Override the status code of the response
     * @return StatusCode The status code of the response
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

        if ($statusCode !== StatusCode::Ok)
            http_response_code(response_code: $statusCode->value);


        extract(array: array_merge($data, [
            'title' => $title,
            'status_code' => $statusCode->value,
            'user' => $this->User,
            'P' => $this->PathPrefix,
            'RenderBody' => function () use ($view_file): void {
                require_once $view_file;
            },
        ]));

        require_once $layout_file_path;
        return $statusCode;
    }

    /**
     * Redirectes to the specified url
     * @param string $url
     * @return StatusCode
     */
    protected function Redirect(?string $url): StatusCode {
        if (empty($url))
        {
            $url = $this->PathPrefix . '/';
        }
        header(header: "Location: $url");
        return StatusCode::MovedPermanently;
    }

    /**
     * Raw string as response
     * @param string $type Mime type of the content
     * @param string $content Actual content
     * @param StatusCode|int $statusCode The status code to return (200 by default)
     * @return StatusCode The status code of the response
     */
    protected function Content(
        string $type, 
        string $content,
        StatusCode|int $statusCode = StatusCode::Ok,
    ): StatusCode
    {
        if (!($statusCode instanceof StatusCode))
            $statusCode = StatusCode::from(value: $statusCode);

        if ($statusCode !== StatusCode::Ok)
            http_response_code(response_code: $statusCode->value);

        header(header: "Content-Type: $type");
        header(header: "Content-length: " . strlen(string: $content));
        
        echo $content;
        return $statusCode;
    }

    /**
     * Render an object as json and print it to the stream
     * @param mixed $object The object. Will be rendered via json_encode()
     * @param StatusCode|int $statusCode The status code to return (200 by default)
     * @return StatusCode The status code of the response (200)
     */
    protected function Json(
        mixed $object, 
        StatusCode|int $statusCode = StatusCode::Ok,
    ): StatusCode
    {
        return $this->Content(
            type: 'application/json', 
            content: json_encode(value: $object),
            statusCode: $statusCode,
        );
    }

    /**
     * Loads a file and prints it to the stream.
     * Calls NotFound if the file can't be found
     * @param string $file_path
     * @param bool $additional_headers
     * @param StatusCode|int $statusCode The status code to return (200 by default)
     * @return StatusCode
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

    protected function NotFound(?string $message = null): StatusCode
    {
        define(constant_name: 'ERROR_LAYOUT', value: 'Shared/Error');

        if ($this->ViewFileName(viewName: ERROR_LAYOUT) === null)
        {
            // View file not avaible
            return $this->Content(
                type: 'text/plain',
                content: $message ?? 'The requested resource was not found.',
                statusCode: StatusCode::NotFound,
            );
        }

        return $this->Render(
            view: ERROR_LAYOUT,
            title: 'Error 404',
            data: [
                'main_banner' => empty($message) ? 
                    'The requested resource was not found' :
                    $message,
            ],
            statusCode: StatusCode::NotFound,
        );
    }

    protected function NotAuthorized(?string $message = null): StatusCode {
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

    protected function BadRequest(?string $message = null): StatusCode {
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

    protected function InternalError(
        ?\Throwable $ex = null,
        bool $render_error = true,
    ): StatusCode {
        $banner = $ex === null ? 
                        'The server encountered an unexpected error' :
                        $ex->getMessage();
        
        if ($render_error)
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