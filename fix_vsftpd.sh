#!/bin/bash

echo "=========================================="
echo "FTP Server Configuration Fix"
echo "=========================================="
echo ""

# Backup original config
echo "[1/8] Backing up original vsftpd.conf..."
sudo cp /etc/vsftpd.conf /etc/vsftpd.conf.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"

# Create new vsftpd configuration
echo ""
echo "[2/8] Creating new vsftpd.conf..."
sudo tee /etc/vsftpd.conf > /dev/null <<'EOF'
# Basic FTP Settings
listen=NO
listen_ipv6=YES
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES
use_localtime=YES
xferlog_enable=YES
connect_from_port_20=YES

# Security Settings
chroot_local_user=YES
allow_writeable_chroot=YES
secure_chroot_dir=/var/run/vsftpd/empty
pam_service_name=vsftpd

# CRITICAL: Passive Mode Configuration for MikroTik
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=192.168.201.1
pasv_addr_resolve=NO
port_enable=YES

# Timeouts
idle_session_timeout=600
data_connection_timeout=120
accept_timeout=60
connect_timeout=60

# Logging (IMPORTANT for debugging)
xferlog_file=/var/log/vsftpd.log
xferlog_std_format=YES
log_ftp_protocol=YES
dual_log_enable=YES

# User Settings
userlist_enable=YES
userlist_file=/etc/vsftpd.userlist
userlist_deny=NO

# UTF-8
utf8_filesystem=YES

# Performance
ls_recurse_enable=NO
ascii_upload_enable=NO
ascii_download_enable=NO
EOF
echo "✅ Configuration file created"

echo ""
echo "[3/8] Creating vsftpd user list..."
echo "ftpuser" | sudo tee /etc/vsftpd.userlist > /dev/null
echo "✅ User list created"

echo ""
echo "[4/8] Setting up firewall rules..."
sudo ufw allow 21/tcp comment 'FTP Control'
sudo ufw allow 40000:40100/tcp comment 'FTP Passive Mode'
echo "✅ Firewall rules added"

echo ""
echo "[5/8] Fixing directory permissions..."
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod -R 755 /home/ftpuser
echo "✅ Permissions fixed"

echo ""
echo "[6/8] Creating test directories for routers..."
# Create directories for each router in database if they don't exist
if [ -d "/home/ftpuser" ]; then
    for dir in /home/ftpuser/*/; do
        if [ -d "$dir" ]; then
            sudo chown ftpuser:ftpuser "$dir"
            sudo chmod 755 "$dir"
        fi
    done
fi
echo "✅ Router directories checked"

echo ""
echo "[7/8] Restarting vsftpd service..."
sudo systemctl restart vsftpd
sleep 2
echo "✅ Service restarted"

echo ""
echo "[8/8] Verifying configuration..."
if systemctl is-active --quiet vsftpd; then
    echo "✅ vsftpd is running"
else
    echo "❌ vsftpd failed to start!"
    echo "Check logs: sudo journalctl -u vsftpd -n 50"
    exit 1
fi

echo ""
echo "=========================================="
echo "Configuration Complete!"
echo "=========================================="
echo ""
echo "📊 Service Status:"
sudo systemctl status vsftpd --no-pager | head -10

echo ""
echo "🔧 Configuration Applied:"
echo "   - Passive Mode: ENABLED"
echo "   - Passive Ports: 40000-40100"
echo "   - Passive Address: 192.168.201.1"
echo "   - Logging: ENABLED"
echo ""
echo "🧪 Test from MikroTik Router:"
echo "   /tool fetch address=192.168.201.1 src-path=test.txt user=ftpuser password=nobody mode=ftp dst-path=test.txt upload=yes"
echo ""
echo "📝 View FTP Logs:"
echo "   sudo tail -f /var/log/vsftpd.log"
echo ""
echo "🔍 If still not working, run diagnostic:"
echo "   sudo bash diagnose_ftp.sh"
echo ""
