# QUDRIX CRM - Deployment Guide (Hostinger Business)

---

## Pre-Deployment Checklist

- [ ] Hostinger Business account active
- [ ] FTP/SFTP access configured
- [ ] Database credentials obtained
- [ ] PHP 8.2+ enabled
- [ ] SSL certificate configured
- [ ] Domain configured and pointing to server

---

## Step 1: Upload Files to Hostinger

### Option A: Using FTP (FileZilla)

1. Download and install [FileZilla](https://filezilla-project.org/)
2. Connect with provided FTP credentials:
   - Host: `ftp.yourdomain.com`
   - Username: Your Hostinger username
   - Password: Your FTP password
   - Port: 21
3. Navigate to `public_html/` directory
4. Upload all project files except:
   - `.git/` directory
   - `.gitignore`
   - `node_modules/` (if present)
   - `/vendor/` directory (will reinstall via Composer)

### Option B: Using Hostinger File Manager

1. Login to Hostinger Control Panel
2. Go to File Manager
3. Navigate to `public_html/`
4. Upload files manually or use the upload function

---

## Step 2: Create Database

### Via Hostinger Control Panel

1. Go to **Databases**
2. Click **Create Database**
3. Database name: `qudrix_crm`
4. Database user: `qudrix_user`
5. Password: Set a strong password
6. Click **Create**

### Via SSH (Recommended)

```bash
mysql -u root -p
CREATE DATABASE qudrix_crm;
CREATE USER 'qudrix_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON qudrix_crm.* TO 'qudrix_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 3: Import Database Schema

### Via SSH (Recommended)

```bash
cd /home/username/public_html
mysql -u qudrix_user -p qudrix_crm < QUDRIX_CRM_PHASE_0_DATABASE.sql
```

### Via phpMyAdmin

1. Go to phpMyAdmin in Hostinger Control Panel
2. Select database `qudrix_crm`
3. Click **Import**
4. Upload `QUDRIX_CRM_PHASE_0_DATABASE.sql`
5. Click **Go**

---

## Step 4: Configure Environment

### 1. Rename `.env.example` to `.env`

Via FTP:
- Rename `.env.example` to `.env` in `public_html/`

Via SSH:
```bash
cd /home/username/public_html
cp .env.example .env
```

### 2. Edit `.env` with Database Credentials

```bash
nano .env
```

Update these values:

```env
APP_NAME="QUDRIX CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=qudrix_crm
DB_USERNAME=qudrix_user
DB_PASSWORD=StrongPassword123!

JWT_SECRET=generate_random_secret_here
```

Generate JWT secret:
```bash
php artisan jwt:secret
```

---

## Step 5: Install Dependencies

### Via SSH (Required)

```bash
cd /home/username/public_html

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Note:** If composer is not available, request it from Hostinger support or upgrade to VPS.

---

## Step 6: Set Permissions

### Via SSH

```bash
cd /home/username/public_html

# Set storage permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# Set file permissions
chmod -R 644 resources/
chmod -R 644 config/
chmod -R 644 routes/
```

### Via FTP

Set permissions using FileZilla:
- Right-click `storage/` → Properties → Set to **755**
- Right-click `bootstrap/cache/` → Set to **755**

---

## Step 7: Verify Installation

### Test API Endpoint

Visit in browser:
```
https://yourdomain.com/api/v1/health
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00Z",
  "database": "connected",
  "version": "1.0.0"
}
```

### Test Login

```bash
curl -X POST https://yourdomain.com/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "Password@123"
  }'
```

---

## Step 8: Enable Required PHP Extensions

### Via Hostinger Control Panel

1. Go to **Advanced → PHP Configuration**
2. Ensure these are enabled:
   - ✅ mysqli
   - ✅ pdo
   - ✅ pdo_mysql
   - ✅ mbstring
   - ✅ xml
   - ✅ curl
   - ✅ gd
   - ✅ json (default)

---

## Troubleshooting

### Issue: 500 Internal Server Error

**Solution:**
1. Check `storage/logs/laravel.log` for errors
2. Verify database connection in `.env`
3. Verify permissions on `storage/` directory
4. Run `php artisan migrate` if tables missing

### Issue: "App key not set"

**Solution:**
```bash
php artisan key:generate
```

### Issue: Database connection refused

**Solution:**
1. Verify DB credentials in `.env`
2. Verify database user has proper permissions:
   ```sql
   GRANT ALL PRIVILEGES ON qudrix_crm.* TO 'qudrix_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Verify MySQL service running

### Issue: Class not found errors

**Solution:**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Issue: 403 Forbidden on storage/

**Solution:**
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

---

## Post-Deployment

### 1. Update Admin Credentials

SSH into server:
```bash
php artisan tinker
```

Then in Tinker:
```php
$user = App\Models\User::find(1);
$user->email = 'new-email@example.com';
$user->password = Hash::make('NewPassword123!');
$user->save();
```

### 2. Configure Email (Optional)

Edit `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### 3. Set Up Regular Backups

Hostinger provides automated backups. Verify:
1. Go to **Backups** in control panel
2. Enable automatic daily backups

### 4. Monitor Logs

Regular log monitoring:
```bash
tail -f /home/username/public_html/storage/logs/laravel.log
```

---

## SSL/HTTPS Configuration

Hostinger includes free SSL with AutoSSL:

1. Go to **SSL/Security**
2. Verify AutoSSL is active
3. Certificate auto-renews automatically

---

## Performance Optimization

### Enable Caching

```bash
# File caching
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Optimize Autoloader

Already included in deployment:
```bash
composer install --optimize-autoloader --no-dev
```

---

## Monitoring & Maintenance

### Daily Tasks
- [ ] Check error logs: `storage/logs/laravel.log`
- [ ] Monitor disk space usage
- [ ] Verify backups running

### Weekly Tasks
- [ ] Review audit logs
- [ ] Check user access logs
- [ ] Verify SSL certificate valid

### Monthly Tasks
- [ ] Update dependencies
- [ ] Run security audit
- [ ] Review performance metrics

---

## Update Procedure (Future Phases)

1. Backup database and files
2. Download new phase ZIP
3. Extract to temporary directory
4. Copy new files to `public_html/`
5. Run migrations: `php artisan migrate`
6. Clear caches: `php artisan optimize:clear`
7. Test thoroughly
8. Monitor error logs

---

## Support & Troubleshooting

### Hostinger Support
- Control Panel: https://hpanel.hostinger.com
- Help Center: https://support.hostinger.com

### Laravel Documentation
- Official: https://laravel.com/docs/11
- JWT Auth: https://jwt-auth.readthedocs.io

### Project Documentation
- README.md - Quick start
- ARCHITECTURE.md - System design
- API.md - API endpoints

---

**Deployment Status:** ✅ Ready for Hostinger Business Shared Hosting
