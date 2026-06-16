# Windows Installation Guide

Install MikroTik Backup Manager web portal on Windows with XAMPP.

## 📋 Prerequisites

- Windows 10/11 or Windows Server 2016+
- Administrator privileges
- 2 GB RAM minimum
- 5 GB disk space minimum
- Ubuntu server for FTP/PPTP (see [Ubuntu Installation](INSTALL_UBUNTU.md))

## 🚀 Quick Installation

### Option 1: Download ZIP

1. **Download from GitHub**
   - Visit: https://github.com/nurexbt/mikrotik-backup-manager
   - Click "Code" → "Download ZIP"
   
2. **Extract files**
   ```
   Extract to: C:\xampp\htdocs\rtbackup\
   ```

### Option 2: Git Clone

```powershell
# Open PowerShell
cd C:\xampp\htdocs
git clone https://github.com/YOUR_USERNAME/mikrotik-backup-manager.git rtbackup
```

## 📝 Step-by-Step Installation

### Step 1: Install XAMPP

#### 1.1 Download XAMPP

- Visit: https://www.apachefriends.org/download.html
- Download XAMPP for Windows (PHP 7.4 or higher)
- File size: ~150 MB

#### 1.2 Install XAMPP

1. **Run installer as Administrator**
   - Right-click installer → "Run as administrator"

2. **Select components:**
   - ☑ Apache
   - ☑ MySQL
   - ☑ PHP
   - ☑ phpMyAdmin
   - ☐ Others (optional)

3. **Installation directory:**
   ```
   C:\xampp
   ```

4. **Complete installation**
   - Click through the installer
   - Uncheck "Launch Control Panel" for now

#### 1.3 Configure Windows Firewall

Windows may block Apache and MySQL. Allow them:

1. **Open Windows Firewall**
   - Control Panel → System and Security → Windows Defender Firewall
   - Click "Allow an app through firewall"

2. **Allow Apache**
   - Click "Change settings"
   - Find "Apache HTTP Server"
   - Check both "Private" and "Public"

3. **Allow MySQL**
   - Find "mysqld"
   - Check both "Private" and "Public"

### Step 2: Start XAMPP Services

#### 2.1 Open XAMPP Control Panel

```
Start Menu → XAMPP → XAMPP Control Panel
```

Or run:
```
C:\xampp\xampp-control.exe
```

#### 2.2 Start Services

1. **Start Apache**
   - Click "Start" button next to Apache
   - Status should show "Running" in green

2. **Start MySQL**
   - Click "Start" button next to MySQL
   - Status should show "Running" in green

#### 2.3 Verify Services

**Test Apache:**
- Open browser
- Go to: http://localhost
- Should see XAMPP welcome page

**Test MySQL:**
- Go to: http://localhost/phpmyadmin
- Should see phpMyAdmin interface

### Step 3: Install Portal Files

#### 3.1 Create Directory

If not created automatically:
```powershell
mkdir C:\xampp\htdocs\rtbackup
```

#### 3.2 Extract/Clone Files

**Option A: Extract ZIP**
```
1. Extract downloaded ZIP
2. Copy all files to: C:\xampp\htdocs\rtbackup\
```

**Option B: Git Clone**
```powershell
cd C:\xampp\htdocs
git clone https://github.com/YOUR_USERNAME/mikrotik-backup-manager.git rtbackup
```

#### 3.3 Verify File Structure

```
C:\xampp\htdocs\rtbackup\
├── index.php
├── config.php
├── login.php
├── logout.php
├── style.css
├── server_stats.php
├── cleanup_old_backups.php
├── cleanup_old_backups.sh
├── test_backup_stats.php
├── README.md
└── ... (other files)
```

### Step 4: Configure Database

#### 4.1 Access phpMyAdmin

- Open browser
- Go to: http://localhost/phpmyadmin

#### 4.2 Database Auto-Creation

The database is created automatically on first access. No manual setup needed!

#### 4.3 Custom MySQL Port (if needed)

If MySQL runs on custom port (e.g., 3307):

1. **Edit config.php**
   ```powershell
   notepad C:\xampp\htdocs\rtbackup\config.php
   ```

