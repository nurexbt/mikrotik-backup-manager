# Fix Backup Statistics - Complete Guide

## Problem
Dashboard shows:
- Total Backups: 0
- Backup Size: 0 B
- Last Backup: N/A

## Solution

### Method 1: Use Debug Tool (Recommended)

1. **Open the debug tool:**
   ```
   http://localhost/rtbackup/test_backup_stats.php
   ```

2. **Check each section:**
   - ✅ FTP Connection Test
   - ✅ Router Directories
   - ✅ Backup Files Scan
   - ✅ Server Stats API Test
   - ✅ Recommendations

3. **Follow the recommendations** shown at the bottom

### Method 2: Manual Fixes

#### Fix 1: Check FTP Connection

**Test FTP manually:**
```bash
# From Windows Command Prompt
ftp 192.168.201.1

# Login with:
# User: ftpuser
# Password: nobody

# List directories
ls

# Exit
quit
```

**If connection fails:**
- Check if vsftpd is running on Ubuntu
- Check firewall allows port 21
- Verify passive mode is configured

#### Fix 2: Verify Router Directories Exist

**On Ubuntu server:**
```bash
ls -la /home/ftpuser/

# You should see directories for each router
# Example:
# drwxr-xr-x 2 ftpuser ftpuser 4096 May 18 14:30 Router-01
# drwxr-xr-x 2 ftpuser ftpuser 4096 May 18 14:30 Router-02
```

**If directories don't exist:**
```bash
# Create manually
sudo mkdir -p /home/ftpuser/Router-Name
sudo chown ftpuser:ftpuser /home/ftpuser/Router-Name
sudo chmod 755 /home/ftpuser/Router-Name
```

#### Fix 3: Upload Test Backup Files

**Create test files on Ubuntu:**
```bash
cd /home/ftpuser/Router-01
sudo touch test-2024-05-18.backup
sudo touch test-2024-05-18.rsc
sudo chown ftpuser:ftpuser *.backup *.rsc
sudo chmod 644 *.backup *.rsc
```

**Or run MikroTik script** to generate real backups

#### Fix 4: Install Server Stats API

**Copy server_stats.php to Ubuntu:**
```bash
sudo nano /var/www/html/server_stats.php
```

Paste the contents from `c:\xampp\htdocs\rtbackup\server_stats.php`

**Set permissions:**
```bash
sudo chmod 755 /var/www/html/server_stats.php
sudo chown www-data:www-data /var/www/html/server_stats.php
```

**Test the API:**
```
http://192.168.201.1/server_stats.php
```

Should return JSON with backup statistics.

## What Was Fixed in Code

### 1. Improved FTP Scanning
- Changed from `ftp_nlist()` to `ftp_rawlist()`
- Better parsing of file listings
- Filters out directories and special entries
- Only counts `.backup` and `.rsc` files

### 2. Dual Data Source
- **Primary:** Direct FTP scan (fast, real-time)
- **Fallback:** Server Stats API (if FTP fails)

### 3. Better Error Handling
- Increased FTP timeout to 5 seconds
- Validates file extensions with regex
- Checks for -1 return values (errors)

### 4. Enhanced Server Stats API
- Now includes backup statistics
- Scans `/home/ftpuser/` directories
- Counts files and calculates sizes
- Returns last backup timestamp

## Testing Steps

### Step 1: Test FTP Connection
```php
// Visit: http://localhost/rtbackup/test_backup_stats.php
// Check "FTP Connection Test" section
```

### Step 2: Verify Routers
```php
// Check "Router Directories" section
// All routers should show "YES" for directory exists
```

### Step 3: Check Backup Files
```php
// Check "Backup Files Scan" section
// Should list all .backup and .rsc files
```

### Step 4: Test API
```php
// Check "Server Stats API Test" section
// Should show backup statistics from API
```

## Common Issues

### Issue 1: FTP Connection Timeout
**Symptom:** Stats show 0 even with backups

**Solution:**
```bash
# On Ubuntu, check vsftpd
sudo systemctl status vsftpd

# Restart if needed
sudo systemctl restart vsftpd

# Check passive mode config
sudo grep pasv /etc/vsftpd.conf
```

### Issue 2: Permission Denied
**Symptom:** Cannot read files via FTP

**Solution:**
```bash
# Fix permissions
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod -R 755 /home/ftpuser
sudo find /home/ftpuser -type f -exec chmod 644 {} \;
```

### Issue 3: Empty Directories
**Symptom:** Directories exist but no files

**Solution:**
- Run MikroTik backup script
- Or manually upload test files
- Check MikroTik logs for upload errors

### Issue 4: API Not Working
**Symptom:** API returns 404 or empty

**Solution:**
```bash
# Install Apache/PHP
sudo apt install apache2 php -y
sudo systemctl start apache2

# Allow HTTP
sudo ufw allow 80/tcp

# Test
curl http://192.168.201.1/server_stats.php
```

## Verification

After fixes, dashboard should show:

✅ **Total Backups:** Actual count (e.g., 24)  
✅ **Backup Size:** Real size (e.g., 15.2 MB)  
✅ **Last Backup:** Timestamp (e.g., May 18, 14:30)

## Files Modified

1. ✅ `index.php` - Improved FTP scanning logic
2. ✅ `server_stats.php` - Added backup statistics
3. ✅ `test_backup_stats.php` - New debug tool

## Need Help?

Run the debug tool and share the output:
```
http://localhost/rtbackup/test_backup_stats.php
```

---

**All backup statistics should now work correctly!** 🎉
