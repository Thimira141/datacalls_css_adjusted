# menuapi (Asterisk|MagnusBilling menu API)

This folder (`api/menuapi`) contains a small, focused API for querying Asterisk-related information (SIP users and a simple index of available modules). It's intended for internal use by your system or other backend services and expects an API key-based Bearer token for authentication.

Files
-----

- `function.php` — Utility helpers
- `index.php` — API index / module listing (authentication + DB check)
- `sip.php` — SIP-related endpoints (authentication + DB access)


Detailed file descriptions
-------------------------

### `function.php`
Purpose:
- Provides reusable helper functions used by the endpoint scripts.
- Keeps endpoint files (index.php / sip.php) small and focused.

Key functions:
- `json_error($message, $code = 400)`
  - Sends a JSON error response and exits the script.
  - Also logs the error via `error_log()`.
- `sanitizeText($text, $length = 50)`
  - Basic sanitization: trims, strips tags, HTML-encodes, removes non-printable characters and truncates to `$length` characters.

Usage:
- Included at the top of `index.php` and `sip.php` via `require_once 'function.php';`.
- Call `json_error()` when validation fails or an unexpected condition occurs.
- Use `sanitizeText()` before inserting user-supplied text into DB or external APIs.

Notes:
- `sanitizeText` truncates to 50 characters by default, which was chosen because some downstream systems (MagnusBilling) enforce short CDR fields. Adjust `$length` if needed.


### `index.php`
Purpose:
- Serves as a module index for the `menuapi` API.
- Authenticates the request and reads Asterisk `res_config_mysql.conf` to open a DB connection.
- Returns a JSON structure describing available modules and endpoints (`get_modules` listing SIP endpoints).

Authentication/Authorization:
- Uses a hardcoded API key string inside the script.
- The client must pass an `Authorization: Bearer <hashed>` header where the hashed value is `password_hash(API_KEY, PASSWORD_DEFAULT)`.
  - The script uses `password_verify($apiKey, $hashedKey)` to authenticate.

DB configuration:
- The script expects Asterisk's MySQL config at `/etc/asterisk/res_config_mysql.conf` in INI format.
- The config must define `dbhost`, `dbname`, `dbuser`, and `dbpass`.

Available option(s):
- `option=get_modules` (POST)
  - Returns a JSON listing of available modules; currently includes a `sip` group with `get_sip_user` metadata.

Example request:
```bash
curl -X POST \
  -H "Authorization: Bearer $(php -r 'echo password_hash("dc3c3e74645d...", PASSWORD_DEFAULT);')" \
  -d "option=get_modules" \
  https://your-host/api/menuapi/index.php
```

Notes & security:
- The API key is embedded in the file; for production, move it to a secure config (environment or vault).
- Using `password_hash` on the client side to compute the header isn't ideal—prefer a shared HMAC or stronger auth (JWT, OAuth2) depending on deployment.


### `sip.php`
Purpose:
- SIP-specific API endpoint(s) backed by Asterisk's `pkg_sip` table.
- Authenticates requests the same way as `index.php` and uses the same DB config file.

Available option(s):
- `option=get_sip_user` (POST)
  - Required POST field: `id_user`
  - Returns a single row from `pkg_sip` with fields: `id_user`, `SIP user` (name), `callerid`, `Username` (accountcode)

Example request:
```bash
curl -X POST \
  -H "Authorization: Bearer <hashed-key>" \
  -d "option=get_sip_user" \
  -d "id_user=7" \
  https://your-host/api/menuapi/sip.php
```

Response shape (success):
```json
{
  "success": true,
  "result": {
    "id_user": "7",
    "SIP user": "alice",
    "callerid": "18001234567",
    "Username": "alice"
  }
}
```

Error responses use `json_error()` and have the form:
```json
{ "success": false, "error": "message" }
```


Operational notes and recommendations
------------------------------------

1. Move secrets out of the codebase
- The API key is hardcoded. Move it to environment variables or a secure config file and load it in both `index.php` and `sip.php`.

2. Use stronger auth
- Consider switching from the hashed API key to one of: signed JWT tokens, mutual TLS, or HMAC-based signatures (which prove the caller controls a secret without storing a hash in code).

3. Validate and sanitize inputs
- The scripts do basic validation but consider stricter checks (numeric `id_user`, length limits) and consistent use of `sanitizeText` for any string inputs.

4. Rate limiting
- Add basic rate limiting (per IP or per API key) if these endpoints are exposed to semi-public networks.

5. Logging and monitoring
- The `json_error` function logs to the PHP error log; consider writing structured logs to a file with timestamps and request IDs for better debugging.

6. DB access security
- Ensure `/etc/asterisk/res_config_mysql.conf` is readable only by the webserver user and contains limited-privilege DB credentials.

7. Unit testing
- Add small integration tests (e.g., via `php -S` or PHPUnit) to ensure endpoint responses remain stable.


Quick troubleshooting
-------------------
- If you get `Config file not found`, confirm the Asterisk MySQL config path and permissions.
- If authentication fails, confirm the header name is exactly `Authorization` and the client is sending the `Bearer <hash>` value.
- DB errors will return `Database connection failed` with the PDO exception message.


Extending the API
-----------------
- Add more SIP endpoints (e.g., `list_sip_users`, `create_sip_user`) by following the `option` switch pattern in `sip.php`.
- If you add write operations, include authentication roles and CSRF protections where appropriate.


Contact
-------
Original author annotations in the source: Thimira Dilshan <thimirad865@gmail.com>


----
Generated: 2025-11-05
