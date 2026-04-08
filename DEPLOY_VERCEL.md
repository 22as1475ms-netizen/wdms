# Vercel Deployment

This project can now run on Vercel with the PHP community runtime.

## Required Environment Variables

- `APP_SECRET`
- `APP_URL`
- `APP_BASE_PATH`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Recommended Environment Variables

- `SESSION_DRIVER=database`
- `STORAGE_DRIVER=database`
- `DB_AUTO_BOOTSTRAP_SCHEMA=1`
- `DB_AUTO_UNIFY_ROUTED_STORAGE=0`
- `MAX_UPLOAD_BYTES_USER=4194304`
- `MAX_UPLOAD_BYTES_ADMIN=4194304`

## Notes

- On Vercel, the app automatically defaults to database-backed sessions and database-backed file storage if `SESSION_DRIVER` and `STORAGE_DRIVER` are not set.
- `APP_SECRET` must be provided on Vercel. The app will not try to persist a local secret there.
- Static assets from `public/` are served by Vercel before the catch-all rewrite runs.
- Document files, avatars, and chat attachments can now persist in MySQL, which avoids dependence on Vercel's read-only filesystem.
- Function-based uploads on Vercel should stay under roughly 4 MB unless you later move to direct-to-blob client uploads.
