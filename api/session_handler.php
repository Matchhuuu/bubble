<?php
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $db;

    public function open($savePath, $sessionName): bool {
        $sname = "mysql-35594af1-reapquizon-ff22.h.aivencloud.com";
        $unmae = "avnadmin";
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;
        if (!$password) { return false; }
        $db_name = "defaultdb";
        $port = "22331";
        $this->db = mysqli_init();
        mysqli_options($this->db, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        mysqli_ssl_set($this->db, NULL, NULL, NULL, NULL, NULL);
        
        $ssl_flag = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : 64;
        
        if (!mysqli_real_connect($this->db, $sname, $unmae, $password, $db_name, (int)$port, NULL, $ssl_flag)) {
            return false;
        }
        return true;
    }

    public function close(): bool {
        if ($this->db) {
            $this->db->close();
        }
        return true;
    }

    public function read($id): string|false {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = ?");
        if (!$stmt) return "";
        
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['data'];
        }
        return "";
    }

    public function write($id, $data): bool {
        $timestamp = time();
        $stmt = $this->db->prepare("REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
        if (!$stmt) return false;
        
        $stmt->bind_param("ssi", $id, $data, $timestamp);
        return $stmt->execute();
    }

    public function destroy($id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        if (!$stmt) return false;
        
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    public function gc($max_lifetime): int|false {
        $old = time() - $max_lifetime;
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE timestamp < ?");
        if (!$stmt) return false;
        
        $stmt->bind_param("i", $old);
        $stmt->execute();
        return $this->db->affected_rows;
    }
}

$handler = new DatabaseSessionHandler();
session_set_save_handler($handler, true);
session_start();
?>
