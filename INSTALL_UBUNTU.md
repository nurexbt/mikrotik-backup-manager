# Ubuntu Installation Guide

Complete all-in-one installation of MikroTik Backup Manager on Ubuntu Server.

## 📋 Prerequisites

- Ubuntu 20.04 LTS or higher
- Root or sudo access
- Public IP address (or accessible from MikroTik routers)
- 2 GB RAM minimum
- 20 GB disk space minimum

## 🚀 Quick Installation

```bash
# Clone repository
git clone https://github.com/nurexbt/mikrotik-backup-manager.git
cd mikrotik-backup-manager

# Run automatic installer
chmod +x install.sh
sudo ./install.sh
```

The installer will:
- ✅ Install Apache, PHP, MySQL
- ✅ Install vsftpd (FTP server)
- ✅ Install pptpd (PPTP VPN server)
- ✅ Configure firewall
- ✅ Create database
- ✅ Setup FTP user
- ✅ Configure services
- ✅ Setup backup rotation

## 📝 Manual Installation

If you prefer manual installation or the automatic installer fails:

### Step 1: Update System

```bash
sudo apt update
sudo apt upgrade -y
```

### Step 2: Install LAMP Stack

```bash
# Install Apache
sudo apt install apache2 -y

# Install MySQL
sudo apt install mysql-server -y

# Install PHP and extensions
sudo apt install php php-mysql php-ftp php-cli php-common php-curl php-json php-mbstring -y

# Enable and start services
sudo systemctl enable apache2
sudo systemctl enable mysql
sudo systemctl start apache2
sudo systemctl start mysql
```

### Step 3: Install FTP and VPN Services

```bash
# Install vsftpd (FTP server)
sudo apt install vsftpd -y

# Install pptpd (PPTP VPN server)
sudo apt install pptpd -y
```

### Step 4: Clone Repository

```bash
# Clone to Apache web directory
cd /var/www/html
sudo git clone https://github.com/nurexbt/mikrotik-backup-manager.git rtbackup

# Set permissions
sudo chown -R www-data:www-data /var/www/html/rtbackup
sudo chmod -R 755 /var/www/html/rtbackup
```

### Step 5: Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -e "CREATE DATABASE IF NOT EXISTS rtbackup;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'rtbackup_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON rtbackup.* TO 'rtbackup_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### Step 6: Configure Portal

```bash
# Edit config file
sudo nano /var/www/html/rtbackup/config.php
```

**Update these values:**
```php
$db_host = 'localhost';
$db_user = 'rtbackup_user';
$db_pass = 'YOUR_STRONG_PASSWORD';
$db_name = 'rtbackup';

$settings = [
    'ftp_server' => 'YOUR_SERVER_PUBLIC_IP',  // Replace with your public IP
    'ftp_user' => 'ftpuser',
    'ftp_pass' => 'nobody',
    'pptp_server' => 'YOUR_SERVER_PUBLIC_IP'
];
```

### Step 7: Create FTP User

```bash
# Create FTP user
sudo useradd -m -d /home/ftpuser -s /bin/bash ftpuser

# Set password
echo "ftpuser:nobody" | sudo chpasswd

# Create directory structure
sudo mkdir -p /home/ftpuser/active_connections

# Set permissions
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod 755 /home/ftpuser
```

### Step 8: Configure vsftpd

```bash
# Backup original config
sudo cp /etc/vsftpd.conf /etc/vsftpd.conf.backup

# Get your public IP
PUBLIC_IP=$(curl -s ifconfig.me)
echo "Your public IP: $PUBLIC_IP"

# Create new config
sudo tee /etc/vsftpd.conf > /dev/null << EOF
listen=YES
listen_ipv6=NO
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES
use_localtime=YES
xferlog_enable=YES
connect_from_port_20=YES
chroot_local_user=YES
secure_chroot_dir=/var/run/vsftpd/empty
pam_service_name=vsftpd
allow_writeable_chroot=YES

# Passive mode configuration
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=$PUBLIC_IP
pasv_addr_resolve=NO

# Logging
xferlog_enable=YES
xferlog_file=/var/log/vsftpd.log
EOF

# Restart service
sudo systemctl restart vsftpd
sudo systemctl enable vsftpd
```