2. **Update database host:**
   ```php
   $db_host = 'localhost:3307';  // Change port here
   ```

### Step 5: Configure FTP Connection

#### 5.1 Get Ubuntu Server IP

You need your Ubuntu server's public IP address where FTP/PPTP is running.

#### 5.2 Edit Config File

```powershell
notepad C:\xampp\htdocs\rtbackup\config.php
```

#### 5.3 Update Settings

```php
// Global settings
$settings = [
    'ftp_server' => '103.166.230.228',  // Your Ubuntu server IP
    'ftp_user' => 'ftpuser',            // FTP username (from Ubuntu setup)
    'ftp_pass' => 'nobody',             // FTP password (from Ubuntu setup)
    'pptp_server' => '103.166.230.228'  // Same as FTP server
];
```

**Important:** Replace `103.166.230.228` with your actual Ubuntu server IP!

#### 5.4 Save File

- File → Save
- Close Notepad

### Step 6: First Access

#### 6.1 Open Portal

Open browser and go to:
```
http://localhost/rtbackup
```

#### 6.2 Default Login

```
Username: admin
Password: admin123
```

#### 6.3 Change Password (Recommended)

1. After login, go to "User Management"
2. Click edit icon for admin user
3. Enter new password
4. Click "Save"

### Step 7: Verify Installation

#### 7.1 Check Dashboard

- Dashboard should load without errors
- Cards may show "0" or "N/A" (normal without routers)

#### 7.2 Test FTP Connection

Go to:
```
http://localhost/rtbackup/test_backup_stats.php
```

**Should show:**
- ✓ FTP Connection: SUCCESS
- ✓ FTP Login: SUCCESS

If connection fails, verify:
- Ubuntu server is running
- FTP server (vsftpd) is running on Ubuntu
- Firewall allows FTP (port 21)
- `config.php` has correct IP address

#### 7.3 Check Database

Go to:
```
http://localhost/phpmyadmin
```

Should see database: `rtbackup` with tables:
- `routers`
- `users`

## ⚙️ Optional Configuration

### Enable HTTPS (SSL)

For production use, enable HTTPS:

1. **Generate SSL Certificate**
   ```powershell
   cd C:\xampp\apache
   .\bin\openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout server.key -out server.crt
   ```

2. **Edit Apache Config**
   ```
   C:\xampp\apache\conf\extra\httpd-ssl.conf
   ```

3. **Enable SSL Module**
   - Edit: `C:\xampp\apache\conf\httpd.conf`
   - Uncomment: `LoadModule ssl_module modules/mod_ssl.so`

4. **Restart Apache**

### Change Apache Port

If port 80 is already in use:

1. **Edit Apache config:**
   ```
   C:\xampp\apache\conf\httpd.conf
   ```

2. **Find and change:**
   ```
   Listen 80  →  Listen 8080
   ```

3. **Restart Apache**

4. **Access portal:**
   ```
   http://localhost:8080/rtbackup
   ```

### MySQL Performance Tuning

For better performance:

1. **Edit MySQL config:**
   ```
   C:\xampp\mysql\bin\my.ini
   ```

2. **Add under [mysqld]:**
   ```ini
   max_connections=100
   innodb_buffer_pool_size=256M
   query_cache_size=64M
   ```

3. **Restart MySQL**

### Automatic Backup Cleanup (Windows Scheduled Task)

#### Option 1: Using Task Scheduler GUI

1. **Open Task Scheduler**
   ```
   Start → Task Scheduler
   ```

2. **Create Basic Task**
   - Name: "Backup Cleanup"
   - Trigger: Daily at 2:00 AM
   - Action: Start a program
     - Program: `C:\xampp\php\php.exe`
     - Arguments: `C:\xampp\htdocs\rtbackup\cleanup_old_backups.php`

#### Option 2: Using PowerShell

```powershell
# Run as Administrator
$action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument "C:\xampp\htdocs\rtbackup\cleanup_old_backups.php"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName "Backup Cleanup" -Action $action -Trigger $trigger -Settings $settings -Description "Deletes backup files older than 90 days"
```

