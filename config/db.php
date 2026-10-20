<?php
// config/db.php
// On Railway these come from the MySQL service's variables (set them on the
// web service as references). On your own PC (XAMPP) none of them exist, so
// it falls back to the old local defaults and keeps working as before.
$host     = getenv('MYSQLHOST')     ?: 'localhost';
$port     = getenv('MYSQLPORT')     ?: '3306';
$dbname   = getenv('MYSQLDATABASE') ?: 'brc_calamba';
$username = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '';

try {
  // MySQL 8 on Railway enables ONLY_FULL_GROUP_BY; XAMPP/MariaDB does not. Drop it
  // for this connection so the existing GROUP BY queries behave the same as locally.
  $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
  ]);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // Don't print host/user details to visitors on a public site.
  error_log('DB connection failed: ' . $e->getMessage());
  die('Database connection failed.');
}
?>
