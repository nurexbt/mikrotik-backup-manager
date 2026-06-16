# Quick Fix: Backup Statistics Not Showing

## Problem Identified ✅

Your test shows:
- ✅ FTP Connection: **SUCCESS**
- ✅ FTP Login: **SUCCESS**
- ❌ Router Directories: **DO NOT EXIST**
- ❌ Total Backups: **0**

## Root Cause

The router directories haven't been created on the FTP server yet. When you add a router in the portal, it should create the directory automatically, but for existing routers, the directories might be missing.

## Solution (Choose One)

### Option 1: Automatic Fix (Easiest) ⭐

1. **Open the directory creator:**
   ```
   http://localhost/rtbackup/create_ftp_directories.php
   ```

2. **Click the page** - it will automatically:
   - Connect to FTP server
   - Create directories for all routers
   - Set proper permissions
   - Show success/failure for each

3. **Refresh dashboard** - statistics should now work!

### Option 2: Manual Fix (Ubuntu Server)

**SSH into your Ubuntu server and run:**

```bash
# Go to FTP directory
cd /home/ftpuser

# Create directories for each router (replace with your router names)
sudo mkdir -p Khan-Access-MKT
sudo mkdir -p Khan-Core-MKT
sudo mkdir -p HFAN-MT

# Set ownership
sudo chown -R ftpuser:ftpuser /home/ftpuser

# Set permissions
sudo chmod 755 /home/ftpuser/*

# Verify
ls -la /home/ftpuser
```

You should see:
```
drwxr-xr-x 2 ftpuser ftpuser 4096 May 18 14:30 Khan-Access-MKT
drwxr-xr-x 2 ftpuser ftpuser 4096 May 18 14:30 Khan-Core-MKT
drwxr-xr-x 2 ftpuser ftpuser 4096 May 18 14:30 HFAN-MT
```

### Option 3: Test with Dummy Files

**Create test backup files to verify everything works:**

```bash
# On Ubuntu server
cd /home/ftpuser/Khan-Access-MKT

# Create test files
sudo touch test-2024-05-18.backup
sudo touch test-2024-05-18.rsc

# Set ownership
sudo chown ftpuser:ftpuser *.backup *.rsc
sudo chmod 644 *.backup *.rsc

# Verify
ls -la
```

Then refresh dashboard - should show 2 backups!

## After Fix

Once directories are created:

1. **Run test again:**
   ```
   http://localhost/rtbackup/test_backup_stats.php
   ```
   
   Should show:
   - ✅ Directory Exists: **YES** for all routers
   - ✅ Files Found: **0** (or more if you added test files)

2. **Run MikroTik backup script** to generate real backups

3. **Check dashboard** - statistics will update automatically

## Why This Happened

When you add a router through the portal, the code tries to create the FTP directory automatically:

```php
$ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
    @ftp_mkdir($ftp_conn, $name);
}
```

But if:
- Routers were added before this code was implemented
- FTP connection failed during router creation
- Permissions were wrong

Then directories won't exist.

## Prevention

From now on, when you add a new router:
1. Directory is created automatically
2. You can verify in test tool
3. Ready for backups immediately

## Verification Steps

After running the fix:

1. ✅ **Test Tool** - All directories show "YES"
2. ✅ **Dashboard** - Shows correct statistics
3. ✅ **FTP Check** - Can browse directories
4. ✅ **Backup Upload** - MikroTik script works

## Files Created

- ✅ `create_ftp_directories.php` - Automatic directory creator
- ✅ `test_backup_stats.php` - Diagnostic tool (updated with quick fix button)
- ✅ `QUICK_FIX.md` - This guide

## Next Steps

1. **Create directories** using Option 1 (automatic)
2. **Run MikroTik scripts** to upload backups
3. **Enjoy statistics** on dashboard!

---

**The fix is simple - just create the missing directories!** 🎉