### Step 9: Configure pptpd

```bash
# Configure pptpd
sudo tee -a /etc/pptpd.conf > /dev/null << EOF

# VPN IP range
localip 192.168.201.1
remoteip 192.168.201.2-254
EOF

# Configure PPP options
sudo tee /etc/ppp/pptpd-options > /dev/null << EOF
name pptpd
refuse-pap
refuse-chap
refuse-mschap
require-mschap-v2
require-mppe-128
ms-dns 8.8.8.8
ms-dns 8.8.4.4
proxyarp
nodefaultroute
lock
nobsdcomp
EOF

# Enable IP forwarding
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

# Restart service
sudo systemctl restart pptpd
sudo systemctl enable pptpd
```

### Step 10: Configure Firewall

```bash
# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow FTP
sudo ufw allow 21/tcp
sudo ufw allow 40000:40100/tcp

# Allow PPTP
sudo ufw allow 1723/tcp
sudo ufw allow 47

# Allow SSH (if not already allowed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw --force enable

# Check status
sudo ufw status numbered
```

### Step 11: Install Server Stats API

```bash
# Copy stats API
sudo cp /var/www/html/rtbackup/server_stats.php /var/www/html/

# Set permissions
sudo chown www-data:www-data /var/www/html/server_stats.php
sudo chmod 755 /var/www/html/server_stats.php

# Allow PHP to read system info
sudo usermod -aG adm www-data

# Restart Apache
sudo systemctl restart apache2
```

### Step 12: Setup Backup Rotation

```bash
# Copy cleanup script
sudo cp /var/www/html/rtbackup/cleanup_old_backups.sh /usr/local/bin/

# Make executable
sudo chmod +x /usr/local/bin/cleanup_old_backups.sh

# Create log file
sudo touch /var/log/backup_cleanup.log
sudo chmod 644 /var/log/backup_cleanup.log

# Setup cron job (runs daily at 2 AM)
(sudo crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/cleanup_old_backups.sh >> /var/log/backup_cleanup.log 2>&1") | sudo crontab -
```

### Step 13: Configure Apache

```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod php7.4  # or php8.x depending on version

# Create virtual host (optional)
sudo nano /etc/apache2/sites-available/rtbackup.conf
```

**Add this content:**
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/rtbackup
    
    <Directory /var/www/html/rtbackup>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/rtbackup_error.log
    CustomLog ${APACHE_LOG_DIR}/rtbackup_access.log combined
</VirtualHost>
```

**Enable site and restart:**
```bash
sudo a2ensite rtbackup
sudo systemctl restart apache2
```

## ✅ Verification

### 1. Check Services

```bash
# Check Apache
sudo systemctl status apache2

# Check MySQL
sudo systemctl status mysql

# Check vsftpd
sudo systemctl status vsftpd

# Check pptpd
sudo systemctl status pptpd
```

### 2. Test Web Portal

```bash
# Get your server IP
curl ifconfig.me

# Access portal in browser:
# http://YOUR_SERVER_IP/rtbackup
```

**Default login:**
- Username: `admin`
- Password: `admin123`

### 3. Test FTP

```bash
# Test FTP connection
ftp localhost
# Login: ftpuser
# Password: nobody
# Type: ls
# Type: quit
```

### 4. Test Server Stats API

```bash
curl http://localhost/server_stats.php
```

### 5. Check Firewall

```bash
sudo ufw status
```

Should show all required ports open.

## 🔐 Security Hardening

### 1. Change Default Passwords

```bash
# Change FTP password
sudo passwd ftpuser

