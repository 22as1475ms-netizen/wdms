# ONLYOFFICE Production Setup (WDMS)

## 1. Configure Apache environment variables (XAMPP Windows)

Edit Apache config (`httpd.conf` or your `httpd-vhosts.conf`) and add:

```apache
SetEnv APP_URL "https://your-public-domain"
SetEnv APP_SECRET "replace-with-long-random-secret"

SetEnv ONLYOFFICE_SERVER_URL "https://your-onlyoffice-docs-endpoint"
SetEnv ONLYOFFICE_JWT_SECRET "replace-with-shared-jwt-secret"
SetEnv ONLYOFFICE_VERIFY_SSL "true"
SetEnv ONLYOFFICE_ENFORCE_TRUSTED_DOWNLOAD_HOST "true"
SetEnv ONLYOFFICE_DOWNLOAD_TIMEOUT_SECONDS "30"
```

Restart Apache after changes.

## 2. Required behavior checks

While logged in to WDMS:

1. `GET /wdms/public/api/editor/health?refresh=1`
2. Confirm:
   - `"reachable": true`
   - `"ready": true`
   - `"critical": []`

## 3. Common blockers

- `APP_URL` is localhost while `ONLYOFFICE_SERVER_URL` is remote:
  callback cannot reach your WDMS.
- `ONLYOFFICE_JWT_SECRET` mismatch:
  editor can open but save callback fails.
- HTTP-only endpoint in production:
  TLS/SSL validation and browser security issues.

## 4. Testing flow

1. Open a `.docx` or `.xlsx` file.
2. Click **Open in OnlyOffice**.
3. Edit and save.
4. Confirm a new version appears in version history.

