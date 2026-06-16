# Fix FTP Access from Public IP

## Problem Identified ✅

Your dashboard can't see the backup files because:

1. ❌ **Config uses VPN IP** (192.168.201.1) but you're accessing from Windows without VPN
2. ❌ **FTP not configured for public IP** access (103.166.230.228)
3. ❌ **Firewall may be blocking** FTP on public interface

## Solution

### Step 1: Update Config (Already Done ✅)

The config has been updated to use public IP:
```php
'ftp_server' => '103.166.230.228'
```

### Step 2: Configure FTP for Public IP Access

**SSH to your Ubuntu server:**
```bash
ssh nms@103.166.230.228
# Password: 1445
```

**Run the firewall fix script:**
```bash
# Upload the script
sudo nano /tmp/fix_ftp_firewall.sh
# Paste the contents from fix_ftp_firewall.sh

# Make executable
sudo chmod +x /tmp/fix_ftp_firewall.sh

# Run it
sudo bash /tmp/fix_ftp_firewall.sh
```

**Or run commands manually:**
```bash
# 1. Allow FTP ports
sudo ufw allow 21/tcp
sudo ufw allow 40000:40100/tcp
sudo ufw reload

# 2. Update vsftpd for public IP
sudo nano /etc/vsftpd.conf

# Add/update these lines:
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=103.166.230.228
pasv_addr_resolve=NO
listen=YES
listen_ipv6=NO
allow_writeable_chroot=YES

# 3. Restart vsftpd
sudo systemctl restart vsftpd

# 4. Fix permissions
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod 755 /home/ftpuser
sudo find /home/ftpuser -type d -exec chmod 755 {} \;
sudo find /home/ftpuser -type f -exec chmod 644 {} \;
```

### Step 3: Test FTP Connection

**From Windows Command Prompt:**
```cmd
ftp 103.166.230.228
# Username: ftpuser
# Password: nobody

# Then type:
ls
dir
quit
```

**You should see:**
```
backups
HOME-RT
Khan-Access-MKT
Khan-Core-MKT
active_connections
```

### Step 4: Verify Dashboard

1. **Refresh dashboard:**
   ```
   http://localhost/rtbackup/
   ```

2. **Run diagnostics:**
   ```
   http://localhost/rtbackup/test_backup_stats.php
   ```

3. **Check sync:**
   ```
   http://localhost/rtbackup/sync_routers_ftp.php
   ```

## Expected Results

After the fix:

✅ **Test Backup Stats:**
- FTP Connection: SUCCESS
- FTP Login: SUCCESS
- Router Directories: All show "YES"
- Total Backups: 4+ (from backups folder)

✅ **Dashboard:**
- Total Backups: 4+
- Backup Size: Real size
- Last Backup: Real timestamp

✅ **Sync Tool:**
- FTP Directories: 4 found
- All matched with database

## Alternative: Use VPN IP (If you have VPN)

If you're connected to the VPN (192.168.201.x network):

**Option A: Keep VPN IP in config**
```php
'ftp_server' => '192.168.201.1'
```

**Option B: Auto-detect**
```php
// Check if on VPN network
$is_vpn = (strpos($_SERVER['REMOTE_ADDR'], '192.168.201.') === 0);
'ftp_server' => $is_vpn ? '192.168.201.1' : '103.166.230.228'
```

## Troubleshooting

### Issue 1: FTP Connection Timeout

**Symptom:** Can't connect to FTP

**Check:**
```bash
# On Ubuntu server
sudo systemctl status vsftpd
sudo ufw status | grep 21
sudo netstat -tulpn | grep 21
```

**Fix:**
```bash
sudo systemctl restart vsftpd
sudo ufw allow 21/tcp
```

### Issue 2: Login Successful but Can't List Files

**Symptom:** FTP login works but `ls` hangs

**Cause:** Passive mode not configured

**Fix:**
```bash
sudo nano /etc/vsftpd.conf

# Add:
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=103.166.230.228

# Restart
sudo systemctl restart vsftpd

# Open firewall
sudo ufw allow 40000:40100/tcp
```

### Issue 3: Permission Denied

**Symptom:** Can connect but can't read directories

**Fix:**
```bash
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod -R 755 /home/ftpuser
```

### Issue 4: Directories Show 0 Files

**Symptom:** Directories exist but show 0 files

**Check:**
```bash
ls -la /home/ftpuser/backups/
ls -la /home/ftpuser/HOME-RT/
ls -la /home/ftpuser/Khan-Access-MKT/
```

**Fix:**
```bash
sudo chmod 644 /home/ftpuser/*/*.backup
sudo chmod 644 /home/ftpuser/*/*.rsc
```

## Security Note

Opening FTP on public IP has security implications:

**Recommendations:**
1. **Use strong password** (change from "nobody")
2. **Restrict by IP** in firewall:
   ```bash
   sudo ufw delete allow 21/tcp
   sudo ufw allow from YOUR_WINDOWS_IP to any port 21
   ```
3. **Use FTPS** (FTP over SSL) instead of plain FTP
4. **Monitor logs:**
   ```bash
   sudo tail -f /var/log/vsftpd.log
   ```

## Files Created

- ✅ `fix_ftp_firewall.sh` - Automatic firewall configuration
- ✅ `fix_ftp_permissions.sh` - Fix file permissions
- ✅ `FIX_PUBLIC_IP_FTP.md` - This guide

## Quick Commands

**Test FTP from Ubuntu:**
```bash
ftp -n localhost << EOF
user ftpuser nobody
ls
quit
EOF
```

**Test FTP from Windows:**
```cmd
echo open 103.166.230.228> ftp.txt
echo ftpuser>> ftp.txt
echo nobody>> ftp.txt
echo ls>> ftp.txt
echo quit>> ftp.txt
ftp -s:ftp.txt
```

**Check vsftpd logs:**
```bash
sudo tail -f /var/log/vsftpd.log
```

---

**After running the fix, your dashboard will show all backup statistics correctly!** 🎉
