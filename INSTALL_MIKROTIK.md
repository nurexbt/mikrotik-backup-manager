# MikroTik Router Configuration Guide

Configure your MikroTik routers to automatically backup to the MikroTik Backup Manager system.

## 📋 Prerequisites

- Portal installed (Windows or Ubuntu)
- Ubuntu backup server configured with FTP/PPTP
- MikroTik RouterOS 6.x or 7.x
- Access to MikroTik router (Winbox, WebFig, or SSH)

## 🚀 Quick Setup

1. **Add router to portal**
2. **Generate script from portal**
3. **Paste script in MikroTik terminal**
4. **Done!** Backups run automatically

## 📝 Step-by-Step Configuration

### Step 1: Add Router to Portal

#### 1.1 Login to Portal

```
http://YOUR_SERVER_IP/rtbackup
Username: admin
Password: admin123
```

#### 1.2 Add New Router

1. Click **"Add Router"** button on Dashboard or Routers page

2. **Fill in details:**
   - **Router Name**: e.g., `Office-Router-01`
     - Use descriptive name
     - No spaces (use hyphens or underscores)
     - This will be the FTP directory name
   
   - **PPTP Username**: e.g., `router01`
     - Unique for each router
     - Used for VPN authentication
   
   - **PPTP Password**: Strong password
     - Minimum 8 characters
     - Use mix of letters, numbers, symbols

3. Click **"Save"**

Router is now added to the portal!

### Step 2: Add PPTP Credentials to Server

The router needs VPN credentials on the Ubuntu server.

#### 2.1 SSH to Ubuntu Server

```bash
ssh user@YOUR_SERVER_IP
```

#### 2.2 Edit PPTP Secrets File

```bash
sudo nano /etc/ppp/chap-secrets
```

#### 2.3 Add Router Credentials

Add a new line at the end:
```
router01 * YOUR_PASSWORD *
```

Format: `username * password *`

Example with multiple routers:
```
router01 * Pass123word! *
router02 * Another@Pass456 *
office-mt * Str0ng!Password *
```

#### 2.4 Save and Restart

```bash
# Save file: Ctrl+O, Enter, Ctrl+X

# Restart PPTP service
sudo systemctl restart pptpd

# Verify service is running
sudo systemctl status pptpd
```

### Step 3: Generate MikroTik Script

#### 3.1 Access Script Generator

In portal, go to **"Generate Script"** page

#### 3.2 Select Router

Choose the router you just added from dropdown

#### 3.3 Click "Generate"

The script will be displayed with:
- PPTP connection configuration
- Backup script
- Scheduler configuration

#### 3.4 Copy Script

Click **"Copy Script"** button or manually select all and copy

### Step 4: Apply Script to MikroTik

You can use Winbox, WebFig, or SSH to apply the script.

#### Method 1: Using Winbox (Recommended)

1. **Open Winbox**
   - Connect to your MikroTik router

2. **Open New Terminal**
   - Menu: **New Terminal** (or press F2)

3. **Paste Script**
   - Right-click in terminal → Paste
   - Or: Ctrl+V

4. **Press Enter**
   - Script will execute
   - Multiple lines will run

5. **Wait for Completion**
   - Should see: "BACKUP FINISHED"
   - May take 10-30 seconds

#### Method 2: Using WebFig

1. **Access WebFig**
   ```
   http://ROUTER_IP
   ```

2. **Go to Terminal**
   - Menu: **New Terminal**

3. **Paste Script**
   - Paste entire script
   - Press Enter

#### Method 3: Using SSH

1. **Connect via SSH**
   ```bash
   ssh admin@ROUTER_IP
   ```

2. **Paste Script**
   - Paste entire script
   - Press Enter

### Step 5: Verify Configuration

#### 5.1 Check PPTP Connection

```routeros
/interface pptp-client print
```

**Expected output:**
```
Flags: X - disabled, R - running
 0  R name="pptp-rtbackup" max-mtu=1450 max-mru=1450 mrru=disabled
      connect-to=YOUR_SERVER_IP user="router01" password="*****"
      profile=default-encryption
```

Status should show **R** (running) - may take 10-30 seconds to connect.

#### 5.2 Check Backup Script

```routeros
/system script print
```

**Expected output:**
```
 # NAME         OWNER        LAST-STARTED
 0 rtbackup     admin        never
```

#### 5.3 Check Scheduler

```routeros
/system scheduler print
```

**Expected output:**
```
 # NAME                  START-DATE          START-TIME   INTERVAL
 0 rtbackup-sched        jan/01/1970         00:00:00     1d
```

### Step 6: Test Backup

#### 6.1 Run Manual Backup

```routeros
/system script run rtbackup
```

Wait 10-60 seconds for completion.

#### 6.2 Check Logs

```routeros
/log print where topics~"system,info"
```

**Should see:**
```
23:15:01 system,info STARTING BACKUP
23:15:02 system,info DELAY 3S
23:15:05 system,info GENERATING RSC
23:15:08 system,info UPLOADING BACKUP TO FTP
23:15:12 system,info UPLOADING RSC TO FTP
23:15:15 system,info REMOVING LOCAL BACKUP FILES
23:15:16 system,info BACKUP COMPLETED & FILES CLEANED
```

#### 6.3 Verify in Portal

1. Go to portal Dashboard
2. Check **"Total Backups"** card
3. Go to **"Routers"** page
4. Click **"Backups"** button for your router
5. Should see 2 files (.backup and .rsc)

### Step 7: Schedule Configuration

By default, backup runs **daily at midnight**.

#### To Change Schedule

