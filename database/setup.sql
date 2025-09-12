-- Database setup script for KanbanFlow Dashboard
-- Run this on the MySQL server to create database and user

-- Create database
CREATE DATABASE IF NOT EXISTS kanbanflow_dashboard 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Create user (change the password!)
CREATE USER IF NOT EXISTS 'kanbanflow_user'@'localhost' 
    IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD_123!';

-- Grant all privileges on the database to the user
GRANT ALL PRIVILEGES ON kanbanflow_dashboard.* 
    TO 'kanbanflow_user'@'localhost';

-- Grant additional privileges needed for Laravel migrations
GRANT CREATE, ALTER, DROP, INDEX, REFERENCES 
    ON kanbanflow_dashboard.* 
    TO 'kanbanflow_user'@'localhost';

-- Apply the changes
FLUSH PRIVILEGES;

-- Show confirmation
SELECT 'Database and user created successfully!' AS Status;
SELECT User, Host FROM mysql.user WHERE User = 'kanbanflow_user';
SHOW GRANTS FOR 'kanbanflow_user'@'localhost';