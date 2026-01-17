<?php
require_once 'config.php';

class Database {
    private $conn = null;
    private static $instance = null;
    private $connected = false;
    
    // منع إنشاء كائنات متعددة (Singleton Pattern)
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATION
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // التأكد من استخدام UTF-8 للاتصال
            $this->conn->exec("SET CHARACTER SET " . DB_CHARSET);
            $this->conn->exec("SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATION);
            
            $this->connected = true;
            
        } catch(PDOException $e) {
            $this->connected = false;
            // لا نوقف التنفيذ، فقط نسجل الخطأ
            error_log("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
        }
    }
    
    // الحصول على نسخة واحدة من الاتصال
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // الحصول على الاتصال
    public function getConnection() {
        return $this->conn;
    }
    
    // التحقق من الاتصال
    public function isConnected() {
        return $this->connected && $this->conn !== null;
    }
    
    // تنفيذ استعلام SELECT
    public function select($query, $params = []) {
        if (!$this->isConnected()) {
            return ["error" => "لا يوجد اتصال بقاعدة البيانات"];
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("خطأ في الاستعلام: " . $e->getMessage());
            return ["error" => $e->getMessage()];
        }
    }
    
    // تنفيذ استعلامات INSERT, UPDATE, DELETE
    public function execute($query, $params = []) {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("خطأ في التنفيذ: " . $e->getMessage());
            return false;
        }
    }
    
    // الحصول على آخر ID تم إدراجه
    public function lastInsertId() {
        if (!$this->isConnected()) {
            return 0;
        }
        
        try {
            return $this->conn->lastInsertId();
        } catch(PDOException $e) {
            error_log("خطأ في lastInsertId: " . $e->getMessage());
            return 0;
        }
    }
    
    // منع استنساخ الكائن
    private function __clone() {}
}
?>