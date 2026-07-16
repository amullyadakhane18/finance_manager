<?php


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
 
?>