# Markdown

Markdown document editor with real-time collaboration and MCP access.

## Stack

- **Laravel 13** (Vue + Inertia starter kit, `dev-workos-teams` branch)
- **WorkOS AuthKit** for authentication (users are keyed by `workos_id`; no local passwords)
- **Teams** — local `Team` / `TeamInvitation` models for team-based ownership
- **Pest 4** for testing, **SQLite** for local development
- **Laravel Boost** for AI-assisted development
- Served locally by **Laravel Herd** at `http://makemarkdown.test`

## Local setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

The SQLite database is created and migrated automatically by the installer. To
re-run migrations:

```bash
php artisan migrate
```

### 3. WorkOS configuration

Authentication is handled by [WorkOS AuthKit](https://workos.com/docs/authkit).
You need a WorkOS account and one application.

**In the WorkOS dashboard → Redirects**, configure both URIs:

| Setting | Value |
| --- | --- |
| Sign-in callback (Redirect URI) | `http://makemarkdown.test/authenticate` |
| Default Logout Redirect URI | `http://makemarkdown.test/login` |

> The logout route calls `$request->logout()` with no explicit return URL, so
> WorkOS falls back to the **Default Logout Redirect URI**. If it isn't set,
> logout fails with an error. If you ever pass an explicit URL to `logout()`,
> that exact URL must also be listed under the dashboard's allowed logout
> redirects.

**In the WorkOS dashboard → API Keys**, copy your Client ID and Secret Key into
`.env`:

```dotenv
WORKOS_CLIENT_ID=client_...
WORKOS_API_KEY=sk_...
WORKOS_REDIRECT_URL="${APP_URL}/authenticate"
```

After editing `.env`, clear the config cache:

```bash
php artisan config:clear
```

### 4. Run

```bash
composer run dev
```

This starts the PHP server, queue worker, and the Vite dev server together.
Visit `http://makemarkdown.test` and click **Log in** to start the WorkOS flow.

## Testing

```bash
php artisan test --compact
```
