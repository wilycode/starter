<?php

declare(strict_types=1);

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Bootstrap\LocaleFactory;
use App\Bootstrap\SessionFactory;
use App\Http\Controller\HomeController;
use App\Http\Controller\ContactController;
use App\Http\Controller\AdminController;
use App\Http\Controller\GuidesController;
use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Middleware\PageCacheMiddleware;
use App\Http\Pagination\PageNumber;
use App\Support\BaseUrl;
use App\Support\Date;
use App\Support\FlashMessage;
use App\Support\Redirector;
use App\Support\RouteUrlGenerator;
use App\Support\Storage;
use App\Support\LocaleContext;
use App\Support\ViewRenderer;
use App\Form\ContactForm;
use App\Model\ContactMessage;
use App\Repository\ContactMessageRepository;
use WilyCode\FormCsrf\CsrfTokenManagerInterface;
use WilyCode\FormCsrf\SessionCsrfTokenManager;
use WilyCode\FileCache\FileCache;
use Psr\SimpleCache\CacheInterface;
use WilyCode\Session\Php\NativeSession;
use WilyCode\Session\SessionInterface;
use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\ModelMetadata;
use WilyCode\Database\Query\Statement\QueryInterface;
use WilyCode\Database\QueryExecutor\QueryExecutorInterface;
use WilyCode\RouterAttribute\AttributeRouteCollection;
use WilyCode\Router\Router;
use WilyCode\Router\RouteCollection;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Extension\TemplateTranslationExtension;
use WilyCode\Translation\LocaleFormatter\LocaleFormatterFactory;
use WilyCode\Template\Extension\TemplateStringFilterExtension;
use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\TranslationArray\ArrayTranslator;

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);

$checks = 0;
$check = static function (bool $value, string $name) use (&$checks): void {
    $checks++;
    if (!$value) throw new RuntimeException('FAILED: ' . $name);
};

// Migration SQL is kept next to the PHP migration, without a database/ wrapper.
$migrationName = '20261027_000001_create_contact_message';
$migrationsDir = $root . '/migrations';
$check(is_dir($migrationsDir) && !is_dir($root . '/database'), 'migrations live directly in the project root');
$check(is_file($migrationsDir . '/' . $migrationName . '.php'), 'PHP migration exists');
$check(is_file($migrationsDir . '/' . $migrationName . '.up.sql'), 'up SQL migration exists');
$check(is_file($migrationsDir . '/' . $migrationName . '.down.sql'), 'down SQL migration exists');
$check(str_contains((string) file_get_contents($migrationsDir . '/' . $migrationName . '.up.sql'), 'CREATE TABLE contact_message'), 'up SQL creates contact_message');
$check(str_contains((string) file_get_contents($migrationsDir . '/' . $migrationName . '.down.sql'), 'DROP TABLE contact_message'), 'down SQL drops contact_message');

// Storage creates persistent project directories when they do not yet exist.
$storageFixture = sys_get_temp_dir() . '/wily-starter-storage-test-' . bin2hex(random_bytes(8));
if (!mkdir($storageFixture, 0775, true)) {
    throw new RuntimeException('Unable to create storage test fixture.');
}
try {
    $projectStorage = Storage::directory($storageFixture);
    $check($projectStorage === $storageFixture . '/var', 'storage prefers project var directory');
    $check(is_dir($projectStorage . '/cache/templates'), 'storage creates template cache directory');
    $check(is_dir($projectStorage . '/logs'), 'storage creates log directory');
    $check(is_writable($projectStorage . '/cache/templates') && is_writable($projectStorage . '/logs'), 'project storage is writable');
    rmdir($projectStorage . '/logs');
    $check(Storage::directory($storageFixture) === $projectStorage && is_dir($projectStorage . '/logs'), 'storage recreates missing directories');
} finally {
    if (is_dir($storageFixture . '/var/cache/templates')) rmdir($storageFixture . '/var/cache/templates');
    if (is_dir($storageFixture . '/var/cache')) rmdir($storageFixture . '/var/cache');
    if (is_dir($storageFixture . '/var/logs')) rmdir($storageFixture . '/var/logs');
    if (is_dir($storageFixture . '/var')) rmdir($storageFixture . '/var');
    rmdir($storageFixture);
}

// If the project's var path cannot be a directory, fall back outside the project.
$fallbackFixture = sys_get_temp_dir() . '/wily-starter-fallback-test-' . bin2hex(random_bytes(8));
if (!mkdir($fallbackFixture, 0775, true)) {
    throw new RuntimeException('Unable to create fallback test fixture.');
}
file_put_contents($fallbackFixture . '/var', 'blocked');
try {
    $fallbackStorage = Storage::directory($fallbackFixture);
    $check($fallbackStorage !== $fallbackFixture . '/var', 'storage uses temporary directory when project var is unavailable');
    $check(is_writable($fallbackStorage . '/cache/templates') && is_writable($fallbackStorage . '/logs'), 'temporary storage has writable cache and logs');
} finally {
    if (is_dir($fallbackStorage ?? '')) {
        // This path is exclusively generated for the random test fixture.
        rmdir($fallbackStorage . '/cache/templates');
        rmdir($fallbackStorage . '/cache');
        rmdir($fallbackStorage . '/logs');
        rmdir($fallbackStorage);
    }
    unlink($fallbackFixture . '/var');
    rmdir($fallbackFixture);
}

// Configure native-session cookies before any test opens the session.
$bootstrapRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/contact');
$bootstrapRequest = $bootstrapRequest->withQueryParams(['lang' => 'pl']);
SessionFactory::configure($bootstrapRequest, new BaseUrl('http://localhost:8090'));
$check(session_get_cookie_params()['path'] === '/', 'session factory configures cookie path');
$check(session_cache_limiter() === '', 'session does not overwrite PSR-7 cache headers');
$check(session_get_cookie_params()['httponly'] === true, 'session factory configures HttpOnly cookies');
session_start();
$check(session_status() === PHP_SESSION_ACTIVE, 'test bootstrap starts session explicitly');
$services = ContainerFactory::create($root, $bootstrapRequest);
$projectCache = $services->get(CacheInterface::class);
$check($projectCache->set('storage-path-check', 'ok', 60), 'page cache can write to project directory');
$hash = hash('sha256', 'storage-path-check');
$path = $root . '/var/cache/pages/' . substr($hash, 0, 2) . '/' . substr($hash, 2);
$check(is_file($path), 'page cache file is visible under var/cache/pages');
$projectCache->delete('storage-path-check');
// The request container owns one router, used by both templates and redirects.
$testRouter = $services->get(Router::class);
$testRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
$check($services->get(CacheInterface::class) instanceof FileCache, 'DI exposes filesystem cache as PSR-16 CacheInterface');

// Flash messages queue in order and are consumed together exactly once.
$flash = new FlashMessage(new NativeSession());
$flash->success('Saved');
$flash->error('Could not save');
$flash->warning('Incomplete');
$flash->info('News');
$flash->set('Another message', 'danger'); // Bootstrap-compatible alias.
$expectedFlash = [
    ['type' => 'success', 'message' => 'Saved'],
    ['type' => 'error', 'message' => 'Could not save'],
    ['type' => 'warning', 'message' => 'Incomplete'],
    ['type' => 'info', 'message' => 'News'],
    ['type' => 'error', 'message' => 'Another message'],
];
$check($flash->pullAll() === $expectedFlash, 'flash queue preserves all messages and their types');
$check($flash->pullAll() === [], 'flash queue is consumed once');
$invalidFlashType = false;
try {
    $flash->set('Not valid', 'custom-css');
} catch (InvalidArgumentException) {
    $invalidFlashType = true;
}
$check($invalidFlashType, 'unknown flash types are rejected');

// Form CSRF is backed by a session and is transparent to business data.
$csrf = new SessionCsrfTokenManager(new NativeSession());
$form = new ContactForm($csrf);
$token = $form->getField('_csrf')->getToken();
$check(strlen($token) === 64, 'CSRF token is generated');
$check($form->getField('_csrf')->isTransparent(), 'CSRF field is transparent');
$check($csrf->isValid('contact', $token), 'CSRF token is session-backed');
$check(!$csrf->isValid('other-form', $token), 'CSRF is scoped to the form ID');

$form->setData(['_csrf' => $token, 'name' => ' Ada ', 'email' => 'ada@example.org', 'message' => '  This is a full test message.  ']);
$check($form->isValid(), 'valid contact form with CSRF');
$check($form->getData()['name'] === 'Ada', 'trim filter');
$check(array_keys($form->getData()) === ['name', 'email', 'message'], 'CSRF excluded from getData');
$check(!array_key_exists('_csrf', $form->getDataWithExcludes([])), 'CSRF excluded from getDataWithExcludes');
$check($form->getDataByName('_csrf') === $token, 'submitted CSRF remains accessible by name');
$check(!$form->getField('_csrf')->getErrors()->hasErrors(), 'valid CSRF has no field error');

$form->setData(['_csrf' => 'tampered', 'name' => 'Ada', 'email' => 'ada@example.org', 'message' => 'This is a full test message.']);
$check(!$form->isValid(), 'invalid CSRF fails validation');
$check($form->getField('_csrf')->getErrors()->hasErrors(), 'invalid CSRF is reported as field error');
$check($form->getField('_csrf')->getToken() === $token, 'invalid submitted token is never rendered as a new token');
$check(!array_key_exists('_csrf', $form->getData()), 'bad CSRF also stays transparent');

$form->setData(['name' => 'Ada', 'email' => 'ada@example.org', 'message' => 'This is a full test message.']);
$check(!$form->isValid() && $form->getField('_csrf')->getErrors()->hasErrors(), 'missing CSRF fails closed');

