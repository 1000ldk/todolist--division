<?php
/**
 * ToDoアプリケーション - ロジック (SQLite版)
 * CRUD + 一覧取得（検索/フィルタ/ソート対応）
 */
require_once __DIR__ . '/config/database.php';

// メッセージ
$error_message = '';
$success_message = '';

// 日時文字列正規化（YYYY-MM-DDTHH:MM -> "YYYY-MM-DD HH:MM:SS"）
function norm_datetime(?string $s): ?string {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '') return null;
    $s = str_replace('T', ' ', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) $s .= ':00';
    return $s;
}

/* ユーザーリクエスト処理 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            if (!in_array($priority, ['high','medium','low'], true)) { $priority = 'medium'; }
            $due_date = norm_datetime($_POST['due_date'] ?? null);
            $tags = trim($_POST['tags'] ?? '');
            $reminder_date = norm_datetime($_POST['calculated_reminder_date'] ?? null);
            if (!$reminder_date && $due_date) $reminder_date = $due_date;

            if ($title !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO todos (title, description, reminder_date, priority, due_date, tags)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $description,
                        $reminder_date,
                        $priority,
                        $due_date,
                        ($tags !== '' ? $tags : null)
                    ]);
                    $success_message = "ToDoが正常に追加されました！";
                } catch (PDOException $e) {
                    $error_message = "エラーが発生しました: " . $e->getMessage();
                }
            } else {
                $error_message = "タイトルは必須です。";
            }
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $title = trim($_POST['title'] ?? '');
            if ($id !== '' && $title !== '') {
                try {
                    $set = ['title = ?'];
                    $params = [$title];

                    if (array_key_exists('description', $_POST)) {
                        $set[] = 'description = ?';
                        $params[] = trim((string)($_POST['description']));
                    }
                    if (array_key_exists('calculated_reminder_date', $_POST) || array_key_exists('reminder_date', $_POST)) {
                        $reminder_input = $_POST['calculated_reminder_date'] ?? ($_POST['reminder_date'] ?? null);
                        $set[] = 'reminder_date = ?';
                        $params[] = norm_datetime($reminder_input);
                    }
                    if (array_key_exists('priority', $_POST)) {
                        $p = $_POST['priority'];
                        if (!in_array($p, ['high','medium','low'], true)) { $p = 'medium'; }
                        $set[] = 'priority = ?';
                        $params[] = $p;
                    }
                    if (array_key_exists('due_date', $_POST)) {
                        $d = norm_datetime($_POST['due_date']);
                        $set[] = 'due_date = ?';
                        $params[] = $d;
                    }
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
            $id = $_POST['id'] ?? '';
            if ($id !== '') {
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
            $id = $_POST['id'] ?? '';
            if ($id !== '') {
                try {
                    $stmt = $pdo->prepare("SELECT status FROM todos WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_status = $stmt->fetchColumn();
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

/* 一覧取得（検索・フィルタ・ソート） */
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
        $conditions[] = "tags LIKE ?";
        $params[] = "%$tagFilter%";
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    switch ($sort) {
        case 'due_asc':
            $order = "ORDER BY (due_date IS NULL) ASC, due_date ASC";
            break;
        case 'priority_desc':
            // high > medium > low
            $order = "ORDER BY CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC, created_at DESC";
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