## 🔧 Troubleshooting

### Apache Won't Start

**Error: Port 80 in use**

```powershell
# Find what's using port 80
netstat -ano | findstr :80

# Kill the process (replace PID with actual number)
taskkill /PID 1234 /F
```

Or change Apache port (see Optional Configuration above).

**Error: Permission denied**

- Run XAMPP Control Panel as Administrator
- Disable Windows Defender temporarily
- Check firewall settings

### MySQL Won't Start

**Error: Port 3306 in use**

```powershell
# Find what's using port 3306
netstat -ano | findstr :3306

# Stop conflicting service
net stop MySQL80
```

Or change MySQL port in `C:\xampp\mysql\bin\my.ini`.

### Portal Shows Blank Page

**Check PHP errors:**

1. **Enable error display:**
   - Edit: `C:\xampp\php\php.ini`
   - Find: `display_errors = Off`
   - Change to: `display_errors = On`
   - Restart Apache

2. **Check error logs:**
   ```
   C:\xampp\apache\logs\error.log
   ```

### Database Connection Error

**Error: "MySQL is not running"**

- Open XAMPP Control Panel
- Click "Start" next to MySQL
- Wait for green "Running" status

**Error: "Access denied"**

- Default credentials: root / (no password)
- Check `config.php` database settings

### FTP Connection Failed

**Error: "FTP Connection: FAILED"**

Check:
1. Ubuntu server is reachable:
   ```powershell
   ping YOUR_SERVER_IP
   ```

2. FTP port is open:
   ```powershell
   telnet YOUR_SERVER_IP 21
   ```

3. Config has correct IP:
   - Edit `config.php`
   - Verify `ftp_server` value

### Page Not Found (404)

**URL showing 404 error**

1. **Verify path:**
   ```
   http://localhost/rtbackup
   ```
   NOT: `http://localhost/rtbackup/index.php`

2. **Check files exist:**
   ```powershell
   dir C:\xampp\htdocs\rtbackup
   ```

3. **Restart Apache:**
   - XAMPP Control Panel → Stop Apache
   - Wait 5 seconds
   - Start Apache

## 🔒 Security Recommendations

### 1. Change Default Passwords

**Portal Admin:**
- Login → User Management
- Edit admin user
- Set strong password

**MySQL Root:**
```
http://localhost/phpmyadmin
→ User accounts
→ Edit privileges for 'root'
→ Change password
```

### 2. Restrict Access

**Limit to specific IPs:**

Create `.htaccess` in `C:\xampp\htdocs\rtbackup\`:
```apache
Order Deny,Allow
Deny from all
Allow from 192.168.1.0/24
Allow from YOUR_IP
```

### 3. Disable Directory Listing

Already disabled by default in portal directory.

### 4. Regular Updates

- Update XAMPP regularly
- Update portal files from GitHub
- Monitor security advisories

## 📊 Performance Optimization

### Use OpCache

Already enabled by default in XAMPP.

### Increase PHP Memory

Edit `C:\xampp\php\php.ini`:
```ini
memory_limit = 256M
```

### Enable Compression

Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
LoadModule deflate_module modules/mod_deflate.so
```

## ✅ Installation Complete!

Your MikroTik Backup Manager portal is now running!

**Access Portal:**
```
http://localhost/rtbackup
```

**Default Login:**
- Username: `admin`
- Password: `admin123`

**Next Steps:**

1. ✅ Change admin password
2. ✅ Add your first router
3. ✅ Configure MikroTik backup script
4. ✅ Monitor backups on dashboard

**Need Help?**
- [Ubuntu Server Setup](INSTALL_UBUNTU.md) - Required for FTP/PPTP
- [MikroTik Configuration](INSTALL_MIKROTIK.md) - Configure routers
- [Troubleshooting Guide](TROUBLESHOOTING.md) - Common issues
- [GitHub Issues](https://github.com/YOUR_USERNAME/mikrotik-backup-manager/issues)

## 🎉 Congratulations!

You've successfully installed MikroTik Backup Manager on Windows!
