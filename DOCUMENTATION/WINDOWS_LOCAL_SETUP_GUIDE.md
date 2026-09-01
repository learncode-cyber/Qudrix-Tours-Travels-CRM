# QUDRIX Travel CRM - Windows Local Development Setup

**Last Updated:** August 18, 2026  
**For:** Windows 10/11 (64-bit)  
**CRM Version:** 2.0.0  
**Estimated Setup Time:** 30-40 minutes

---

## ⚠️ IMPORTANT NOTES FOR WINDOWS

- This guide uses **Windows PowerShell** (NOT Command Prompt)
- Use **exactly** the commands shown - do NOT mix with Linux commands
- File paths use backslashes: `C:\Users\YourName\projects\`
- Open PowerShell as **Administrator** for some steps

---

## STEP 1: Install Required Software

### 1.1 Install PHP

**Download from:** https://windows.php.net/download/

1. Go to "VS16 x64 Thread Safe" (for local development)
2. Download latest PHP 8.3 ZIP
3. Extract to: `C:\php\`

**Verify folder structure:**
```
C:\php\
├── php.exe
├── php-cgi.exe
├── php.ini-production
├── php.ini-development
└── ext/
```

### 1.2 Setup php.ini

1. Copy `php.ini-development` to `php.ini`
   ```powershell
   Copy-Item C:\php\php.ini-development C:\php\php.ini
   ```

2. Edit `C:\php\php.ini` - Find and uncomment these lines:
   ```ini
   extension_dir = "ext"
   
   extension=bcmath
   extension=ctype
   extension=curl
   extension=fileinfo
   extension=filter
   extension=gd
   extension=hash
   extension=json
   extension=mbstring
   extension=mysqli
   extension=openssl
   extension=pdo_mysql
   extension=pcre
   extension=session
   extension=tokenizer
   extension=xml
   extension=zip
   
   ; For Laravel
   max_execution_time = 300
   memory_limit = 512M
   post_max_size = 100M
   upload_max_filesize = 100M
   ```

3. Save file

### 1.3 Add PHP to Windows PATH

1. Open **Settings → Environment Variables**
2. Click "Environment Variables"
3. Under "User variables", click "New"
   - Variable name: `PATH`
   - Variable value: `C:\php`
4. Click OK, OK, OK
5. **Restart PowerShell** as Administrator

**Verify:**
```powershell
php --version
```

Should show: `PHP 8.3.x (cli) ...`

### 1.4 Install MySQL (or MariaDB)

**Option A: MySQL Community Server**

1. Download from: https://dev.mysql.com/downloads/mysql/
2. Download Windows (x86, 64-bit) MSI Installer
3. Run installer
4. Choose "Developer Default"
5. Configure MySQL Server:
   - Port: `3306`
   - Service name: `MySQL80`
   - Launch at startup: ✅ Yes
6. Configure MySQL Server as Windows Service
7. Set password for root user
8. Complete installation

**Option B: MariaDB** (easier for development)

1. Download from: https://mariadb.org/download/
2. Download Windows (x86_64) MSI installer
3. Run installer
4. Select "Typical" installation
5. Enable "Launch MariaDB Service"
6. Set root password
7. Complete installation

**Verify:**
```powershell
mysql --version
```

Should show: `mysql Ver 8.0.x ...` or `mysql Ver 15.1.x (MariaDB) ...`

### 1.5 Install Composer

1. Download from: https://getcomposer.org/download/
2. Choose "Windows Installer (Composer-Setup.exe)"
3. Run installer
4. Select PHP executable location: `C:\php\php.exe`
5. Complete installation
6. **Restart PowerShell**

**Verify:**
```powershell
composer --version
```

Should show: `Composer version 2.x.x ...`

### 1.6 Install Git (Optional but Recommended)

1. Download from: https://git-scm.com/download/win
2. Run installer
3. Choose all defaults
4. Select "Use Git from PowerShell"
5. Complete installation

**Verify:**
```powershell
git --version
```

---

## STEP 2: Create Project Directory

### 2.1 Create Folder

```powershell
# Navigate to your projects folder
Set-Location C:\Users\YourUsername\Documents

