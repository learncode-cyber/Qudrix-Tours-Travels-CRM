# QUDRIX Travel CRM - Hostinger Shared Hosting Deployment Guide

**Last Updated:** August 18, 2026  
**For:** Hostinger Shared Hosting (cPanel)  
**CRM Version:** 2.0.0  
**Author:** AR Qudrix

---

## ⚠️ BEFORE YOU START

- **Backup your Hostinger cPanel** (File Manager → Backups)
- **Document your credentials** (FTP, cPanel username/password, domain name)
- **Read this ENTIRE guide before starting**
- **Estimated time:** 45-60 minutes

---

## STEP 1: Verify Hostinger Environment

### 1.1 Check PHP Version & Extensions

**In cPanel → PHP version:**

Required: **PHP 8.2 or higher** (recommended: PHP 8.3)

**Required PHP Extensions** - Check in cPanel → "Select PHP Version" → Extensions:
- ✅ bcmath (Crypto)
- ✅ ctype (string functions)
- ✅ curl (API calls)
- ✅ fileinfo (file upload)
- ✅ filter (input validation)
- ✅ gd (image manipulation)
- ✅ hash (cryptographic)
- ✅ json (JSON parsing)
- ✅ mbstring (multibyte strings)
- ✅ mysqli (MySQL)
- ✅ openssl (SSL/TLS)
- ✅ pdo_mysql (PDO driver)
- ✅ pcre (regex)
- ✅ session (sessions)
- ✅ tokenizer (Laravel parsing)
- ✅ xml (XML parsing)
- ✅ zip (ZIP archives)

**In cPanel:**
1. Go to "Select PHP Version"
2. Look for your domain/addon domain
3. Click "Switch to PHP 8.2+" button
4. Select all extensions above
5. Apply settings

### 1.2 Check MySQL/MariaDB

**In cPanel → MySQL Databases:**

- Database server: **MySQL 5.7.x or MySQL 8.0+ OR MariaDB 10.3+**
- Check available space: At least **500 MB free**

### 1.3 Enable Composer

**In cPanel → PHP Settings (or Terminal):**

```bash
# SSH into Hostinger
# Check if composer is installed
composer --version

# If not installed, install it
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

---

## STEP 2: Prepare Domain & Directory Structure

### 2.1 Create New Subdirectory (Recommended)

Create a dedicated folder for the CRM to keep it separate from other apps:

**Option A: Using cPanel File Manager**
1. Go to File Manager → public_html
2. Create new folder: `crm` (or `travel-crm`, `qudrix`)
3. Right-click → Change Permissions → `755`

**Option B: Using FTP**
```
Create folder: /public_html/crm/
Set permissions: 755
```

### 2.2 Upload Project Files

**Using FTP (FileZilla or Hostinger FTP):**

1. Download `CLEAN_FINAL_HANDOVER.zip` to your local computer
2. Extract it locally: `QUDRIX_CRM_FINAL/`
3. In FTP, navigate to `/public_html/crm/`
4. Upload folder contents:
   - app/
   - config/
   - database/
   - routes/
   - tests/
   - docs/
   - composer.json
   - composer.lock
   - artisan
   - phpunit.xml
   - .env.example (rename to .env later)

**Using Hostinger File Manager:**

1. Download ZIP to your computer
2. In Hostinger File Manager → Navigate to `/public_html/crm/`
3. Upload ZIP file
4. Extract ZIP
5. Move all contents up one level (remove extra folder)

**Verify Upload:**
```
/public_html/crm/
├── app/
├── config/
├── database/
├── routes/
├── vendor/ (will be created by composer)
├── composer.json
├── composer.lock
├── artisan
├── phpunit.xml
└── .env (to be created)
```

---

## STEP 3: Create Database

### 3.1 Create MySQL Database in cPanel

**Via cPanel → MySQL Databases:**

1. **Create New Database:**
   - Database Name: `yourdomain_qudrix` (e.g., `arqudrix_travel`)
   - Note the full name: `yourusername_qudrix`

2. **Create MySQL User:**
   - Username: `yourdomain_admin` (e.g., `arqudrix_admin`)
   - Password: Generate strong password (25+ chars, mixed case, numbers, symbols)
   - **Save this password safely**

3. **Assign Privileges:**
   - Add user to database
   - Grant ALL PRIVILEGES

4. **Note down:**
   ```
   DB_HOST: localhost
   DB_DATABASE: yourusername_qudrix (full name as shown in cPanel)
   DB_USERNAME: yourusername_admin (full name)
   DB_PASSWORD: your_secure_password
   DB_PORT: 3306
   ```

### 3.2 Verify Database Connection

**Via cPanel → phpMyAdmin:**

1. Login to phpMyAdmin
2. Select your database from left panel
3. Run test query: `SELECT NOW();`
4. If OK, database is working

---

## STEP 4: Configure .env File

### 4.1 Create .env File

**In File Manager or FTP:**

1. Navigate to `/public_html/crm/`
2. Rename `.env.example` to `.env`
3. Edit `.env` with correct values

### 4.2 .env Configuration

**Replace with YOUR values:**

```env
APP_NAME="QUDRIX Travel CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com/crm

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yourusername_qudrix
DB_USERNAME=yourusername_admin
DB_PASSWORD=your_secure_password

