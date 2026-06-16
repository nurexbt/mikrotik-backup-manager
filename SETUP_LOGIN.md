# Login System Setup Instructions

## Quick Setup

1. **Run the setup script** by visiting:
   ```
   http://your-server-ip/rtbackup/setup_users.php
   ```

2. **Login with default credentials:**
   - Username: `admin`
   - Password: `admin123`

3. **Change the default password** immediately after first login through User Management.

## Features Added

### ✅ Login System
- Secure password hashing (bcrypt)
- Session management
- Auto-redirect if not logged in
- Remember login state

### ✅ User Management
- Add new users
- Edit existing users
- Delete users (except yourself)
- Assign roles (Admin/User)
- View last login time
- Search users

### ✅ User Interface
- Beautiful login page with gradient background
- User info display in sidebar
- Logout button
- Role-based badges

## Default Credentials

**Username:** admin  
**Password:** admin123

⚠️ **IMPORTANT:** Change the default password immediately after first login!

## User Roles

- **Admin**: Full access to all features including user management
- **User**: Access to all features except user management

## Security Notes

1. Passwords are hashed using PHP's `password_hash()` function
2. Sessions are used for authentication
3. SQL injection protection with prepared statements
4. Users cannot delete their own accounts
5. All forms use POST method for security

## Files Created

- `login.php` - Login page
- `logout.php` - Logout handler
- `setup_users.php` - Database setup script (can be deleted after setup)
- `create_users_table.sql` - SQL file for manual setup (optional)

## Manual Database Setup (Alternative)

If you prefer to set up manually, run this SQL:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Insert default admin (password: admin123)
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@example.com', 'admin');
```

## Troubleshooting

### Can't login?
- Make sure you ran `setup_users.php` first
- Check that the `users` table exists in your database
- Verify credentials: admin / admin123

### Session issues?
- Make sure PHP sessions are enabled
- Check that `session_start()` is working
- Clear browser cookies and try again

### Permission denied?
- Check file permissions on PHP files
- Ensure database user has proper privileges

## Next Steps

1. ✅ Run setup_users.php
2. ✅ Login with admin/admin123
3. ✅ Go to User Management
4. ✅ Change admin password
5. ✅ Add more users as needed
6. ✅ Delete setup_users.php file

Enjoy your secure MikroTik Backup Manager! 🎉
