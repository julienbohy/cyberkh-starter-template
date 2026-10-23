# Cyberkh Starter Template

A Symfony starter template. Create a new project from it with the Symfony CLI:

```shell
symfony new my-project --skeleton=julienbohy/cyberkh-starter-template
```

or with **Use this template** on GitHub.

## Stack

- **PHP 8.5** (`.php-version`) and **Symfony 8.1**
- **Doctrine ORM** with migrations, on **PostgreSQL 18**
- **Messenger** (Doctrine transport), **Mailer**, **Notifier**
- **Users**: login, registration with email verification, password reset, and their
  management by the super admins
- **Application shell**: collapsible sidebar menu, breadcrumbs and user menu
- **Twig**, **AssetMapper**, **Stimulus**, **Turbo**, **Twig and Live Components**, **UX Icons**
- **Tailwind CSS** with the shadcn kit of the **UX Toolkit**
- **PHPUnit**, **Foundry** and **DAMA Doctrine Test Bundle** for the tests
- **PHPStan** (level max), **ECS**, **Twig-CS-Fixer** and **GrumPHP** for the quality

## Requirements

- [Symfony CLI](https://symfony.com/download)
- PHP 8.5, with the `pdo_pgsql` and `intl` extensions
- Docker with Docker Compose
- [Castor](https://castor.jolicode.com)

## Getting started

```shell
castor app:install
castor db:reset
symfony open:local
```

`castor app:install` installs the dependencies, asks for the ports of the Docker services (see
below), starts them (PostgreSQL, Mailpit) and the web server, then runs the migrations. `castor db:reset` loads the development data:
log in with `admin@example.com` (super admin) or `user@example.com`, and the password
`correct-horse-battery-staple`.

The web server also runs two workers (`.symfony.local.yaml`): the Messenger consumer, which
sends the emails, and the Tailwind watcher.

Every command goes through the Symfony CLI, which uses the PHP version of `.php-version`.

### Ports

The Docker services listen on fixed ports of `127.0.0.1`, set in `.env` and read by Docker Compose
too:

| Variable           | Default | Service           |
|--------------------|---------|-------------------|
| `DATABASE_PORT`    | `5432`  | PostgreSQL        |
| `MAILER_SMTP_PORT` | `1025`  | Mailpit (SMTP)    |
| `MAILER_WEB_PORT`  | `8025`  | Mailpit (web UI)  |

Change them in `.env.local` when another project already uses them: `castor env:init` proposes
free ones (the ports of this project's own containers count as free) and writes them there,
keeping the other lines of the file. Then run `castor stack:start` again. Docker Compose alone reads `.env` only: the Castor tasks give it the `.env` files of the
dev environment, in the order of Symfony (`.env`, `.env.local`, `.env.dev`, `.env.dev.local`).
Run `docker compose` through `castor stack:start` and `castor stack:stop`, or pass these files yourself with
`--env-file`.

The Castor tasks also give the Symfony CLI the socket of the active Docker context (Docker
Desktop, OrbStack, Colima...), unless `DOCKER_HOST` is set.

## Daily tasks

| Task                 | What it does                                                    |
|----------------------|-----------------------------------------------------------------|
| `castor app:install` | Install everything and start the stack (first checkout)         |
| `castor stack:start` | Start the Docker services and the web server                    |
| `castor stack:stop`  | Stop the web server and the Docker services                     |
| `castor env:init`    | Choose the ports of the Docker services, in `.env.local`        |
| `castor db:reset`    | Empty and migrate the database, then load the fixtures          |
| `castor qa:lint`     | Run all the static checks (`composer lint`)                     |
| `castor qa:fix`      | Apply the coding standard fixes (`composer fix`)                |
| `castor qa:test`     | Run the test suite                                              |

The tasks are in `.castor/`, one file per prefix; `castor.php` imports them and gives the
Symfony CLI the Docker socket.

The mails sent in development land in Mailpit: `symfony open:local:webmail`.

## Users

| Page               | Route                         |
|--------------------|-------------------------------|
| Login, logout      | `/login`, `/logout`           |
| Registration       | `/register`                   |
| Email verification | `/verify/email` (signed link) |
| Password reset     | `/reset-password`             |

- A user cannot log in before verifying their email address (`App\Security\UserChecker`).
- The registration is turned off with `APP_REGISTRATION_ENABLED=0`: `/register` answers 404
  and its links disappear. The verification links already sent keep working.
- The emails are sent from `MAILER_FROM`, through the `async` Messenger transport.
- The pages are translated in French (the default locale) and in English (`translations/`).
- The forms are rendered with the shadcn components (`templates/form/shadcn_layout.html.twig`).

### Roles and user management

`ROLE_SUPER_ADMIN` > `ROLE_ADMIN` > `ROLE_USER` (`config/packages/security.yaml`, `App\Security\Role`).
`ROLE_ADMIN` is ready for the business administration of each project; only the super admins
manage the users, at `/admin/users`:

- search the users by email;
- invite a user: they receive a link to choose their password, valid for 3 days;
- change their roles, disable their account (they are logged out at their next request), mark
  their email as verified, send them a new password link;
- delete their account, or log in as them (`switch_user`), to see the application with their rights.

A super admin cannot change their own roles, disable or delete their own account.

Create the first super admin with:

```shell
symfony console app:user:create admin@your-domain.com --super-admin
```

## Application shell

The signed-in pages extend `layout/app.html.twig`: the `AppShell` component puts them in a
collapsible sidebar, with breadcrumbs and a user menu. A page sets its breadcrumbs after `extends`:

```twig
{% set breadcrumbs = [{label: 'admin.user.index.title'|trans}] %}
```

The menu is in `templates/components/AppShell/Navigation.html.twig`: a feature adds its entries
there, in a group of its own, with the role that can see them. The pages of the visitors (login,
registration, password reset) extend `layout/auth.html.twig`.

## Quality

GrumPHP runs ECS, PHPStan, Twig-CS-Fixer, the Composer validation and the YAML lint on the
staged files before each commit. `castor app:install` installs its Git hooks.

## After creating a project from the template

- Rename the project in this README (and in `composer.json` with **Use this template**: Flex removes the name)
- Change the database credentials, and set the `APP_SECRET` of production with `symfony console secrets:set`
- Set `MAILER_FROM` and the application name (`app.name` in `translations/`)
- Choose a license: Symfony Flex removes `LICENSE` and makes the project `proprietary`

## License

[MIT](LICENSE)