# Encryption Key (will generate in STEP 5)
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# JWT Secret (will generate in STEP 5)
JWT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Mail (if using email - optional for Hostinger)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=youremail@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=youremail@yourdomain.com
MAIL_FROM_NAME="QUDRIX Travel CRM"

# Cache & Session
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

### 4.3 Important .env Settings for Production

```env
APP_DEBUG=false              # NEVER true in production
APP_ENV=production
ASSET_URL=https://yourdomain.com/crm
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
SESSION_DOMAIN=yourdomain.com
TRUSTED_PROXIES=*            # For Hostinger load balancer
LOG_CHANNEL=stack
```

---

## STEP 5: Generate Application Keys

**Via Hostinger Terminal/SSH:**

```bash
cd /home/yourusername/public_html/crm

# Generate APP_KEY
php artisan key:generate

# Generate JWT_SECRET
php artisan jwt:secret

# Output should show:
# Application key set successfully.
# JWT secret set successfully.
```

**Copy both values from .env to confirm they're set**

---

## STEP 6: Install PHP Dependencies (Composer)

### 6.1 Via SSH Terminal

**Connect via SSH (Hostinger → Tools → SSH Terminal):**

```bash
cd /home/yourusername/public_html/crm

# Install dependencies
composer install --no-dev --optimize-autoloader

# This installs everything in vendor/
# Estimated time: 3-5 minutes
# Shows: "Generating optimized autoload files"
```

### 6.2 Verify Installation

```bash
# Check if vendor folder created
ls -la vendor | head -20

# Should show hundreds of packages
```

### 6.3 If Composer Fails

**Common issues:**

```bash
# Memory limit error
php -d memory_limit=512M /usr/local/bin/composer install --no-dev

# Timeout error - try update
php -d memory_limit=512M /usr/local/bin/composer update --no-dev

# Check PHP version
php -v  # Should show 8.2+
```

---

## STEP 7: Run Database Migrations

### 7.1 Create Database Tables

**Via SSH Terminal:**

```bash
cd /home/yourusername/public_html/crm

# Run migrations
php artisan migrate

# Output should show:
# Migration: xxx_create_users_table
# Migration: xxx_create_api_keys_table
# ... (68 migrations total)
```

### 7.2 Verify Database

**Via cPanel → phpMyAdmin:**

1. Select your database
2. Tables tab should show: `users`, `customers`, `leads`, `bookings`, `packages`, etc.
3. Count tables - should be approximately **68 tables**

---

## STEP 8: Create Storage Symlink

### 8.1 Link Storage Folder

```bash
cd /home/yourusername/public_html/crm

# Create symlink for file uploads
php artisan storage:link

# Output: "The [public/storage] directory has been successfully linked."
```

### 8.2 Set Permissions

```bash
# Make storage writable
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Verify owner (if using FTP)
chown -R yourusername:yourusername storage/
chown -R yourusername:yourusername bootstrap/cache/
```

---

