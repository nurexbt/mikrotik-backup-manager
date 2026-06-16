#!/bin/bash

################################################################################
# MikroTik Backup Manager - Automatic Installer for Ubuntu
# 
# This script will install and configure:
# - Apache Web Server
# - MySQL Database Server
# - PHP with required extensions
# - vsftpd (FTP Server)
# - pptpd (PPTP VPN Server)
# - Firewall rules
# - Backup rotation cron job
#
# Requirements:
# - Ubuntu 20.04 LTS or higher
# - Root or sudo privileges
# - Public IP address
#
# Usage: sudo ./install.sh
################################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}║         MikroTik Backup Manager - Installer                ║${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Get public IP address
get_public_ip() {
    log_info "Detecting public IP address..."
    PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null || curl -s icanhazip.com 2>/dev/null || echo "")
    
    if [ -z "$PUBLIC_IP" ]; then
        log_warning "Could not auto-detect public IP"
        read -p "Enter your server's public IP address: " PUBLIC_IP
    else
        log_success "Detected public IP: $PUBLIC_IP"
        read -p "Is this correct? (y/n): " confirm
        if [ "$confirm" != "y" ]; then
            read -p "Enter your server's public IP address: " PUBLIC_IP
        fi
    fi
}

# Generate random password
generate_password() {
    openssl rand -base64 12 | tr -d "=+/" | cut -c1-16
}

# Update system
update_system() {
    log_info "Updating system packages..."
    apt update -y
    apt upgrade -y
    log_success "System updated successfully"
}

# Install LAMP stack
install_lamp() {
    log_info "Installing Apache, MySQL, and PHP..."
    
    # Install Apache
    apt install apache2 -y
    systemctl enable apache2
    systemctl start apache2
    log_success "Apache installed and started"
    
    # Install MySQL
    apt install mysql-server -y
    systemctl enable mysql
    systemctl start mysql
    log_success "MySQL installed and started"
    
    # Install PHP and extensions
    apt install php php-mysql php-ftp php-cli php-common php-curl php-json php-mbstring -y
    log_success "PHP and extensions installed"
}

# Install FTP and VPN services
install_services() {
    log_info "Installing vsftpd and pptpd..."
    
    # Install vsftpd (FTP Server)
    apt install vsftpd -y
    systemctl enable vsftpd
    log_success "vsftpd installed"
    
    # Install pptpd (PPTP VPN Server)
    apt install pptpd -y
    systemctl enable pptpd
    log_success "pptpd installed"
}

# Configure MySQL database
configure_database() {
    log_info "Configuring MySQL database..."
    
    DB_NAME="rtbackup"
    DB_USER="rtbackup_user"
    DB_PASS=$(generate_password)
    
    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null || true
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';" 2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    
    log_success "Database configured: $DB_NAME"
    
    # Create routers table
    mysql $DB_NAME -e "CREATE TABLE IF NOT EXISTS routers (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        pptp_username VARCHAR(255) NOT NULL,
        pptp_password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );" 2>/dev/null || true
    
    # Create users table
    mysql $DB_NAME -e "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        email VARCHAR(100),
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    );" 2>/dev/null || true
    
    # Create default admin user (password: admin123)
    ADMIN_HASH='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
    mysql $DB_NAME -e "INSERT IGNORE INTO users (username, password, full_name, role) VALUES ('admin', '$ADMIN_HASH', 'System Administrator', 'admin');" 2>/dev/null || true
    
    log_success "Database tables created"
}

