# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Project state

Cyberkh starter template: users, auth, profile, user admin, application shell and file storage,
for the projects created from it. It holds no business domain.

Stack: PHP 8.5, Symfony 8.1, Doctrine ORM 3 on PostgreSQL 18, served by FrankenPHP in Docker.
Twig + AssetMapper + Stimulus + Turbo + Twig/Live Components, Tailwind with the shadcn kit of
the UX Toolkit. No Node, no API Platform.

## Commands

Everything runs in the `php` container through Castor (the host has no PHP/Composer). Setup, the
task list, ports, dev accounts and production are in `README.md`. The usual ones: `castor db:reset`
(loads `src/Story/AppStory.php`), `castor app:console <cmd>`, `castor qa:lint`, `castor qa:fix`,
`castor qa:test`. App at https://localhost:8443, Mailpit on :8025. To run a single test, call
phpunit directly (`-T` because there is no TTY):
`docker compose exec -T php bin/phpunit --filter testSomething`.

`docker compose` alone only reads `.env`; Castor also passes the `.env.*` files. The `messenger`
worker keeps old code: `docker compose restart messenger` after changing a message handler.

## Architecture notes

- **Security**: role hierarchy `ROLE_SUPER_ADMIN > ROLE_ADMIN > ROLE_USER`, defined in
  `config/packages/security.yaml` and mirrored by the `App\Security\Role` enum (use the enum,
  e.g. `#[IsGranted(attribute: Role::SuperAdmin->value)]`). `ROLE_ADMIN` is reserved for business
  administration; only super admins manage users (`/admin/users`). `App\Security\UserChecker`
  blocks unverified/disabled users. Registration can be switched off with
  `APP_REGISTRATION_ENABLED=0`.
- **Pages & layout**: signed-in pages extend `layout/app.html.twig` (the `AppShell` component:
  sidebar, breadcrumbs, user menu) and set `{% set breadcrumbs = [...] %}` after `extends`.
  Visitor pages extend `layout/auth.html.twig`. New features add their menu entries, in a group of
  their own with the role that sees them, in `templates/components/AppShell/Navigation.html.twig`.
- **UI components**: `templates/components/` holds the shadcn UX Toolkit components (Button,
  Card, Table, Dialog, Sidebar...); reuse them. Forms render through
  `templates/form/shadcn_layout.html.twig`.
- **Translations**: default locale `en`, also `fr` (`translations/*.{en,fr}.yaml`). Templates, flashes and form labels use
  translation keys (e.g. `admin.user.flash.invited`); add keys to both languages. Functional tests
  assert on translation keys via `$this->trans()` (English unless given a `locale`), not raw text.
- **Language and theme**: `User::$language` (`App\Preference\Language`) and `User::$theme`
  (`Theme`), switched from the user menu or, for visitors, the auth header (cookies).
  `LanguageSubscriber` picks account, cookie, browser, then English.
- **Files**: VichUploader on Flysystem; each storage exists as local and S3 variants, picked at
  runtime by `STORAGE_ADAPTER` (`local` in `.env`/tests, `s3` = RustFS in `.env.dev`). Private files
  are served through a controller (see `AvatarController`), thumbnails via LiipImagine.
- **Async**: emails go through the Doctrine `async` Messenger transport.
- **Entities** use UUID ids (routes use `Requirement::UUID`).

## Tests

PHPUnit 13 + Foundry + DAMA Doctrine Test Bundle. Controller tests extend
`tests/Controller/AbstractControllerTestCase.php` (Foundry `Factories` + `ResetDatabase`,
helpers `logIn()`, `trans()`, `sentEmail()`, `linkOf()`). Build data with factories in
`src/Factory/` (e.g. `UserFactory::new()->superAdmin()->create()`; password is
`UserFactory::DEFAULT_PASSWORD`).

## Code style

Code style is enforced by ECS (`ecs.php`, PER-CS + common + cleanCode sets), not php-cs-fixer as
`AGENTS.md` says, and PHPStan at level max with strict, Symfony, Doctrine and symplify rules;
`castor qa:lint` must pass. Files use `declare(strict_types=1);`, classes are `final` by default,
and docblocks explain *why* in short sentences. PRs follow `.github/pull_request_template.md`
(lint + tests pass, migrations reversible).