# Update in config.php
sudo nano /var/www/html/rtbackup/config.php
# Update 'ftp_pass' value
```

### 2. Change Web Portal Admin Password

- Login to portal
- Go to User Management
- Edit admin user
- Set strong password

### 3. Restrict FTP Access by IP

```bash
# Edit vsftpd config
sudo nano /etc/vsftpd.conf

# Add:
# tcp_wrappers=YES

# Edit hosts.allow
sudo nano /etc/hosts.allow
# Add: vsftpd: YOUR_MIKROTIK_IP_RANGE

# Edit hosts.deny
sudo nano /etc/hosts.deny
# Add: vsftpd: ALL
```

### 4. Enable SSL (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Get SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo certbot renew --dry-run
```

### 5. Secure MySQL

```bash
# Run security script
sudo mysql_secure_installation

# Remove test database
sudo mysql -e "DROP DATABASE IF EXISTS test;"

# Remove anonymous users
sudo mysql -e "DELETE FROM mysql.user WHERE User='';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

## 🔧 Troubleshooting

### Portal Shows 500 Error

```bash
# Check Apache logs
sudo tail -f /var/log/apache2/error.log

# Check PHP errors
sudo tail -f /var/log/apache2/rtbackup_error.log

# Verify permissions
sudo chown -R www-data:www-data /var/www/html/rtbackup
```

### FTP Connection Failed

```bash
# Check vsftpd status
sudo systemctl status vsftpd

# Check logs
sudo tail -f /var/log/vsftpd.log

# Test connection
ftp localhost

# Verify firewall
sudo ufw status | grep 21
```

### PPTP Connection Failed

```bash
# Check pptpd status
sudo systemctl status pptpd

# Check logs
sudo tail -f /var/log/syslog | grep pptpd

# Verify IP forwarding
cat /proc/sys/net/ipv4/ip_forward  # Should be 1
```

### Database Connection Error

```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u rtbackup_user -p rtbackup

# Reset password if needed
sudo mysql -e "ALTER USER 'rtbackup_user'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';"
```

### Server Stats Not Showing

```bash
# Test API
curl http://localhost/server_stats.php

# Check permissions
ls -la /var/www/html/server_stats.php

# Verify www-data has access
sudo usermod -aG adm www-data
sudo systemctl restart apache2
```

## 📊 Post-Installation

### 1. Add First Router

- Access portal: `http://YOUR_SERVER_IP/rtbackup`
- Login with admin credentials
- Click "Add Router"
- Enter router details

### 2. Add PPTP Credentials

```bash
sudo nano /etc/ppp/chap-secrets

# Add line:
# username * password *
# Example:
# router01 * mypassword *

sudo systemctl restart pptpd
```

### 3. Generate MikroTik Script

- Go to "Generate Script" in portal
- Select your router
- Copy script
- Run on MikroTik terminal

### 4. Verify Backup

- Check dashboard after 24 hours
- Or manually run script on MikroTik:
  ```
  /system script run rtbackup
  ```

## 📚 Additional Configuration

### Enable Email Notifications

Edit `config.php` to add email settings (future feature).

### Customize Retention Period

```bash
sudo nano /usr/local/bin/cleanup_old_backups.sh
# Change: RETENTION_DAYS=90
```

### Setup External Backup

Use `rsync` to backup `/home/ftpuser` to external storage:

```bash
# Add to crontab
sudo crontab -e
# Add: 0 3 * * * rsync -av /home/ftpuser /backup/location
```

## 🎉 Installation Complete!

Your MikroTik Backup Manager is now ready!

**Access Portal:**
```
http://YOUR_SERVER_IP/rtbackup
```

**Next Steps:**
1. Change default admin password
2. Add your MikroTik routers
3. Configure MikroTik backup scripts
4. Monitor backups on dashboard

**Need Help?**
- [MikroTik Configuration Guide](INSTALL_MIKROTIK.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [GitHub Issues](https://github.com/YOUR_USERNAME/mikrotik-backup-manager/issues)