$form->setData(['_csrf' => $token, 'name' => 'X', 'email' => 'broken', 'message' => 'No']);
$check(!$form->isValid(), 'invalid contact form');
$check(!$form->getField('_csrf')->getErrors()->hasErrors(), 'CSRF valid even when other fields fail');
$check($form->getErrors()['name']->hasErrors(), 'name validation');
$check($form->getErrors()['email']->hasErrors(), 'email validation');
$check($form->getErrors()['message']->hasErrors(), 'message validation');

$form->setData(['_csrf' => $token, 'name' => ['bad'], 'email' => ['bad'], 'message' => ['bad']]);
$check(!$form->isValid(), 'arrays rejected by StringValidator');
$check($form->getErrors()['name']->hasErrors(), 'array name validation error');
$check($form->getErrors()['email']->hasErrors(), 'array email validation error');
$check($form->getErrors()['message']->hasErrors(), 'array message validation error');

$pl = new ArrayTranslator($root . '/languages', new Pl(), new En());
$en = new ArrayTranslator($root . '/languages', new En());
$check($pl->translate('home.index', 'title') === 'Witaj, WilyCode!', 'Polish translation');
$check($en->translate('home.index', 'title') === 'Hello, WilyCode!', 'English translation');

// Validation is a standalone translation resource, with short keys to avoid duplication.
$check(is_file($root . '/languages/pl/validation.php') && is_file($root . '/languages/en/validation.php'), 'validation.php exists in both languages');
$check(!is_file($root . '/languages/pl/forms.php') && !is_file($root . '/languages/en/forms.php'), 'old forms.php resources removed');
$check($pl->translate('validation', 'email.invalid') === 'Wpisz poprawny adres e-mail.', 'validation resource supports short keys');
$validationPlMessages = require $root . '/languages/pl/validation.php';
$validationEnMessages = require $root . '/languages/en/validation.php';
$check(array_keys($validationPlMessages) === array_keys($validationEnMessages), 'PL/EN validation message keys match');
$check(!array_filter(array_keys($validationPlMessages), static fn (string $key): bool => str_starts_with($key, 'validation.')), 'validation resource avoids duplicate prefixes');
// Verify that shared PL/EN resources cover built-in validation codes (and optional integrations).
$expectedValidationCodes = [
    'validation.not_empty.required',
    'validation.string.invalid',
    'validation.string_length.too_short',
    'validation.string_length.too_long',
    'validation.email.invalid',
    'validation.url.invalid',
    'validation.http_origin.invalid_type',
    'validation.http_origin.invalid',
    'validation.uuid.invalid',
    'validation.ip.invalid',
    'validation.integer.invalid',
    'validation.integer.too_small',
    'validation.integer.too_large',
    'validation.float.invalid',
    'validation.float.too_small',
    'validation.float.too_large',
    'validation.boolean.invalid',
    'validation.array.invalid_type',
    'validation.array_items.invalid_type',
    'validation.array_custom_values.invalid_type',
    'validation.array_length.too_few',
    'validation.array_length.too_many',
    'validation.in_options.invalid_option',
    'validation.json.invalid',
    'validation.json.expected_array',
    'validation.json.expected_object',
    'validation.regex.invalid_type',
    'validation.regex.invalid_pattern',
    'validation.regex.pattern_mismatch',
    'validation.date.invalid_format',
    'validation.date.too_early',
    'validation.date.too_late',
    'validation.time.invalid',
    'validation.file.upload_error',
    'validation.file.too_small',
    'validation.file.too_large',
    'validation.file.invalid_type',
    'validation.file.invalid_extension',
    'validation.field_compare.mismatch',
    'validation.csrf_token.invalid',
    'validation.callback.invalid',
    'validation.recaptcha.failed',
    'validation.recaptcha.unavailable',
];
foreach ($expectedValidationCodes as $code) {
    $check($pl->translate('validation', substr($code, strlen('validation.'))) !== $code, 'PL translation for ' . $code);
    $check($en->translate('validation', substr($code, strlen('validation.'))) !== $code, 'EN translation for ' . $code);
}
$validationFormatterPl = new \App\Form\ValidationErrorFormatter($pl);
$validationFormatterEn = new \App\Form\ValidationErrorFormatter($en);
$check($validationFormatterPl->format(new \WilyCode\Validator\Error('validation.string_length.too_short', ['min' => 10])) === 'Wpisz co najmniej 10 znaków.', 'validation message interpolation PL');
$check($validationFormatterEn->format(new \WilyCode\Validator\Error('validation.string_length.too_long', ['max' => 254])) === 'Enter no more than 254 characters.', 'validation message interpolation EN');
$check(str_contains($validationFormatterPl->format(new \WilyCode\Validator\Error('validation.file.too_large', ['sizeMax' => 2, 'sizeUnit' => \WilyCode\Validators\Enums\FileSizeUnit::MB])), '2 MB'), 'file upload options support backed enums');
$check($validationFormatterEn->format(new \WilyCode\Validator\Error('validation.unknown.code')) === $en->translate('validation', 'custom.invalid'), 'unknown codes show a safe generic error');


// The Application bootstrap builds a reusable, request-scoped container.
$check($services->get(TemplateEngine::class) instanceof TemplateEngine, 'bootstrap registers configured template engine');
$localizedHtml = $services->get(TemplateEngine::class)->render('home.index', [
    'description' => 'Wily Starter',
]);
$check(str_contains($localizedHtml, 'Witaj, WilyCode!'), 'bootstrap honors selected locale in templates');
$check($services->get(RouteUrlGenerator::class)->generate('contact.index', fragment: 'details') === '/contact#details', 'bootstrap registers named-route URL generator');
$check($services->get(TemplateEngine::class)->callFunction('route', ['contact.index', [], ['lang' => 'en', 'next' => '/path?a=1&b=2'], 'cos_tam']) === '/contact?lang=en&amp;next=%2Fpath%3Fa%3D1%26b%3D2#cos_tam', 'template route function safely escapes query separators for HTML');
$check($services->get(TemplateEngine::class)->callFunction('route', ['home.index', [], [], 'sekcja 1']) === '/#sekcja%201', 'template route function encodes fragment');
$registeredSession = $services->get(SessionInterface::class);
$check($registeredSession instanceof NativeSession, 'session interface resolves to native session adapter');
$check($registeredSession === $services->get(SessionInterface::class), 'session interface resolves to the same singleton');
$preferences = $registeredSession->withNamespace('preferences');
$preferences->set('locale', 'en');
$invalidLocale = LocaleFactory::create($root, $bootstrapRequest->withQueryParams(['lang' => ['pl']]), $registeredSession);
$check($invalidLocale->code === 'en', 'non-string language selection is rejected without a redundant type check');
$check($services->get(FlashMessage::class) instanceof FlashMessage, 'bootstrap registers flash messages');
$services->get(FlashMessage::class)->success('Saved in session');
$check($registeredSession->withNamespace('flash')->get('messages') === [['type' => 'success', 'message' => 'Saved in session']], 'flash service uses registered session');
$check($services->get(FlashMessage::class)->pullAll()[0]['message'] === 'Saved in session', 'flash queue is consumed through shared session');
$check($services->get(LocaleContext::class)->code === 'pl', 'locale context is available for action injection');
$check($services->get(CsrfTokenManagerInterface::class) instanceof SessionCsrfTokenManager, 'bootstrap registers CSRF token manager');
$bootstrapToken = $services->get(CsrfTokenManagerInterface::class)->getToken('__bootstrap_form_test');
$check($registeredSession->withNamespace('csrf')->get(hash('sha256', '__bootstrap_form_test')) === $bootstrapToken, 'CSRF manager uses registered session');
$check($services->has(DatabaseInterface::class), 'bootstrap registers database service without connecting');
$preferences->remove('locale');

// Rendering is tied to the matched route name; explicit overrides work outside dispatch.
$views = $services->get(ViewRenderer::class);
$check($views instanceof ViewRenderer, 'view renderer is autowired using the request router');
$manualResponse = $views->render(
    ['description' => 'Standalone view'],
    template: 'home.index',
    status: HttpStatus::Ok,
    headers: ['Cache-Control' => 'no-store'],
);
$check(str_contains((string) $manualResponse->getBody(), 'Standalone view'), 'explicit template works without a matched route');
$check($manualResponse->getHeaderLine('Cache-Control') === 'no-store', 'renderer forwards custom headers');
$publicResponse = $views->render(
    ['description' => 'Cache policy override'],
    template: 'home.index',
    headers: ['Cache-Control' => 'public, max-age=60'],
);
$check($publicResponse->getHeaderLine('Cache-Control') === 'public, max-age=60', 'renderer supports explicit caching headers');
$missingRouteRejected = false;
try {
    $views->render();
} catch (LogicException) {
    $missingRouteRejected = true;
}
$check($missingRouteRejected, 'implicit render without matched route fails with a helpful exception');

