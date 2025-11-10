<?php

namespace App\Database;

use PDO;

class TodoTable
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function createTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS todos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reminder_date DATETIME,
            priority TEXT DEFAULT 'medium',
            due_date DATETIME,
            tags TEXT
        )";
        
        $this->pdo->exec($sql);
    }
    
    public function updateSchema()
    {
        // SQLite用のカラム存在チェック
        $columns = $this->getTableColumns('todos');
        
        $requiredColumns = [
            'reminder_date' => "ALTER TABLE todos ADD COLUMN reminder_date DATETIME",
            'priority' => "ALTER TABLE todos ADD COLUMN priority TEXT DEFAULT 'medium'",
            'due_date' => "ALTER TABLE todos ADD COLUMN due_date DATETIME",
            'tags' => "ALTER TABLE todos ADD COLUMN tags TEXT"
        ];
        
        foreach ($requiredColumns as $columnName => $alterSql) {
            if (!in_array($columnName, $columns)) {
                try {
                    $this->pdo->exec($alterSql);
                } catch (\PDOException $e) {
                    // カラムが既に存在する場合はスキップ
                    if (strpos($e->getMessage(), 'duplicate column name') === false) {
                        throw $e;
                    }
                }
            }
        }
    }
    
    /**
     * SQLiteのテーブルからカラム一覧を取得
     */
    private function getTableColumns($tableName)
    {
        $stmt = $this->pdo->query("PRAGMA table_info($tableName)");
        $columns = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        return $columns;
    }
    
    public function insert($data)
    {
        $sql = "INSERT INTO todos (title, description, reminder_date, priority, due_date, tags) 
                VALUES (:title, :description, :reminder_date, :priority, :due_date, :tags)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':reminder_date' => $data['reminder_date'] ?? null,
            ':priority' => $data['priority'] ?? 'medium',
            ':due_date' => $data['due_date'] ?? null,
            ':tags' => $data['tags'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function findAll($filters = [])
    {
        $sql = "SELECT * FROM todos WHERE 1=1";
        $params = [];
        
        // ステータスフィルター
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        // 優先度フィルター
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        
        // タグフィルター
        if (!empty($filters['tag'])) {
            $sql .= " AND tags LIKE :tag";
            $params[':tag'] = '%' . $filters['tag'] . '%';
        }
        
        // 検索キーワード
        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM todos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data)
    {
        $sql = "UPDATE todos SET 
                title = :title,
                description = :description,
                status = :status,
                reminder_date = :reminder_date,
                priority = :priority,
                due_date = :due_date,
                tags = :tags
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':reminder_date' => $data['reminder_date'] ?? null,
            ':priority' => $data['priority'] ?? 'medium',
            ':due_date' => $data['due_date'] ?? null,
            ':tags' => $data['tags'] ?? null
        ]);
    }
    
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM todos WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function toggleStatus($id)
    {
        $todo = $this->findById($id);
        if (!$todo) {
            return false;
        }
        
        $newStatus = $todo['status'] === 'completed' ? 'pending' : 'completed';
        
        $stmt = $this->pdo->prepare("UPDATE todos SET status = :status WHERE id = :id");
        return $stmt->execute([
            ':status' => $newStatus,
            ':id' => $id
        ]);
    }
}