## STEP 9: Configure Web Server (Apache)

### 9.1 Set Web Root

**In cPanel → Addon Domains (or Main Domain):**

1. Go to "Addon Domains"
2. For your domain, set "Document Root" to:
   ```
   /home/yourusername/public_html/crm/public
   ```
3. Or create a subdomain:
   ```
   crm.yourdomain.com → /home/yourusername/public_html/crm/public
   ```

### 9.2 .htaccess Configuration

**Create/Update `.htaccess` in `/public_html/crm/public/`:**

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)/$ /$1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Gzip Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Caching Headers
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 9.3 Parent .htaccess

**Create `.htaccess` in `/public_html/crm/` (parent):**

```apache
# Redirect all traffic to public/
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## STEP 10: Enable SSL/HTTPS

### 10.1 Auto SSL in cPanel

1. **cPanel → SSL/TLS:**
   - Find your domain
   - Click "Install SSL Website"
   - Hostinger provides free AutoSSL

2. **Force HTTPS:**
   - Add to `.htaccess` in `public/`:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### 10.2 Update .env

```env
APP_URL=https://yourdomain.com/crm  # Must be HTTPS
```

---

## STEP 11: Set File Permissions

### 11.1 Correct Permissions via SSH

```bash
cd /home/yourusername/public_html/crm

# Make everything readable
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Make storage & bootstrap writable
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Make artisan executable
chmod +x artisan
```

### 11.2 If Using FTP

Right-click folders → Permissions:
```
storage/         → 755 (or 775)
bootstrap/cache/ → 755 (or 775)
config/          → 755
```

---

## STEP 12: Configure Production Settings

### 12.1 Optimize Laravel

```bash
cd /home/yourusername/public_html/crm

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Don't cache if still debugging
```

### 12.2 Disable Debug Mode (CRITICAL)

In `.env`:
```env
APP_DEBUG=false
```

### 12.3 Set Log Channel

In `.env`:
```env
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

---

## STEP 13: Create Admin User

### 13.1 Via Artisan Command (SSH)

```bash
cd /home/yourusername/public_html/crm

php artisan tinker

# Inside tinker shell:
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@yourdomain.com';
$user->password = bcrypt('YourSecurePassword123!');
$user->role = 'super-admin';
$user->is_active = true;
$user->save();

exit
```

### 13.2 Or Use Database Directly

**Via phpMyAdmin:**

1. Go to `users` table
2. Insert new record:
   - name: `Admin`
   - email: `admin@yourdomain.com`
   - password: Hash using bcrypt (use online tool if needed)
   - role: `super-admin`
   - is_active: `1`

### 13.3 Login

```
URL: https://yourdomain.com/crm/admin
Email: admin@yourdomain.com
Password: YourSecurePassword123!
```

---

## STEP 14: Setup Cron Jobs (Optional but Recommended)

### 14.1 For Automated Tasks

**In cPanel → Cron Jobs:**

1. Add Standard Cron Job:
   - Time: Every 5 minutes
   - Command:
   ```bash
   cd /home/yourusername/public_html/crm && php artisan schedule:run >> /dev/null 2>&1
   ```

This runs queued jobs, webhooks, automations every 5 minutes.

### 14.2 For Backups (Daily)

```bash
cd /home/yourusername/public_html/crm && php artisan backup:run
```

---

## STEP 15: Test Installation

### 15.1 Health Check

**Via browser:**

```
https://yourdomain.com/crm/api/v1/health
```

Should return:
```json
{
  "status": "ok",
  "message": "QUDRIX API is running",
  "timestamp": "2026-08-18T12:34:56.789Z"
}
```

### 15.2 Admin Login

1. Go to `https://yourdomain.com/crm/admin`
2. Login with admin@yourdomain.com / YourSecurePassword123!
3. Should see dashboard

### 15.3 Database Connection

```bash
php artisan tinker
DB::connection()->getPdo();  # Should return connection object
```

---

## STEP 16: Setup Backups

### 16.1 Hostinger Backup Manager

1. cPanel → Backups
2. Set automatic daily backups
3. Download to external storage weekly

### 16.2 Database Backup

