<?php
// file: config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "hs_enrollment";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Create a global connection variable for procedural style
$database = new Database();
$conn = $database->getConnection();

// Optional: Check if connection was successful
if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}
?>