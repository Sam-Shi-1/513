<?php
class Database {
    private $host = "sql103.infinityfree.com";
    private $db_name = "if0_39913189_wp887";
    private $username = "if0_39913189";
    private $password = "lyE2sjuBnU";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            $this->conn = null;
        }
        return $this->conn;
    }
}
?>