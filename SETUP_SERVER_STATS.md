# Server Statistics Setup Guide

This guide will help you set up real-time server statistics (CPU, RAM, Disk) on your dashboard.

## Overview

The dashboard now displays:
- ✅ Total Routers
- ✅ Active Connections
- ✅ Total Backups
- ✅ Backup Size
- ✅ Storage Used/Free
- ✅ FTP Server Status
- ✅ Last Backup Time
- ✅ **Server CPU Usage**
- ✅ **Server RAM Used/Free**

## Setup Instructions

### Step 1: Copy the Stats API to Ubuntu Server

Copy the `server_stats.php` file to your Ubuntu FTP server's web directory:

```bash
# On your Ubuntu server (192.168.201.1)
sudo nano /var/www/html/server_stats.php
```

Paste the contents of `server_stats.php` from this directory.

### Step 2: Set Permissions

```bash
sudo chmod 755 /var/www/html/server_stats.php
sudo chown www-data:www-data /var/www/html/server_stats.php
```

### Step 3: Install Apache/Nginx (if not already installed)

**For Apache:**
```bash
sudo apt update
sudo apt install apache2 php -y
sudo systemctl enable apache2
sudo systemctl start apache2
```

**For Nginx:**
```bash
sudo apt update
sudo apt install nginx php-fpm -y
sudo systemctl enable nginx
sudo systemctl start nginx
```

### Step 4: Allow HTTP Access in Firewall

```bash
sudo ufw allow 80/tcp
sudo ufw reload
```

### Step 5: Test the API

Open your browser and visit:
```
http://192.168.201.1/server_stats.php
```

You should see JSON output like:
```json
{
    "cpu": {
        "usage_percent": 15.2,
        "cores": 4
    },
    "ram": {
        "total_bytes": 8589934592,
        "used_bytes": 4294967296,
        "free_bytes": 4294967296,
        "usage_percent": 50.0
    },
    "disk": {
        "total_bytes": 107374182400,
        "used_bytes": 21474836480,
        "free_bytes": 85899345920,
        "usage_percent": 20.0
    },
    "ftp_server": {
        "status": "online",
        "service": "vsftpd"
    }
}
```

## How It Works

1. **Dashboard** (Windows/XAMPP) makes an HTTP request to the Ubuntu server
2. **server_stats.php** (Ubuntu) executes system commands to get real-time stats
3. **JSON response** is sent back to the dashboard
4. **Dashboard displays** the statistics in beautiful cards

## Troubleshooting

### Stats showing "N/A"

**Problem:** Dashboard shows "N/A" for CPU/RAM/Disk

**Solution:**
1. Check if Apache/Nginx is running:
   ```bash
   sudo systemctl status apache2
   # or
   sudo systemctl status nginx
   ```

2. Test the API directly:
   ```bash
   curl http://192.168.201.1/server_stats.php
   ```

3. Check PHP is installed:
   ```bash
   php --version
   ```

### Permission Denied Errors

**Problem:** API returns empty or error

**Solution:**
```bash
# Give PHP permission to execute system commands
sudo usermod -aG adm www-data
sudo systemctl restart apache2
```

### Firewall Blocking

**Problem:** Cannot access the API from Windows

**Solution:**
```bash
# Check firewall status
sudo ufw status

# Allow HTTP
sudo ufw allow 80/tcp
sudo ufw reload
```

## Alternative: Manual Stats Entry

If you cannot set up the API, you can manually view stats by SSH:

```bash
# CPU Usage
top -bn1 | grep "Cpu(s)"

# RAM Usage
free -h

# Disk Usage
df -h /home/ftpuser

# FTP Status
systemctl status vsftpd
```

## Security Note

The `server_stats.php` file exposes system information. For production environments:

1. **Add authentication:**
   ```php
   if ($_GET['key'] !== 'your-secret-key') {
       die('Unauthorized');
   }
   ```

2. **Restrict by IP:**
   ```apache
   # In Apache .htaccess
   Order Deny,Allow
   Deny from all
   Allow from 192.168.200.0/24
   Allow from 192.168.201.0/24
   ```

3. **Use HTTPS** instead of HTTP

## Benefits

✅ **Real-time monitoring** - See server health at a glance  
✅ **No SSH required** - Stats fetched via HTTP  
✅ **Beautiful UI** - Animated cards with progress bars  
✅ **Automatic refresh** - Stats update on page load  
✅ **Cross-platform** - Works from Windows XAMPP to Ubuntu server

---

**Need Help?** Check the logs:
```bash
# Apache logs
sudo tail -f /var/log/apache2/error.log

# PHP errors
sudo tail -f /var/log/php*.log
```