# Create project folder
New-Item -ItemType Directory -Path "qudrix-crm"

# Enter project folder
Set-Location qudrix-crm

# Verify
Get-Location
```

Should show: `C:\Users\YourUsername\Documents\qudrix-crm`

### 2.2 Extract Project Files

1. Download `CLEAN_FINAL_HANDOVER.zip`
2. Right-click → Extract All
3. Extract to: `C:\Users\YourUsername\Documents\qudrix-crm\`

**Verify structure:**
```powershell
dir

# Should show:
# PROJECT/
# DOCUMENTATION/
```

---

## STEP 3: Copy PROJECT Files

```powershell
# Navigate to extracted folder
Set-Location "C:\Users\YourUsername\Documents\qudrix-crm\PROJECT"

# Copy all files from PROJECT to parent
Get-ChildItem | ForEach-Object { Copy-Item $_ -Path ".." -Recurse -Force }

# Go back to main folder
Set-Location ..

# Verify (should see app/, config/, routes/, etc. here)
dir
```

---

## STEP 4: Create Database

### 4.1 Open MySQL Command Line

```powershell
mysql -u root -p
```

When prompted, enter your root password (set during MySQL installation)

### 4.2 Create Database & User

**Inside MySQL prompt (after entering password):**

```sql
-- Create database
CREATE DATABASE qudrix_travel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'qudrix_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';

-- Grant privileges
GRANT ALL PRIVILEGES ON qudrix_travel.* TO 'qudrix_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify
SHOW DATABASES;
SHOW USERS;

-- Exit
EXIT;
```

### 4.3 Verify Connection

```powershell
mysql -u qudrix_user -p -h localhost qudrix_travel

# When prompted, enter password: YourSecurePassword123!
# Should connect without error
# Type: EXIT
```

---

## STEP 5: Configure .env File

### 5.1 Create .env

```powershell
# In your project folder
Copy-Item .env.example .env
```

### 5.2 Edit .env

**Open with Notepad:**
```powershell
notepad .env
```

**Update these values:**

```env
APP_NAME="QUDRIX Travel CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qudrix_travel
DB_USERNAME=qudrix_user
DB_PASSWORD=YourSecurePassword123!

# Encryption (will generate)
APP_KEY=

# JWT Secret (will generate)
JWT_SECRET=

# Mail (Optional)
MAIL_MAILER=log
MAIL_HOST=localhost
MAIL_PORT=1025

# Cache
CACHE_DRIVER=file
SESSION_DRIVER=cookie
QUEUE_CONNECTION=sync
```

**Save file**

---

## STEP 6: Install Dependencies

### 6.1 Install PHP Packages

```powershell
# Navigate to project folder
Set-Location C:\Users\YourUsername\Documents\qudrix-crm

# Install composer dependencies
composer install

# This downloads ~200MB of packages to vendor/
# Estimated time: 2-5 minutes
```

**Verify:**
```powershell
# Check if vendor folder created
dir vendor | head -10

# Should show many folders
```

### 6.2 If Installation Fails

**Clear cache and retry:**
```powershell
# Clear composer cache
composer clear-cache

# Try again
composer install --no-interaction
```

---

## STEP 7: Generate Application Keys

### 7.1 Generate APP_KEY

```powershell
php artisan key:generate
```

Output should show:
```
Application key set successfully.
```

### 7.2 Generate JWT_SECRET

```powershell
php artisan jwt:secret
```

Output should show:
```
JWT secret set successfully.
```

### 7.3 Verify .env

```powershell
# Check .env was updated
notepad .env

# Should now have:
# APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxx
# JWT_SECRET=xxxxxxxxxxxxxxxxxxxxx
```

---

## STEP 8: Run Database Migrations

### 8.1 Create Tables

```powershell
php artisan migrate

# Output should show:
# Migration: 2024_08_17_000001_create_users_table
# Migration: 2024_08_17_000002_create_customers_table
# ... (68 migrations total)
```

### 8.2 Verify Database

**Open MySQL client:**
```powershell
mysql -u qudrix_user -p qudrix_travel
```

**Inside MySQL:**
```sql
SHOW TABLES;

