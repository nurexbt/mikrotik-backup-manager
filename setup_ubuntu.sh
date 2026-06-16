#!/bin/bash
# Ubuntu PPTP and FTP Auto-Setup Script
# Run this script on your Ubuntu server as root.
# Usage: sudo bash setup_ubuntu.sh

echo "=========================================="
echo "Starting PPTP and FTP Installation..."
echo "=========================================="

# 1. Update and install packages
echo "[1/4] Installing necessary packages (pptpd, vsftpd)..."
apt update
apt install -y pptpd vsftpd

# 2. Configure PPTPD
echo "[2/4] Configuring PPTP VPN settings..."
# Set IPs
grep -q "localip 192.168.201.1" /etc/pptpd.conf || echo "localip 192.168.201.1" >> /etc/pptpd.conf
grep -q "remoteip 192.168.200.2-255,192.168.201.2-254" /etc/pptpd.conf || echo "remoteip 192.168.200.2-255,192.168.201.2-254" >> /etc/pptpd.conf

# Set DNS
grep -q "ms-dns 8.8.8.8" /etc/ppp/pptpd-options || echo "ms-dns 8.8.8.8" >> /etc/ppp/pptpd-options
grep -q "ms-dns 8.8.4.4" /etc/ppp/pptpd-options || echo "ms-dns 8.8.4.4" >> /etc/ppp/pptpd-options

# Enable IPv4 Forwarding (so internet works over VPN)
sed -i 's/#net.ipv4.ip_forward=1/net.ipv4.ip_forward=1/g' /etc/sysctl.conf
sysctl -p

# Restart and enable PPTP Service
systemctl restart pptpd
systemctl enable pptpd

# 3. Configure FTP Server (vsftpd)
echo "[3/4] Configuring FTP Server settings..."
cp /etc/vsftpd.conf /etc/vsftpd.conf.bak

# Enable writing and local users
sed -i 's/#write_enable=YES/write_enable=YES/g' /etc/vsftpd.conf
sed -i 's/#local_enable=YES/local_enable=YES/g' /etc/vsftpd.conf

# Restart and enable FTP Service
systemctl restart vsftpd
systemctl enable vsftpd

# 4. Create FTP User (ftpuser / nobody)
echo "[4/4] Creating FTP user account for backups..."
if id "ftpuser" &>/dev/null; then
    echo "User 'ftpuser' already exists."
else
    # Create user with home directory
    useradd -m -d /home/ftpuser -s /bin/bash ftpuser
fi

# Set password non-interactively
echo "ftpuser:nobody" | chpasswd

# Setup directory permissions
mkdir -p /home/ftpuser/backups
chown -R ftpuser:ftpuser /home/ftpuser
chmod 755 /home/ftpuser

# 5. Configure Realtime Status Tracking
echo "[5/5] Configuring Realtime PPTP Status Tracking..."
mkdir -p /home/ftpuser/active_connections
chown ftpuser:ftpuser /home/ftpuser/active_connections
chmod 755 /home/ftpuser/active_connections

cat << 'EOF' > /etc/ppp/ip-up.d/rtbackup_status
#!/bin/bash
# Write active connection details for the web dashboard
echo "$PEERNAME $IFNAME $IPREMOTE $CALLER_ID $(date +'%Y-%m-%d %H:%M:%S')" > "/home/ftpuser/active_connections/$IFNAME.txt"
chown ftpuser:ftpuser "/home/ftpuser/active_connections/$IFNAME.txt"
EOF
chmod +x /etc/ppp/ip-up.d/rtbackup_status

cat << 'EOF' > /etc/ppp/ip-down.d/rtbackup_status
#!/bin/bash
# Remove connection details when disconnected
rm -f "/home/ftpuser/active_connections/$IFNAME.txt"
EOF
chmod +x /etc/ppp/ip-down.d/rtbackup_status

echo "=========================================="
echo "    INSTALLATION COMPLETELY FINISHED!     "
echo "=========================================="
echo "-> The FTP server is running. (User: ftpuser, Pass: nobody)"
echo "-> The PPTP server is running. (Local IP pool: 192.168.59.x)"
echo "--- FIREWALL (UFW) SETTINGS ---"
echo "If you use UFW (Ubuntu Firewall), you must allow PPTP and GRE. Run these commands:"
echo "  sudo ufw allow 1723/tcp"
echo "  sudo ufw allow proto gre"
echo ""
echo "To ensure GRE packets are not dropped by UFW's strict state tracker, run this patch:"
echo "  sudo sed -i '/-A ufw-before-input -i lo -j ACCEPT/a \\n# Allow PPTP GRE packets\\n-A ufw-before-input -p gre -j ACCEPT' /etc/ufw/before.rules"
echo "  sudo ufw reload"
echo ""
echo "To restrict FTP access so ONLY the PPTP connected routers (192.168.200.0/24) can upload:"
echo "  sudo ufw allow from 192.168.200.0/24 to any port 21"
echo "  sudo ufw deny 21"
echo "  sudo ufw enable"
echo "*(WARNING: Since your dashboard is hosted on this server, make sure to also allow localhost: sudo ufw allow from 127.0.0.1 to any port 21)*"
echo ""
echo "Note on 10MB Max File Size:"
echo "vsftpd does not have a native 'max file size' setting per file. To strictly enforce a 10MB limit, you would need to enable Ubuntu user 'quotas' for the 'ftpuser' account, or switch the FTP server from vsftpd to ProFTPD (which supports MaxStoreFileSize 10 Mb)."
