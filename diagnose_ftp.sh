#!/bin/bash

echo "=========================================="
echo "FTP Upload Diagnostic Tool"
echo "=========================================="
echo ""

# Check vsftpd configuration
echo "[1] Checking vsftpd configuration..."
echo "---"
if grep -q "pasv_enable=YES" /etc/vsftpd.conf; then
    echo "✅ Passive mode is enabled"
else
    echo "❌ Passive mode is NOT enabled"
fi

if grep -q "pasv_address" /etc/vsftpd.conf; then
    echo "✅ Passive address is set: $(grep pasv_address /etc/vsftpd.conf)"
else
    echo "❌ Passive address is NOT set"
fi

if grep -q "pasv_min_port" /etc/vsftpd.conf; then
    echo "✅ Passive port range: $(grep pasv_min_port /etc/vsftpd.conf) to $(grep pasv_max_port /etc/vsftpd.conf)"
else
    echo "❌ Passive port range is NOT set"
fi

echo ""

# Check if vsftpd is running
echo "[2] Checking vsftpd service..."
echo "---"
if systemctl is-active --quiet vsftpd; then
    echo "✅ vsftpd is running"
    echo "   PID: $(systemctl show -p MainPID vsftpd | cut -d= -f2)"
else
    echo "❌ vsftpd is NOT running"
fi

echo ""

# Check listening ports
echo "[3] Checking listening ports..."
echo "---"
echo "FTP Control Port (21):"
sudo netstat -tulpn | grep ":21 " || echo "❌ Not listening on port 21"

echo ""
echo "FTP Passive Ports (40000-40100):"
sudo netstat -tulpn | grep vsftpd | grep -E ":(4[0-9]{4})" | head -5 || echo "⚠️  No passive connections currently"

echo ""

# Check firewall
echo "[4] Checking firewall rules..."
echo "---"
sudo ufw status | grep "21\|40000:40100" || echo "⚠️  FTP ports may not be allowed"

echo ""

# Check permissions
echo "[5] Checking /home/ftpuser permissions..."
echo "---"
ls -la /home/ftpuser | head -10
echo ""
echo "Owner: $(stat -c '%U:%G' /home/ftpuser)"
echo "Permissions: $(stat -c '%a' /home/ftpuser)"

echo ""

# Check recent FTP logs
echo "[6] Recent FTP activity (last 20 lines)..."
echo "---"
sudo tail -20 /var/log/vsftpd.log

echo ""

# Check for errors in syslog
echo "[7] Checking for FTP errors in syslog..."
echo "---"
sudo grep -i "vsftpd\|ftp" /var/log/syslog | tail -10

echo ""

# Test file creation
echo "[8] Testing file write permissions..."
echo "---"
TEST_FILE="/home/ftpuser/test_write_$(date +%s).txt"
if sudo -u ftpuser touch "$TEST_FILE" 2>/dev/null; then
    echo "✅ ftpuser can create files"
    sudo rm "$TEST_FILE"
else
    echo "❌ ftpuser CANNOT create files"
fi

echo ""

# Check SELinux (if applicable)
echo "[9] Checking SELinux status..."
echo "---"
if command -v getenforce &> /dev/null; then
    SELINUX_STATUS=$(getenforce)
    echo "SELinux: $SELINUX_STATUS"
    if [ "$SELINUX_STATUS" = "Enforcing" ]; then
        echo "⚠️  SELinux may be blocking FTP writes"
    fi
else
    echo "✅ SELinux not installed"
fi

echo ""

# Check AppArmor
echo "[10] Checking AppArmor status..."
echo "---"
if command -v aa-status &> /dev/null; then
    if sudo aa-status | grep -q vsftpd; then
        echo "⚠️  AppArmor profile for vsftpd is active"
        sudo aa-status | grep vsftpd
    else
        echo "✅ No AppArmor restrictions on vsftpd"
    fi
else
    echo "✅ AppArmor not installed"
fi

echo ""
echo "=========================================="
echo "Diagnostic Complete!"
echo "=========================================="
echo ""
echo "Common Issues Found:"
echo ""

# Analyze and suggest fixes
ISSUES_FOUND=0

if ! grep -q "pasv_enable=YES" /etc/vsftpd.conf; then
    echo "❌ ISSUE: Passive mode not configured"
    echo "   FIX: Add passive mode settings to /etc/vsftpd.conf"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

if ! sudo netstat -tulpn | grep -q ":21 "; then
    echo "❌ ISSUE: vsftpd not listening on port 21"
    echo "   FIX: Restart vsftpd service"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

FTPUSER_PERM=$(stat -c '%a' /home/ftpuser)
if [ "$FTPUSER_PERM" != "755" ] && [ "$FTPUSER_PERM" != "775" ]; then
    echo "⚠️  WARNING: /home/ftpuser permissions are $FTPUSER_PERM"
    echo "   FIX: sudo chmod 755 /home/ftpuser"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✅ No obvious issues found!"
    echo ""
    echo "If uploads still fail, check:"
    echo "1. Router can reach 192.168.201.1 via PPTP VPN"
    echo "2. Router script is using correct FTP credentials"
    echo "3. Firewall allows passive ports 40000-40100"
fi

echo ""
echo "To apply automatic fix, run:"
echo "sudo bash /tmp/fix_vsftpd.sh"
echo ""
