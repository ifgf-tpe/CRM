-- ChurchCRM local MySQL setup
-- Run as root: mysql -u root -p < docker/setup-local-mysql.sql

CREATE DATABASE IF NOT EXISTS churchcrm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'churchcrm'@'localhost' IDENTIFIED BY 'changeme';
GRANT ALL PRIVILEGES ON churchcrm.* TO 'churchcrm'@'localhost';
FLUSH PRIVILEGES;

SELECT 'Database and user created.' AS status;
