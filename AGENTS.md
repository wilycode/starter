# Agent instructions — Wily Starter

These instructions apply to the entire `wilycode/starter` repository. Read `README.md` for setup, features, and examples before making substantial changes.

## Project boundaries

- This repository is a small **PHP 8.4+ application template**, not a framework or a reusable Composer library.
- Prefer the existing, independently maintained `wilycode/*` packages. Before using or extending a package API, check its installed implementation or published documentation; do not invent methods or work around missing features unnecessarily.
- Keep changes focused, understandable, and suitable for a starter. Do not introduce a framework, a JavaScript build pipeline, or additional services without a concrete requirement.
- Preserve compatibility with macOS, Linux, and Windows. The project can run without containers.

## Where code belongs

- `public/index.php` is the HTTP entry point; **only `public/` is web-accessible**.
- `src/Application.php` dispatches requests; `src/Bootstrap/` configures services, sessions, locale, and templates.
- `src/Http/Controller/` contains attribute-routed controllers; `src/Http/Middleware/` contains PSR-15 middleware.
- `src/Form/`, `src/Model/`, and `src/Repository/` separate form processing, mapped data, and persistence.
- `templates/` contains WilyCode `.tpl` files, including reusable components; `languages/en/` and `languages/pl/` contain translations.
- `migrations/` contains versioned PHP migrations alongside their `.up.sql` and `.down.sql` files. There is no `database/` wrapper or custom SQL-file relocation step. Follow the native `wilycode/migration` API and generator.

## Coding rules

- Follow the existing PHP style: `declare(strict_types=1)`, `App\` PSR-4 classes, explicit types, and constructor-based dependency injection where applicable.
- Keep controllers focused and reuse existing form, repository, routing, and template components rather than duplicating their logic.
- Maintain route names, base-path-aware links, and the ability to run the application in a URL subdirectory.
- When adding user-visible text, update both Polish and English translation resources. Keep validation errors and other dynamic output properly escaped.
- Preserve the existing CSRF protection, validation, session behavior, and HTTP response semantics. Do not weaken Basic Auth checks or cache bypass rules for requests with `Authorization`.
- Do not commit real credentials or `.env` files. `.env.example` must contain placeholders only.
- Do not commit generated files: `vendor/`, generated files under `var/` (retain `.gitkeep` placeholders), or `public/assets/build/`. The `wilycode/template-assets` package creates production bundles when needed; source assets live under `public/assets/css/` and `public/assets/js/`.
- Keep `composer.lock` committed: this is an application, not a library.

## Commands and verification

Run from the repository root after `composer install`:

```bash
composer lint
composer test
composer validate --strict
```

Other useful commands:

```bash
composer start
composer migrate:status
composer migrate:make "Create example table"
composer migrate
composer migrate:down
```

- The test scripts in `tests/` do not require a live MySQL server; applying migrations and manually testing persistence do.
- Add or update relevant tests when behavior changes. At minimum, run lint and the smoke tests; disclose any checks you could not run.
- Never run destructive database commands (`composer migrate:down`, dropping tables, or resetting data) against an existing database without explicit authorization.
- If a change affects setup, configuration, routes, migration usage, or user-facing features, update `README.md` accordingly.
