# Repository Guidelines

## Project Structure & Module Organization
Core Symfony code lives in `src/` (controllers, services, entities, repositories, commands, and infrastructure helpers). Templates are in `templates/` (`admin/`, `site/`, `pdf/`), and public assets are served from `public/`. Database migrations are stored in `migrations/`, seed/fixture classes in `fixtures/` and `src/DataFixtures/`, and automated tests in `tests/` (`Unit/`, `Integration/`, `Functional/`, plus Playwright specs in `tests/e2e/`). Container and runtime setup are in `docker/`, `docker-compose.yml`, and `Makefile`.

## Build, Test, and Development Commands
Use the Makefile wrappers for daily work:
- `make up` / `make down`: start or stop Docker services.
- `make install`: install Composer dependencies in the PHP container.
- `make db && make migrate && make seed`: create schema and load fixtures.
- `make test`: run PHPUnit.
- `make lint`: run PHPStan static analysis.
- `make fix`: run PHP-CS-Fixer.
For browser flows: `npm install`, `npx playwright install chromium`, then `npm run e2e` (or `npm run e2e:public-signup-flow`).

## Coding Style & Naming Conventions
Follow `.editorconfig`: UTF-8, LF endings, 4-space indentation (2 spaces for compose YAML). PHP code is formatted with `friendsofphp/php-cs-fixer` using `@PSR12` + `@Symfony`; run `make fix` before opening a PR. Use PSR-4 class naming under `App\\...` and keep existing suffix conventions (`*Controller`, `*Service`, `*Repository`, `*Command`). Prefer one class per file and clear, domain-based names.

## Testing Guidelines
PHPUnit 11 is configured via `phpunit.xml.dist` with `Unit`, `Integration`, and `Functional` suites. Place new PHP tests in matching folders and name files `*Test.php`. End-to-end tests use Playwright and should be named `*.spec.ts` under `tests/e2e/`. No hard coverage threshold is enforced yet; new features should include at least one automated test at the appropriate level.

## Commit & Pull Request Guidelines
Recent history follows Conventional Commit-style subjects such as `feat(QR-11): ...` and `hotfix: ...`. Prefer: `<type>(<ticket>): concise imperative summary` (example: `feat(QR-12): add QR label background option`). Keep commits focused and include migrations with related code changes. PRs should include:
- clear description and linked issue/ticket,
- setup/migration notes (if schema or fixtures changed),
- screenshots for UI/template changes,
- test evidence (`make test`, `npm run e2e` when relevant).

## Security & Configuration Tips
Never commit secrets; keep local overrides in `.env.local` or `.env.*.local`. Treat `var/`, `playwright-report/`, `test-results/`, and temporary generated artifacts as non-source outputs unless explicitly needed for fixtures or documentation.
