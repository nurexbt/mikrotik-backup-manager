# MikroTik Backup Manager

A complete web-based backup management system for MikroTik routers with automatic FTP backup, real-time monitoring, and backup rotation.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/license-MIT-green)

## 🚀 Quick Start

```bash
# Ubuntu/Linux Installation
git clone https://github.com/nurexbt/mikrotik-backup-manager.git
cd mikrotik-backup-manager
chmod +x install.sh
sudo ./install.sh

# Windows Installation
# See INSTALL_WINDOWS.md for detailed steps
```

**🔗 [Ubuntu Installation Guide](INSTALL_UBUNTU.md)** | **🔗 [Windows Installation Guide](INSTALL_WINDOWS.md)**

## 📋 Table of Contents

- [Features](#features)
- [Demo](#demo)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Installation](#installation)
  - [Ubuntu/Linux Installation](INSTALL_UBUNTU.md)
  - [Windows Installation](INSTALL_WINDOWS.md)
- [Configuration](#configuration)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

### Core Features
- 🖥️ **Modern Dashboard** - Real-time statistics with 11 animated cards
- 📊 **Backup Management** - View, download, and manage all router backups
- 🔐 **User Management** - Role-based access control (Admin/User)
- 🎨 **6 Color Themes** - Customizable portal themes
- 📜 **Auto Script Generator** - One-click MikroTik script generation
- 🗑️ **Backup Rotation** - Automatic cleanup of old backups (90 days)
- 📁 **FTP Integration** - Secure backup storage via FTP
- 🔄 **PPTP VPN** - Automatic VPN connection for backups

### Advanced Features
- 📈 **Real-time Monitoring** - Server CPU, RAM, disk usage
- 🔍 **Search & Filter** - Find backups quickly with pagination
- 📊 **Backup Statistics** - Total backups, sizes, last backup time
- 🚀 **Auto-cleanup** - Removes backup files from routers after upload
- 🔔 **Server Logs** - View PPTP and FTP logs directly
- 🛠️ **Diagnostic Tools** - Built-in FTP and connection testing

## 📸 Screenshots

### Dashboard
![Dashboard](docs/screenshots/dashboard.png)

### Router Management
![Routers](docs/screenshots/routers.png)

### Backup Browser
![Backups](docs/screenshots/backups.png)

## 🏗️ Architecture

```
┌─────────────────┐       ┌──────────────────┐       ┌─────────────────┐
│   Windows PC    │       │  Ubuntu Server   │       │ MikroTik Router │
│                 │       │                  │       │                 │
│  Web Portal     │◄─────►│  FTP Server      │◄─────►│  Backup Script  │
│  (XAMPP/Apache) │       │  PPTP Server     │       │  (RouterOS)     │
│  MySQL Database │       │  Backup Storage  │       │                 │
└─────────────────┘       └──────────────────┘       └─────────────────┘
```

**Flow:**
1. MikroTik connects to PPTP VPN (Ubuntu)
2. Router runs backup script (daily)
3. Backups uploaded to FTP server (Ubuntu)
4. Web portal displays backups (Windows/Ubuntu)
5. Old backups cleaned automatically (90 days)

## 🖥️ System Requirements

### Option 1: Ubuntu Server (All-in-One - Recommended)
- **OS**: Ubuntu 20.04 LTS or higher
- **Services**: Apache, PHP 7.4+, MySQL 5.7+, vsftpd, pptpd
- **RAM**: 2 GB minimum
- **Disk**: 20 GB+ (depends on backup frequency)
- **Network**: Public IP address

### Option 2: Windows Management + Ubuntu Backup Server
- **Windows**: XAMPP, 2 GB RAM, 1 GB disk
- **Ubuntu**: vsftpd, pptpd, 1 GB RAM, 10 GB+ disk
- **Network**: Both accessible from MikroTik routers

### MikroTik Routers
- **RouterOS**: Version 6.x or 7.x
- **Features**: PPTP Client, FTP Client, Scheduler
- **Disk**: 10 MB for temporary storage

## 📥 Installation

Choose your installation method:

### 🐧 Ubuntu/Linux (Recommended)

Complete all-in-one installation on Ubuntu server:

**[📖 Ubuntu Installation Guide](INSTALL_UBUNTU.md)**

```bash
git clone https://github.com/YOUR_USERNAME/mikrotik-backup-manager.git
cd mikrotik-backup-manager
chmod +x install.sh
sudo ./install.sh
```

### 🪟 Windows

Install management portal on Windows with XAMPP:

**[📖 Windows Installation Guide](INSTALL_WINDOWS.md)**

```powershell
# Download repository
git clone https://github.com/YOUR_USERNAME/mikrotik-backup-manager.git

# Or download ZIP from GitHub
# Extract to C:\xampp\htdocs\rtbackup\
```

### 📡 MikroTik Configuration

After installing the portal, configure your routers:

**[📖 MikroTik Configuration Guide](INSTALL_MIKROTIK.md)**

## ⚙️ Configuration

After installation, configure the system:

### Portal Configuration

Edit `config.php`:
```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'rtbackup';

$settings = [
    'ftp_server' => 'YOUR_SERVER_IP',
    'ftp_user' => 'ftpuser',
    'ftp_pass' => 'nobody',
    'pptp_server' => 'YOUR_SERVER_IP'
];
```

### Add Your First Router

1. Login to portal (admin/admin123)
2. Click "Add Router"
3. Enter router details
4. Generate and apply MikroTik script

**[📖 MikroTik Configuration Guide](INSTALL_MIKROTIK.md)**

### Setup Backup Rotation

Automatically delete backups older than 90 days:

**[📖 Backup Rotation Setup](BACKUP_ROTATION_SETUP.md)**

## 📖 Usage

### Dashboard
- View real-time statistics
- Monitor backup status
- Check server health

### Router Management
- Add/edit/delete routers
- View router backups
- Generate backup scripts

### Backup Management
- Browse all backups
- Search and filter
- Download backup files
- Delete old backups

### User Management
- Add users (Admin role required)
- Assign roles (Admin/User)
- Change passwords

### Themes
- 6 color themes available
- Settings → Click theme to change
- Automatic save

## 🔧 Troubleshooting

### Common Issues

**Portal shows 0 backups:**
- Verify FTP connection: `http://localhost/rtbackup/test_backup_stats.php`
- Check router directories: `http://localhost/rtbackup/sync_routers_ftp.php`

**MikroTik can't connect:**
- Check PPTP credentials on server
- Verify firewall allows port 1723
- Test: `/ping SERVER_IP` on MikroTik

**Backups not uploading:**
- Check vsftpd status on server
- Verify firewall allows ports 21 and 40000-40100
- Check FTP logs: `sudo tail -f /var/log/vsftpd.log`

**[📖 Complete Troubleshooting Guide](TROUBLESHOOTING.md)**

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [Ubuntu Installation](INSTALL_UBUNTU.md) | Complete Ubuntu server setup |
| [Windows Installation](INSTALL_WINDOWS.md) | XAMPP installation on Windows |
| [MikroTik Configuration](INSTALL_MIKROTIK.md) | Configure MikroTik routers |
| [Backup Rotation](BACKUP_ROTATION_SETUP.md) | Setup automatic cleanup |
| [Server Stats API](SETUP_SERVER_STATS.md) | Real-time monitoring |
| [FTP Fix Guide](FTP_FIX_GUIDE.md) | Troubleshoot FTP issues |

## 🛠️ Built-in Tools

Diagnostic tools accessible from portal:

- **FTP Connection Test**: `/test_ftp_connection.php`
- **Quick FTP Test**: `/quick_ftp_test.php`
- **Backup Stats Test**: `/test_backup_stats.php`
- **Router Sync**: `/sync_routers_ftp.php`
- **Create Directories**: `/create_ftp_directories.php`
- **Cleanup Backups**: `/cleanup_old_backups.php`

## 📊 Default Settings

| Setting | Value |
|---------|-------|
| Database | rtbackup |
| Default User | admin |
| Default Password | admin123 |
| FTP Port | 21 |
| PPTP Port | 1723 |
| Passive Ports | 40000-40100 |
| Backup Retention | 90 days |
| Backup Schedule | Daily at midnight |
| Cleanup Schedule | Daily at 2 AM |

## 🔐 Security Recommendations

1. **Change default password** immediately after first login
2. **Use strong passwords** for all accounts
3. **Restrict FTP access** by IP if possible
4. **Use HTTPS** in production (setup SSL certificate)
5. **Keep software updated** regularly
6. **Monitor logs** for suspicious activity
7. **Backup the backup server** periodically
8. **Use firewall rules** to restrict access

## 🚀 Performance Tips

1. **Use SSD storage** for backup server
2. **Monitor disk space** regularly
3. **Setup backup rotation** to prevent disk full
4. **Optimize MySQL** for better performance
5. **Use caching** for dashboard stats
6. **Monitor server resources** (CPU, RAM)

## 📝 Maintenance

### Daily Tasks
- Check dashboard for backup status
- Verify backups are being uploaded

### Weekly Tasks
- Review server logs
- Check disk space usage
- Test backup downloads

### Monthly Tasks
- Update software (XAMPP, Ubuntu)
- Review user access
- Test disaster recovery

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👨‍💻 Author

Created with ❤️ for MikroTik administrators

## 📞 Support

For issues and questions:
- Check [Troubleshooting](#troubleshooting) section
- Review [Additional Documentation](#additional-documentation)
- Use built-in [Diagnostic Tools](#diagnostic-tools)

## 🎉 Acknowledgments

- MikroTik for RouterOS
- XAMPP team for the stack
- Ubuntu community
- All contributors

---

**Version**: 1.0.0  
**Last Updated**: May 2026  
**Status**: Production Ready ✅