// GET renders by its route name; invalid POST explicitly renders the GET template.
$homeResponse = $testRouter->dispatch(new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/'));
$check($homeResponse->getStatusCode() === 200, 'home route returns an HTML response');
$check($testRouter->getCurrentRoute()?->getName() === 'home.index', 'matched route name is available for rendering');
$check(str_contains((string) $homeResponse->getBody(), 'Independent, reusable PHP components'), 'home route renders localized Template String demo');
$check($homeResponse->getHeaderLine('Content-Type') === 'text/html; charset=utf-8', 'renderer keeps HTML content type');
$overrideResponse = $views->render(['form' => new ContactForm($services->get(CsrfTokenManagerInterface::class))], template: 'contact.index', status: HttpStatus::Forbidden);
$check($overrideResponse->getStatusCode() === 403, 'explicit template override preserves an error status');
$check(str_contains((string) $overrideResponse->getBody(), 'name="_csrf"'), 'explicit override can render a different template than the route');
$contactGet = $testRouter->dispatch(new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/contact'));
$check($contactGet->getStatusCode() === 200, 'GET contact route renders form');
$check($testRouter->getCurrentRoute()?->getName() === 'contact.index', 'GET contact route uses view name');
$check(str_contains((string) $contactGet->getBody(), 'name="_csrf"'), 'GET shows CSRF field');
// Redirector reuses router URL generation and supports query parameters.
$redirector = $services->get(Redirector::class);
$check($redirector->route('contact.index')->getHeaderLine('Location') === '/contact', 'redirector uses a route name');
$check($redirector->route('contact.index', query: ['lang' => 'pl', 'next' => '/home'])->getHeaderLine('Location') === '/contact?lang=pl&next=%2Fhome', 'redirector uses RFC3986 query encoding');
$check($redirector->route('contact.index', query: ['lang' => 'pl'], fragment: 'cos_tam')->getHeaderLine('Location') === '/contact?lang=pl#cos_tam', 'redirect supports query parameters and a fragment');
$check($redirector->route('contact.index', fragment: 'sekcja 1')->getHeaderLine('Location') === '/contact#sekcja%201', 'fragment is URL-encoded');
$check($redirector->route('contact.index', status: HttpStatus::Found)->getStatusCode() === 302, 'redirector accepts a custom status');
$nestedRedirector = new Redirector(new RouteUrlGenerator($testRouter, new BaseUrl('http://localhost/wily-starter/public')), $services->get(\WilyCode\Http\Response\ResponseFactory::class));
$check($nestedRedirector->route('contact.index')->getHeaderLine('Location') === '/wily-starter/public/contact', 'redirector supports subdirectory deployments');
$unknownRedirectRejected = false;
try {
    $redirector->route('not.existing');
} catch (InvalidArgumentException) {
    $unknownRedirectRejected = true;
}
$check($unknownRedirectRejected, 'redirector rejects unknown route names');

$dynamicRouter = new Router($services);
$dynamicRouter->addCollection((new RouteCollection())->get('demo.user', '/users/{id}', static fn (): null => null, rules: ['id' => IntRule::class]));
$dynamicRedirector = new Redirector(new RouteUrlGenerator($dynamicRouter, new BaseUrl('http://localhost:8090')), $services->get(\WilyCode\Http\Response\ResponseFactory::class));
$check($dynamicRedirector->route('demo.user', ['id' => 15], ['tab' => 'profile'])->getHeaderLine('Location') === '/users/15?tab=profile', 'redirector supports path parameters and query together');
$check($dynamicRedirector->route('demo.user', ['id' => 15], ['tab' => 'profile'], fragment: 'details')->getHeaderLine('Location') === '/users/15?tab=profile#details', 'redirector supports route params, query and fragment');
// Compile real @route() calls, including four arguments and named arguments.
$routesFixture = new TemplateEngine(
    new TemplateLoader(new FileLocator(['' => $root . '/tests/fixtures'])),
    $root . '/var/cache/templates',
);
$routesFixture->addFunction('route', [(new RouteUrlGenerator($dynamicRouter, new BaseUrl('http://localhost/wily-starter/public'))), 'html']);
$routesHtml = $routesFixture->render('named_route');
$check(str_contains($routesHtml, 'href="/wily-starter/public/users/15?tab=a%26b&amp;q=two%20words#cos_tam"'), 'compiled @route handles all four arguments and safe HTML');
$check(str_contains($routesHtml, 'href="/wily-starter/public/users/15?tab=profile#details"'), 'compiled @route accepts named parameters');
$check($nestedRedirector->route('contact.index', query: ['lang' => 'en'], fragment: 'form')->getHeaderLine('Location') === '/wily-starter/public/contact?lang=en#form', 'nested redirect supports query and fragment');

$contactPost = (new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact'))
    ->withParsedBody(['_csrf' => 'tampered', 'name' => 'Ada', 'email' => 'ada@example.org', 'message' => 'A sufficiently long contact message.']);
$invalidContactPost = $testRouter->dispatch($contactPost);
$check($invalidContactPost->getStatusCode() === 422, 'POST contact route renders invalid form');
$check($testRouter->getCurrentRoute()?->getName() === 'contact.store', 'POST contact route uses its own name');
$check(str_contains((string) $invalidContactPost->getBody(), $services->get(LocaleContext::class)->translator->translate('validation', 'csrf_token.invalid')), 'POST renders CSRF error using the GET template');

// An optional PSR-15 middleware protects only the route that opts in.
$authResponses = $services->get(\WilyCode\Http\Response\ResponseFactory::class);
$authHandler = new class implements \Psr\Http\Server\RequestHandlerInterface {
    public int $calls = 0;

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $this->calls++;
        return new \Nyholm\Psr7\Response(200, [], 'Middleware passed');
    }
};
$auth = new BasicAuthMiddleware($authResponses, 'tester', 'password:with:colon', true);
$localAuthRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/admin/dashboard', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
$disabledAuthResponse = (new BasicAuthMiddleware($authResponses, '', ''))->process($localAuthRequest, $authHandler);
$check($disabledAuthResponse->getStatusCode() === 404, 'Basic Auth demo is disabled without credentials');
$check($disabledAuthResponse->getHeaderLine('Cache-Control') === 'no-store', 'disabled Basic Auth is never cached');
$unauthenticated = $auth->process($localAuthRequest, $authHandler);
$check($unauthenticated->getStatusCode() === 401, 'Basic Auth rejects requests without credentials');
$check(str_starts_with($unauthenticated->getHeaderLine('WWW-Authenticate'), 'Basic '), 'Basic Auth sends browser challenge');
$check($unauthenticated->getHeaderLine('Cache-Control') === 'no-store', 'Basic Auth challenges are not cached');
$invalidAuthorization = $localAuthRequest->withHeader('Authorization', 'Basic definitely:invalid');
$check($auth->process($invalidAuthorization, $authHandler)->getStatusCode() === 401, 'Basic Auth rejects malformed authorization');
$wrongAuthorization = $localAuthRequest->withHeader('Authorization', 'Basic ' . base64_encode('tester:wrong'));
$check($auth->process($wrongAuthorization, $authHandler)->getStatusCode() === 401, 'Basic Auth rejects incorrect password');
$wrongUsername = $localAuthRequest->withHeader('Authorization', 'Basic ' . base64_encode('another:password:with:colon'));
$check($auth->process($wrongUsername, $authHandler)->getStatusCode() === 401, 'Basic Auth rejects incorrect username');
$missingColon = $localAuthRequest->withHeader('Authorization', 'Basic ' . base64_encode('tester'));
$check($auth->process($missingColon, $authHandler)->getStatusCode() === 401, 'Basic Auth rejects malformed user/password pair');
$wrongScheme = $localAuthRequest->withHeader('Authorization', 'Bearer anything');
$check($auth->process($wrongScheme, $authHandler)->getStatusCode() === 401, 'Basic Auth rejects non-Basic credentials');
$validAuthorization = $localAuthRequest->withHeader('Authorization', 'Basic ' . base64_encode('tester:password:with:colon'));
$authorized = $auth->process($validAuthorization, $authHandler);
$check($authorized->getStatusCode() === 200, 'Basic Auth passes valid credentials to the handler');
$check($authorized->getHeaderLine('Cache-Control') === 'private, no-store', 'successful Basic Auth responses cannot be stored in HTTP caches');
$cacheableHandler = new class implements \Psr\Http\Server\RequestHandlerInterface {
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        return new \Nyholm\Psr7\Response(200, ['Cache-Control' => 'public, max-age=3600'], 'sensitive');
    }
};
$check($auth->process($validAuthorization, $cacheableHandler)->getHeaderLine('Cache-Control') === 'private, no-store', 'auth middleware overrides cacheable handler responses');
$check($authHandler->calls === 1, 'Basic Auth does not call the handler for rejected requests');
$remoteHttp = new \Nyholm\Psr7\ServerRequest('GET', 'http://example.org/admin/dashboard');
$remoteDenied = $auth->process($remoteHttp, $authHandler);
$check($remoteDenied->getStatusCode() === 403, 'Basic Auth blocks remote non-HTTPS connections');
$check($remoteDenied->getHeaderLine('Cache-Control') === 'no-store', 'insecure HTTP denials are never cached');
$check(!$remoteDenied->hasHeader('WWW-Authenticate'), 'no Basic Auth challenge on insecure HTTP');
$check($auth->process($remoteHttp->withHeader('X-Forwarded-Proto', 'https'), $authHandler)->getStatusCode() === 403, 'untrusted forwarded scheme must not bypass HTTPS requirement');
$spoofedHost = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/admin/dashboard', serverParams: ['REMOTE_ADDR' => '203.0.113.20']);
$check($auth->process($spoofedHost, $authHandler)->getStatusCode() === 403, 'spoofing localhost Host does not bypass HTTP restriction');
$spoofedPeerHeader = $remoteHttp->withHeader('Host', 'localhost')->withHeader('X-Forwarded-For', '127.0.0.1');
$check($auth->process($spoofedPeerHeader, $authHandler)->getStatusCode() === 403, 'untrusted forwarded peer cannot bypass HTTP restriction');
$remoteHttps = (new \Nyholm\Psr7\ServerRequest('GET', 'https://example.org/admin/dashboard'))
    ->withHeader('Authorization', 'Basic ' . base64_encode('tester:password:with:colon'));
$httpsAuthorized = $auth->process($remoteHttps, $authHandler);
$check($httpsAuthorized->getStatusCode() === 200, 'Basic Auth accepts credentials over HTTPS');
$check($httpsAuthorized->getHeaderLine('Cache-Control') === 'private, no-store', 'HTTPS admin response cannot be stored in HTTP caches');
$check($authHandler->calls === 2, 'HTTPS success reaches the next middleware or controller');

// Wily Run / Podman: Apache/PHP sees the container bridge as REMOTE_ADDR.
$podmanRequest = new \Nyholm\Psr7\ServerRequest(
    'GET', 'http://wily-starter.localhost:8888/admin/dashboard',
    serverParams: ['REMOTE_ADDR' => '10.88.0.1'],
);
$devAuth = new BasicAuthMiddleware($authResponses, 'tester', 'password:with:colon', true);
$check($devAuth->process($podmanRequest, $authHandler)->getStatusCode() === 401, 'local Wily Run network returns Basic Auth challenge, not 403');
$check(str_starts_with($devAuth->process($podmanRequest, $authHandler)->getHeaderLine('WWW-Authenticate'), 'Basic '), 'local Wily Run challenge asks browser for credentials');
$check($devAuth->process($podmanRequest->withHeader('Authorization', 'Basic ' . base64_encode('tester:password:with:colon')), $authHandler)->getStatusCode() === 200, 'local Wily Run accepts valid Basic Auth credentials');
$productionAuth = new BasicAuthMiddleware($authResponses, 'tester', 'password:with:colon', false);
$check($productionAuth->process($podmanRequest, $authHandler)->getStatusCode() === 403, 'production blocks unencrypted bridge access');
$check($productionAuth->process($localAuthRequest, $authHandler)->getStatusCode() === 403, 'production requires HTTPS even when peer is loopback');
$check($devAuth->process($podmanRequest->withUri(new \Nyholm\Psr7\Uri('http://example.net/admin/dashboard')), $authHandler)->getStatusCode() === 403, 'local environment does not allow HTTP to ordinary hostnames');
$publicPeer = new \Nyholm\Psr7\ServerRequest('GET', 'http://wily-starter.localhost:8888/admin/dashboard', serverParams: ['REMOTE_ADDR' => '203.0.113.20']);
$check($devAuth->process($publicPeer, $authHandler)->getStatusCode() === 403, 'local host cannot bypass restriction from public peer');
$check($devAuth->process(new \Nyholm\Psr7\ServerRequest('GET', 'http://wily-starter.localhost:8888/admin/dashboard', serverParams: ['REMOTE_ADDR' => '172.20.0.1']), $authHandler)->getStatusCode() === 401, 'Docker private network is allowed in local mode');
$check($devAuth->process(new \Nyholm\Psr7\ServerRequest('GET', 'http://wily-starter.localhost:8888/admin/dashboard', serverParams: ['REMOTE_ADDR' => '192.168.65.1']), $authHandler)->getStatusCode() === 401, 'private LAN bridge is allowed in local mode');

// Route middleware is resolved from the same container as the controller.
$originalUser = getenv('BASIC_AUTH_USERNAME');
$originalPassword = getenv('BASIC_AUTH_PASSWORD');
$originalEnvironment = getenv('APP_ENV');
try {
    putenv('BASIC_AUTH_USERNAME=demo_user');
    putenv('BASIC_AUTH_PASSWORD=demo_secret');
    putenv('APP_ENV=local');
    $authServices = ContainerFactory::create($root, $bootstrapRequest);
    $authServices->set(QueryExecutorInterface::class, new class implements QueryExecutorInterface {
        public function execute(QueryInterface $query): int { throw new RuntimeException('Read-only route'); }
        public function fetch(QueryInterface $query): array { return []; }
        public function fetchAll(QueryInterface $query): array { return []; }
        public function fetchColumn(QueryInterface $query, int $columnIndex = 0): mixed { return 0; }
        public function getLastInsertId(): string { return '0'; }
        public function transaction(callable $callback): mixed { return $callback($this); }
        public function inTransaction(): bool { return false; }
    });
    $authRouter = $authServices->get(Router::class);
    $authRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
    $oldAdminRedirect = $authRouter->dispatch($localAuthRequest->withUri(new \Nyholm\Psr7\Uri('http://localhost:8090/admin')));
    $check($oldAdminRedirect->getStatusCode() === 302, 'public /admin entry redirects without a Basic Auth challenge');
    $check($oldAdminRedirect->getHeaderLine('Location') === '/admin/dashboard', 'public /admin points to protected dashboard');
    $check(!$oldAdminRedirect->hasHeader('WWW-Authenticate'), 'redirect does not broaden browser Basic Auth scope');
    $routeUnauthenticated = $authRouter->dispatch($localAuthRequest);
    $check($routeUnauthenticated->getStatusCode() === 401, 'route attribute runs Basic Auth middleware');
    $routeAuthenticated = $authRouter->dispatch(
        $localAuthRequest->withHeader('Authorization', 'Basic ' . base64_encode('demo_user:demo_secret')),
    );
    $check($routeAuthenticated->getStatusCode() === 200, 'correct Basic Auth credentials reach admin controller');
$check($routeAuthenticated->getHeaderLine('Cache-Control') === 'private, no-store', 'protected route does not depend on controller cache headers');
    $check(str_contains((string) $routeAuthenticated->getBody(), $authServices->get(LocaleContext::class)->translator->translate('admin.dashboard', 'empty')), 'protected admin renders the empty inbox');
    $check(str_contains((string) $routeAuthenticated->getBody(), 'href="/admin/dashboard"'), 'admin link appears in menu when configured');
    $configuredAuth = $authServices->get(BasicAuthMiddleware::class);
    $check($configuredAuth->process($podmanRequest, $authHandler)->getStatusCode() === 401, 'container enables bridge-compatible Basic Auth in APP_ENV=local');
} finally {
    $originalUser === false ? putenv('BASIC_AUTH_USERNAME') : putenv('BASIC_AUTH_USERNAME=' . $originalUser);
    $originalPassword === false ? putenv('BASIC_AUTH_PASSWORD') : putenv('BASIC_AUTH_PASSWORD=' . $originalPassword);
    $originalEnvironment === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $originalEnvironment);
}

