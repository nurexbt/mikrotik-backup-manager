# Backup Rotation Setup Guide

Automatically delete backup files older than 90 days to save disk space.

## Quick Start

### Option 1: Manual Cleanup (Web Interface)

1. **Access the cleanup page:**
   ```
   http://localhost/rtbackup/cleanup_old_backups.php
   ```

2. **Preview what will be deleted (Dry Run):**
   ```
   http://localhost/rtbackup/cleanup_old_backups.php?days=90&dry_run=1
   ```

3. **Run actual cleanup:**
   ```
   http://localhost/rtbackup/cleanup_old_backups.php?days=90
   ```

### Option 2: Automatic Cleanup (Ubuntu Cron - Recommended)

**Step 1: Upload the script to Ubuntu server**

```bash
# SSH to your server
ssh nms@103.166.230.228

# Create the script
sudo nano /usr/local/bin/cleanup_old_backups.sh
```

Paste the contents from `cleanup_old_backups.sh`, then:

```bash
# Make executable
sudo chmod +x /usr/local/bin/cleanup_old_backups.sh

# Test it
sudo /usr/local/bin/cleanup_old_backups.sh
```

**Step 2: Setup cron job (runs daily at 2 AM)**

```bash
# Edit root crontab
sudo crontab -e

# Add this line:
0 2 * * * /usr/local/bin/cleanup_old_backups.sh >> /var/log/backup_cleanup.log 2>&1

# Save and exit
```

**Step 3: Verify cron job**

```bash
# List cron jobs
sudo crontab -l

# Check logs (after it runs)
cat /var/log/backup_cleanup.log
```

### Option 3: Windows Scheduled Task

**Method 1: PowerShell (Recommended)**

```powershell
# Open PowerShell as Administrator
# Create scheduled task to run daily at 2 AM

$action = New-ScheduledTaskAction -Execute "php.exe" -Argument "C:\xampp\htdocs\rtbackup\cleanup_old_backups.php"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName "Backup Cleanup" -Action $action -Trigger $trigger -Settings $settings -Description "Deletes backup files older than 90 days"
```

**Method 2: Task Scheduler GUI**

1. Open **Task Scheduler** (taskschd.msc)
2. Click **Create Basic Task**
3. Name: "Backup Cleanup"
4. Trigger: **Daily** at **2:00 AM**
5. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\rtbackup\cleanup_old_backups.php`
6. Click **Finish**

## Configuration Options

### Change Retention Period

**Web Interface:**
```
?days=60    # Keep 60 days
?days=30    # Keep 30 days
?days=180   # Keep 180 days (6 months)
```

**Shell Script:**
Edit `/usr/local/bin/cleanup_old_backups.sh`:
```bash
RETENTION_DAYS=90  # Change this value
```

### Dry Run (Preview Only)

**Web Interface:**
```
?days=90&dry_run=1
```

**Shell Script:**
Add this line before the find command:
```bash
echo "DRY RUN MODE - No files will be deleted"
# Then add -print instead of actual deletion
```

## What Gets Deleted?

The cleanup script:
- ✅ Deletes files older than retention period (default 90 days)
- ✅ Only affects `.backup` and `.rsc` files
- ✅ Processes all router directories
- ✅ Skips special directories (active_connections)
- ✅ Logs all deletions
- ✅ Reports space freed

## Monitoring

### Check Cleanup Logs

**Ubuntu:**
```bash
# View log file
cat /var/log/backup_cleanup.log

# Tail real-time
tail -f /var/log/backup_cleanup.log

# Check last cleanup
tail -n 50 /var/log/backup_cleanup.log
```

**Windows:**
```powershell
# View task history in Task Scheduler
Get-ScheduledTaskInfo -TaskName "Backup Cleanup"
```

### Verify Cleanup Status

**Via Web:**
```
http://localhost/rtbackup/cleanup_old_backups.php?days=90&dry_run=1
```

**Via SSH:**
```bash
# Count files older than 90 days
find /home/ftpuser -type f \( -name "*.backup" -o -name "*.rsc" \) -mtime +90 | wc -l

# Show old files
find /home/ftpuser -type f \( -name "*.backup" -o -name "*.rsc" \) -mtime +90 -ls
```

## Safety Features

1. **Dry Run Mode** - Preview deletions before actual cleanup
2. **Logging** - All deletions are logged with timestamps
3. **Backup-only** - Only deletes .backup and .rsc files
4. **Skip Special Dirs** - Ignores active_connections and other non-router folders
5. **Warning Alerts** - Alerts if >100 files deleted (potential issue)

## Troubleshooting

### Cleanup Not Running

**Check cron job:**
```bash
# List cron jobs
sudo crontab -l

# Check cron service
sudo systemctl status cron

# View cron logs
grep CRON /var/log/syslog
```

### Permission Denied

**Fix permissions:**
```bash
sudo chown -R ftpuser:ftpuser /home/ftpuser
sudo chmod -R 755 /home/ftpuser
```

### Files Not Deleting

**Check file ages:**
```bash
# List files with ages
find /home/ftpuser -type f -name "*.backup" -mtime +90 -printf "%p %TY-%Tm-%Td\n"
```

## Best Practices

1. **Test First** - Always run dry-run mode first
2. **Monitor Logs** - Check logs regularly for issues
3. **Adjust Retention** - Based on your backup frequency and storage capacity
4. **Backup Before Cleanup** - Consider backing up to external storage before cleanup
5. **Alert on Large Deletes** - Set up notifications if many files are deleted

## Retention Recommendations

| Backup Frequency | Recommended Retention |
|------------------|----------------------|
| Daily | 90 days (default) |
| Multiple per day | 60 days |
| Weekly | 180 days (6 months) |
| Monthly | 365 days (1 year) |

## Example Output

**Successful Cleanup:**
```
[2026-05-18 02:00:01] ==========================================
[2026-05-18 02:00:01] Starting backup rotation cleanup
[2026-05-18 02:00:01] Retention period: 90 days
[2026-05-18 02:00:01] ==========================================
[2026-05-18 02:00:02] Processing router: HOME-RT
[2026-05-18 02:00:02]   ✓ Deleted: HOME-RT-2026-02-15-120000.backup (1.2 MiB)
[2026-05-18 02:00:02]   ✓ Deleted: HOME-RT-2026-02-15-120000.rsc (45.3 KiB)
[2026-05-18 02:00:03] Processing router: Khan-Core-MKT
[2026-05-18 02:00:03]   ✓ Deleted: Khan-Core-MKT-2026-02-16-000000.backup (2.1 MiB)
[2026-05-18 02:00:04] ==========================================
[2026-05-18 02:00:04] Cleanup Summary:
[2026-05-18 02:00:04]   Total old files found: 3
[2026-05-18 02:00:04]   Files deleted: 3
[2026-05-18 02:00:04]   Space freed: 3.4 MiB
[2026-05-18 02:00:04] ==========================================
[2026-05-18 02:00:04] Backup rotation completed successfully
```

## Support

For manual cleanup or adjustments:
- **Web Interface:** http://localhost/rtbackup/cleanup_old_backups.php
- **SSH Command:** `sudo /usr/local/bin/cleanup_old_backups.sh`
- **Logs:** `/var/log/backup_cleanup.log`

---

**Backup rotation is now set up! Old files will be automatically deleted after 90 days.** 🗑️✨
