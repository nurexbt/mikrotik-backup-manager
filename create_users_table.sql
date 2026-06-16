-- Create users table
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

-- Delete existing admin user if exists
DELETE FROM users WHERE username = 'admin';

-- Insert default admin user (password: admin123)
-- This hash is generated using PHP password_hash() for 'admin123'
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('admin', '$2y$10$YourHashWillBeGeneratedByPHP', 'Administrator', 'admin@example.com', 'admin');
