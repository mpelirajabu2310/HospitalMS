<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public static function query($sql, $params = []) {
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert($sql, $params = []) {
        self::query($sql, $params);
        return self::getInstance()->getConnection()->lastInsertId();
    }

    public static function update($sql, $params = []) {
        return self::query($sql, $params)->rowCount();
    }

    public static function delete($sql, $params = []) {
        return self::query($sql, $params)->rowCount();
    }

    public static function rowCount($sql, $params = []) {
        return self::query($sql, $params)->rowCount();
    }
}
