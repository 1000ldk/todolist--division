<?php
/**
 * ToDoアプリケーション - ロジック
 * 基本的なCRUD操作（Create, Read, Update, Delete）と一覧取得
 */

// データベース接続設定を読み込み
require_once __DIR__ . '/../config/database.php';

// エラーメッセージ用の変数
$error_message = '';
$success_message = '';

// 簡易マイグレーション: 必要な列が無ければ追加（priority, due_date, tags）
try {
    // priority
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'todos' AND COLUMN_NAME = 'priority'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE todos ADD COLUMN priority ENUM('high','medium','low') NOT NULL DEFAULT 'medium'");
    }
    // due_date
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'todos' AND COLUMN_NAME = 'due_date'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE todos ADD COLUMN due_date DATETIME NULL DEFAULT NULL");
    }
    // tags
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'todos' AND COLUMN_NAME = 'tags'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE todos ADD COLUMN tags VARCHAR(255) NULL DEFAULT NULL");
    }
} catch (PDOException $e) {
    // マイグレーション失敗は致命的にせず、メッセージだけ保持
    $error_message = $error_message ?: ('スキーマ更新で警告: ' . $e->getMessage());
}

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            // ToDoの作成
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $reminder_date = $_POST['calculated_reminder_date'] ?? null;
            // 新機能: 優先度/締切/タグ
            $priority = $_POST['priority'] ?? 'medium';
            if (!in_array($priority, ['high','medium','low'], true)) { $priority = 'medium'; }
            $due_date = $_POST['due_date'] ?? null;
            $tags = trim($_POST['tags'] ?? '');
            // リマインド未指定で締切がある場合は、締切でリマインド
            if ((empty($reminder_date) || $reminder_date === '') && !empty($due_date)) {
                $reminder_date = $due_date;
            }
            if (!empty($title)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO todos (title, description, reminder_date, priority, due_date, tags) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $reminder_date ?: null, $priority, ($due_date ?: null), ($tags !== '' ? $tags : null)]);
                    $success_message = "ToDoが正常に追加されました！";
                } catch (PDOException $e) {
                    $error_message = "エラーが発生しました: " . $e->getMessage();
                }
            } else {
                $error_message = "タイトルは必須です。";
            }
            break;
            
        case 'update':
            // ToDoの更新（指定されたフィールドのみ更新）
            $id = $_POST['id'] ?? '';
            $title = trim($_POST['title'] ?? '');
            if (!empty($id) && !empty($title)) {
                try {
                    $set = ['title = ?'];
                    $params = [$title];
                    // description は送信されている場合のみ変更
                    if (array_key_exists('description', $_POST)) {
                        $set[] = 'description = ?';
                        $params[] = trim((string)($_POST['description']));
                    }
                    // reminder_date は calculated または reminder_date があれば更新
                    if (array_key_exists('calculated_reminder_date', $_POST) || array_key_exists('reminder_date', $_POST)) {
                        $reminder_input = $_POST['calculated_reminder_date'] ?? ($_POST['reminder_date'] ?? null);
                        $set[] = 'reminder_date = ?';
                        $params[] = ($reminder_input === '' ? null : $reminder_input);
                    }
                    // priority
                    if (array_key_exists('priority', $_POST)) {
                        $p = $_POST['priority'];
                        if (!in_array($p, ['high','medium','low'], true)) { $p = 'medium'; }
                        $set[] = 'priority = ?';
                        $params[] = $p;
                    }
                    // due_date
                    if (array_key_exists('due_date', $_POST)) {
                        $d = $_POST['due_date'];
                        $set[] = 'due_date = ?';
                        $params[] = ($d === '' ? null : $d);
                    }
                    // tags
                    if (array_key_exists('tags', $_POST)) {
                        $t = trim((string)$_POST['tags']);
                        $set[] = 'tags = ?';
                        $params[] = ($t === '' ? null : $t);
                    }
                    $params[] = $id;
                    $sql = 'UPDATE todos SET ' . implode(', ', $set) . ' WHERE id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success_message = "ToDoが正常に更新されました！";
                } catch (PDOException $e) {
                    $error_message = "エラーが発生しました: " . $e->getMessage();
                }
            } else {
                $error_message = "IDとタイトルは必須です。";
            }
            break;
            
        case 'delete':
            // ToDoの削除
            $id = $_POST['id'] ?? '';
            
            if (!empty($id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "ToDoが正常に削除されました！";
                } catch (PDOException $e) {
                    $error_message = "エラーが発生しました: " . $e->getMessage();
                }
            } else {
                $error_message = "IDが指定されていません。";
            }
            break;
            
        case 'toggle_status':
            // ステータスの切り替え
            $id = $_POST['id'] ?? '';
            
            if (!empty($id)) {
                try {
                    // 現在のステータスを取得
                    $stmt = $pdo->prepare("SELECT status FROM todos WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_status = $stmt->fetchColumn();
                    
                    // ステータスを切り替え
                    $new_status = ($current_status === 'pending') ? 'completed' : 'pending';
                    
                    $stmt = $pdo->prepare("UPDATE todos SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $id]);
                    $success_message = "ステータスが更新されました！";
                } catch (PDOException $e) {
                    $error_message = "エラーが発生しました: " . $e->getMessage();
                }
            }
            break;
    }
}

// ToDo一覧を取得（検索・フィルタ・ソート対応）
try {
    $q = trim($_GET['q'] ?? '');
    $priorityFilter = $_GET['priority'] ?? '';
    $tagFilter = trim($_GET['tag'] ?? '');
    $sort = $_GET['sort'] ?? 'created_desc';
    $conditions = [];
    $params = [];
    if ($q !== '') {
        $conditions[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }
    if (in_array($priorityFilter, ['high','medium','low'], true)) {
        $conditions[] = "priority = ?";
        $params[] = $priorityFilter;
    }
    if ($tagFilter !== '') {
        $conditions[] = "tags LIKE ?"; // 簡易一致
        $params[] = "%$tagFilter%";
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    // ソート
    switch ($sort) {
        case 'due_asc':
            $order = "ORDER BY due_date IS NULL ASC, due_date ASC";
            break;
        case 'priority_desc':
            $order = "ORDER BY FIELD(priority,'high','medium','low'), created_at DESC";
            break;
        case 'created_asc':
            $order = "ORDER BY created_at ASC";
            break;
        case 'created_desc':
        default:
            $order = "ORDER BY created_at DESC";
    }
    $sql = "SELECT * FROM todos $where $order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $todos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "ToDoの取得でエラーが発生しました: " . $e->getMessage();
    $todos = [];
}
