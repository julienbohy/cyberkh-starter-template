# Cyberkh Starter Template

A Symfony starter template, served by FrankenPHP in Docker. Create a new project from it with
the Symfony CLI (and PHP 8.5, for this step only):

```shell
symfony new my-project --skeleton=julienbohy/cyberkh-starter-template --php=8.5
```

or with **Use this template** on GitHub.

## Stack

- **PHP 8.5** and **Symfony 8.1**, served by **FrankenPHP** (Caddy, HTTPS), in Docker
- **Doctrine ORM** with migrations, on **PostgreSQL 18**
- **Messenger** (Doctrine transport), **Mailer**, **Notifier**
- **Users**: login, registration with email verification, password reset, and their
  management by the super admins
- **Application shell**: collapsible sidebar menu, breadcrumbs and user menu
- **Files**: VichUploader on Flysystem storages, local or S3 (RustFS in development), with
  LiipImagine thumbnails: the photo of the profile
- **Twig**, **AssetMapper**, **Stimulus**, **Turbo**, **Twig and Live Components**, **UX Icons**
- **Tailwind CSS** with the shadcn kit of the **UX Toolkit**
- **PHPUnit**, **Foundry** and **DAMA Doctrine Test Bundle** for the tests
- **PHPStan** (level max), **ECS** and **Twig-CS-Fixer** for the quality

## Requirements

- Docker with Docker Compose
- [Castor](https://castor.jolicode.com)

The application runs in the containers: the host needs neither PHP nor Composer.

## Getting started

```shell
castor app:install
castor db:reset
```

`castor app:install` asks for the ports of the host (see below), builds the image, starts the
Docker services, installs the dependencies and runs the migrations. `castor db:reset` loads the
development data. Then open https://localhost:8443 and log in with `admin@example.com` (super
admin) or `user@example.com`, and the password `correct-horse-battery-staple`.

The HTTPS certificate comes from the local authority of Caddy: `castor stack:trust` makes macOS
trust it (once per machine and volume).

### The Docker services

| Service     | What it does                                                          |
|-------------|-----------------------------------------------------------------------|
| `php`       | FrankenPHP: serves the application, over HTTPS                        |
| `messenger` | Sends the emails queued in the `async` transport                      |
| `tailwind`  | Rebuilds the CSS when a template changes                              |
| `database`  | PostgreSQL                                                            |
| `mailer`    | Mailpit: catches the emails sent in development                       |
| `storage`   | RustFS, an S3-compatible server: the uploaded files of the development |
| `storage-init` | Creates the bucket, then exits                                     |

The checkout is bind-mounted in the containers, except `var/`, which is a volume of its own. The
`messenger` worker keeps the code it started with: `docker compose restart messenger` after a
change of a message handler (it restarts on its own after an hour).

The image is built by the `Dockerfile`: `frankenphp_dev` for the development (Xdebug, with
`XDEBUG_MODE=debug` in `.env.local`), `frankenphp_prod` for the production (see below).

### Ports

The application reaches the services inside the Docker network (`database:5432`, `mailer:1025`).
Only these ports are published on `127.0.0.1` of the host, set in `.env`:

| Variable          | Default | Service                     |
|-------------------|---------|-----------------------------|
| `HTTPS_PORT`      | `8443`  | The application             |
| `DATABASE_PORT`   | `5432`  | PostgreSQL, for its clients |
| `MAILER_WEB_PORT` | `8025`  | Mailpit                     |
| `STORAGE_CONSOLE_PORT` | `9001` | The console of RustFS, at `/rustfs/console/` (keys in `.env.dev`) |