```bash
cd /home/yourusername/public_html/crm

# Manual backup
php artisan backup:run

# Or export via phpMyAdmin
```

### 16.3 Create Script for Regular Backups

Create `backup.sh` in `/home/yourusername/`:

```bash
#!/bin/bash
BACKUP_DIR="/home/yourusername/backups"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u yourusername_admin -p'your_password' yourusername_qudrix > $BACKUP_DIR/db_$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/crm_$DATE.tar.gz /home/yourusername/public_html/crm/

# Keep only last 30 days
find $BACKUP_DIR -mtime +30 -delete
```

---

## TROUBLESHOOTING

### Issue: 500 Internal Server Error

**Check:**
1. `.env` file exists and configured
2. `APP_KEY` is set: `php artisan key:generate`
3. Database connection works
4. Storage folder writable: `chmod -R 775 storage/`
5. Check error logs: `tail -f storage/logs/laravel.log`

### Issue: "Class not found" Errors

**Fix:**
```bash
composer install
php artisan clear-compiled
php artisan optimize
```

### Issue: Database Connection Failed

**Check:**
```bash
php artisan tinker
DB::connection()->getPdo();
```

If fails, verify .env:
- DB_HOST: localhost (not IP for Hostinger)
- DB_DATABASE: Full name from cPanel (yourusername_qudrix)
- DB_USERNAME: Full name from cPanel (yourusername_admin)
- DB_PASSWORD: Correct special chars? Use quotes if special chars

### Issue: Composer Memory Limit

**Fix:**
```bash
php -d memory_limit=512M /usr/local/bin/composer install
```

### Issue: HTTPS Redirect Loop

**Fix:** Remove conflicting redirect rules from `.htaccess`, keep only one:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Issue: File Upload Fails

**Check permissions:**
```bash
chmod -R 775 storage/app/
chmod -R 775 storage/logs/
chmod -R 775 bootstrap/cache/
```

### Issue: API Routes Not Working

**Verify:**
```bash
php artisan route:list | grep api

# Should show all /api/v1 routes
```

**Fix:**
```bash
php artisan route:cache
php artisan config:cache
```

### Check Laravel Logs

```bash
# Real-time logs
tail -f storage/logs/laravel.log

# Last 50 lines
tail -50 storage/logs/laravel.log

# All errors
grep -i "error\|exception" storage/logs/laravel.log
```

---

## VERIFICATION CHECKLIST

Before going live, verify:

- ✅ PHP 8.2+ installed
- ✅ All extensions enabled
- ✅ MySQL database created
- ✅ .env file configured correctly
- ✅ APP_KEY generated
- ✅ JWT_SECRET generated
- ✅ Composer packages installed
- ✅ Migrations run successfully
- ✅ Storage linked
- ✅ Web root set to `/public`
- ✅ .htaccess in place
- ✅ SSL enabled
- ✅ API health check working
- ✅ Admin login working
- ✅ Storage/cache folders writable
- ✅ Logs checking properly
- ✅ Backups enabled
- ✅ Cron jobs configured

---

## PRODUCTION CHECKLIST

Before real usage:

- ✅ Change all default passwords
- ✅ APP_DEBUG=false
- ✅ Change email/SMTP settings
- ✅ Configure branding
- ✅ Test all major features
- ✅ Setup email notifications
- ✅ Enable API for websites
- ✅ Configure webhooks
- ✅ Test backups work
- ✅ Monitor performance
- ✅ Security audit logs

---

## PERFORMANCE OPTIMIZATION (Optional)

```bash
cd /home/yourusername/public_html/crm

# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize

# Clear any old cache
php artisan cache:clear
```

---

## SUPPORT & NEXT STEPS

1. **Verify everything works:** Run tests, login, test API
2. **Read documentation:** Check DOCUMENTATION/ folder
3. **Configure features:** Set up email, webhooks, API keys
4. **Train team:** Show dashboard, basic CRM usage
5. **Monitor:** Check logs daily for first week

---

**Deployment Status:** Ready for production  
**Estimated Setup Time:** 45-60 minutes  
**Next Update:** As needed based on your requirements

Good luck! 🚀