```routeros
# Change to run every 12 hours
/system scheduler set rtbackup-sched interval=12h

# Change to run at specific time (2 AM daily)
/system scheduler set rtbackup-sched start-time=02:00:00

# Change to run weekly (Sundays at 3 AM)
/system scheduler set rtbackup-sched interval=7d start-time=03:00:00
```

## 🔧 Advanced Configuration

### Custom Backup Script

If you want to customize the backup script:

```routeros
/system script edit rtbackup
```

**Add custom commands before or after backup:**
```routeros
# Example: Backup user manager database
/tool user-manager database save name=userman-backup

# Example: Create log entry
:log info "Custom backup started"
```

### Multiple Backup Destinations

Create additional scripts for different FTP servers:

```routeros
/system script add name=rtbackup-secondary source={
    # Backup to secondary server
    :global ftpIp2 "SECONDARY_SERVER_IP";
    # ... rest of script
}
```

### Backup Specific Configurations Only

Create script to backup only configuration (no system backup):

```routeros
/system script add name=config-only source={
    :global filename [/system clock get date];
    /export file=$filename;
    # Upload to FTP
    /tool fetch address=FTP_IP src-path="$filename.rsc" user=FTP_USER password=FTP_PASS mode=ftp dst-path="router/$filename.rsc" upload=yes;
}
```

## ⚠️ Troubleshooting

### PPTP Connection Failed

**Check logs:**
```routeros
/log print where topics~"pptp"
```

**Common issues:**

1. **Wrong credentials**
   ```
   Solution: Verify username/password in /etc/ppp/chap-secrets on server
   ```

2. **Server unreachable**
   ```
   Test: /ping YOUR_SERVER_IP
   Solution: Check firewall, routing
   ```

3. **Firewall blocking**
   ```
   On server: sudo ufw allow 1723/tcp && sudo ufw allow 47
   ```

### FTP Upload Failed

**Check logs:**
```routeros
/log print where topics~"system,info"
```

**Common issues:**

1. **Connection timeout**
   ```
   Error: "failure: connection timeout"
   Solution: Check FTP server firewall (port 21 and passive ports 40000-40100)
   ```

2. **Login failed**
   ```
   Error: "failure: login failed"
   Solution: Verify FTP credentials in config.php
   ```

3. **Directory doesn't exist**
   ```
   Solution: Portal auto-creates directory when router is added
   Manually create: mkdir /home/ftpuser/ROUTER_NAME on server
   ```

### Script Errors

**Check script syntax:**
```routeros
/system script print
```

**Test manually:**
```routeros
/system script run rtbackup
/log print where topics~"system,info,error"
```

**Common issues:**

1. **Script not found**
   ```
   Solution: Re-paste script from portal
   ```

2. **Variable error**
   ```
   Solution: Check for typos in router name, FTP credentials
   ```

### Scheduler Not Running

**Check scheduler status:**
```routeros
/system scheduler print
```

**Enable if disabled:**
```routeros
/system scheduler enable rtbackup-sched
```

**Check next run time:**
```routeros
/system scheduler print detail
```

## 📊 Monitoring

### Check Last Backup Time

```routeros
/file print where name~"backup|rsc"
```

Shows backup files created locally (they get deleted after upload).

### Check PPTP Statistics

```routeros
/interface pptp-client monitor pptp-rtbackup
```

Shows connection status, uptime, IP address.

### View All Logs

```routeros
/log print
```

### Export Logs

```routeros
/log print where topics~"system" file=system-logs
/tool fetch address=FTP_IP src-path="system-logs.txt" mode=ftp dst-path="logs/system-logs.txt" upload=yes
```

## 🔒 Security Best Practices

### 1. Use Strong Passwords

- PPTP password: Minimum 12 characters
- Mix uppercase, lowercase, numbers, symbols

### 2. Restrict PPTP Access

```routeros
# Only allow connection to backup server
/interface pptp-client
set pptp-rtbackup add-default-route=no
```

### 3. Backup Encryption

```routeros
# Ensure MPPE encryption is enabled
/ppp profile
print detail where name=default-encryption
```

Should show: `use-encryption=required`

### 4. Firewall Rules

```routeros
# Allow only backup server PPTP
/ip firewall filter add chain=output dst-port=1723 dst-address=YOUR_SERVER_IP action=accept protocol=tcp comment="Allow PPTP to backup server"

# Allow FTP to backup server
/ip firewall filter add chain=output dst-port=21 dst-address=YOUR_SERVER_IP action=accept protocol=tcp comment="Allow FTP to backup server"
```

### 5. Regular Testing

Test backup monthly:
```routeros
/system script run rtbackup
```

Verify files in portal.

## 📝 Configuration Checklist

- [ ] Router added to portal
- [ ] PPTP credentials added to server
- [ ] Script generated from portal
- [ ] Script applied to MikroTik
- [ ] PPTP connection established
- [ ] Manual backup tested successfully
- [ ] Backup files visible in portal
- [ ] Scheduler configured and enabled
- [ ] Logs showing successful backups
- [ ] Strong passwords used

## 🎉 Configuration Complete!

Your MikroTik router is now configured for automatic backups!

**What Happens Next:**
- Backup runs daily at midnight automatically
- Files uploaded to FTP server
- Old backups cleaned after 90 days
- View all backups in portal

**Monitor Backups:**
```
Portal Dashboard → Total Backups card
Portal Routers page → Click "Backups" button
```

**Need Help?**
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Portal Documentation](README.md)
- [GitHub Issues](https://github.com/YOUR_USERNAME/mikrotik-backup-manager/issues)