# Copy portal files
install_portal() {
    log_info "Installing web portal files..."
    
    WEB_DIR="/var/www/html/rtbackup"
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    # Create directory if it doesn't exist
    mkdir -p $WEB_DIR
    
    # Copy files
    if [ "$SCRIPT_DIR" != "$WEB_DIR" ]; then
        cp -r "$SCRIPT_DIR"/* $WEB_DIR/
        log_success "Portal files copied to $WEB_DIR"
    else
        log_info "Files already in correct location"
    fi
    
    # Set permissions
    chown -R www-data:www-data $WEB_DIR
    chmod -R 755 $WEB_DIR
    log_success "Permissions set"
}

# Configure portal
configure_portal() {
    log_info "Configuring portal..."
    
    WEB_DIR="/var/www/html/rtbackup"
    
    # Update config.php with database credentials
    cat > $WEB_DIR/config.php << EOF
<?php
session_start();

\$db_host = 'localhost';
\$db_user = '$DB_USER';
\$db_pass = '$DB_PASS';
\$db_name = '$DB_NAME';

mysqli_report(MYSQLI_REPORT_STRICT);

try {
    \$conn = new mysqli(\$db_host, \$db_user, \$db_pass, \$db_name);
    
    if (\$conn->connect_error) {
        die("Connection failed: " . \$conn->connect_error);
    }
} catch (mysqli_sql_exception \$e) {
    die("<div style='font-family: sans-serif; padding: 2rem; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 5px; margin: 2rem; text-align: center;'>
        <h2>Database Connection Failed</h2>
        <p><strong>MySQL is not running!</strong></p>
        <p style='font-size: 0.8em; color: #666; margin-top: 1rem;'>Technical Details: " . \$e->getMessage() . "</p>
    </div>");
}

// Global settings
\$settings = [
    'ftp_server' => '$PUBLIC_IP',
    'ftp_user' => 'ftpuser',
    'ftp_pass' => 'nobody',
    'pptp_server' => '$PUBLIC_IP'
];
?>
EOF
    
    log_success "Portal configured"
}

# Create FTP user
create_ftp_user() {
    log_info "Creating FTP user..."
    
    # Create user if doesn't exist
    if ! id "ftpuser" &>/dev/null; then
        useradd -m -d /home/ftpuser -s /bin/bash ftpuser
        log_success "FTP user created"
    else
        log_info "FTP user already exists"
    fi
    
    # Set password
    echo "ftpuser:nobody" | chpasswd
    
    # Create directory structure
    mkdir -p /home/ftpuser/active_connections
    
    # Set permissions
    chown -R ftpuser:ftpuser /home/ftpuser
    chmod 755 /home/ftpuser
    
    log_success "FTP user configured"
}

# Configure vsftpd
configure_vsftpd() {
    log_info "Configuring vsftpd..."
    
    # Backup original config
    if [ -f /etc/vsftpd.conf ]; then
        cp /etc/vsftpd.conf /etc/vsftpd.conf.backup
    fi
    
    # Create new config
    cat > /etc/vsftpd.conf << EOF
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
    systemctl restart vsftpd
    log_success "vsftpd configured and restarted"
}

# Configure pptpd
configure_pptpd() {
    log_info "Configuring pptpd..."
    
    # Backup original config
    if [ -f /etc/pptpd.conf ]; then
        cp /etc/pptpd.conf /etc/pptpd.conf.backup
    fi
    
    # Add VPN IP configuration
    cat >> /etc/pptpd.conf << EOF

# VPN IP range
localip 192.168.201.1
remoteip 192.168.201.2-254
EOF
    
    # Configure PPP options
    cat > /etc/ppp/pptpd-options << EOF
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
    if ! grep -q "net.ipv4.ip_forward=1" /etc/sysctl.conf; then
        echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
    fi
    sysctl -p > /dev/null 2>&1
    
    # Restart service
    systemctl restart pptpd
    log_success "pptpd configured and restarted"
}

# Configure firewall
configure_firewall() {
    log_info "Configuring firewall..."
    
    # Check if UFW is installed
    if ! command -v ufw &> /dev/null; then
        apt install ufw -y
    fi
    
    # Configure rules
    ufw --force reset > /dev/null 2>&1
    ufw default deny incoming > /dev/null 2>&1
    ufw default allow outgoing > /dev/null 2>&1
    
    # Allow SSH (important!)
    ufw allow 22/tcp > /dev/null 2>&1
    
    # Allow HTTP/HTTPS
    ufw allow 80/tcp > /dev/null 2>&1
    ufw allow 443/tcp > /dev/null 2>&1
    
    # Allow FTP
    ufw allow 21/tcp > /dev/null 2>&1
    ufw allow 40000:40100/tcp > /dev/null 2>&1
    
    # Allow PPTP
    ufw allow 1723/tcp > /dev/null 2>&1
    ufw allow 47 > /dev/null 2>&1
    
    # Enable firewall
    ufw --force enable > /dev/null 2>&1
    
    log_success "Firewall configured"
}

# Install server stats API
install_server_stats() {
    log_info "Installing server stats API..."
    
    # Copy stats file if exists
    if [ -f "$(dirname "$0")/server_stats.php" ]; then
        cp "$(dirname "$0")/server_stats.php" /var/www/html/
        chown www-data:www-data /var/www/html/server_stats.php
        chmod 755 /var/www/html/server_stats.php
        
        # Allow PHP to read system info
        usermod -aG adm www-data 2>/dev/null || true
        
        log_success "Server stats API installed"
    else
        log_warning "server_stats.php not found, skipping"
    fi
}

# Setup backup rotation
setup_backup_rotation() {
    log_info "Setting up backup rotation..."
    
    # Copy cleanup script if exists
    if [ -f "$(dirname "$0")/cleanup_old_backups.sh" ]; then
        cp "$(dirname "$0")/cleanup_old_backups.sh" /usr/local/bin/
        chmod +x /usr/local/bin/cleanup_old_backups.sh
        
        # Create log file
        touch /var/log/backup_cleanup.log
        chmod 644 /var/log/backup_cleanup.log
        
        # Add cron job (runs daily at 2 AM)
        (crontab -l 2>/dev/null | grep -v "cleanup_old_backups.sh"; echo "0 2 * * * /usr/local/bin/cleanup_old_backups.sh >> /var/log/backup_cleanup.log 2>&1") | crontab -
        
        log_success "Backup rotation configured (runs daily at 2 AM)"
    else
        log_warning "cleanup_old_backups.sh not found, skipping"
    fi
}

# Restart all services
restart_services() {
    log_info "Restarting all services..."
    
    systemctl restart apache2
    systemctl restart mysql
    systemctl restart vsftpd
    systemctl restart pptpd
    
    log_success "All services restarted"
}

# Print completion message
print_completion() {
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}║           Installation Completed Successfully!            ║${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Portal Access:${NC}"
    echo -e "  URL: ${GREEN}http://$PUBLIC_IP/rtbackup${NC}"
    echo -e "  Username: ${GREEN}admin${NC}"
    echo -e "  Password: ${GREEN}admin123${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Database Credentials:${NC}"
    echo -e "  Database: ${GREEN}$DB_NAME${NC}"
    echo -e "  User: ${GREEN}$DB_USER${NC}"
    echo -e "  Password: ${GREEN}$DB_PASS${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}FTP Server:${NC}"
    echo -e "  Server: ${GREEN}$PUBLIC_IP:21${NC}"
    echo -e "  Username: ${GREEN}ftpuser${NC}"
    echo -e "  Password: ${GREEN}nobody${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}PPTP VPN Server:${NC}"
    echo -e "  Server: ${GREEN}$PUBLIC_IP:1723${NC}"
    echo -e "  VPN IP Range: ${GREEN}192.168.201.2-254${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Important Notes:${NC}"
    echo -e "  ${RED}⚠${NC}  Change admin password immediately after first login"
    echo -e "  ${RED}⚠${NC}  Add PPTP credentials in /etc/ppp/chap-secrets"
    echo -e "  ${GREEN}✓${NC}  Backup rotation: Old backups deleted after 90 days"
    echo -e "  ${GREEN}✓${NC}  Firewall configured with required ports"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Next Steps:${NC}"
    echo -e "  1. Login to the portal: ${GREEN}http://$PUBLIC_IP/rtbackup${NC}"
    echo -e "  2. Change admin password"
    echo -e "  3. Add PPTP users: ${GREEN}sudo nano /etc/ppp/chap-secrets${NC}"
    echo -e "     Format: ${YELLOW}username * password *${NC}"
    echo -e "  4. Add your MikroTik routers"
    echo -e "  5. Generate and apply backup scripts"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Documentation:${NC}"
    echo -e "  MikroTik Setup: ${GREEN}INSTALL_MIKROTIK.md${NC}"
    echo -e "  Troubleshooting: ${GREEN}TROUBLESHOOTING.md${NC}"
    echo -e "  GitHub: ${GREEN}https://github.com/YOUR_USERNAME/mikrotik-backup-manager${NC}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Save credentials to file
    cat > /root/rtbackup_credentials.txt << EOF
MikroTik Backup Manager - Installation Details
================================================

Installation Date: $(date)
Server IP: $PUBLIC_IP

Portal Access:
--------------
URL: http://$PUBLIC_IP/rtbackup
Username: admin
Password: admin123

Database Credentials:
---------------------
Database: $DB_NAME
User: $DB_USER
Password: $DB_PASS

FTP Server:
-----------
Server: $PUBLIC_IP:21
Username: ftpuser
Password: nobody

PPTP VPN Server:
----------------
Server: $PUBLIC_IP:1723
VPN IP Range: 192.168.201.2-254

IMPORTANT: Change admin password immediately after first login!
EOF
    
    chmod 600 /root/rtbackup_credentials.txt
    log_success "Credentials saved to: /root/rtbackup_credentials.txt"
}

# Main installation process
main() {
    print_header
    
    check_root
    get_public_ip
    
    echo ""
    log_info "Starting installation..."
    echo ""
    
    update_system
    install_lamp
    install_services
    configure_database
    create_ftp_user
    configure_vsftpd
    configure_pptpd
    configure_firewall
    install_portal
    configure_portal
    install_server_stats
    setup_backup_rotation
    restart_services
    
    print_completion
}

# Run main installation
main
