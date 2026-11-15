# Wily Starter

A small, **real PHP 8.4+ application** built from independently maintained WilyCode libraries. It demonstrates PHP attribute routes, controllers, dependency injection, route-name-based `.tpl` rendering, CSS/JS assets, English/Polish translation, an Auto/Light/Dark appearance menu, form filters and validators, a `wilycode/form-csrf` token field, MySQL persistence, sessions, PSR-15 middleware examples (a Basic Auth-protected message inbox and a public HTML page cache), and migrations.

**This is an application template, not a Packagist library package or a new PHP framework.** Composer is used to install the *libraries* it depends on.

Repository: [wilycode/starter](https://github.com/wilycode/starter).

> **WilyCode PHP Ecosystem**  
> The reusable libraries live in independent repositories, with the ecosystem index at [wilycode/wilycode](https://github.com/wilycode/wilycode). Explore the [WilyCode GitHub organization](https://github.com/wilycode) and [published component packages](https://packagist.org/packages/wilycode/).

## Contents

- [Getting started](#getting-started)
- [Demo pages](#demo-pages)
- [Appearance menu (Auto / Light / Dark)](#appearance-menu-auto--light--dark)
- [Admin message inbox (Basic Auth example)](#admin-message-inbox-basic-auth-example)
- [Server-side page cache middleware example](#server-side-page-cache-middleware-example)
- [Migration commands](#migration-commands)
- [Other ways to run](#other-ways-to-run)
- [Web server configuration](#web-server-configuration)
- [WampServer and other subdirectory installations](#wampserver-and-other-subdirectory-installations)
- [Project structure](#project-structure)
- [Components and examples](#components-and-examples)
- [Testing](#testing)
- [Security and production notes](#security-and-production-notes)

## Getting started

**No containers are required.** The Starter includes a Composer `start` script that runs PHP's built-in development server. This works on **Windows, macOS and Linux**.

You need **PHP 8.4+**, **Composer 2**, a running **MySQL** server, and the PHP `pdo_mysql`, `mbstring`, `session`, `ctype` and `filter` extensions. Create a database named `wily_starter` (or choose another name in `.env`) and use a MySQL account that can create tables.

After publishing the repository, clone it with `git clone https://github.com/wilycode/starter.git` and `cd starter`, or open an existing local copy.

1. Open a terminal in the Starter project directory and copy the example configuration:

   ```bash
   # macOS / Linux
   cp .env.example .env
   ```

   On **Windows Command Prompt**, use `copy .env.example .env`; in **PowerShell**, use `Copy-Item .env.example .env`.

2. Edit `.env` with your local MySQL connection and application URL:

   ```dotenv
   APP_URL=http://localhost:8090
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=wily_starter
   DB_USERNAME=your_mysql_user
   DB_PASSWORD=your_mysql_password
   ```

   Replace the sample credentials with your own. If your MySQL server uses a nonstandard port, update `DB_PORT` accordingly.

3. Install libraries, create the application tables and start the development server:

   ```bash
   composer install
   composer migrate
   composer start
   ```

4. Open **http://localhost:8090** or **http://localhost:8090/contact**.

`composer start` executes PHP's built-in server with `public/` as the document root, without a custom `router.php` file. Stop it with **Ctrl+C**. It is for local development, not production. Use a web server such as Apache or Nginx with `public/` as the document root for deployment.

For Apache, Nginx or container-based environments, see [Web server configuration](#web-server-configuration). No specific development stack is required.

## Demo pages

| URL | What it shows |
| --- | --- |
| `/` | Home page, attribute-based WilyCode routing, `.tpl` inheritance and assets |
| `/?lang=pl` | Polish translation, saved in a server-side session |
| `/contact` | Form, server-side validation, CSRF, translations and MySQL insert |
| `/contact?lang=en` | Switch back to English |
| `/admin` | Redirects to the read-only admin inbox without a Basic Auth challenge |
| `/admin/dashboard` | Read-only admin inbox with paginated contact messages; Basic Auth; disabled (404) until credentials are set |
| `/guides` | Public knowledge base, cached full HTML (MISS then HIT); linked from menu |
| `/missing` | Custom translated 404 |

The top navigation also provides two unobtrusive icon menus: **Appearance** (Auto, Light, Dark) and **Language** (Polski, English). The `A/文` icon means translations; it does not introduce a Chinese locale. The Appearance menu defaults to Auto and follows the operating system's preference. The choice is saved in browser `localStorage`, so it remains in effect between visits, across languages and on cached pages. This does not require a server session or invalidate the HTML cache.

Submit a valid contact form, then inspect the `contact_message` table in your preferred MySQL client (for example, phpMyAdmin). No email is sent.

## Appearance menu (Auto / Light / Dark)

The navigation has a compact appearance button (monitor/sun/moon icon) opening a small **Auto** (default), **Light**, **Dark** menu. The menu supports keyboard navigation, Escape to close, and a visible check on the selected option. `public/assets/js/theme-init.js` restores the user's preference **before CSS is loaded**, avoiding flashes of the wrong color scheme. `public/assets/js/app.js` handles changes and saves them in `localStorage` (`wily-starter.theme`). Choosing Auto removes the saved override and follows the operating system automatically, including subsequent system theme changes.

The stylesheet uses modern CSS `light-dark()` and `color-scheme` rather than maintaining two complete duplicated stylesheets. The WilyCode SVG logo and favicon also follow the selected appearance. Since the preference is client-side, the public `/guides` page stays compatible with the server-side page cache (the cache key does not need a theme dimension). If browser storage is unavailable, the theme still works for the current page.

## Admin message inbox (Basic Auth example)

The `src/Http/Middleware/BasicAuthMiddleware.php` implements **PSR-15** and guards the read-only `AdminController::dashboard()` action before it queries the database. The protected route is:

```php
#[Get(path: '/admin/dashboard', name: 'admin.dashboard', middlewares: [BasicAuthMiddleware::class])]
```

Configure **both** values in your ignored `.env` (never commit real passwords):

```dotenv
BASIC_AUTH_USERNAME=demo_user
BASIC_AUTH_PASSWORD=replace_with_a_private_password
```

Open `http://localhost:8090/admin/dashboard` and enter your credentials in the browser's Basic Auth prompt. The public `/admin` entry redirects there without issuing an authentication challenge. Once configured, the **Admin** link appears in the navigation. It shows up to **20 contact messages per page**, newest first, with the name, email, date and message content, using the existing `ContactMessageRepository::orderBy(...)->paginate(...)`. `App\Http\Pagination\PageNumber` handles the `?page=` input (defaults to 1 for invalid/out-of-range values), keeping query validation out of the controller. Try submitting a message through `/contact`, then check the inbox. When no credentials are set, `/admin/dashboard` returns **404** and the menu link is hidden. Incorrect credentials return **401**. Contact pages and the page-cache example remain public. No new model or migration is needed; run `composer migrate` to create `contact_message` before opening the inbox.

**Why `/admin/dashboard` rather than `/admin`?** Under [RFC 7617, section 2.2](https://www.rfc-editor.org/rfc/rfc7617#section-2.2), browsers may proactively reuse Basic Auth credentials under the authenticated URL's directory. A challenge on `/admin` covers the root `/` and can send `Authorization` to public `/guides`, causing a page-cache `BYPASS`. Challenging only on `/admin/dashboard` scopes normal reuse to `/admin/`, while `/admin` is an unprotected redirect. **Browsers that already authenticated on `/admin` may retain the old root-level scope for the remainder of their session**; restart the browser, clear its cached HTTP authentication state, or use a private window when testing the change. Do not disable the page-cache Authorization check as a workaround. For production, use session-backed authentication and HTTPS instead of this example.

The admin page is **never** handled by `PageCacheMiddleware`. Basic Auth enforces `Cache-Control: private, no-store` on every successful protected response, and `no-store` on denials (including disabled auth). The controller does not have to manage those headers. The message text is HTML-escaped. It is an example, **not** a production-grade admin system: there are no user accounts, roles, access logs, rate limiting or session login.

**Security:** HTTP Basic Auth sends reusable credentials (Base64 is **not encryption**). The demo permits plain HTTP only in `APP_ENV=local` for loopback peers; in local **Wily Run / Podman / Docker**, it also permits RFC1918/IPv6 ULA container peers **only for** `localhost`, `*.localhost`, `127.0.0.1` and `::1` request hosts. For production (`APP_ENV=production`), **HTTPS is required even on loopback**. The middleware returns HTTP 403 on insecure requests, but this cannot undo any credentials the browser already transmitted: enforce HTTPS (and, where appropriate, HSTS) at the web server or trusted reverse proxy. Wily Run 1.6.41 binds Apache's published HTTP port to `127.0.0.1`; **this loopback-only binding is essential** for the local HTTP exception. A private `REMOTE_ADDR` may only identify the container bridge, not the original visitor. Never expose a development port to the LAN or Internet, or rely on `Host: localhost` as a security boundary: HTTP Basic Auth is not secure against interception. Configure TLS and trusted proxy forwarding before deployment. Some Apache/FastCGI configurations also require forwarding the `Authorization` header to PHP. This is a teaching example, not a production-grade authentication system.

## Server-side page cache middleware example

The generation timestamp uses the built-in `ldatetime('long')` filter from `wilycode/template-translation`, configured with the PL/EN locale resources shipped by `wilycode/translation-languages`. Formatting follows the selected language; no custom date formatter or extra Composer dependency is needed.

`GET /guides` is a more realistic **public knowledge base** (five localized FAQ articles). It uses `src/Http/Middleware/PageCacheMiddleware.php` (PSR-15). Open **Guides** from the menu. FAQ titles and descriptions are translated directly in the template via `trans`.

```php
#[Get(path: '/guides', name: 'guides.index', middlewares: [PageCacheMiddleware::class])]
```

The first request invokes `GuidesController::index()`, loads localized FAQ content, renders HTML and saves the body through `wilycode/cache-file` (PSR-16) in `var/cache/pages/` (response header `X-Page-Cache: MISS`). Further requests reuse the saved HTML (`X-Page-Cache: HIT`) **without calling the controller**. After the TTL elapses, the page is generated again. The visible timestamp remains unchanged while it is served from cache. The cache distinguishes English and Polish selected via `?lang=en`, `?lang=pl` or the stored language preference. For simplicity, the example articles come from the application's translation files; a larger application could read public articles from a repository.

If you use Apache with PHP-FPM, `public/.htaccess` also forwards the Basic Auth `Authorization` header to PHP when supported by `mod_setenvif`.

```dotenv
PAGE_CACHE_TTL=60
```

The TTL is in seconds (default **60**, maximum **3600**); set `PAGE_CACHE_TTL=0` to disable the example. To invalidate cached HTML immediately, use the dedicated PSR-16 cache store (`CacheInterface::clear()`), or remove its generated hashed subdirectories from `var/cache/pages/` while keeping `.gitkeep`. Generated files are excluded from Git. The example uses [`wilycode/cache-file`](https://github.com/wilycode/cache-file) (`^0.1`, PSR-16), with dependency injection via `Psr\SimpleCache\CacheInterface`. The middleware does not implement its own filesystem operations or TTL handling. The cache always uses the project directory `var/cache/pages/` (not the general runtime fallback directory): if the directory is not writable, the public page continues to work but reports `X-Page-Cache: BYPASS`.

**Check it in Wily Run:** make two GET requests to `/guides` and inspect the `X-Page-Cache` response header (`MISS`, then `HIT`). The files are stored in hash-prefixed subfolders such as `var/cache/pages/ab/…`, **without a `.html` or `.json` extension**. From the PHP shell, check with `find var/cache/pages -type f ! -name .gitkeep`. `BYPASS` means the response was not cached, for example because the folder is not writable or the request explicitly forbids storage (`Cache-Control: no-store`). Browser refresh should normally yield `HIT` after the initial `MISS`. This middleware is intended for a single public route only.

**Safety:** This is a deliberately *public, anonymous, HTML-only* example. Do **not** apply it to `/contact` (contains a CSRF token and flash messages), login, authenticated pages or content that varies by user/cookie. The middleware skips non-GET requests, Authorization headers, unexpected query parameters, private or cookie-setting responses, non-200 responses and explicit no-store requests. Ordinary browser reloads often send `Cache-Control: no-cache` and/or `Pragma: no-cache`; the example deliberately ignores those request directives so refreshing `/guides` shows `HIT` while the origin-cache TTL remains active. Browser and proxy caching remain disabled (`Cache-Control: no-store`) so each reload contacts the server and makes the origin-cache HIT/MISS visible. For production, add suitable invalidation, monitoring, concurrency protection and shared storage when multiple servers are involved.

## Migration commands

Run these in the **Starter project directory** after `composer install`:

| Command | Action |
| --- | --- |
| `composer migrate:make "Create users table"` | Generate a PHP migration and matching `.up.sql`/`.down.sql` files in `migrations/`. |
| `composer migrate:status` | Show pending and applied migrations. |
| `composer migrate` | Apply pending migrations. |
| `composer migrate:down` | Roll back the most recent migration **batch**. |

New migrations include companion `.up.sql` and `.down.sql` files **by default**. Put SQL statements in the generated `.sql` files and keep the PHP migration as a thin wrapper calling `execSqlFile()`; replace the generator's placeholder contents before applying migrations. The SQL files deliberately sit next to the PHP migration: this is the native layout of `wilycode/migration`'s generator. While `execSqlFile()` supports relative subpaths such as `sql/...`, the generator does not expose a separate SQL output directory, so the Starter does not move or rewrite generated files. **Warning:** rolling back the bundled migration drops the `contact_message` table and deletes its data. Back up important data first.

## Other ways to run

The Starter is independent of any particular server or container engine:

- **PHP built-in server:** the [Getting started](#getting-started) path above; simplest for local development.
- **Apache / WampServer:** follow [WampServer and other subdirectory installations](#wampserver-and-other-subdirectory-installations).
- **Existing server or container environment:** follow [Web server configuration](#web-server-configuration); there is no dependency on any particular stack.

There is deliberately **no Docker/Podman configuration inside this application**. You can develop without containers and choose a server setup independently.

## Web server configuration

Wily Starter also works with an existing Apache or Nginx server, or in a container. It does not depend on any particular development stack. The PHP built-in server (`composer start`) remains the simplest local setup.

### Apache

For a dedicated virtual host, set `DocumentRoot` to the **absolute path to `wily-starter/public/`**, enable `mod_rewrite`, and allow `.htaccess` overrides. The bundled `public/.htaccess` routes requests that do not match files to `index.php`. Set `APP_URL` in your private `.env` to the browser-facing origin (including the port if needed), for example `http://wily-starter.localhost`.

When hosting the project in an Apache **subdirectory** such as `http://localhost/wily-starter/public`, set `APP_URL` to that **exact URL**. See [WampServer and other subdirectory installations](#wampserver-and-other-subdirectory-installations) for a complete example. Using `public/` as the document root is preferable for a real deployment.

### Nginx

Set the site's `root` to the absolute path to **`wily-starter/public/`**. Route non-file requests to `index.php` through PHP-FPM, with a suitable PHP handler for your server's PHP-FPM socket or port. For example, the site location can use:

```nginx
try_files $uri $uri/ /index.php?$query_string;
```

Do **not** expose the project's root directory as the web root. Only `public/` should be accessible over HTTP.

### Containers and remote databases

Run `composer install` and `composer migrate` **in the environment where PHP executes**, with PHP 8.4+ and its required extensions (`mbstring`, `pdo_mysql`, etc.). Set `DB_HOST` in `.env` to a hostname reachable from that PHP process — often a database service name on the container network, **not** `127.0.0.1` — and set `DB_PORT` to the MySQL port accessible from that environment.

Ensure PHP can write to `var/cache/templates/` and `var/logs/` (and `var/cache/pages/` if using the page-cache example). Keep `public/` as the only web-exposed directory; never expose `var/`, `.env`, `vendor/`, `languages/`, `migrations/` or `src/` over HTTP. Never commit real credentials; start from `.env.example`.

## WampServer and other subdirectory installations

When running under Apache in a subdirectory, **set `APP_URL` to the exact public URL of the project**. The starter reads its path as a deployment prefix for links, assets, redirects, session cookies, and request routing; controller attributes stay simple (e.g. `#[Get('/contact')]`).

For example, if you place the project at `C:\wamp64\www\wily-starter` and visit `http://localhost/wily-starter/public`, use these lines in the **starter application's** `.env`:

```dotenv
APP_URL=http://localhost/wily-starter/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wily_starter
DB_USERNAME=root
DB_PASSWORD=
```

Adjust the database settings for your WampServer MySQL configuration and run `composer install`, then `composer migrate` in the starter folder. Enable Apache `mod_rewrite` and allow `.htaccess` overrides for the `public` directory; `public/.htaccess` routes non-file URLs to `index.php`.

Open:

- `http://localhost/wily-starter/public/`
- `http://localhost/wily-starter/public/contact`

**Recommended for a real deployment:** configure an Apache virtual host whose `DocumentRoot` points directly to the starter's `public/` directory. Then use a root-level `APP_URL` such as `http://wily-starter.local`, and browse `http://wily-starter.local/contact`. This hides the project's parent directory from the web.

When you switch between the two deployment styles, update `APP_URL` accordingly. You do **not** need to change the route attributes or templates.

## Project structure

```text
bin/migrate.php         WilyCode migration runner
bin/lint.php            Check PHP syntax
public/                ONLY web-exposed directory; index.php is the entry point; CSS/JS
src/Application.php     HTTP request handling and route dispatch
src/Bootstrap/         ContainerFactory (service bindings), SessionFactory (cookie settings),
                       LocaleFactory (language selection), TemplateFactory (view setup)
src/Http/Controller/    Home, Contact, Admin and Guides controllers with route attributes
src/Http/Middleware/    PSR-15 BasicAuthMiddleware (admin) and PageCacheMiddleware (public)
src/Http/Pagination/    PageNumber parses the GET page query with safe bounds
src/Support/ViewRenderer.php  HTML responses with templates selected by route name
src/Form/               ContactForm with reusable filters and validators
src/Model/              ContactMessage with mapped database columns
src/Repository/         Model-aware repository for persistence and queries
src/Support/            Base URL, locale, flash, date-time formatting and DB config
templates/              WilyCode .tpl pages, reusable form/notice components and parent layout
languages/en,pl/        ArrayTranslator resources
migrations/            PHP migrations and companion .up.sql/.down.sql files
tests/run.php           Smoke suite (after Composer install)
tests/database.php      MySQL repository SQL checks, no running database required
var/                   Generated template/page cache and private logs (not exposed)
```

## Template cache and logs

On first use, Wily Starter automatically creates `var/cache/templates/` for compiled `.tpl` templates, `var/cache/pages/` for the optional HTML cache, and `var/logs/` for application logs. These directories are **outside `public/`**, and their generated contents are ignored by Git. You do not need to create them manually; empty `.gitkeep` files keep the directory structure in Git and ZIP packages.

If the project is mounted read-only or PHP has no permission to write there, `Storage` falls back to a project-specific folder in the system's temporary directory. This fallback is not intended as persistent storage. When running under Apache/Podman, make sure the PHP process can write to `var/` if you want compiled templates and logs to remain in the project.

## Components and examples

| Functionality | Components | Starting point |
| --- | --- | --- |
| Routing | `wilycode/router`, `wilycode/router-attribute` | `src/Http/Controller/`, `src/Application.php` |
| Service registration | `wilycode/container` | `src/Bootstrap/ContainerFactory.php` |
| Environment | `wilycode/dotenv` | `src/Support/Environment.php` |
| Views | `wilycode/template`, `template-string`, `template-assets` | `src/Bootstrap/TemplateFactory.php`, `templates/` |
| Translation | `wilycode/translation-array`, `translation-languages`, `template-translation` | `languages/{en,pl}/` |
| Form + rules | `wilycode/form` `^0.1.2`, `wilycode/filters`, `wilycode/validators` | `src/Form/ContactForm.php` |
| CSRF field | [`wilycode/form-csrf`](https://github.com/wilycode/form-csrf) | `CsrfTokenField`, `SessionCsrfTokenManager` |
| Database + model | `wilycode/database-pdo`, `database-query-executor`, `database-model`, `database-repository` | `src/Model/ContactMessage.php`, `src/Repository/ContactMessageRepository.php` |
| Migrations | `wilycode/migration` | `migrations/` |
| Sessions | `wilycode/session-php` | `src/Bootstrap/SessionFactory.php`, `src/Support/FlashMessage.php`; locale, one-time feedback and CSRF token storage |
| HTTP responses | `wilycode/http-response` + `nyholm/psr7` | `src/Http/Controller/` |
| Route middleware | PSR-15 (via `wilycode/router`) | `src/Http/Middleware/BasicAuthMiddleware.php`, `PageCacheMiddleware.php` |
| File logging | `wilycode/logger-file` | `src/Bootstrap/ContainerFactory.php` |

### Application bootstrap and service definitions

`Application::handle()` focuses on dispatching the HTTP request. It creates a request-scoped container with `ContainerFactory::create($root, $request)` and resolves the routing, response, logging and template services it needs. There is no separate service-provider framework.

- `src/Bootstrap/ContainerFactory.php` composes the factories and registers services (including lazy database access) in the existing WilyCode PSR-11 container. The native session adapter is registered only as `SessionInterface`; consumers depend on the contract instead of its implementation.
- `src/Bootstrap/SessionFactory.php` configures PHP session cookies. `public/index.php` calls `SessionFactory::configure($request, $baseUrl)` **before** `session_start()`; `ContainerFactory` then registers the native adapter only under `SessionInterface`. The request entry point owns session startup.
- `src/Bootstrap/LocaleFactory.php` selects the current language from the request and session preferences and creates `LocaleContext` with a translator.
- `src/Bootstrap/TemplateFactory.php` creates the ready-to-use `TemplateEngine`: template directories, extensions, asset mode, locale and shared flash messages.

To add an application service, register it in `ContainerFactory`. Keep session, locale, and view configuration in their respective factories rather than adding more setup code to `Application`.

Routes are declared on controller methods using the WilyCode attribute package. GET and POST actions have different names, even when their URL is the same:

```php
#[Get(path: '/contact', name: 'contact.index')]
public function index(ViewRenderer $views, ContactForm $form): ResponseInterface
{
    return $views->render(['form' => $form]);
}

#[Post(path: '/contact', name: 'contact.store')]
public function store(ServerRequestInterface $request, ContactForm $form /* ... */): ResponseInterface
{
    $form->setRequest($request);
    if (!$form->isValid()) { /* render HTTP 422 */ }
    // Save and redirect on valid POST.
}
```

Because `store()` is a dedicated POST action, it calls `isValid()` after `setRequest()`; `isSubmittedAndValid()` remains useful for controllers sharing GET and POST.

The router resolves action parameters from the request container. A controller does not need a constructor just to receive dependencies used by a single action. Constructor injection remains useful for dependencies shared by multiple actions.

`src/Application.php` registers `AttributeRouteCollection` for `src/Http/Controller/`.
There is no separate `routes/` folder, no manual route table, and no additional routing framework.

The project keeps view templates directly in `templates/`. Language files stay in `languages/` and public CSS/JS in `public/assets/`, so a `resources/` wrapper would add no value.

Templates deliberately use the native WilyCode syntax:

```tpl
@extends('layouts.app')

@section('content')
    <h1>{{ 'headline' | trans }}</h1>
    <p>{{ $excerpt | truncate(120) }}</p>
@endsection
```

The header logo uses the original SVG assets from [wilycode/wilycode](https://github.com/wilycode/wilycode/tree/main/docs/assets), with variants for light/dark mode.

The layout registers `@css('/assets/css/app.css')`, calls `@renderCss()`, and does the equivalent for JS. Development mode provides individual files with cache busting; production mode creates bundled/minified assets. No Node/Vite/NPM build is required.

Generated production bundles are written on demand to `public/assets/build/` by `wilycode/template-assets`. This directory is intentionally not committed to Git and does not need an empty placeholder: the library creates it when needed. On production servers, the PHP process must be able to create and write that directory. Commit only source assets under `public/assets/css/` and `public/assets/js/`.

The form uses `TrimFilter`, `StringValidator`, `EmailValidator` and `StringLengthValidator`. The controller builds a `ContactMessage` model and calls `ContactMessageRepository::saveModel()`. The repository extends the existing WilyCode `Repository` class and delegates persistence to `QueryExecutorInterface`. The request container autowires the `ContactForm` into the controller and its CSRF token manager into the form. No manual SQL or application-specific persistence logic is needed. The translator is shared between controllers and the template engine.

### Route-name-based view rendering

`src/Support/ViewRenderer.php` is a small application helper that combines the existing `TemplateEngine` and `ResponseFactory`. By default, `render()` reads the **matched route name** from `Router::getCurrentRoute()` and uses it as the `.tpl` template name. `ContainerFactory` registers the shared request router; `Application` adds the controller route collection before dispatch.

For example, `ContactController` has two separate actions:

- `GET /contact` — `contact.index`, `index()` renders `templates/contact/index.tpl` automatically with HTTP 200.
- `POST /contact` — `contact.store`, `store()` validates the submitted form. When invalid, it explicitly renders `template: 'contact.index'` with HTTP 422. When valid, it saves the model, queues a translated flash message, and redirects to `contact.index` (HTTP 303).

Dependencies are injected into the action that needs them. The database-backed `ContactMessageRepository` is resolved from the request container only **after** form validation succeeds, so malformed submissions do not open a database connection. No `AbstractController` is required.

### Named-route redirects

`App\Support\RouteUrlGenerator` wraps the existing `WilyCode\Router\UrlGenerator` and `BaseUrl` so both redirects and templates share the same URL-generation logic. It handles named routes, path parameters, query strings, optional URL fragments (`#section`), and installations in subdirectories:

```php
return $redirector->route('contact.index');
return $redirector->route('user.edit', ['id' => 15], ['tab' => 'profile'], fragment: 'settings');
// /user/15/edit?tab=profile#settings (assuming the user.edit route exists)
```

The starter registers `@route()` in `TemplateFactory` (no changes to `wilycode/template` required). In any `.tpl` template:

```tpl
<a href="@route('contact.index')">Contact</a>
<a href="@route('user.edit', ['id' => 15], ['tab' => 'profile'], 'settings')">Edit</a>
<a href="@route('home.index', [], [], 'cos_tam')">Section</a>
```

Arguments are route name, path params, query params, and **fragment without `#`**. The fragment is appended after the query and URL-encoded by the router. `@route()` returns an HTML-attribute-safe URL (so query `&` becomes `&amp;` in HTML); redirects use the raw URL instead. Route naming works inside a subdirectory defined by `APP_URL`. The `Router` is created once per request in `ContainerFactory` and registered before rendering; `Application` registers its attribute routes on this same router.

The default redirect status is **303 See Other** (POST/Redirect/GET). You can override it with `status: HttpStatus::Found` for 302. Unknown route names and missing path parameters are rejected by the existing router.

The helper accepts arbitrary view variables, HTTP status and optional headers. An invalid form returns **422**, including when the CSRF token is invalid or missing. A CSRF error is presented as a friendly form-level notice, without exposing technical token details. After a failed submission, `@form.error_summary($form)` displays a translated form-level alert for visible field errors; individual field messages remain in place. The alert receives keyboard focus on page load, and CSRF errors keep their dedicated `@form.csrf_error($form)` notice. The example retains its small dependency-free CSS with organized sections instead of adopting Bootstrap or Tailwind. The controller does not need a separate CSRF-specific status branch. `Application::handle()` automatically adds `Cache-Control: no-store` to dynamic responses for HTTP clients (HTML, errors and redirects) unless a middleware or controller has set another cache policy. The optional page-cache middleware caches a public response **inside the server** despite this browser/proxy policy. Static assets bypass the front controller and can still be cached by the web server. There is no need to repeat cache headers in individual controllers. This does not require a base controller or changes to WilyCode libraries. For rendering outside a matched route, pass `template:` explicitly.

The Starter already requires both `nyholm/psr7` (PSR-7 messages) and `nyholm/psr7-server` (building the incoming request from PHP globals); no extra Composer packages are needed.

### CSRF integration and transparent fields

The contact form uses the published [`wilycode/form-csrf`](https://packagist.org/packages/wilycode/form-csrf) package (used here with `wilycode/form:^0.1.2`) instead of an application-specific CSRF helper. `src/Bootstrap/ContainerFactory.php` registers a session-backed `SessionCsrfTokenManager` under `CsrfTokenManagerInterface`.

`ContactForm::init()` registers the reusable CSRF field with `$this->add(new CsrfTokenField($this->csrfTokens, 'contact'))`. The field validates the submitted `_csrf` token, but is **transparent**, so `$form->getData()` includes only `name`, `email` and `message`. Render the hidden input with the application-level `@form.csrf($form)` component defined in `templates/components/form.tpl`; it uses `CsrfTokenField::getToken()`, never untrusted submitted `getData()`. The `wilycode/form-csrf` package has no template-engine dependency. Invalid or missing CSRF prevents saving and returns HTTP 422, just like other invalid form submissions; the `@form.csrf_error($form)` component displays a friendly notice above the form using the CSRF field validation error.

`StringValidator` runs before string-length and email validation and stops on malformed non-string values. This avoids manual request-field parsing and prevents passing request arrays to the view. The library keeps one stable token per session and form ID for simultaneous browser tabs; it does **not** rotate after each submission. Clear the CSRF session namespace after authentication or privilege changes if the application later gains login functionality.

### One-time flash messages

`src/Support/FlashMessage.php` stores an ordered list of one-time messages in the session (`flash` namespace). Calling `set()` appends a message; `pullAll()` returns the entire list and removes it from the session. Convenience methods: `success()`, `error()`, `warning()` and `info()`. Accepted types are `success`, `error`, `warning`, `info` (the input alias `danger` maps to `error`).

```php
$flash->success('Your message was saved.');
$flash->warning('Some settings need attention.');
$flash->set('An update is available.', 'info');

// In templates/contact/index.tpl:
@use('components.message', 'message')
@message.flash($flash)
```

The reusable component renders every pending flash message once, escapes message text, and uses suitable `role="status"` or `role="alert"` attributes. The contact controller translates the success string using the current `LocaleContext` **before** storing it; flash messages themselves contain plain text, not translation keys. Existing notices such as `@message.notice('csrf_error', variant: 'error')` still translate their keys in the current template context. The `FlashMessage` API remains an application helper and does not change the `wilycode/session-php` package.

### Pagination component

`templates/components/pagination.tpl` provides reusable, translated previous/next links for any `PaginationResult`. The component is hidden for a single page, uses the named route provided by the caller, and can retain additional query parameters:

```tpl
@use('components.pagination', 'pagination')
@pagination.nav($messages, 'admin.dashboard')
@pagination.nav($results, 'search.index', query: ['q' => $searchTerm])
```

The labels come from `languages/{en,pl}/global.php`. Navigation links preserve the requested route and any provided query parameters, while replacing only `page`.

### Reusable form components

`templates/components/form.tpl` uses native WilyCode `@component` definitions instead of custom PHP rendering helpers:

```tpl
@use('components.form', 'form')

@form.csrf_error($form)
@form.csrf($form)
@form.text_field($form->getField('name'), 'name', autocomplete: 'name', maxLength: 120)
@form.email_field($form->getField('email'), 'email', autocomplete: 'email', maxLength: 254)
@form.textarea_field($form->getField('message'), 'message', rows: 6, maxLength: 5000)
@form.submit_button()
```

The `@form.submit_button()` component renders a translated primary submit button (default key `submit`), keeping the `data-submit-label` attribute used by form JavaScript. Use `@form.submit_button('save', variant: 'secondary')` for another label key and the existing secondary button style. The parent form can use `data-prevent-double-submit` to disable the first submit button after submission.

The `@form.csrf_error($form)` component displays an alert only if the `_csrf` field has validation errors. Both `@form.csrf($form)` and `@form.csrf_error($form)` accept an optional `fieldName` (default `_csrf`). For other form-level notices use `@form.error_notice($field, 'error.code', resource: 'validation')`.

Additional input components share the same rendering and error handling:

```tpl
@form.number_field($form->getField('quantity'), 'quantity', min: 0, max: 100, step: 1)
@form.tel_field($form->getField('phone'), 'phone', autocomplete: 'tel')
@form.url_field($form->getField('website'), 'website')
```

These components select HTML input types only; add appropriate server-side validators to the form separately.

Each component renders the label, safely escaped field value and translated error message using `$field->getErrors()->hasErrors()`; it adds accessible `aria-invalid` and `aria-describedby` attributes only when needed. The lower-level `@form.input($field)` component renders an input without its label or error, while `@form.input_field()` composes `input` and `error`. `@form.text_field()`, `@form.email_field()`, `@form.number_field()`, `@form.tel_field()` and `@form.url_field()` are typed conveniences that call `input_field` with their respective HTML input types. A shared `@form.error($field)` component handles the error element. The template translates field labels and messages in the `contact.index` context; the controller does **not** build `$errors` or `$values` arrays. Malformed input types remain rejected by `StringValidator` and are never rendered as arrays. CSRF remains a transparent field; an invalid token stops submission and displays a friendly notice while the response remains HTTP 422. `@form.csrf($form)` is a component owned by the starter (not a feature of `wilycode/form-csrf` or `wilycode/template`); other template engines can render the same public `CsrfTokenField` API.

### Model and repository

`ContactMessage` maps form field names directly to model properties using `database-model`. No separate field-by-field mapping is needed:

```php
$message = new ContactMessage($form->getData())
    ->setCreatedAt(Date::now());

$contactMessageRepository->saveModel($message);
```

Import `App\Support\Date` when using the `Date::now()` helper. It formats the current time as `Y-m-d H:i:s` using PHP's configured timezone; configure the application timezone consistently with your database.

`ContactMessage` maps the `contact_message` table via `#[Table]`, `#[PrimaryKey]` and `#[Property]`. The `createdAt` property maps to the `created_at` column in MySQL. Its `setCreatedAt()` method returns the model for fluent chaining. `ContactMessageRepository` extends the generic WilyCode `Repository<ContactMessage>`.

The read-only admin uses this repository to list messages with `orderBy('id', 'DESC')->paginate($page, 20)`. For other application features, the repository also supports `$contactMessageRepository->find($id)` and `$contactMessageRepository->findAll()` without writing an additional SQL query. The database schema remains unchanged; the migration uses separate SQL files.

## Testing

After `composer install` (the included smoke tests do not require PHPUnit):

```bash
composer test
composer lint
```

If PHP runs inside a container, execute these Composer commands in that PHP environment. See [Web server configuration](#web-server-configuration).

For manual integration testing, check the home page, change languages, submit invalid fields, submit a valid form and verify the saved row in phpMyAdmin. A missing route should return HTTP 404; a missing or changed CSRF token on POST should return HTTP 422 and display the form notice without saving anything.

## Security and production notes

- Contact submissions are stored in MySQL; **no email is delivered**. A real public contact form should also have anti-spam protection and rate limiting.
- The form uses `wilycode/form-csrf` session-backed CSRF validation, POST/Redirect/GET, output escaping in `.tpl` and parameterized SQL. The session cookie is `HttpOnly` / `SameSite=Lax`, and marked `Secure` when the request scheme is HTTPS.
- Keep `.env` and database credentials out of Git. MySQL/phpMyAdmin bind to localhost in the supplied development stack.
- Do not expose `var/`, `vendor/`, `languages/`, `src/`, `migrations/` or private configuration through your web server. Document root must remain `public/`.
- For HTTPS behind a reverse proxy, configure trusted proxy handling for HTTPS detection before using this template in production.
- Set `APP_ENV=production` only after configuring writable cache/build folders and secure hosting, including correct exception monitoring.
- Application template code is MIT licensed. Individual dependencies keep their own licenses.

> **Form validation:** Uses `wilycode/form` v0.1.2 or newer. Fields stop at their first validation error by default; all other fields are still checked. Call `$this->setStopOnFirstError(false)` inside a form only if all errors per field are required.

### Validation messages

Form errors are rendered from the actual validator error code and options, not a generic per-field string.
Shared translations are in `languages/en/validation.php` and `languages/pl/validation.php` and include file upload, CSRF and optional reCAPTCHA messages.
The `validation` resource stores short keys such as `email.invalid` (full identifier: `validation.email.invalid`).
The `validation_error` template filter formats placeholders such as `{min}`, `{max}` and `{sizeUnit}`.
Only text messages for reCAPTCHA are provided: reCAPTCHA integration is **not** enabled by the Starter.
Application-specific messages should remain in their own language resources.
