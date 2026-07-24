<?php
/**
 * config.php
 * Database configuration + connection for Finance Manager.
 * Update the constants below with your own database credentials.
 * Every page that needs the database includes this file.
 */

declare(strict_types=1);

// ---- Database credentials (edit these) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'finance-manager'); // matches the hyphenated DB name shown in phpMyAdmin
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection. Throws PDOException on failure,
 * which is caught by whichever page includes this file.
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

/*
 * SQL to create the required table:
 *
 * CREATE TABLE users (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   full_name VARCHAR(120) NOT NULL,
 *   email VARCHAR(150) NOT NULL UNIQUE,
 *   password VARCHAR(255) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * CREATE TABLE income (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   user_id INT UNSIGNED NOT NULL,
 *   source VARCHAR(120) NOT NULL,
 *   amount DECIMAL(12,2) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * CREATE TABLE expenses (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   user_id INT UNSIGNED NOT NULL,
 *   category VARCHAR(120) NOT NULL,
 *   amount DECIMAL(12,2) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * CREATE TABLE budgets (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   user_id INT UNSIGNED NOT NULL,
 *   category VARCHAR(120) NOT NULL,
 *   monthly_limit DECIMAL(12,2) NOT NULL,
 *   alert_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   UNIQUE KEY user_category (user_id, category),
 *   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * -- If you already created `budgets` before alert_threshold existed, run:
 * -- ALTER TABLE budgets ADD COLUMN alert_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80;
 */