// Opt-in full-page middleware: cache the HTML body, not the controller or browser.
$pageCacheDirectory = sys_get_temp_dir() . '/wily-page-cache-test-' . bin2hex(random_bytes(8));
$pageResponseFactory = $authResponses;
$pageHandler = new class($pageResponseFactory) implements \Psr\Http\Server\RequestHandlerInterface {
    public int $calls = 0;

    public function __construct(private \WilyCode\Http\Response\ResponseFactory $responses) {}

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        return $this->responses->html('<p>Rendered ' . ++$this->calls . '</p>');
    }
};
$pageCacheRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/guides');
$pageCacheStore = new FileCache($pageCacheDirectory);
$pageCache = new PageCacheMiddleware($pageCacheStore, $pageResponseFactory, new LocaleContext('en', $en));
$pageMiss = $pageCache->process($pageCacheRequest, $pageHandler);
$check($pageMiss->getHeaderLine('X-Page-Cache') === 'MISS', 'page cache populates cache on first GET');
$check($pageMiss->getHeaderLine('Cache-Control') === 'no-store', 'page cache disallows browser caching');
$check((string) $pageMiss->getBody() === '<p>Rendered 1</p>', 'page cache initial response comes from handler');
$pageHit = $pageCache->process($pageCacheRequest, $pageHandler);
$check($pageHit->getHeaderLine('X-Page-Cache') === 'HIT', 'page cache serves subsequent GET from disk');
$check((string) $pageHit->getBody() === '<p>Rendered 1</p>' && $pageHandler->calls === 1, 'cache hit skips controller');
// A new middleware instance should still find the same entry on disk.
$anotherPageCache = new PageCacheMiddleware(new FileCache($pageCacheDirectory), $pageResponseFactory, new LocaleContext('en', $en));
$check($anotherPageCache->process($pageCacheRequest, $pageHandler)->getHeaderLine('X-Page-Cache') === 'HIT', 'page cache survives request-scoped middleware instances');
$polishPageCache = new PageCacheMiddleware($pageCacheStore, $pageResponseFactory, new LocaleContext('pl', $pl));
$pagePl = $polishPageCache->process($pageCacheRequest->withQueryParams(['lang' => 'pl']), $pageHandler);
$check($pagePl->getHeaderLine('X-Page-Cache') === 'MISS', 'Polish page does not share English HTML');
$check($polishPageCache->process($pageCacheRequest, $pageHandler)->getHeaderLine('X-Page-Cache') === 'HIT', 'Polish cache hits independently');
$check($pageCache->process($pageCacheRequest->withHeader('Authorization', 'Bearer secret'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'auth requests bypass cache');
$check($pageCache->process($pageCacheRequest->withUri(new \Nyholm\Psr7\Uri('http://localhost:8090/guides?user=1')), $pageHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'unexpected query bypasses cache');
$check($pageCache->process($pageCacheRequest->withHeader('Cache-Control', 'no-cache'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'HIT', 'browser refresh uses origin cache');
$check($pageCache->process($pageCacheRequest->withHeader('Pragma', 'no-cache'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'HIT', 'legacy browser refresh also uses origin cache');
$check($pageCache->process($pageCacheRequest->withHeader('Cache-Control', 'no-cache')->withHeader('Pragma', 'no-cache'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'HIT', 'combined browser refresh headers still yield HIT');
$check($pageCache->process($pageCacheRequest->withHeader('Cache-Control', 'no-store'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'explicit no-store bypasses origin cache');
$check($pageCache->process($pageCacheRequest->withMethod('POST'), $pageHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'POST is never cached');
$check((new PageCacheMiddleware($pageCacheStore, $pageResponseFactory, new LocaleContext('en', $en), 0))
    ->process($pageCacheRequest, $pageHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'TTL 0 disables caching');
// Invalidation through the PSR-16 store regenerates the page.
$check($pageCacheStore->clear(), 'cache store can be cleared with the standard interface');
$invalidated = $pageCache->process($pageCacheRequest, $pageHandler);
$check($invalidated->getHeaderLine('X-Page-Cache') === 'MISS', 'clearing cache regenerates HTML');
$check((string) $invalidated->getBody() !== '<p>Rendered 1</p>', 'cleared entry is not reused');
// TTL expiry is implemented by the cache package, not HTTP middleware.
$ttlDirectory = $pageCacheDirectory . '-ttl';
$ttlStore = new FileCache($ttlDirectory);
$shortCache = new PageCacheMiddleware($ttlStore, $pageResponseFactory, new LocaleContext('en', $en), 1);
$check($shortCache->process($pageCacheRequest, $pageHandler)->getHeaderLine('X-Page-Cache') === 'MISS', 'short TTL entry is initially saved');
sleep(2);
$check($shortCache->process($pageCacheRequest, $pageHandler)->getHeaderLine('X-Page-Cache') === 'MISS', 'expired HTML is regenerated');
$ttlStore->clear();
rmdir($ttlDirectory);
// Never cache login pages, redirects, 404s, cookie-setting or private responses.
$restrictedHandler = new class($pageResponseFactory) implements \Psr\Http\Server\RequestHandlerInterface {
    public function __construct(private \WilyCode\Http\Response\ResponseFactory $responses) {}

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        return $this->responses->html('Private', headers: ['Set-Cookie' => 'session=secret; HttpOnly']);
    }
};
$privateCache = new PageCacheMiddleware(new FileCache($pageCacheDirectory . '-private'), $pageResponseFactory, new LocaleContext('en', $en));
$check($privateCache->process($pageCacheRequest, $restrictedHandler)->getHeaderLine('X-Page-Cache') === 'BYPASS', 'Set-Cookie responses are never cached');
$check(!is_dir($pageCacheDirectory . '-private'), 'private responses create no cache directory');
$pageCacheStore->clear();
rmdir($pageCacheDirectory);

// Application-level policy, not individual controller headers, protects dynamic responses.
$application = new Application($root);
$appHome = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/'));
$check($appHome->getStatusCode() === 200, 'application serves the home route');
$check($appHome->getHeaderLine('Cache-Control') === 'no-store', 'application disables caching for HTML');
$check($appHome->getHeaderLine('X-Content-Type-Options') === 'nosniff', 'application sends nosniff header');
$appContact = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/contact'));
$check($appContact->getHeaderLine('Cache-Control') === 'no-store', 'contact form is not cached');
$appMissing = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/nonexistent'));
$check($appMissing->getStatusCode() === 404, 'application handles unknown routes');
$check($appMissing->getHeaderLine('Cache-Control') === 'no-store', '404 errors are not cached');

$postPayload = [
    'name' => 'Ada',
    'email' => 'ada@example.org',
    'message' => 'A valid message that must not be saved with a bad token.',
];
$csrfPost = new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact');
$csrfPost = $csrfPost->withParsedBody(['_csrf' => 'invalid', ...$postPayload]);
$csrfResponse = $application->handle($csrfPost);
$check($csrfResponse->getStatusCode() === 422, 'invalid CSRF returns 422 without database access');
$check(str_contains((string) $csrfResponse->getBody(), $pl->translate('validation', 'csrf_token.invalid')), 'invalid CSRF displays a friendly form-level message');
$check(str_contains((string) $csrfResponse->getBody(), 'notice notice-error'), 'invalid CSRF notice is displayed above the form');
$check(!str_contains((string) $csrfResponse->getBody(), 'name="_csrf" value="invalid"'), 'invalid submitted token is not reflected into the hidden field');
$missingCsrfPost = (new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact'))
    ->withParsedBody($postPayload);
$missingCsrfResponse = $application->handle($missingCsrfPost);
$check($missingCsrfResponse->getStatusCode() === 422, 'missing CSRF returns 422 without database access');
$check(str_contains((string) $missingCsrfResponse->getBody(), $pl->translate('validation', 'csrf_token.invalid')), 'missing CSRF displays a friendly form-level message');
$check($csrfResponse->getHeaderLine('Cache-Control') === 'no-store', 'invalid form is not cached');
$validToken = $services->get(CsrfTokenManagerInterface::class)->getToken('contact');
$invalidPost = (new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact'))
    ->withParsedBody(['_csrf' => $validToken, ...$postPayload, 'email' => 'bad-address']);
$invalidResponse = $application->handle($invalidPost);
$check($invalidResponse->getStatusCode() === 422, 'invalid fields return 422 without database access');
$check(!str_contains((string) $invalidResponse->getBody(), $pl->translate('validation', 'csrf_token.invalid')), 'valid CSRF does not show a security notice for ordinary field errors');
$check($invalidResponse->getHeaderLine('Cache-Control') === 'no-store', '422 response is not cached');
$check(str_contains((string) $invalidResponse->getBody(), $pl->translate('validation', 'email.invalid')), 'POST shows the actual email validator message');
$shortPost = (new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact'))
    ->withParsedBody(['_csrf' => $validToken, 'name' => 'A', 'email' => 'ada@example.org', 'message' => 'Short']);
$shortResponse = $application->handle($shortPost);
$check($shortResponse->getStatusCode() === 422, 'too short fields return 422');
$check(str_contains((string) $shortResponse->getBody(), $pl->translate('validation', 'string_length.too_short', ['min' => 2])), 'name shows min value from validator');
$check(str_contains((string) $shortResponse->getBody(), $pl->translate('validation', 'string_length.too_short', ['min' => 10])), 'message shows its own min value');


// Base URL works both at a virtual-host root and below a local WampServer directory.
$atRoot = new BaseUrl('http://localhost:8090');
$subdir = new BaseUrl('http://localhost/wily-starter/public');
$check($atRoot->prefix() === '' && $atRoot->path('/contact') === '/contact', 'root base URL');
$check($subdir->prefix() === '/wily-starter/public', 'subdirectory prefix');
$check($subdir->path('/contact') === '/wily-starter/public/contact', 'subdirectory route URL');
$check($subdir->relativePath('/wily-starter/public/contact') === '/contact', 'subdirectory incoming contact route');
$check($subdir->relativePath('/wily-starter/public/') === '/', 'subdirectory incoming home route');
$check($subdir->relativePath('/wily-starter/publicity/contact') === '/wily-starter/publicity/contact', 'prefix boundary');

// Routes are discovered from controller method attributes; no routes/web.php file.
$routeCollection = new AttributeRouteCollection([$root . '/src/Http/Controller']);
$registered = $routeCollection->getRoutes();
$definitions = [];
foreach ($registered as $route) {
    $definitions[$route->getMethod()->value . ' ' . $route->getPath()] = [
        $route->getName(),
        $route->getHandler(),
    ];
}
$check(count($registered) === 7, 'seven discovered attribute routes');
$check(
    ($definitions['GET /'] ?? null) === ['home.index', [HomeController::class, 'index']],
    'GET home route attribute',
);
$check(
    ($definitions['GET /contact'] ?? null) === ['contact.index', [ContactController::class, 'index']],
    'GET contact route attribute',
);
$check(
    ($definitions['POST /contact'] ?? null) === ['contact.store', [ContactController::class, 'store']],
    'POST contact route has a separate name and action',
);
$check(
    ($definitions['GET /admin/dashboard'] ?? null) === ['admin.dashboard', [AdminController::class, 'dashboard']],
    'GET /admin/dashboard is a protected, named action',
);
$check(
    ($definitions['GET /guides'] ?? null) === ['guides.index', [GuidesController::class, 'index']],
    'GET public knowledge base is a real cached page',
);
$check(
    ($definitions['GET /admin'] ?? null) === ['admin.index', [AdminController::class, 'index']],
    'GET /admin is a redirect without Basic Auth',
);
$redirectRoute = array_values(array_filter(
    $registered,
    static fn ($route): bool => $route->getName() === 'admin.index',
))[0] ?? null;
$check($redirectRoute?->getMiddlewares() === [], 'GET /admin redirect does not request Basic Auth');
$pageCacheRoute = array_values(array_filter(
    $registered,
    static fn ($route): bool => $route->getName() === 'guides.index',
))[0] ?? null;
$check($pageCacheRoute?->getMiddlewares() === [PageCacheMiddleware::class], 'public knowledge base uses page cache middleware');
$check(!isset($definitions['GET /examples/page-cache']), 'obsolete page-cache example URL is not registered');
$authDemoRoute = array_values(array_filter(
    $registered,
    static fn ($route): bool => $route->getName() === 'admin.dashboard',
))[0] ?? null;
$check($authDemoRoute?->getMiddlewares() === [BasicAuthMiddleware::class], 'Basic Auth protects the admin route');

$guideController = new GuidesController();
$guideReflection = new ReflectionMethod(GuidesController::class, 'index');
$check($guideReflection->getName() === 'index', 'knowledge base has normal index action');

$engine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $root . '/templates', 'fixtures' => $root . '/tests/fixtures'])), $root . '/var/cache/templates');
$engine->addExtension(new TemplateStringFilterExtension());
$localeFormatters = new LocaleFormatterFactory($root . '/vendor/wilycode/translation-languages/src/resources/locales', 'en-US');
$engine->addExtension(new TemplateTranslationExtension(translator: $en, formatter: $localeFormatters->forCode('en-US')));
$engine->addFilter('validation_error', [(new \App\Form\ValidationErrorFormatter($en)), 'format']);
$engine->addFunction('route', [(new RouteUrlGenerator($testRouter, new BaseUrl('http://localhost:8090'))), 'html']);
$engine->share('appName', 'Wily Starter');
$engine->share('locale', 'en');
$engine->share('basePath', '');
$engine->share('adminEnabled', false);
$engine->share('flash', $flash);
$engine->addExtension(new \WilyCode\Template\Extension\TemplateAssetExtension(new \WilyCode\Template\Assets\Assets($root . '/public')));
$html = $engine->render('home.index');
$check(str_contains($html, 'Hello, WilyCode!'), 'home template rendering');
$check(str_contains($html, '/assets/css/app.css'), 'asset rendering');
$check(str_contains($html, 'data-theme-menu') && str_contains($html, 'data-theme-trigger'), 'appearance menu is rendered in navigation');
$check(str_contains($html, 'theme-icon-auto') && str_contains($html, 'theme-icon-light') && str_contains($html, 'theme-icon-dark'), 'appearance selector has Auto/Light/Dark icons');
$check(str_contains($html, 'Independent, reusable PHP components'), 'English Template String filter demo is localized');
$check(!str_contains($html, 'components for routing'), 'English filter demo does not narrow the ecosystem to routing');
$plEngine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $root . '/templates'])), $root . '/var/cache/templates');
$plEngine->addExtension(new TemplateStringFilterExtension());
$plEngine->addExtension(new TemplateTranslationExtension(translator: $pl, formatter: $localeFormatters->forCode('pl-PL')));
$plEngine->addFunction('route', [(new RouteUrlGenerator($testRouter, new BaseUrl('http://localhost:8090'))), 'html']);
$plEngine->share('appName', 'Wily Starter');
$plEngine->share('locale', 'pl');
$plEngine->share('basePath', '');
$plEngine->share('adminEnabled', false);
$plEngine->share('flash', $flash);
$plEngine->addExtension(new \WilyCode\Template\Extension\TemplateAssetExtension(new \WilyCode\Template\Assets\Assets($root . '/public')));
$plHome = $plEngine->render('home.index');
$check($pl->translate('global', 'form_errors.title') === 'Formularz zawiera błędy', 'Polish form summary title is available globally');
$check($pl->translate('global', 'form_errors.description') === 'Popraw zaznaczone pola i spróbuj ponownie.', 'Polish form summary description is available globally');
$check(str_contains($plHome, 'Niezależne komponenty PHP'), 'Polish Template String filter demo is translated');
$check(!str_contains($plHome, 'do routingu'), 'Polish filter demo is ecosystem-wide');
$check(!str_contains($plHome, 'Independent, reusable PHP'), 'Polish homepage does not show the English demo');
$check(str_contains($plHome, 'Szablony') && str_contains($plHome, 'Baza danych'), 'Polish feature cards have translated headings');
$check(str_contains($plHome, 'Nawigacja główna'), 'Polish navigation accessibility label is translated');
$check(str_contains($html, 'data-theme-option="auto"') && str_contains($html, '>Auto</span>'), 'automatic theme is available');
$check(str_contains($html, 'data-theme-option="light"') && str_contains($html, '>Light</span>'), 'light theme is available');
$check(str_contains($html, 'data-theme-option="dark"') && str_contains($html, '>Dark</span>'), 'dark theme is available');
$check(str_contains($html, '/assets/js/theme-init.js'), 'theme loads before page styling');
$check(str_contains($html, 'aria-pressed="true"') && str_contains($html, 'theme-option-check'), 'appearance menu exposes the selected theme accessibly');
$check(str_contains($html, 'brand-logo-light') && str_contains($html, 'brand-logo-dark'), 'logo supports both schemes');
$check(str_contains($html, '<span>Wily <span class="brand-light">Starter</span></span>'), 'header branding reads Wily Starter');
$check(!str_contains($html, 'WilyCode <span class="brand-light">Starter'), 'old header branding is not rendered');
$check(str_contains($html, '<title>Wily Starter</title>'), 'browser title reads Wily Starter');
$check(str_contains($plHome, 'Wily Starter — strona główna'), 'Polish brand accessibility label reads Wily Starter');
$check($en->translate('global', 'nav.theme') === 'Appearance', 'English theme label');
$check($pl->translate('global', 'nav.theme') === 'Wygląd', 'Polish theme label');
$check($pl->translate('global', 'theme.auto') === 'Automatyczny', 'Polish automatic theme label');
$check(str_contains(file_get_contents($root . '/public/assets/css/app.css'), 'light-dark('), 'theme colors follow native color-scheme');
$check(str_contains(file_get_contents($root . '/public/assets/css/app.css'), '.form-error-summary:focus'), 'form summary has a visible keyboard focus style');
$check(str_contains(file_get_contents($root . '/public/assets/js/app.js'), "errorSummary.focus()"), 'client JavaScript focuses invalid form summary');

$check(str_contains($html, 'href="/contact"'), 'contact navigation');
$check(str_contains($html, 'href="/guides"'), 'public knowledge base is linked from menu');
$check(str_contains($html, 'href="/"'), 'home links use route names');
$check(str_contains($html, 'href="/contact"'), 'home call-to-action resolves contact route');

// Admin inbox template: escaped content, localized text and pagination.
$adminMessage = (new ContactMessage())
    ->setId(31)
    ->setName('<img src=x onerror=alert(1)>')
    ->setEmail('test@example.org')
    ->setMessage('<script>alert(1)</script>')
    ->setCreatedAt('2026-11-12 20:00:00');
$adminPage = new \WilyCode\Database\Repository\PaginationResult([$adminMessage], 25, 1, 20);
$adminHtml = $engine->render('admin.dashboard', ['messages' => $adminPage]);
$check(str_contains($adminHtml, 'Message inbox'), 'admin English translation renders');
$check(str_contains($adminHtml, 'test@example.org'), 'admin inbox displays stored email');
$check(str_contains($adminHtml, '11/12/2026 20:00:00'), 'admin inbox formats received time in English');
$check(!str_contains($adminHtml, '2026-11-12 20:00:00'), 'admin inbox does not display raw MySQL timestamp');
$check(!str_contains($adminHtml, '<script>') && str_contains($adminHtml, '&lt;script&gt;'), 'message content is escaped');
$check(!str_contains($adminHtml, '<img src=x') && str_contains($adminHtml, '&lt;img'), 'sender names are escaped');
$check(str_contains($adminHtml, '/admin/dashboard?page=2'), 'admin uses named route for pagination');
$check(!str_contains($adminHtml, '/admin/dashboard?page=0'), 'admin does not link before first page');
$check(str_contains($adminHtml, 'aria-label="Pagination"'), 'pagination has accessible global label');
$check(str_contains($adminHtml, 'rel="next"') && str_contains($adminHtml, '>Next</a>'), 'pagination renders translated next link');
$check(str_contains($adminHtml, '>Page 1 / 2</span>'), 'pagination shows current and last page');
$adminEmpty = $engine->render('admin.dashboard', ['messages' => new \WilyCode\Database\Repository\PaginationResult([], 0, 1, 20)]);
$check(str_contains($adminEmpty, 'No messages yet.'), 'admin inbox renders empty state');
$check(!str_contains($adminEmpty, 'class="pagination"'), 'pagination hidden for empty list');
$check(str_contains($adminEmpty, 'href="/contact"'), 'empty inbox still displays contact navigation');
$adminPolish = $services->get(TemplateEngine::class)->render('admin.dashboard', ['messages' => $adminPage]);
$check(str_contains($adminPolish, 'Skrzynka wiadomości'), 'admin Polish translation renders');
$check(str_contains($adminPolish, '12.11.2026 20:00:00'), 'admin inbox formats received time in Polish');
$check(str_contains($adminPolish, 'aria-label="Strony"') && str_contains($adminPolish, '>Następna</a>'), 'pagination translates navigation to Polish');
$paginationSecond = new \WilyCode\Database\Repository\PaginationResult([$adminMessage], 45, 2, 20);
$paginationFixture = $engine->render('fixtures::pagination', ['pages' => $paginationSecond]);
$check(str_contains($paginationFixture, '/admin/dashboard?filter=unread&amp;page=1') && str_contains($paginationFixture, '/admin/dashboard?filter=unread&amp;page=3'), 'reusable pagination preserves filters and generates neighboring pages');
$check(str_contains($paginationFixture, 'rel="prev"') && str_contains($paginationFixture, 'rel="next"'), 'reusable pagination renders both directions on middle pages');
$check(!str_contains($paginationFixture, 'page=99'), 'pagination replaces existing page query parameter');

$guidesEn = $engine->render('guides.index', [
    'generatedAt' => new DateTimeImmutable('2026-11-13 08:00:00'),
]);
$check(str_contains($guidesEn, 'Help and frequently asked questions'), 'knowledge base renders English template');
$check(str_contains($guidesEn, 'How to use the Starter?'), 'knowledge base renders FAQ cards');
$check(str_contains($guidesEn, 'November 13, 2026 08:00:00'), 'knowledge base shows localized English date and time');
$check(!str_contains($guidesEn, '2026-11-13 08:00:00'), 'knowledge base does not show raw SQL-style dates');
$check(str_contains($guidesEn, 'href="/guides"'), 'knowledge base links itself from menu');
$check(str_contains($guidesEn, '<time datetime="2026-11-13T08:00:00+00:00">'), 'generated time includes machine-readable datetime');
$guidesPl = $services->get(TemplateEngine::class)->render('guides.index', [
    'generatedAt' => new DateTimeImmutable('2026-11-13 08:00:00'),
]);
$check(str_contains($guidesPl, 'Pomoc i najczęstsze pytania'), 'knowledge base renders Polish template');
$check(str_contains($guidesPl, 'Jak uruchomić Wily Starter?'), 'knowledge base renders Polish FAQ cards directly in template');
$check(str_contains($guidesPl, '13 listopada 2026 r. 08:00:00'), 'knowledge base shows localized Polish date and time');

$contactForm = new ContactForm($csrf);
$contactHtml = $engine->render('contact.index', [
    'form' => $contactForm,
]);
$check(str_contains($contactHtml, 'name="_csrf" value="' . $token . '"'), 'CSRF hidden input in contact template');
$check(str_contains($contactHtml, 'action="/contact"'), 'form submits to named POST route');
$check(str_contains($contactHtml, 'class="button button-primary" type="submit" data-submit-label>Send message</button>'), 'submit_button renders translated default primary button and preserves data attribute');
$alternateSubmitHtml = $engine->callComponent('form.submit_button', ['submit'], ['variant' => 'secondary']);
$check(str_contains($alternateSubmitHtml, 'class="button button-secondary" type="submit" data-submit-label>'), 'submit_button supports the existing secondary button style');
$check(substr_count($contactHtml, 'name="_csrf"') === 1, 'CSRF component renders one hidden field');
$check(!method_exists(ContactForm::class, 'getCsrfToken') && !method_exists(ContactForm::class, 'hasCsrfError'), 'CSRF convenience methods are no longer duplicated in application forms');
$check(str_contains($contactHtml, 'name="name" type="text"'), 'text_field component renders name input');
$check(str_contains($contactHtml, 'name="email" type="email"'), 'email_field component renders email input');
$check(substr_count($contactHtml, 'type="text"') >= 1 && substr_count($contactHtml, 'type="email"') >= 1, 'text and email wrappers set their HTML types');
$check(str_contains($contactHtml, 'name="message"'), 'textarea_field component renders message textarea');
$check(!str_contains($contactHtml, 'aria-invalid="true"'), 'clean form has no field errors');
$check(!str_contains($contactHtml, 'data-error-summary'), 'clean form does not render an error summary');
$check(!str_contains($contactHtml, 'The form could not be verified.'), 'csrf_error renders nothing for a valid CSRF field');
$numberHtml = $engine->callComponent('form.number_field', [$contactForm->getField('name'), 'name'], ['min' => 0, 'max' => 100, 'step' => 1]);
$check(str_contains($numberHtml, 'type="number"') && str_contains($numberHtml, ' min="0"') && str_contains($numberHtml, ' max="100"') && str_contains($numberHtml, ' step="1"'), 'number_field supports number constraints');
$telHtml = $engine->callComponent('form.tel_field', [$contactForm->getField('name'), 'name'], ['autocomplete' => 'tel']);
$check(str_contains($telHtml, 'type="tel"') && str_contains($telHtml, 'autocomplete="tel"'), 'tel_field renders phone input');
$urlHtml = $engine->callComponent('form.url_field', [$contactForm->getField('name'), 'name']);
$check(str_contains($urlHtml, 'type="url"'), 'url_field renders URL input');
$genericHtml = $engine->callComponent('form.input_field', [$contactForm->getField('name'), 'name'], ['type' => 'search']);
$check(str_contains($genericHtml, 'type="search"'), 'input_field supports custom HTML types');

// Flash is read by the template, not pre-consumed in the controller.
$flash->success('Thank you! Your message was saved successfully.');
$flash->warning('Review the form');
$successHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(str_contains($successHtml, 'Thank you! Your message was saved successfully.'), 'template renders a pending flash message');
$check(str_contains($successHtml, 'notice notice-success') && str_contains($successHtml, 'role="status"'), 'flash component renders success notice with accessibility role');
$check(str_contains($successHtml, 'notice notice-warning') && str_contains($successHtml, 'role="alert"'), 'flash component renders an additional warning');
$check(is_file($root . '/templates/components/message.tpl') && !file_exists($root . '/templates/components/ui.tpl'), 'shared message component replaces ui.tpl');
$refreshHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(!str_contains($refreshHtml, 'Thank you! Your message was saved successfully.') && !str_contains($refreshHtml, 'Review the form'), 'all flash messages disappear on refresh');
$flash->error('<script>alert(1)</script>');
$escapedFlashHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(!str_contains($escapedFlashHtml, '<script>') && str_contains($escapedFlashHtml, '&lt;script&gt;'), 'flash text is HTML-escaped');

$contactForm->setData([
    '_csrf' => $token,
    'name' => 'X',
    'email' => 'invalid-email',
    'message' => 'Too short',
]);
$check(!$contactForm->isValid(), 'component test: invalid form');
$invalidHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(str_contains($invalidHtml, 'data-error-summary') && str_contains($invalidHtml, 'tabindex="-1"'), 'invalid form renders a focusable summary');
$plEngine->addFilter('validation_error', [(new \App\Form\ValidationErrorFormatter($pl)), 'format']);
$plInvalidHtml = $plEngine->render('contact.index', ['form' => $contactForm]);
$check(str_contains($plInvalidHtml, 'class="button button-primary" type="submit" data-submit-label>Wyślij wiadomość</button>'), 'submit_button uses Polish translation in contact form');
$check(str_contains($plInvalidHtml, 'Formularz zawiera błędy') && str_contains($plInvalidHtml, 'Popraw zaznaczone pola'), 'Polish contact page renders translated error summary');
$check(substr_count($invalidHtml, 'data-error-summary') === 1, 'invalid form renders only one summary');
$check(str_contains($invalidHtml, 'There are errors in the form'), 'English form error summary title is translated');
$check(str_contains($invalidHtml, 'Please correct the highlighted fields and try again.'), 'English summary includes helpful instructions');
$check(!str_contains($invalidHtml, 'The form could not be verified.'), 'valid CSRF does not show its error notice');
$check(substr_count($invalidHtml, 'aria-invalid="true"') === 3, 'all three fields display invalid state');
$check(str_contains($invalidHtml, 'id="name-error"'), 'error macro generates linked name error');
$check(str_contains($invalidHtml, 'Enter a valid email address'), 'error macro uses translated message');
$check(str_contains($invalidHtml, 'aria-describedby="message-error"'), 'textarea error is accessible');
$check(str_contains($invalidHtml, 'role="alert"'), 'validation errors are announced');
$contactForm->setData(['_csrf' => 'invalid-token', 'name' => 'Ada', 'email' => 'ada@example.org', 'message' => 'This is a valid contact message.']);
$contactForm->isValid();
$csrfHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(str_contains($csrfHtml, 'The form could not be verified.'), 'friendly CSRF banner renders from field validation');
$check(!str_contains($csrfHtml, 'data-error-summary'), 'CSRF-only failures do not duplicate the CSRF error notice');
$check(substr_count($csrfHtml, 'The form could not be verified.') === 1, 'CSRF error notice is not duplicated');
$customNotice = $engine->callComponent('form.error_notice', [$contactForm->getField('_csrf'), 'csrf_token.invalid']);
$check(str_contains($customNotice, 'notice notice-error') && str_contains($customNotice, 'The form could not be verified.'), 'error_notice works with an explicit field');
$customCsrfNotice = $engine->callComponent('form.csrf_error', [$contactForm], ['fieldName' => '_csrf']);
$check(str_contains($customCsrfNotice, 'The form could not be verified.'), 'csrf_error accepts an explicit CSRF field name');
$check(str_contains($csrfHtml, 'notice notice-error') && str_contains($csrfHtml, 'role="alert"'), 'shared notice component renders error state');
$check(str_contains($csrfHtml, 'name="_csrf" value="' . $token . '"'), 'tampered submitted CSRF not echoed to page');


// Input is read directly from the Field and safely escaped in the component.
$contactForm->setData([
    '_csrf' => $token,
    'name' => '<script>alert(1)</script>',
    'email' => 'a@example.org',
    'message' => 'This is a sufficiently long message.',
]);
$contactForm->isValid();
$escapedHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(!str_contains($escapedHtml, '<script>alert(1)</script>'), 'component escapes field values');
$check(str_contains($escapedHtml, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'component retains escaped input');
$contactForm->setData(['_csrf' => $token, 'name' => ['bad'], 'email' => ['bad'], 'message' => ['bad']]);
$contactForm->isValid();
$malformedHtml = $engine->render('contact.index', ['form' => $contactForm]);
$check(!str_contains($malformedHtml, 'name="name" type="text" value="Array"'), 'component safely handles array input');

// In WampServer the same layout and assets must resolve beneath APP_URL's path.
$nestedEngine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $root . '/templates'])), $root . '/var/cache/templates');
$nestedEngine->addExtension(new TemplateStringFilterExtension());
$nestedEngine->addExtension(new TemplateTranslationExtension($en));
$nestedEngine->addFilter('validation_error', [(new \App\Form\ValidationErrorFormatter($en)), 'format']);
$nestedEngine->addFunction('route', [(new RouteUrlGenerator($testRouter, $subdir)), 'html']);
$nestedEngine->share('appName', 'Wily Starter');
$nestedEngine->share('locale', 'en');
$nestedEngine->share('basePath', $subdir->prefix());
$nestedEngine->share('adminEnabled', false);
$nestedEngine->addExtension(new \WilyCode\Template\Extension\TemplateAssetExtension(new \WilyCode\Template\Assets\Assets(
    publicBasePath: $root . '/public',
    publicBaseUrl: $subdir->prefix(),
)));
$nestedHtml = $nestedEngine->render('home.index');
$check(str_contains($nestedHtml, 'href="/wily-starter/public/contact"'), 'nested contact link');
$check(str_contains($nestedHtml, 'href="/wily-starter/public/"'), 'nested home link');
$check($nestedEngine->callFunction('route', ['contact.index', [], ['a' => '1', 'b' => '2'], 'cos_tam']) === '/wily-starter/public/contact?a=1&amp;b=2#cos_tam', 'nested template link escapes query and supports fragment');
$check(str_contains($nestedHtml, 'href="/wily-starter/public/assets/css/app.css'), 'nested CSS asset');

// The model describes the existing contact_message table without SQL in application code.
$metadata = ModelMetadata::for(ContactMessage::class);
$check($metadata->getTableName() === 'contact_message', 'contact message table metadata');
$check($metadata->getPrimaryKeyDbName() === 'id', 'contact message primary key');
$check($metadata->getDbPropertyName('createdAt') === 'created_at', 'timestamp column mapping');

$messageData = [
    'name' => 'Ada',
    'email' => 'ada@example.org',
    'message' => 'A demonstration contact message.',
];
$now = Date::now();
$check((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now), 'Date::now returns MySQL DATETIME format');
$message = new ContactMessage($messageData)
    ->setCreatedAt('2026-10-27 10:00:00');
$check($message->getCreatedAt() === '2026-10-27 10:00:00', 'fluent timestamp setter');
$check($message->getName() === $messageData['name'], 'model hydrates form keys without manual mapping');
$check(!$message->isPersisted(), 'new model is not persisted');
$check($message->getInsertDbValues() === [
    'name' => 'Ada',
    'email' => 'ada@example.org',
    'message' => 'A demonstration contact message.',
    'created_at' => '2026-10-27 10:00:00',
], 'model produces database insert fields');

// Exercise Repository::saveModel() without requiring a running MySQL server.
$fakeExecutor = new class implements QueryExecutorInterface {
    public ?QueryInterface $lastWrite = null;
    public function execute(QueryInterface $query): int { $this->lastWrite = $query; return 1; }
    public function fetch(QueryInterface $query): array { return []; }
    public function fetchAll(QueryInterface $query): array { return []; }
    public function fetchColumn(QueryInterface $query, int $columnIndex = 0): mixed { return null; }
    public function getLastInsertId(): string { return '42'; }
    public function transaction(callable $callback): mixed { return $callback($this); }
    public function inTransaction(): bool { return false; }
};
$repository = new ContactMessageRepository($fakeExecutor);
$check($repository->getTable() === 'contact_message', 'repository uses mapped table');
$saved = $repository->saveModel($message);
$check($saved === $message && $saved->getId() === 42, 'repository assigns generated primary key');
$check($saved->isPersisted(), 'repository marks saved model persisted');
$check($fakeExecutor->lastWrite instanceof QueryInterface, 'repository delegates SQL to query executor');

// The protected admin route reads through the repository only after Basic Auth.
$adminExecutor = new class implements QueryExecutorInterface {
    public int $reads = 0;
    public function execute(QueryInterface $query): int { throw new RuntimeException('Admin is read-only'); }
    public function fetch(QueryInterface $query): array { return []; }
    public function fetchAll(QueryInterface $query): array {
        $this->reads++;
        return [['id' => '31', 'name' => 'Ada', 'email' => 'ada@example.org',
            'message' => 'A stored contact message', 'created_at' => '2026-11-12 20:00:00']];
    }
    public function fetchColumn(QueryInterface $query, int $columnIndex = 0): mixed { $this->reads++; return 25; }
    public function getLastInsertId(): string { return '0'; }
    public function transaction(callable $callback): mixed { return $callback($this); }
    public function inTransaction(): bool { return false; }
};
$adminRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/admin/dashboard', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
$adminServices = ContainerFactory::create($root, $adminRequest);
$adminServices->set(QueryExecutorInterface::class, $adminExecutor);
$adminServices->set(BasicAuthMiddleware::class, new BasicAuthMiddleware($adminServices->get(\WilyCode\Http\Response\ResponseFactory::class), 'tester', 'password', true));
$adminRouter = $adminServices->get(Router::class);
$adminRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
$legacyAdminRequest = $adminRequest->withUri(new \Nyholm\Psr7\Uri('http://localhost:8090/admin'));
$legacyAdminResponse = $adminRouter->dispatch($legacyAdminRequest);
$check($legacyAdminResponse->getStatusCode() === 302 && $legacyAdminResponse->getHeaderLine('Location') === '/admin/dashboard', 'legacy admin entry point redirects only');
$check(!$legacyAdminResponse->hasHeader('WWW-Authenticate'), 'legacy admin redirect does not challenge');
$check($adminExecutor->reads === 0, 'legacy redirect never queries private messages');
$deniedAdmin = $adminRouter->dispatch($adminRequest);
$check($deniedAdmin->getStatusCode() === 401, 'admin requires Basic Auth');
$check($adminExecutor->reads === 0, 'unauthorized admin request never queries database');
$authorizedRequest = $adminRequest->withHeader('Authorization', 'Basic ' . base64_encode('tester:password'));
$allowedAdmin = $adminRouter->dispatch($authorizedRequest);
$check($allowedAdmin->getStatusCode() === 200, 'valid Basic Auth unlocks the dashboard');
$check(str_contains((string) $allowedAdmin->getBody(), 'ada@example.org'), 'admin renders messages from repository');
$check($allowedAdmin->getHeaderLine('Cache-Control') === 'private, no-store', 'admin response is not shared or cached');
$check($adminExecutor->reads === 2, 'admin uses pagination count and message query');
$secondPageRequest = $authorizedRequest->withUri(new \Nyholm\Psr7\Uri('http://localhost:8090/admin?page=2'))->withQueryParams(['page' => '2']);
$secondPage = $adminRouter->dispatch($secondPageRequest);
$check(str_contains((string) $secondPage->getBody(), '/admin/dashboard?page=1'), 'admin pagination links to preceding page');
$check(!str_contains((string) $secondPage->getBody(), '/admin/dashboard?page=3'), 'admin pagination stops at final page');


// Reusable query pagination: invalid or dangerous values safely fall back to page one.
$pageRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/admin/dashboard');
foreach ([
    [[], 1],
    [['page' => '1'], 1],
    [['page' => '25'], 25],
    [['page' => '1000000'], 1000000],
    [['page' => '0'], 1],
    [['page' => '-1'], 1],
    [['page' => '1000001'], 1],
    [['page' => 'x'], 1],
    [['page' => ['3']], 1],
    [['page' => '999999999999999999999999999999'], 1],
] as [$params, $expected]) {
    $check(PageNumber::fromRequest($pageRequest->withQueryParams($params)) === $expected,
        'pagination resolves query page safely: ' . json_encode($params));
}

// A valid POST exercises the separate store action without a running database.
$successfulServices = ContainerFactory::create($root, $bootstrapRequest);
$successfulServices->set(QueryExecutorInterface::class, $fakeExecutor);
$successfulRouter = $successfulServices->get(Router::class);
$successfulRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
$successRequest = (new \Nyholm\Psr7\ServerRequest('POST', 'http://localhost:8090/contact'))
    ->withParsedBody([
        '_csrf' => $csrf->getToken('contact'),
        'name' => 'Ada',
        'email' => 'ada@example.org',
        'message' => 'A valid contact message saved by the store action.',
    ]);
$fakeExecutor->lastWrite = null;
$successResponse = $successfulRouter->dispatch($successRequest);
$check($successResponse->getStatusCode() === 303, 'valid POST redirects after saving');
$check($successResponse->getHeaderLine('Location') === '/contact', 'valid POST redirects back to contact');
$check($fakeExecutor->lastWrite instanceof QueryInterface, 'valid POST persists message through the repository');
$check($successfulServices->get(FlashMessage::class)->pullAll() === [['type' => 'success', 'message' => $successfulServices->get(LocaleContext::class)->translator->translate('contact.index', 'success')]], 'valid POST sets a translated success flash');
$check($message->getDirtyDbValues() === [], 'saved model change snapshot is clean');

// Flash feedback is visible after a redirect in a *new* request, then consumed.
$successfulServices->get(FlashMessage::class)->success('Visible once after redirect');
$redirectGetRequest = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost:8090/contact');
$afterRedirectServices = ContainerFactory::create($root, $redirectGetRequest);
$afterRedirectRouter = $afterRedirectServices->get(Router::class);
$afterRedirectRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
$afterRedirectResponse = $afterRedirectRouter->dispatch($redirectGetRequest);
$check(str_contains((string) $afterRedirectResponse->getBody(), 'Visible once after redirect'), 'new request renders queued flash feedback');
$refreshedServices = ContainerFactory::create($root, $redirectGetRequest);
$refreshedRouter = $refreshedServices->get(Router::class);
$refreshedRouter->addCollection(new AttributeRouteCollection([$root . '/src/Http/Controller']));
$refreshedResponse = $refreshedRouter->dispatch($redirectGetRequest);
$check(!str_contains((string) $refreshedResponse->getBody(), 'Visible once after redirect'), 'subsequent request no longer renders flash');


$hydrated = ModelHydrator::hydrate(ContactMessage::class, [
    'id' => '7',
    'name' => 'Jan',
    'email' => 'jan@example.org',
    'message' => 'Message from the database',
    'created_at' => '2026-10-27 10:15:00',
]);
$check($hydrated->getId() === 7 && $hydrated->getCreatedAt() === '2026-10-27 10:15:00', 'model hydration');
$check($hydrated->isPersisted(), 'hydrated model is persisted');

print "OK: {$checks} checks passed.\n";