Change them in `.env.local` when another project already uses them: `castor env:init` proposes
free ones (the ports of this project's own containers count as free) and writes them there,
keeping the other lines of the file. Then run `castor stack:start` again. Docker Compose alone
reads `.env` only: the Castor tasks give it the `.env` files of the dev environment, in the order
of Symfony (`.env`, `.env.local`, `.env.dev`, `.env.dev.local`). Run `docker compose` through
Castor, or pass these files yourself with `--env-file`.

## Daily tasks

| Task                   | What it does                                                    |
|------------------------|-----------------------------------------------------------------|
| `castor app:install`   | Install everything and start the stack (first checkout)         |
| `castor app:console …` | Run a command of the Symfony console, in the container          |
| `castor app:composer …`| Run Composer, in the container                                  |
| `castor stack:start`   | Build the image if needed, then start the Docker services       |
| `castor stack:stop`    | Stop the Docker services                                        |
| `castor stack:trust`   | Make macOS trust the local HTTPS certificates                   |
| `castor env:init`      | Choose the ports of the host, in `.env.local`                   |
| `castor db:reset`      | Empty and migrate the database, then load the fixtures          |
| `castor qa:lint`       | Run all the static checks (`composer lint`)                     |
| `castor qa:fix`        | Apply the coding standard fixes (`composer fix`)                |
| `castor qa:test`       | Run the test suite                                              |

The tasks are in `.castor/`, one file per prefix; `castor.php` imports them. The mails sent in
development land in Mailpit, at http://localhost:8025.

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

### Profile

At `/profile`, a user uploads, replaces or deletes the photo of their profile, and changes their
password (with the current one; they stay logged in). The photo shows in the user menu and in
the administration of the users.

## Files

VichUploader stores the uploaded files on Flysystem storages (`config/packages/flysystem.yaml`).
Each storage exists twice, on the local disk (`var/storage/`) and on S3, and `STORAGE_ADAPTER`
chooses between them, at runtime:

| Variable          | What it is                                                         |
|-------------------|--------------------------------------------------------------------|
| `STORAGE_ADAPTER` | `local` (`.env`, the tests) or `s3` (`.env.dev`, RustFS)             |
| `S3_BUCKET`, `S3_REGION` | The bucket                                                  |
| `S3_ENDPOINT`     | Empty on AWS; the URL of a compatible server (RustFS, Scaleway, OVH...) |
| `S3_ACCESS_KEY`, `S3_SECRET_KEY` | The keys; without them, the SDK looks for them (IAM role) |
| `S3_PATH_STYLE`   | `1` for most of the compatible servers                              |

The photos are private: `App\Controller\AvatarController` serves them to the signed-in users only,
whatever the storage. Their thumbnails (`config/packages/liip_imagine.yaml`: 64 and 256 pixels,
WebP) are made on the first request, kept in the `avatars_cache.storage`, and removed with the
photo.

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
castor app:console app:user:create admin@your-domain.com --super-admin
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

`castor qa:lint` runs every static check: the linters of Symfony (container, YAML, Twig), ECS,
PHPStan, Twig-CS-Fixer and the audits of Composer and of the importmap. `castor qa:fix` applies
the fixes of ECS and Twig-CS-Fixer.

## Production

The `frankenphp_prod` image holds the code, the dependencies and the compiled assets, and serves
every request through the long-running kernel of FrankenPHP (worker mode). On the server:

```shell
export SERVER_NAME=your-domain.com APP_SECRET=… POSTGRES_PASSWORD=… MAILER_DSN=… MAILER_FROM=…
# The uploaded files: in the app_storage volume by default, or on S3
export STORAGE_ADAPTER=s3 S3_BUCKET=… S3_REGION=… S3_ACCESS_KEY=… S3_SECRET_KEY=…
docker compose -f compose.yaml -f compose.prod.yaml up --detach --wait --build
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
```

Caddy gets the HTTPS certificate of `SERVER_NAME` on its own.

## After creating a project from the template

- Rename the project in this README (and in `composer.json` with **Use this template**: Flex removes the name)
- Change the database credentials, and set the `APP_SECRET` of production in its environment (or with `bin/console secrets:set`)
- Set `MAILER_FROM` and the application name (`app.name` in `translations/`)
- Choose a license: Symfony Flex removes `LICENSE` and makes the project `proprietary`

## License

[MIT](LICENSE)
