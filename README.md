# DevFest Lab — Code it. Break it. Secure it.

A deliberately vulnerable PHP application and its hardened twin, built for a
live DevFest session on practical secure coding.

> **Warning:** the app on port 8080 is intentionally exploitable. Run it on your
> laptop only, never on a public host, and never reuse this code in production.

## What you get

| Build | URL | Session cookie | Database |
| --- | --- | --- | --- |
| Vulnerable | http://localhost:8080 | `PULSE_SID` | `devfest_vuln` |
| Hardened | http://localhost:8081 | `PULSE_SID2` | `devfest_secure` |

The UI uses the neutral product name **Pulse** so the demo does not announce
itself to the audience. The repository and presenter docs call it the DevFest
Lab.

Both builds start from the single `docker-compose.yaml` file, share one MariaDB
service and the same database credentials (`devfest` / `devfest`), and seed the
same four application users with identical profiles. The two schemas are
separate on purpose: the vulnerable build stores plaintext passwords so the
classic SQL-injection login bypass is easy to demonstrate, while the hardened
build stores bcrypt hashes and verifies them with `password_verify()`. Accounts
created at runtime belong to the build where they were created; the seeded
accounts work on both ports.

## Quick start

```bash
# from the repository root
docker compose up -d --build

# or
make up
```

Give MariaDB a few seconds to initialise on the first run, then open:

- Vulnerable app: <http://localhost:8080>
- Hardened app: <http://localhost:8081>

Run the end-to-end checks at any time:

```bash
make test
# or
./scripts/smoke-test.sh
```

Reset the database and rebuild both containers before a fresh demo:

```bash
make reset
```

That runs `docker compose down -v --remove-orphans` followed by a clean build.
Because the database initialisation scripts only run when the MariaDB volume is
first created, `make reset` is the reliable way to get back to the seeded state.

Stop everything:

```bash
make down
```

## Demo accounts

The same accounts exist in both builds:

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `Admin123!` | admin |
| `alice` | `Password123!` | user |
| `bob` | `Password123!` | user |
| `carol` | `Password123!` | user |

Use `alice` for the normal-user perspective and `admin` when you need a
legitimate administrator session. The SQL-injection demo does not need the
admin password at all.

> Tip: sign in as Alice and Admin in two different browser profiles (or one
> normal window and one incognito window). Sessions are per cookie, so two tabs
> in the same profile will overwrite each other's login.

## The four lessons

| # | Vulnerability | Endpoint | Payload / action | Result |
| --- | --- | --- | --- | --- |
| 1 | SQL injection | `POST /index.php` | username `admin' -- -` | Log in as admin without a password |
| 2 | IDOR | `GET /profile.php?id=...` | change `2` to `3` while signed in as Alice | Read Bob's private note |
| 3 | File upload → RCE | `POST /upload.php` | upload `shell.php.jpg` | Execute commands and read `/srv/pulse/.env` |
| 4 | Stored XSS | `POST /feedback.php` | `<script>alert(document.cookie)</script>` | Steal a non-HttpOnly session cookie |

There are no CTF flags in this lab. The proof of each bug is access to data or
functionality that should not be available: the admin console, another user's
private note, the server's environment file, or a live session cookie.

The walkthrough with exact commands is in [docs/ATTACKS.md](docs/ATTACKS.md).
The live presentation plan is in [docs/PRESENTATION.md](docs/PRESENTATION.md).

### 1. SQL injection

The vulnerable login query is assembled from strings:

```php
$sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password' LIMIT 1";
```

Submitting the username `admin' -- -` produces:

```sql
SELECT * FROM users WHERE username = 'admin' -- -' AND password = '...' LIMIT 1
```

Everything after `-- ` is a SQL comment, so the password check disappears. The
first matching user is `admin`, and the session is created as an administrator.
`' OR 1=1 -- -` works as well and logs in as the first row in the table.
Opening `/admin.php` after the bypass is the proof; there is no flag to collect.

The hardened build uses a prepared statement and `password_verify()`:

```php
$stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

$valid = $user !== false && password_verify($password, (string) $user['password']);
```

### 2. IDOR

The profile page trusts the `id` query parameter:

```php
$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$stmt = db()->query("SELECT * FROM users WHERE id = {$id} LIMIT 1");
```

There is no check that the signed-in user owns that record, so Alice can read
Bob's profile, email, bio and private note by visiting `/profile.php?id=3`.
Bob's note contains a private backup email and phone number, which is the kind
of data an IDOR should never expose.

The hardened build performs the ownership check before reading the row, and
private notes are rendered only for the owner:

```php
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$isOwner = $requestedId === (int) $me['id'];

if (!$isOwner && !is_admin($me)) {
    http_response_code(403);
    exit;
}
```

