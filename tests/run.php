<?php
// Dependency-free regression suite: php tests/run.php
require dirname(__DIR__) . '/vendor/autoload.php';

use Richie314\SimpleMvc\Controllers\Controller;
use Richie314\SimpleMvc\Controllers\Attributes\Attribute;
use Richie314\SimpleMvc\Http\Method;
use Richie314\SimpleMvc\Http\StatusCode;
use Richie314\SimpleMvc\Routers\Router;
use Richie314\SimpleMvc\Utils\Cookie;
use Richie314\SimpleMvc\Utils\File;
use Richie314\SimpleMvc\Utils\Security;

set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function capture(callable $callback): array {
    ob_start();
    try {
        $result = $callback();
        return [$result, ob_get_contents()];
    } finally {
        ob_end_clean();
    }
}
#[\Attribute]
class FailingAttribute implements Attribute {
    public function DoWork(Controller $controller, string $action, array $parameters): void {
        throw new RuntimeException('attribute failed');
    }
}
class TestController extends Controller {
    public function index(string $first_name = 'world'): StatusCode {
        return $this->Content('text/plain', 'Hello ' . $first_name);
    }
    public function page(): StatusCode {
        return $this->Render('Home/index', ['message' => 'body', 'view_file' => '/missing'], 'Title');
    }
    #[FailingAttribute]
    public function guarded(): StatusCode { return StatusCode::Ok; }
    public function broken(): StatusCode { throw new RuntimeException('action failed'); }
    public function jsonAction(): StatusCode { return $this->JsonWithOptions(['url' => '/demo'], flags: JSON_UNESCAPED_SLASHES); }
    public function invalidJson(): StatusCode { return $this->Json([NAN]); }
    public function redirectAction(): StatusCode { return $this->Redirect('/target'); }
    public function temporary(): StatusCode { return $this->RedirectWithStatus('/target', StatusCode::SeeOther); }
    public function bad(): StatusCode { return $this->BadRequest(); }
    public function unauthorized(): StatusCode { return $this->NotAuthorized(); }
}
class LegacyController extends Controller {
    protected function Json(mixed $object, StatusCode|int $statusCode = StatusCode::Ok): StatusCode {
        return parent::Json($object, $statusCode);
    }
    protected function Redirect(?string $url): StatusCode { return parent::Redirect($url); }
}
class LegacyCookie extends Cookie {
    public static function Set(string $name, string $value, int $exp, string $path = '/', string $domain = ''): bool {
        return parent::Set($name, $value, $exp, $path, $domain);
    }
    public static function Delete(string $name): bool { return parent::Delete($name); }
}
$root = sys_get_temp_dir() . '/simple-mvc-' . bin2hex(random_bytes(8));
mkdir($root . '/Views/Home', 0777, true);
mkdir($root . '/Views/Shared');
file_put_contents($root . '/Views/Home/index.php', '<?= $message ?>:<?= $site ?>:<?= $title ?>:<?= $P ?>');
file_put_contents($root . '/Views/Shared/Layout.php', '<main><?php $RenderBody(); ?></main>');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = $root;
try {
    $router = new Router(applicationInstallationPath: $root);
    $router->AddController(TestController::class, '/');
    $router->SetDefaultVariable('site', 'example');
    foreach (['/page', '/page'] as $uri) {
        [$status, $body] = capture(fn() => $router->Dispatch($uri));
        check($status === StatusCode::Ok && $body === '<main>body:example:Title:</main>', 'Rendering and variable isolation');
    }
    [$status, $body] = capture(fn() => $router->Dispatch('/?first-name=Ada'));
    check($body === 'Hello Ada' && $status === StatusCode::Ok, 'GET named parameters');
    foreach (['/missing?unknown=value', '/missing?again=value'] as $uri) {
        [$status] = capture(fn() => $router->Dispatch($uri));
        check($status === StatusCode::NotFound && http_response_code() === 404, '404 with query and repeated errors');
    }
    foreach (['/broken', '/guarded', '/invalidJson'] as $uri) {
        [$status] = capture(fn() => $router->Dispatch($uri));
        check($status === StatusCode::ServerError && http_response_code() === 500, 'Error fallback: ' . $uri);
    }
    [$status, $body] = capture(fn() => $router->Dispatch('/jsonAction'));
    check($body === '{"url":"/demo"}' && http_response_code() === 200, 'JSON flags and response status reset');
    http_response_code(200);
    [$status] = capture(fn() => $router->Dispatch('/redirectAction'));
    check($status === StatusCode::MovedPermanently && http_response_code() === 302, 'Legacy redirect behavior');
    foreach (['/temporary' => 303, '/bad' => 400, '/unauthorized' => 401] as $uri => $code) {
        [$status] = capture(fn() => $router->Dispatch($uri));
        check($status->value === $code && http_response_code() === $code, 'HTTP status: ' . $uri);
    }
    file_put_contents($root . '/Views/Shared/Error.php', 'Error:<?= $status_code ?>');
    [$status, $body] = capture(fn() => $router->Dispatch('/broken'));
    check($status === StatusCode::ServerError && $body === '<main>Error:500</main>', 'Custom error view');
    unlink($root . '/Views/Shared/Error.php');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['first-name' => 'Grace'];
    $postRouter = new Router(applicationInstallationPath: $root);
    $postRouter->AddController(TestController::class, '/');
    [, $body] = capture(fn() => $postRouter->Dispatch('/'));
    check($body === 'Hello Grace', 'POST named parameters');
    foreach (['PATCH', 'OPTIONS'] as $method) {
        $_SERVER['REQUEST_METHOD'] = $method;
        new Router(applicationInstallationPath: $root);
    }
    $_COOKIE = [];
    check(Cookie::Get('missing') === null, 'Missing cookie');
    check(Cookie::SetWithOptions('example', 'value', 60, secure: true, sameSite: 'Lax'), 'Cookie options');
    check(Cookie::DeleteWithOptions('example', path: '/app', domain: 'example.com'), 'Scoped cookie deletion');
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    check(Security::GetIpAddress() === '127.0.0.1', 'CLI IP lookup');
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.1';
    check(Security::GetIpAddress(true) === '192.0.2.1', 'Cloudflare header lookup');
    foreach (['', '0', 'secret\n'] as $value) {
        file_put_contents($root . '/secret', $value);
        $_ENV['TEST_SECRET_FILE'] = $root . '/secret';
        check(Security::LoadEnvironmentOfFromFile('TEST_SECRET', 'fallback') === $value, 'Secret file content');
    }
    // Exact routing under a prefix and replacement of an explicitly registered route.
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $prefixed = new Router('/app', $root);
    $prefixed->AddRouteAction('/hello', TestController::class, 'broken');
    $prefixed->AddRouteAction('/hello', TestController::class, 'index');
    [$status, $body] = capture(fn() => $prefixed->Dispatch('/app/hello?first-name=Linus'));
    check($status === StatusCode::Ok && $body === 'Hello Linus', 'Prefixed explicit route replacement');
    foreach (['/hello', '/app/hello/'] as $uri) {
        [$status] = capture(fn() => $prefixed->Dispatch($uri));
        check($status === StatusCode::NotFound, 'Exact prefix and trailing slash matching');
    }
    try {
        new Router('missing-leading-slash', $root);
        check(false, 'Invalid prefix must throw');
    } catch (InvalidArgumentException $expected) {
        check(str_contains($expected->getMessage(), 'missing-leading-slash'), 'Invalid prefix diagnostic');
    }
    unset($_SERVER['REQUEST_METHOD']);
    try {
        new Router(applicationInstallationPath: $root);
        check(false, 'Missing HTTP method must throw');
    } catch (BadMethodCallException $expected) {
        check(str_contains($expected->getMessage(), 'REQUEST_METHOD'), 'Missing method diagnostic');
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';

    // Utility edge cases used by applications consuming the library.
    $hash = Security::Hash('correct horse battery staple');
    check(Security::TestPassword('correct horse battery staple', $hash), 'Password round trip');
    check(!Security::TestPassword('wrong password', $hash), 'Reject incorrect password');
    check(Security::Hash('') === '' && !Security::TestPassword('', $hash), 'Empty password behavior');
    check(!Security::TestPassword('password', ''), 'Empty hash behavior');
    foreach ([199 => false, 200 => false, 399 => false, 400 => true, 499 => true, 500 => true, 599 => true, 600 => false] as $code => $isError) {
        check(StatusCode::IsError($code) === $isError, 'Status classification: ' . $code);
    }
    check(StatusCode::IsError(StatusCode::NotFound), 'Enum error classification');
    check(!StatusCode::IsError(StatusCode::Created), 'Enum success classification');
    $_COOKIE = ['empty' => '', 'zero' => '0', 'null' => null, 'other' => 'keep'];
    check(Cookie::Exists('empty') && Cookie::Exists('zero'), 'Empty and zero cookies exist');
    check(!Cookie::Exists('null') && !Cookie::Exists(''), 'Null and unnamed cookies do not exist');
    check(!Cookie::DeleteIfItIs('missing', 'value'), 'Conditional delete of missing cookie');
    check(Cookie::DeleteIfItIs('other', 'different'), 'Nonmatching conditional cookie deletion');
    check(Cookie::Get('other') === 'keep', 'Cookie reads retain incoming values');
    check(Cookie::DeleteIfExists('missing'), 'Deleting an absent cookie succeeds');
    check(!Cookie::Set('', 'value', 60), 'Reject empty cookie name');

    check(File::Size($root . '/missing') === '', 'Missing file size');
    file_put_contents($root . '/secret', '');
    check(File::Size($root . '/secret') === '0 B', 'Empty file size');
    file_put_contents($root . '/secret', str_repeat('a', 1536));
    check(File::Exists($root . '/secret') && File::Size($root . '/secret') === '1 KB', 'Whole-unit file size');
    check(File::GetMimeType($root . '/secret') === 'text/plain', 'Text MIME detection');
    check(!File::Exists($root . '/Views'), 'Directories are not files');
    $listing = File::ListDirectory($root . '/Views');
    check($listing['Home']['index.php'] === 'index.php', 'Nested directory listing');
    check(!isset($listing['.']) && !isset($listing['..']), 'Directory listing omits dot entries');
    check(File::Delete($root . '/secret') && !File::Delete($root . '/secret'), 'File deletion and absent file behavior');

    $_FILES = [];
    check(File::UploadingFiles('attachment') === [], 'Missing upload field');
    $upload = ['name' => 'note.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/upload', 'error' => UPLOAD_ERR_OK, 'size' => 12];
    $_FILES['attachment'] = $upload;
    check(File::UploadingFiles('attachment') === [$upload], 'Single upload normalization');
    $failedUpload = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
    foreach (array_keys($upload) as $key) {
        $_FILES['attachments'][$key] = [$upload[$key], $failedUpload[$key]];
    }
    check(File::UploadingFiles('attachments') === [$upload, $failedUpload], 'Multiple uploads retain error records');
    unset($_ENV['UNSET_TEST_VALUE'], $_ENV['UNSET_TEST_VALUE_FILE']);
    check(Security::LoadEnvironmentOfFromFile('UNSET_TEST_VALUE', 'fallback') === 'fallback', 'Missing environment fallback');
    $_ENV['TEST_SECRET'] = 'direct';
    check(Security::LoadEnvironmentOfFromFile('TEST_SECRET') === 'direct', 'Environment precedence');
} finally {
    foreach (['/Views/Home/index.php', '/Views/Shared/Layout.php', '/Views/Shared/Error.php', '/secret'] as $path) {
        if (is_file($root . $path)) unlink($root . $path);
    }
    rmdir($root . '/Views/Home');
    rmdir($root . '/Views/Shared');
    rmdir($root . '/Views');
    rmdir($root);
}
echo "All regression checks passed.\n";
