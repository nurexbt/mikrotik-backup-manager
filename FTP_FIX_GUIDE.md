# FTP Upload Fix Guide

## Problem Identified
Your MikroTik router gets "connection timeout" when uploading to FTP. This is because **vsftpd passive mode is not configured correctly**.

## Root Cause
- FTP has two modes: Active and Passive
- MikroTik uses **Passive Mode** by default
- Your vsftpd server doesn't have passive mode configured
- Result: Connection timeout

## Solution: Configure vsftpd for Passive Mode

### Step 1: Edit vsftpd Configuration

On your Ubuntu server, run:

```bash
sudo nano /etc/vsftpd.conf
```

### Step 2: Add These Lines at the End

```conf
# Passive Mode Configuration for MikroTik
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=192.168.201.1
pasv_addr_resolve=NO
```

**Important:** Make sure these lines are added!

### Step 3: Open Firewall Ports

```bash
sudo ufw allow 40000:40100/tcp comment 'FTP Passive Mode'
```

### Step 4: Restart vsftpd

```bash
sudo systemctl restart vsftpd
sudo systemctl status vsftpd
```

### Step 5: Test from MikroTik

```routeros
/tool fetch address=192.168.201.1 src-path=test.txt user=ftpuser password=nobody mode=ftp dst-path=test.txt upload=yes
```

## Alternative: Use Automated Script

Download and run the fix script:

```bash
cd /tmp
wget http://103.165.230.228/rtbackup/fix_vsftpd.sh
chmod +x fix_vsftpd.sh
sudo ./fix_vsftpd.sh
```

## Verification

After applying the fix, you should see:

```
status: finished
```

Instead of:

```
status: failed
failure: connection timeout
```

## What These Settings Do

- `pasv_enable=YES` - Enables passive mode
- `pasv_min_port=40000` - Start of passive port range
- `pasv_max_port=40100` - End of passive port range (100 ports)
- `pasv_address=192.168.201.1` - Your VPN IP address
- `pasv_addr_resolve=NO` - Don't resolve IP (use as-is)

## Why This Fixes It

1. MikroTik connects to FTP server (port 21) ✅
2. MikroTik requests passive mode
3. Server responds: "Use port 40000-40100 on 192.168.201.1"
4. MikroTik connects to that port for data transfer ✅
5. Upload succeeds! ✅

## Troubleshooting

If still not working:

### Check if ports are open:
```bash
sudo netstat -tulpn | grep vsftpd
```

### Check firewall:
```bash
sudo ufw status numbered
```

### View FTP logs:
```bash
sudo tail -f /var/log/vsftpd.log
```

### Test locally:
```bash
ftp 192.168.201.1
# Login as ftpuser
# Try: put test.txt
```

## Complete vsftpd.conf Example

If you want to replace the entire file:

```conf
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

chroot_local_user=YES
allow_writeable_chroot=YES
secure_chroot_dir=/var/run/vsftpd/empty
pam_service_name=vsftpd

# PASSIVE MODE - CRITICAL FOR MIKROTIK
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=192.168.201.1
pasv_addr_resolve=NO

idle_session_timeout=600
data_connection_timeout=120
accept_timeout=60
connect_timeout=60

xferlog_file=/var/log/vsftpd.log
xferlog_std_format=YES
log_ftp_protocol=YES

userlist_enable=YES
userlist_file=/etc/vsftpd.userlist
userlist_deny=NO

utf8_filesystem=YES
```

Then create user list:
```bash
echo "ftpuser" | sudo tee /etc/vsftpd.userlist
```

## After Fix

Once working, your MikroTik backup script will:
1. Connect via PPTP to get VPN IP
2. Create backup files
3. Upload to FTP via passive mode ✅
4. Clean up local files
5. Run daily via scheduler

Your backups will appear in the portal automatically!
