# Netlify + Railway Deployment Guide

## Architecture Overview

```
┌─────────────────────────────────┐
│ Your Domain                     │
│ (example.com)                   │
└────────────────┬────────────────┘
                 │
        ┌────────▼────────┐
        │                 │
   ┌────▼────┐      ┌────▼─────┐
   │ Netlify │      │ Railway   │
   │(CDN)    │      │(Backend)  │
   │Static   │────→ │PHP Server │
   │Frontend │      │MySQL DB   │
   └─────────┘      └───────────┘
```

**How it works:**
1. Netlify serves static files (HTML, CSS, JS) from its global CDN
2. Netlify proxies all PHP requests to your Railway backend via `_redirects`
3. Railway runs PHP-FPM + Nginx + MySQL (or uses Railway's managed MySQL)
4. Sessions and cookies work seamlessly across the proxy layer
5. Everything is HTTPS-encrypted

---

## Prerequisites

- GitHub account with your repo pushed
- Netlify account (free tier OK)
- Railway account (free tier with $5/month credits OK)
- Your domain (can point to Netlify or use their subdomain)

---

## Step 1: Deploy Backend to Railway

### 1a. Create Railway Project

1. Go to https://railway.app and log in
2. Click **"+ New Project"** or **"Create"**
3. Select **"Deploy from GitHub"**
4. Authorize Railway to access your GitHub
5. Select your `ejustice_portal` repository
6. Click **"Deploy now"**

Railway auto-detects your `Procfile` and starts building.

### 1b. Add MySQL Database Plugin

1. In your Railway project dashboard, click **"+ Add Service"** or **"+ Add Plugin"**
2. Select **"MySQL"** (or **"Postgres"** if you prefer)
3. Railway provisions a managed database and auto-injects `DATABASE_URL` into your app environment

**Wait for MySQL to be Ready** — you'll see a green checkmark.

### 1c. Set Environment Variables

In Railway project dashboard → **Variables** tab:

```
DB_HOST=localhost
DB_NAME=railway
DB_USER=root
DB_PASS=[auto-generated, shown in Variables]
DOC_ENC_KEY=[generate strong 32+ char string]
APP_ENV=production
APP_DEBUG=false
```

**To generate DOC_ENC_KEY** (PowerShell):
```powershell
[System.Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes((Get-Random -Count 32)))
```

Or use OpenSSL:
```bash
openssl rand -hex 32
```

Copy the key value and paste into Railway Variables.

### 1d. Verify Backend Deployment

1. Wait for Railway build to complete (shows "Active" status)
2. Click **"View Logs"** to see PHP server starting
3. Copy your **Railway public URL** (e.g., `https://ejustice-production.railway.app`)
4. Test in browser: `https://your-railway-url/login.php`
   - Should see login page (no 502, 404, or blank)

### 1e. Run Database Migrations on Railway

Once MySQL is ready and app is deployed:

**Option 1: Via phpMyAdmin (if exposed)**
- Access `https://your-railway-url/phpmyadmin`
- Select `railway` database
- Import each SQL file in order:
  1. `sql/ejustice_portal.sql`
  2. `sql/002_add_audit_logs.sql`
  3. `sql/003_add_barangay_module.sql`
  4. `sql/004_add_barangay_case_routing.sql`

**Option 2: Via SSH (if Railway provides SSH access)**
```bash
ssh [railway-connection-string]
cd /app
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/ejustice_portal.sql
# Repeat for other 3 files
```

**Option 3: Create a migration endpoint (recommended)**
Add `public/run_migrations.php`:
```php
<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

// Only allow system_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'system_admin') {
    die('Unauthorized');
}

$migrations = [
    'sql/ejustice_portal.sql',
    'sql/002_add_audit_logs.sql',
    'sql/003_add_barangay_module.sql',
    'sql/004_add_barangay_case_routing.sql',
];

foreach ($migrations as $file) {
    if (file_exists("../$file")) {
        $sql = file_get_contents("../$file");
        try {
            $pdo->exec($sql);
            echo "✓ $file<br>";
        } catch (Exception $e) {
            echo "✗ $file: " . $e->getMessage() . "<br>";
        }
    }
}
?>
```

Then visit: `https://your-railway-url/run_migrations.php` (logged in as admin)

---

## Step 2: Deploy Frontend to Netlify

### 2a. Connect Netlify to Git

1. Go to https://netlify.com and log in
2. Click **"Add new site"** → **"Connect to Git"**
3. Select your Git provider (GitHub, GitLab, Bitbucket)
4. Authorize Netlify
5. Select your `ejustice_portal` repository
6. Click **"Deploy site"**

### 2b. Configure Netlify Settings

When prompted for build settings:
- **Base directory**: (leave empty)
- **Build command**: (leave empty — this is static)
- **Publish directory**: `public`

Click **"Deploy site"**.

Netlify auto-deploys and gives you a URL like `https://your-site-name.netlify.app`.

### 2c. Update Proxy Rules

Netlify uses `public/_redirects` or `netlify.toml` to proxy API calls to your Railway backend.

**Update `public/_redirects`:**

Find the line with `BACKEND_URL` and replace with your Railway URL:

```
# Before:
/api/*                      BACKEND_URL/:splat  200!
/index.php                  BACKEND_URL/index.php  200!
/login.php                  BACKEND_URL/login.php  200!

# After (example):
/api/*                      https://ejustice-production.railway.app/:splat  200!
/index.php                  https://ejustice-production.railway.app/index.php  200!
/login.php                  https://ejustice-production.railway.app/login.php  200!
```

**Replace ALL occurrences of `BACKEND_URL`** in the file with your actual Railway URL.

Commit and push:
```bash
git add public/_redirects
git commit -m "Update Netlify proxy to Railway backend URL"
git push origin main
```

Netlify auto-redeploys when you push to Git.

### 2d. Verify Frontend Deployment

1. Wait for Netlify redeploy (watch "Deployments" tab)
2. Open `https://your-site-name.netlify.app/login.php`
3. You should see the login page (proxied from Railway)

---

## Step 3: Test End-to-End

### 3a. Seed Demo Users

Visit: `https://your-site-name.netlify.app/seed_demo_users.php`

(Only runs once; creates demo accounts.)

### 3b. Test Login & Sessions

1. Login with: `admin@example.com` / `password`
2. Navigate to dashboard (session should persist)
3. Reload page — should still be logged in

### 3c. Test File Upload & Encryption

1. File a case as complainant
2. Upload a document
3. View the document (should decrypt and audit log)

### 3d. Test Barangay Features

1. Login as barangay staff (`barangay@example.com`)
2. Check Barangay Dashboard
3. Record complaint, track mediation, generate settlement form
4. Verify escalation to police blotter if unresolved

### 3e. Test Audit Logs

1. Login as RTC staff (`rtcstaff@example.com`)
2. Go to Audit Logs page
3. View encrypted document access trails

---

## Step 4: Set Up Custom Domain (Optional)

### On Netlify

1. Go to Netlify dashboard → Your site → **Site settings** → **Domain management**
2. Click **"Add domain"**
3. Enter your domain (e.g., `ejustice.example.com`)
4. Follow DNS setup instructions (point nameservers or add CNAME record)
5. Netlify auto-provisions SSL certificate (Let's Encrypt)

Your site is now live at `https://ejustice.example.com` ✅

---

## Step 5: Enable Auto-Deployment

Both Netlify and Railway auto-deploy when you push to GitHub:

1. **Make changes** locally
2. **Commit**: `git commit -m "..."`
3. **Push**: `git push origin main`
4. Netlify redeploys frontend (~30 seconds)
5. Railway redeploys backend (~1–2 minutes)

**Both services deploy in parallel** → Your changes are live!

---

## Environment Variables Reference

### Railway (Backend)

```
DATABASE_URL              [auto-set by MySQL plugin]
DB_HOST                   localhost
DB_NAME                   railway
DB_USER                   root
DB_PASS                   [auto-generated]
DOC_ENC_KEY              [your strong secret key]
APP_ENV                   production
APP_DEBUG                 false
PORT                      [auto-set, default 8080]
```

### Netlify (Frontend)

```
REACT_APP_BACKEND_URL    https://your-railway-url  [optional, for frontend config]
```

(Most configs are in proxy rules, not env vars.)

---

## Troubleshooting

### Issue: "Cannot GET /login.php" (404 on Netlify)

**Cause**: Proxy rule not matching or Railway URL is wrong

**Fix**:
- Check `public/_redirects` — is your Railway URL correct?
- Test Railway directly: `curl https://your-railway-url/login.php`
- Netlify Deployments tab → View deploy logs for proxy errors

### Issue: "502 Bad Gateway"

**Cause**: Railway backend overloaded or crashed

**Fix**:
- Check Railway logs: Railway dashboard → Your app → View Logs
- Look for PHP errors, DB connection errors
- Restart service: Railway dashboard → Service → Restart

### Issue: Sessions lost after redirects

**Cause**: Cookies not preserved across proxy

**Fix**:
- Ensure `config/config.php` does NOT set `session.cookie_domain` to a fixed domain
- Set `session.cookie_samesite = 'Lax'` (default, OK for proxy)
- Verify Netlify isn't stripping Set-Cookie headers (unlikely)

### Issue: Database connection fails on Railway

**Cause**: DB credentials wrong or MySQL not ready

**Fix**:
- Check Railway Variables → DB_HOST, DB_USER, DB_PASS match
- Ensure MySQL plugin is "Active" (green checkmark)
- Restart MySQL service and re-deploy app

### Issue: Encrypted document upload fails

**Cause**: `storage/documents/` is ephemeral on Railway

**Fix**:
- Option A: Use Railway volumes (persistent storage)
  - In Railway settings, attach volume to `/app/storage`
- Option B: Use Supabase Storage or S3
  - Update `upload_document.php` to store to S3 (not local)

---

## Performance & Scaling

### Netlify (Frontend)
- Global CDN → fast static asset delivery
- No server resources used for static files
- Auto-scales to handle spikes

### Railway (Backend)
- Shared PHP runtime (free tier) or dedicated (paid)
- MySQL auto-scales based on load
- Monitor resource usage in Railway dashboard

### Optimization Tips

1. **Enable PHP OPcache** (Railway usually has it by default)
2. **Set up database indexes** (see `sql/` files)
3. **Compress encrypted documents** before upload
4. **Cache static assets** (CSS, JS, images) on Netlify CDN

---

## Continuous Integration / Deployment (CI/CD)

Both Netlify and Railway support Git webhooks:

1. **On commit to main branch**:
   - GitHub sends webhook to Netlify + Railway
   - Both services auto-pull latest code and redeploy

2. **To disable auto-deploy**:
   - Netlify: Site settings → Build & deploy → Auto publish (disable)
   - Railway: Project settings → Auto-deploy (disable)

3. **Manual deploy**:
   - Netlify: Deployments tab → "Trigger deploy" button
   - Railway: Deployments tab → Restart service

---

## Backup & Disaster Recovery

### Railway Backups

1. Go to Railway project → MySQL service
2. In service details, backups are auto-created daily
3. Download backup from Railway dashboard or retain for 7–30 days

### Manual Database Backup

```bash
# SSH into Railway or use local CLI
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME > backup_$(date +%Y%m%d).sql

# Or from Netlify (if you have CLI access):
curl https://your-railway-url/phpmyadmin → export DB
```

### Code Backup

Your code is always on GitHub — use GitHub as your backup source.

---

## Security Checklist

- [x] HTTPS enabled (Netlify + Railway auto-provide SSL)
- [x] Environment variables stored securely (Railway + Netlify dashboards)
- [x] `DOC_ENC_KEY` is strong (32+ chars, random)
- [x] Database credentials in env vars, NOT in code
- [x] PHP error messages hidden in production (`APP_DEBUG=false`)
- [x] File permissions set correctly (Procfile + docker-entrypoint.sh handle this)
- [x] Audit logs enabled (records all actions)
- [ ] Add rate limiting on login (optional, for brute-force protection)
- [ ] Add CORS headers if needed (for future API clients)

---

## Cost Breakdown

| Service | Free Tier | Paid Tier |
|---------|-----------|-----------|
| **Netlify** | 100 GB/month bandwidth, 300 builds/month | $19/month (Pro) or pay-as-you-go |
| **Railway** | $5/month free credits | $5–50/month depending on usage |
| **Domain** | Not included | $10–15/year (from Namecheap, GoDaddy, etc.) |
| **Total** | ~$10–15/month for small app | $30–40/month for moderate traffic |

---

## Next Steps

1. ✅ Create Railway project and deploy backend
2. ✅ Add MySQL plugin to Railway
3. ✅ Set environment variables on Railway
4. ✅ Run database migrations
5. ✅ Create Netlify project and deploy frontend
6. ✅ Update `public/_redirects` with Railway URL
7. ✅ Test end-to-end: login, file cases, upload docs, audit logs
8. ✅ Set up custom domain (optional)
9. ✅ Monitor logs and backups

**You're live!** 🚀 Every `git push` deploys both frontend and backend automatically.

---

## Support & Resources

- **Railway Docs**: https://docs.railway.app
- **Netlify Docs**: https://docs.netlify.com
- **PHP on Railway**: https://docs.railway.app/guides/php
- **Netlify Redirects**: https://docs.netlify.com/routing/redirects/
- **Railway MySQL**: https://docs.railway.app/databases/mysql

For specific errors, check your service logs in the respective dashboards.
