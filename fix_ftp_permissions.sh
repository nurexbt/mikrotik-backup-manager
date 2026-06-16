#!/bin/bash

# FTP Permissions Fix Script
# Run this on Ubuntu server to fix FTP directory permissions
# Usage: sudo bash fix_ftp_permissions.sh

echo "=========================================="
echo "FTP Permissions Fix Script"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root: sudo bash fix_ftp_permissions.sh${NC}"
    exit 1
fi

echo "Step 1: Checking FTP user and directory..."
if id "ftpuser" &>/dev/null; then
    echo -e "${GREEN}✓ FTP user 'ftpuser' exists${NC}"
else
    echo -e "${RED}✗ FTP user 'ftpuser' does not exist${NC}"
    echo "Creating ftpuser..."
    useradd -m -d /home/ftpuser -s /bin/bash ftpuser
    echo "ftpuser:nobody" | chpasswd
fi

if [ -d "/home/ftpuser" ]; then
    echo -e "${GREEN}✓ FTP directory exists${NC}"
else
    echo -e "${RED}✗ FTP directory does not exist${NC}"
    echo "Creating /home/ftpuser..."
    mkdir -p /home/ftpuser
fi

echo ""
echo "Step 2: Fixing ownership..."
chown -R ftpuser:ftpuser /home/ftpuser
echo -e "${GREEN}✓ Ownership set to ftpuser:ftpuser${NC}"

echo ""
echo "Step 3: Fixing permissions..."
# Main directory
chmod 755 /home/ftpuser
echo -e "${GREEN}✓ /home/ftpuser set to 755${NC}"

# All subdirectories
find /home/ftpuser -type d -exec chmod 755 {} \;
echo -e "${GREEN}✓ All subdirectories set to 755${NC}"

# All files
find /home/ftpuser -type f -exec chmod 644 {} \;
echo -e "${GREEN}✓ All files set to 644${NC}"

echo ""
echo "Step 4: Listing current directories..."
ls -la /home/ftpuser/

echo ""
echo "Step 5: Checking vsftpd configuration..."
if systemctl is-active --quiet vsftpd; then
    echo -e "${GREEN}✓ vsftpd is running${NC}"
else
    echo -e "${RED}✗ vsftpd is not running${NC}"
    echo "Starting vsftpd..."
    systemctl start vsftpd
fi

# Check if passive mode is configured
if grep -q "pasv_enable=YES" /etc/vsftpd.conf; then
    echo -e "${GREEN}✓ Passive mode is enabled${NC}"
else
    echo -e "${YELLOW}⚠ Passive mode not configured${NC}"
    echo "Adding passive mode configuration..."
    cat >> /etc/vsftpd.conf << 'EOF'

# Passive mode configuration
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=192.168.201.1
EOF
    systemctl restart vsftpd
    echo -e "${GREEN}✓ Passive mode configured and vsftpd restarted${NC}"
fi

echo ""
echo "Step 6: Testing FTP connection..."
# Test FTP login
ftp -n 192.168.201.1 << EOF
user ftpuser nobody
ls
quit
EOF

echo ""
echo "Step 7: Checking file counts..."
for dir in /home/ftpuser/*/; do
    if [ -d "$dir" ]; then
        dirname=$(basename "$dir")
        if [ "$dirname" != "active_connections" ]; then
            filecount=$(find "$dir" -type f \( -name "*.backup" -o -name "*.rsc" \) 2>/dev/null | wc -l)
            echo "  $dirname: $filecount backup files"
        fi
    fi
done

echo ""
echo "=========================================="
echo -e "${GREEN}✓ FTP Permissions Fix Complete!${NC}"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Test FTP connection from Windows: ftp 192.168.201.1"
echo "2. Refresh dashboard: http://localhost/rtbackup/"
echo "3. Run diagnostics: http://localhost/rtbackup/test_backup_stats.php"
echo ""