-- Should show ~68 tables:
-- users, customers, leads, bookings, packages, etc.

EXIT;
```

---

## STEP 9: Create Storage Link

### 9.1 Link Public Storage

```powershell
php artisan storage:link

# Output:
# The [public/storage] directory has been successfully linked.
```

---

## STEP 10: Create Admin User

### 10.1 Using Artisan Tinker

```powershell
php artisan tinker
```

**Inside Tinker shell:**

```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@local.test';
$user->password = bcrypt('Admin@123');
$user->role = 'super-admin';
$user->is_active = true;
$user->save();

exit
```

### 10.2 Verify User Created

**Check in MySQL:**
```powershell
mysql -u qudrix_user -p qudrix_travel
```

```sql
SELECT id, name, email, role FROM users;

EXIT;
```

---

## STEP 11: Start Development Server

### 11.1 Run Laravel Development Server

```powershell
php artisan serve

# Output:
# Laravel development server started: http://127.0.0.1:8000
```

**Keep this PowerShell window open while developing**

### 11.2 Access Application

Open your browser:

- **Admin Panel:** http://localhost:8000/admin
- **API Health:** http://localhost:8000/api/v1/health
- **API Docs:** http://localhost:8000/api/docs

### 11.3 Login

- Email: `admin@local.test`
- Password: `Admin@123`

---

## STEP 12: Run Tests

### 12.1 In New PowerShell Window

**While dev server is running, open NEW PowerShell as Administrator:**

```powershell
Set-Location C:\Users\YourUsername\Documents\qudrix-crm

# Run all tests
php artisan test

# Or specific test file
php artisan test tests/Api/PublicApiTest.php

# Expected output:
# PASS  tests/Api/PublicApiTest.php
# ... (45+ tests)
# Tests: 45 passed
```

---

## WORKFLOW FOR DEVELOPMENT

### Start Each Day

**Terminal 1 - Start Dev Server:**
```powershell
Set-Location C:\Users\YourUsername\Documents\qudrix-crm
php artisan serve
# Stays open - shows requests log
```

**Terminal 2 - For Commands:**
```powershell
Set-Location C:\Users\YourUsername\Documents\qudrix-crm

# Run migrations if needed
php artisan migrate

# Run tests
php artisan test

# Create new model
php artisan make:model YourModelName -m

# Create controller
php artisan make:controller YourControllerName --resource
```

### Clear Cache During Development

```powershell
# Clear all cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear view cache
php artisan view:clear

# Clear compiled cache
php artisan clear-compiled

# All at once
php artisan optimize:clear
```

---

## USEFUL COMMANDS

### Database

```powershell
# Create seeder
php artisan make:seeder UserSeeder

# Run seeders
php artisan db:seed

# Refresh database (drops and recreates all tables)
php artisan migrate:fresh

# Refresh with seeding
php artisan migrate:fresh --seed

# Rollback last migration
php artisan migrate:rollback
```

### Development

```powershell
# List all routes
php artisan route:list

# Create new model
php artisan make:model ModelName -m

# Create controller
php artisan make:controller ControllerName

# Create migration
php artisan make:migration create_table_name

# Run tinker (interactive PHP)
php artisan tinker

# Generate API documentation
php artisan l5-swagger:generate
```

### Testing

```powershell
# Run all tests
php artisan test

# Run specific test
php artisan test tests/Api/PublicApiTest.php

# Run with coverage
php artisan test --coverage

# Stop on first failure
php artisan test --stop-on-failure
```

---

## TROUBLESHOOTING

### Issue: "PHP command not found"

**Fix:**
```powershell
# Check PATH
$env:PATH

# Should contain C:\php

# If not, add it manually
$env:PATH += ";C:\php"

# Make permanent (edit Environment Variables in Settings)
```

### Issue: "Port 8000 already in use"

**Fix:**
```powershell
# Use different port
php artisan serve --port=8001

# Or kill process using port 8000
Get-Process | Where-Object {$_.ProcessName -eq "php"} | Stop-Process
```

### Issue: "Database connection refused"

**Fix:**
```powershell
# Check MySQL is running
Get-Service | Where-Object {$_.Name -like "*MySQL*"}