Administrators may inspect accounts, but they still cannot read another user's
private note. That is intentional least-privilege behaviour.

### 3. File upload → RCE

The vulnerable upload only looks at the last extension:

```php
$filename  = basename((string) $file['name']);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($extension, $allowedExtensions, true)) {
    // reject
}

move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $filename);
```

`shell.php.jpg` has the final extension `jpg`, so it passes. The filename is
kept, and the vulnerable Apache configuration maps any name containing `.php`
before the final extension to the PHP handler. The result:

```text
http://localhost:8080/uploads/shell.php.jpg?cmd=id
http://localhost:8080/uploads/shell.php.jpg?cmd=cat%20/srv/pulse/.env
```

`/srv/pulse/.env` is a fake environment file added to the container for the
demo. It contains realistic-looking secrets, which makes the impact of RCE
concrete without turning the exercise into a flag hunt.

The filter does **not** inspect the file contents, does not strip or reject
null bytes explicitly, and does not consider double extensions. Null-byte
attacks such as `shell.php%00.jpg` were a real bypass on PHP versions before
5.3.4, but modern PHP rejects null bytes in file paths. The lab keeps the
insecure coding pattern so the audience can see why "the extension is `.jpg`"
is not a security control; the actual exploit used here is the double
extension plus the loose Apache handler.

The hardened upload never trusts the filename, the client MIME type or the
extension:

```php
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file((string) $file['tmp_name']);
$imageInfo = @getimagesize((string) $file['tmp_name']);

if (!is_string($mime) || !isset($allowed[$mime]) || $imageInfo === false) {
    // reject
}

$newName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $newName);
```

The hardened `uploads/.htaccess` also disables PHP execution in that directory
as a second layer of defence.

### 4. Stored XSS

The feedback page stores the message and later prints it without output
encoding:

```php
<p><?= $item['message'] ?></p>
```

A message such as `<script>alert(document.cookie)</script>` runs in every
visitor's browser. Because the vulnerable session cookie is not `HttpOnly`, a
real payload could send the session id to an attacker and take over the
account.

The hardened build encodes the value on output and adds a Content Security
Policy as defence in depth:

```php
<p><?= e($item['message']) ?></p>
```

```php
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
```

## What else is hardened in the secure build

- PDO prepared statements everywhere, with emulated prepares disabled.
- `password_hash()` / `password_verify()` instead of plaintext passwords.
- CSRF tokens on every state-changing form.
- Server-side authorization for profiles and the admin area.
- Contextual output encoding for all user-controlled values.
- Session id regeneration after login, plus `HttpOnly`, `SameSite=Lax` and
  strict session mode.
- Security headers: CSP, `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`.
- Upload validation by real content, random filenames, size limits and a
  non-executable upload directory.
- Generic error messages to users; details go to the server log.
- Private notes hidden from administrators unless they own the profile.

## Project layout

```text
.
├── docker-compose.yaml       # one file starts database + both apps
├── Makefile                  # make up / down / reset / test
├── db/init/01-schema.sql     # schema + seed data for both builds
├── vuln/
│   ├── Dockerfile            # loose Apache upload handler
│   ├── env.txt               # fake secrets read in the RCE demo
│   ├── php.ini               # display_errors On, cookie not HttpOnly
│   └── src/                  # vulnerable PHP application
├── secure/
│   ├── Dockerfile
│   ├── php.ini               # hardened PHP settings
│   └── src/                  # hardened PHP application
├── payloads/shell.php        # upload payload used by the RCE demo
├── scripts/smoke-test.sh     # 25 end-to-end checks
└── docs/
    ├── PRESENTATION.md       # talk runbook and timings
    └── ATTACKS.md            # payloads, curl commands and fixes
```

## Troubleshooting

**Port 8080 or 8081 is already in use.** Change the host side of the port
mapping in `docker-compose.yaml` (for example `"9080:80"`) and use the new URL.

**The database looks stale after a schema or seed change.** The init scripts
run only on a fresh volume. Run `make reset`.

**I changed a PHP file but the browser still shows the old code.** The source
is copied into the image at build time. Run `docker compose up -d --build` (or
`make up`) to rebuild. This keeps the demo reproducible and avoids host/container
permission surprises around the upload directory.

**The admin and Alice sessions interfere.** Use separate browser profiles or an
incognito window. Both apps use distinct cookie names, but two logins inside the
same profile still share one cookie jar per app.

**Checking logs.** `make logs` follows the stack; `docker compose logs -f vuln`
or `docker compose logs -f secure` follows one service.

**Reset after experimenting.** `make reset` wipes the MariaDB volume and any
uploaded files baked into the containers, then rebuilds from scratch.
