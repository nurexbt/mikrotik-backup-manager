#!/bin/bash

# FTP Firewall Configuration Script
# Run this on Ubuntu server to allow FTP access from public IP
# Usage: sudo bash fix_ftp_firewall.sh

echo "=========================================="
echo "FTP Firewall Configuration"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root: sudo bash fix_ftp_firewall.sh${NC}"
    exit 1
fi

echo "Step 1: Checking current firewall status..."
ufw status numbered

echo ""
echo "Step 2: Allowing FTP port 21..."
ufw allow 21/tcp
echo -e "${GREEN}✓ Port 21 (FTP) allowed${NC}"

echo ""
echo "Step 3: Allowing FTP passive mode ports..."
ufw allow 40000:40100/tcp
echo -e "${GREEN}✓ Ports 40000-40100 (FTP Passive) allowed${NC}"

echo ""
echo "Step 4: Reloading firewall..."
ufw reload
echo -e "${GREEN}✓ Firewall reloaded${NC}"

echo ""
echo "Step 5: Checking vsftpd configuration..."

# Backup original config
if [ ! -f /etc/vsftpd.conf.backup ]; then
    cp /etc/vsftpd.conf /etc/vsftpd.conf.backup
    echo -e "${GREEN}✓ Backup created: /etc/vsftpd.conf.backup${NC}"
fi

# Check and update vsftpd.conf for public IP access
echo "Updating vsftpd configuration for public IP access..."

# Remove old passive address if exists
sed -i '/pasv_address=/d' /etc/vsftpd.conf

# Get public IP
PUBLIC_IP=$(curl -s ifconfig.me)
if [ -z "$PUBLIC_IP" ]; then
    PUBLIC_IP="103.166.230.228"
fi

echo "Detected public IP: $PUBLIC_IP"

# Add/update configuration
cat > /tmp/vsftpd_additions.conf << EOF

# Public IP FTP Configuration
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

# Passive mode for public IP
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=$PUBLIC_IP
pasv_addr_resolve=NO

# Performance
idle_session_timeout=600
data_connection_timeout=120
EOF

# Merge configurations
grep -vxFf /tmp/vsftpd_additions.conf /etc/vsftpd.conf > /tmp/vsftpd_clean.conf
cat /tmp/vsftpd_clean.conf /tmp/vsftpd_additions.conf > /etc/vsftpd.conf

echo -e "${GREEN}✓ vsftpd.conf updated${NC}"

echo ""
echo "Step 6: Restarting vsftpd service..."
systemctl restart vsftpd
sleep 2

if systemctl is-active --quiet vsftpd; then
    echo -e "${GREEN}✓ vsftpd is running${NC}"
else
    echo -e "${RED}✗ vsftpd failed to start${NC}"
    echo "Checking logs..."
    journalctl -u vsftpd -n 20 --no-pager
fi

echo ""
echo "Step 7: Testing FTP connection..."
echo "Testing local connection..."
ftp -n localhost << EOF
user ftpuser nobody
ls
quit
EOF

echo ""
echo "Step 8: Current firewall rules..."
ufw status numbered | grep -E "(21|40000)"

echo ""
echo "=========================================="
echo -e "${GREEN}✓ FTP Firewall Configuration Complete!${NC}"
echo "=========================================="
echo ""
echo "Configuration Summary:"
echo "  • FTP Port: 21 (OPEN)"
echo "  • Passive Ports: 40000-40100 (OPEN)"
echo "  • Public IP: $PUBLIC_IP"
echo "  • FTP User: ftpuser"
echo "  • FTP Pass: nobody"
echo ""
echo "Test from Windows:"
echo "  ftp $PUBLIC_IP"
echo "  Username: ftpuser"
echo "  Password: nobody"
echo ""
echo "Or test from browser:"
echo "  ftp://ftpuser:nobody@$PUBLIC_IP"
echo ""