# Should show "Running"

# If not running, start it
Start-Service MySQL80
# Or
Start-Service MariaDB

# Verify connection
mysql -u root -p
```

### Issue: "Class not found" errors

**Fix:**
```powershell
# Regenerate autoloader
composer dump-autoload

# Clear cache
php artisan clear-compiled
php artisan optimize
```

### Issue: "Migration table exists"

**Fix:**
```powershell
# Rollback all migrations
php artisan migrate:rollback --step=999

# Then re-run
php artisan migrate
```

### Issue: "Permission denied" on storage folder

**Fix:**
```powershell
# Windows doesn't have same permission system
# Usually automatically writable
# If issues, run PowerShell as Administrator and retry
```

### Check Error Logs

```powershell
# View Laravel logs
Get-Content storage\logs\laravel.log

# Or real-time
Get-Content -Path storage\logs\laravel.log -Wait
```

---

## EDITOR SETUP (RECOMMENDED)

### Visual Studio Code

1. Download from: https://code.visualstudio.com/
2. Install extensions:
   - PHP Intelephense
   - Laravel Extension Pack
   - MySQL
   - REST Client
   - Thunder Client (for API testing)

3. Open project:
   ```powershell
   code .
   ```

### PhpStorm

1. Download from: https://www.jetbrains.com/phpstorm/
2. Open project folder
3. Select PHP 8.3 interpreter: `C:\php\php.exe`
4. Configure database: Settings → Database → MySQL

---

## DATABASE VISUALIZATION

### Via MySQL Workbench

1. Download: https://dev.mysql.com/downloads/workbench/
2. Install and open
3. Create connection:
   - Host: `localhost`
   - User: `qudrix_user`
   - Password: `YourSecurePassword123!`
   - Database: `qudrix_travel`
4. View tables, data, structure

### Via DBeaver (Free Alternative)

1. Download: https://dbeaver.io/
2. Create MySQL connection
3. Browse database visually

---

## BACKUP LOCAL DATABASE

```powershell
# Export database to SQL file
mysqldump -u qudrix_user -p qudrix_travel > qudrix_backup.sql

# When prompted, enter password

# Import database from backup
mysql -u qudrix_user -p qudrix_travel < qudrix_backup.sql
```

---

## WINDOWS DEFENDER / ANTIVIRUS

If Windows Defender flags PHP or Composer:

1. Go to **Settings → Virus & threat protection**
2. Click "Manage settings"
3. Scroll to "Exclusions"
4. Add folder: `C:\php\`
5. Add folder: `C:\Users\YourUsername\Documents\qudrix-crm\`

---

## VERIFICATION CHECKLIST

Before starting development:

- ✅ PHP 8.3 installed and in PATH
- ✅ MySQL/MariaDB running
- ✅ Composer installed
- ✅ Database `qudrix_travel` created
- ✅ User `qudrix_user` created with password
- ✅ .env file configured with database credentials
- ✅ APP_KEY generated
- ✅ JWT_SECRET generated
- ✅ `composer install` completed
- ✅ `php artisan migrate` ran successfully
- ✅ Storage link created
- ✅ Admin user created
- ✅ Dev server starts: `php artisan serve`
- ✅ Admin login works: http://localhost:8000/admin
- ✅ API health check works: http://localhost:8000/api/v1/health
- ✅ Tests pass: `php artisan test`

---

## NEXT STEPS

1. **Explore codebase:**
   - `app/Models/` - Database models
   - `app/Http/Controllers/` - Request handlers
   - `routes/` - API routes
   - `database/migrations/` - Database schema

2. **Read documentation:**
   - PROJECT_STATUS.md
   - API_DOCUMENTATION.md
   - SECURITY_AUDIT.md

3. **Start developing:**
   - Create new features
   - Modify CRM to fit your needs
   - Add new models/controllers

---

**Status:** ✅ Ready for local development  
**Need Help?** Check DOCUMENTATION/ folder  
**Ready to Deploy?** Use HOSTINGER_DEPLOYMENT_GUIDE.md

Happy coding! 🚀
