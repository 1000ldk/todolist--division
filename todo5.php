<?php
// エントリーポイント: ロジックとビューを分離
require_once __DIR__ . '/todo5_logic.php';
include __DIR__ . '/todo5_view.php';

// データベース接続設定を読み込み
require_once '../config/database.php';

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
?>


/**ここから下がテスト用のHTMLコード */

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ToDoアプリケーション</title>

/** ここからCSS */

    <style>
        /* --- minimal overrides to simplify overall look --- */
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; max-width: 760px; padding: 16px; background: #fafafa; }
        .container { background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #eee; box-shadow: none; }
        h1 { color: #222; text-align: left; font-size: 22px; margin: 8px 0 16px; font-weight: 700; }
        h2 { font-size: 16px; margin: 16px 0 8px; color: #333; }
        .btn { padding: 8px 12px; font-size: 13px; border-radius: 6px; }
        .todo-item { background: #fff; padding: 12px; margin: 8px 0; border-radius: 6px; border: 1px solid #eee; border-left: 4px solid #4CAF50; }
        .todo-title { font-size: 16px; font-weight: 600; margin-bottom: 4px; color: #222; }
        .todo-description { color: #555; margin-bottom: 8px; }
        .todo-meta { font-size: 12px; color: #666; margin-bottom: 8px; }
        .todo-actions .btn { margin-right: 6px; padding: 6px 10px; font-size: 12px; }
        .toast-container { position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index: 9999; }
        .toast { background: #333; color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .toast .toast-actions { display: none; }
        .filters-form { margin: 12px 0 20px; display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
        .filters-form > div { flex: 1 1 180px; min-width: 180px; }
        .filters-actions { width: 100%; display: flex; gap: 8px; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .btn-danger {
            background-color: #f44336;
        }
        
        .btn-danger:hover {
            background-color: #da190b;
        }
        
        .btn-warning {
            background-color: #ff9800;
        }
        
        .btn-warning:hover {
            background-color: #e68900;
        }
        
        .todo-list {
            margin-top: 30px;
        }
        
        .todo-item {
            background: #f9f9f9;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        
        .todo-item.completed {
            border-left-color: #9e9e9e;
            opacity: 0.7;
        }
        
        .todo-item.completed .todo-title {
            text-decoration: line-through;
        }
        
        .todo-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .todo-description {
            color: #666;
            margin-bottom: 10px;
        }
        
        .todo-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .todo-actions {
            margin-top: 10px;
        }
        
        .todo-actions .btn {
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .status-pending {
            color: #ff9800;
            font-weight: bold;
        }
        
        .status-completed {
            color: #4CAF50;
            font-weight: bold;
        }
        /* 優先度バッジ */
        .priority-badge {
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
        }
        .priority-high { background-color: #e53935; }
        .priority-medium { background-color: #1e88e5; }
        .priority-low { background-color: #43a047; }

        /* タグ */
        .tag-chip {
            display: inline-block;
            background: #e0f2f1;
            color: #00695c;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            border: 1px solid #b2dfdb;
        }

        /* 締切のハイライト */
        .todo-item.overdue { border-left-color: #d32f2f; background: #fff5f5; }
        .todo-item.due-soon { border-left-color: #f9a825; background: #fffbe6; }
        
        /* トースト通知（画面内通知） */
        .toast-container {
            position: fixed;
            left: 50%;
            bottom: 20px;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 9999;
            pointer-events: none;
        }
        .toast {
            background: rgba(33, 33, 33, 0.96);
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            min-width: 260px;
            max-width: min(92vw, 420px);
            font-size: 14px;
            line-height: 1.4;
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast .toast-title {
            font-weight: 700;
            margin-right: 6px;
        }
        .toast .toast-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
        }
        .toast .toast-btn {
            appearance: none;
            -webkit-appearance: none;
            border: 0;
            border-radius: 6px;
            padding: 6px 10px;
            background: #ffc107;
            color: #222;
            font-size: 12px;
            font-weight: 600;
        }
    </style>


    <style>
        /* simplified pop theme overrides (take precedence) */
        :root { --accent: #7c4dff; --accent-contrast:#fff; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans"; max-width: 760px; padding: 16px; background: #fafafa; }
        .container { background: #fff; padding: 16px; border-radius: 12px; border: 1px solid #eee; box-shadow: none; }
        h1 { color: #222; text-align: left; font-size: 22px; margin: 8px 0 16px; font-weight: 700; }
        h2 { font-size: 16px; margin: 16px 0 8px; color: #333; }
        .btn { padding: 8px 12px; font-size: 13px; border-radius: 999px; background: var(--accent); color: var(--accent-contrast); border: 1px solid transparent; }
        .btn:hover { filter: brightness(0.96); }
        .btn-outline { background: #fff; color: var(--accent); border-color: var(--accent); }
        .todo-item { background: #fff; padding: 12px; margin: 8px 0; border-radius: 10px; border: 1px solid #eee; border-left: 4px solid var(--accent); }
        .todo-title { font-size: 16px; font-weight: 700; margin-bottom: 4px; color: #222; }
        .todo-description { color: #555; margin-bottom: 8px; }
        .todo-meta { font-size: 12px; color: #666; margin-bottom: 8px; }
        .todo-actions .btn { margin-right: 6px; padding: 6px 10px; font-size: 12px; }
        .priority-high { background-color: #ff5252; }
        .priority-medium { background-color: #42a5f5; }
        .priority-low { background-color: #66bb6a; }
        .tag-chip { background: #ede7f6; color: #4527a0; border-color: #d1c4e9; }
        .toast-container { position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index: 9999; }
        .toast { background: #333; color: #fff; padding: 8px 12px; border-radius: 10px; font-size: 13px; box-shadow: none; min-width: 0; max-width: 92vw; }
        .toast .toast-actions { display: none; }
        .filters-form { margin: 8px 0 0; display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
        .filters-form > div { flex: 1 1 180px; min-width: 180px; }
        .filters-actions { width: 100%; display: flex; gap: 8px; margin-top: 8px; }
        .toolbar { display:flex; justify-content:flex-end; margin: 8px 0 12px; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 9998; }
        .modal-sheet { background: #fff; border: 1px solid #eee; border-radius: 12px; width: min(720px, 92vw); max-height: 90vh; overflow: auto; padding: 16px; }
        .modal-header { display:flex; align-items:center; justify-content: space-between; margin-bottom: 8px; }
        .modal-header h3 { margin: 0; font-size: 16px; }
        .modal-close { background: transparent; border: none; font-size: 20px; line-height: 1; cursor: pointer; }
    </style>

/** ここまでCSS */

</head>


<body>
/**ここからHTML */
    <div class="container">
        <h1>ToDoアプリケーション</h1>
        
        <!-- メッセージ表示 -->
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <!-- ToDo追加フォーム -->
        <h2>新しいToDoを追加</h2>
        <form method="POST" id="create-form">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="title">タイトル *</label>
                <input type="text" id="title" name="title" required placeholder="ToDoのタイトルを入力してください">
            </div>
            
            <div class="form-group">
                <label for="description">詳細説明</label>
                <textarea id="description" name="description" placeholder="詳細説明を入力してください（任意）"></textarea>
            </div>
            
            <input type="hidden" id="calculated_reminder_date" name="calculated_reminder_date">
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="btn btn-outline" onclick="openOptions()">オプション</button>
                <button type="submit" class="btn">ToDoを追加</button>
            </div>
        </form>
        
        <!-- 検索/絞り込み（モーダル起動ボタン） -->
        <div class="toolbar">
            <button type="button" class="btn btn-outline" onclick="openFilters()">検索・絞り込み</button>
        </div>

        <!-- 検索/絞り込みモーダル -->
        <div id="filters-modal" class="modal-backdrop" onclick="backdropClose(event)" style="display:none;">
          <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="filters-title">
            <div class="modal-header">
              <h3 id="filters-title">検索・絞り込み</h3>
              <button type="button" class="modal-close" aria-label="閉じる" onclick="closeFilters()">×</button>
            </div>
            <form method="GET" class="filters-form">
                <div>
                    <label for="q">検索</label>
                    <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="タイトル・説明・タグを検索">
                </div>
                <div>
                    <label for="f_priority">優先度</label>
                    <select id="f_priority" name="priority">
                        <option value="">すべて</option>
                        <option value="high" <?php echo (($_GET['priority'] ?? '')==='high')?'selected':''; ?>>高</option>
                        <option value="medium" <?php echo (($_GET['priority'] ?? '')==='medium')?'selected':''; ?>>中</option>
                        <option value="low" <?php echo (($_GET['priority'] ?? '')==='low')?'selected':''; ?>>低</option>
                    </select>
                </div>
                <div>
                    <label for="tag">タグ</label>
                    <input type="text" id="tag" name="tag" value="<?php echo htmlspecialchars($_GET['tag'] ?? ''); ?>" placeholder="タグ名で絞り込み">
                </div>
                <div>
                    <label for="sort">並び順</label>
                    <select id="sort" name="sort">
                        <option value="created_desc" <?php echo (($_GET['sort'] ?? '')==='created_desc')?'selected':''; ?>>作成日時（新しい順）</option>
                        <option value="created_asc" <?php echo (($_GET['sort'] ?? '')==='created_asc')?'selected':''; ?>>作成日時（古い順）</option>
                        <option value="due_asc" <?php echo (($_GET['sort'] ?? '')==='due_asc')?'selected':''; ?>>締切（近い順）</option>
                        <option value="priority_desc" <?php echo (($_GET['sort'] ?? '')==='priority_desc')?'selected':''; ?>>優先度（高い順）</option>
                    </select>
                </div>
                <div class="filters-actions">
                    <button type="submit" class="btn">適用</button>
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="btn btn-outline" style="text-decoration: none; display: inline-block;">クリア</a>
                    <button type="button" class="btn btn-outline" onclick="closeFilters()">閉じる</button>
                </div>
            </form>
          </div>
        </div>

        <!-- ToDo一覧 -->
        <div class="todo-list">
            <h2>ToDo一覧</h2>
            
            <?php if (empty($todos)): ?>
                <p>まだToDoがありません。上記のフォームから追加してください。</p>
            <?php else: ?>
                <?php foreach ($todos as $todo): ?>
                    <div class="todo-item <?php echo $todo['status'] === 'completed' ? 'completed' : ''; ?>"
                         data-id="<?php echo (int)$todo['id']; ?>"
                         data-title="<?php echo htmlspecialchars($todo['title']); ?>"
                         data-priority="<?php echo htmlspecialchars($todo['priority'] ?? 'medium'); ?>"
                         <?php if (!empty($todo['reminder_date'])): ?>
                             data-reminder="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($todo['reminder_date']))); ?>"
                         <?php endif; ?>
                         <?php if (!empty($todo['due_date'])): ?>
                             data-due="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($todo['due_date']))); ?>"
                         <?php endif; ?>>
                        <div class="todo-title" id="title-<?php echo $todo['id']; ?>" onclick="editTitle(<?php echo $todo['id']; ?>)">
                            <?php echo htmlspecialchars($todo['title']); ?>
                        </div>
                        
                        <?php if (!empty($todo['description'])): ?>
                            <div class="todo-description"><?php echo htmlspecialchars($todo['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="todo-meta">
                            作成日: <?php echo date('Y年m月d日 H:i', strtotime($todo['created_at'])); ?>
                            | ステータス: <span class="status-<?php echo $todo['status']; ?>">
                                <?php echo $todo['status'] === 'pending' ? '未完了' : '完了'; ?>
                            </span>
                            <?php if (!empty($todo['priority'])): ?>
                                | 優先度: <span class="priority-badge priority-<?php echo htmlspecialchars($todo['priority']); ?>"><?php echo $todo['priority']==='high'?'高':($todo['priority']==='low'?'低':'中'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($todo['reminder_date'])): ?>
                                | リマインド: <?php echo date('Y年m月d日 H:i', strtotime($todo['reminder_date'])); ?>
                            <?php endif; ?>
                            <?php if (!empty($todo['due_date'])): ?>
                                | 締切: <span class="due-display"><?php echo date('Y年m月d日 H:i', strtotime($todo['due_date'])); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($todo['tags'])): ?>
                            <div class="todo-tags" style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px;">
                                <?php foreach (array_filter(array_map('trim', explode(',', $todo['tags']))) as $tag): ?>
                                    <a class="tag-chip" href="?<?php 
                                        $qs = $_GET; $qs['tag'] = $tag; echo htmlspecialchars(http_build_query($qs));
                                    ?>" style="text-decoration:none;">#<?php echo htmlspecialchars($tag); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="todo-actions">
                            <!-- ステータス切り替え -->
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $todo['id']; ?>">
                                <button type="submit" class="btn btn-warning">
                                    <?php echo $todo['status'] === 'pending' ? '完了にする' : '未完了にする'; ?>
                                </button>
                            </form>
                            
                            <!-- 編集（モーダルを開く） -->
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($todo)); ?>)" class="btn">編集</button>
                            
                            <!-- 削除 -->
                            <form method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $todo['id']; ?>">
                                <button type="submit" class="btn btn-danger">削除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 画面内トースト通知のコンテナ -->
    <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    
    <!-- 作成オプション モーダル -->
    <div id="options-modal" class="modal-backdrop" onclick="backdropCloseOptions(event)" style="display:none;">
      <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="options-title">
        <div class="modal-header">
          <h3 id="options-title">作成オプション</h3>
          <button type="button" class="modal-close" aria-label="閉じる" onclick="closeOptions()">×</button>
        </div>
        <div class="filters-form">
            <div>
                <label for="priority">優先度（任意）</label>
                <select id="priority" name="priority" form="create-form">
                    <option value="high">高</option>
                    <option value="medium" selected>中</option>
                    <option value="low">低</option>
                </select>
            </div>
            
            <div>
                <label for="due_date">締切日（任意）</label>
                <input type="datetime-local" id="due_date" name="due_date" form="create-form">
                <small>リマインド未指定の場合、締切時刻で通知します</small>
            </div>
            
            <div>
                <label for="tags">タグ（カンマ区切り、任意）</label>
                <input type="text" id="tags" name="tags" placeholder="仕事, プライベート, 買い物 など" form="create-form">
            </div>
            
            <div>
                <label for="reminder_type">リマインド設定（任意）</label>
                <select id="reminder_type" name="reminder_type" onchange="toggleReminderInput()" form="create-form">
                    <option value="">リマインドなし</option>
                    <option value="relative">相対時間（何時間何分後）</option>
                    <option value="absolute">絶対時間（日時指定）</option>
                </select>
            </div>
            
            <div id="relative_reminder" style="display: none;">
                <label>何時間何分後にリマインド</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" id="reminder_hours" name="reminder_hours" min="0" max="168" value="1" style="width: 80px;" form="create-form">
                    <span>時間</span>
                    <input type="number" id="reminder_minutes" name="reminder_minutes" min="0" max="59" value="0" style="width: 80px;" form="create-form">
                    <span>分後</span>
                </div>
            </div>
            
            <div id="absolute_reminder" style="display: none;">
                <label for="reminder_date">リマインド日時</label>
                <input type="datetime-local" id="reminder_date" name="reminder_date" form="create-form">
            </div>
            
            <div class="filters-actions">
                <button type="button" class="btn btn-outline" onclick="closeOptions()">閉じる</button>
            </div>
        </div>
      </div>
    </div>
    
    <!-- 編集モーダル（全項目一括編集） -->
    <div id="edit-modal" class="modal-backdrop" onclick="backdropCloseEdit(event)" style="display:none;">
      <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="edit-title">
        <div class="modal-header">
          <h3 id="edit-title">ToDoを編集</h3>
          <button type="button" class="modal-close" aria-label="閉じる" onclick="closeEditModal()">×</button>
        </div>
        <form method="POST" id="edit-form" class="filters-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="calculated_reminder_date" id="edit_calculated_reminder_date">

            <div style="flex-basis:100%;">
                <label for="edit_title">タイトル *</label>
                <input type="text" id="edit_title" name="title" required>
            </div>
            <div style="flex-basis:100%;">
                <label for="edit_description">詳細説明</label>
                <textarea id="edit_description" name="description" rows="3"></textarea>
            </div>
            <div>
                <label for="edit_priority">優先度</label>
                <select id="edit_priority" name="priority">
                    <option value="high">高</option>
                    <option value="medium">中</option>
                    <option value="low">低</option>
                </select>
            </div>
            <div>
                <label for="edit_due_date">締切日</label>
                <input type="datetime-local" id="edit_due_date" name="due_date">
            </div>
            <div style="flex-basis:100%;">
                <label for="edit_tags">タグ（カンマ区切り）</label>
                <input type="text" id="edit_tags" name="tags" placeholder="仕事, プライベート など">
            </div>
            <div>
                <label for="edit_reminder_type">リマインド設定</label>
                <select id="edit_reminder_type" name="reminder_type" onchange="toggleEditReminderInput()">
                    <option value="">リマインドなし</option>
                    <option value="relative">相対時間（何時間何分後）</option>
                    <option value="absolute">絶対時間（日時指定）</option>
                </select>
            </div>
            <div id="edit_relative_reminder" style="display:none;">
                <label>何時間何分後にリマインド</label>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="number" id="edit_reminder_hours" min="0" max="168" value="1" style="width:80px;">
                    <span>時間</span>
                    <input type="number" id="edit_reminder_minutes" min="0" max="59" value="0" style="width:80px;">
                    <span>分後</span>
                </div>
            </div>
            <div id="edit_absolute_reminder" style="display:none;">
                <label for="edit_reminder_date">リマインド日時</label>
                <input type="datetime-local" id="edit_reminder_date">
            </div>
            <div class="filters-actions">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">閉じる</button>
                <button type="submit" class="btn">保存</button>
            </div>
        </form>
      </div>
    </div>

/** ここまでHTML */

/** ここからJS */

    <script>
        // インライン編集機能
        function editTitle(todoId) {
            const titleElement = document.getElementById('title-' + todoId);
            const currentTitle = titleElement.textContent.trim();
            
            // 入力フィールドを作成
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentTitle;
            input.style.width = '100%';
            input.style.padding = '5px';
            input.style.border = '2px solid #4CAF50';
            input.style.borderRadius = '3px';
            
            // 元のテキストを入力フィールドに置き換え
            titleElement.innerHTML = '';
            titleElement.appendChild(input);
            input.focus();
            input.select();
            
            // 保存処理
            function saveTitle() {
                const newTitle = input.value.trim();
                if (newTitle && newTitle !== currentTitle) {
                    // フォームを作成して送信
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="${todoId}">
                        <input type="hidden" name="title" value="${newTitle}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                } else {
                    // 変更がない場合は元に戻す
                    titleElement.textContent = currentTitle;
                }
            }
            
            // キャンセル処理
            function cancelEdit() {
                titleElement.textContent = currentTitle;
            }
            
            // イベントリスナーを追加
            input.addEventListener('blur', saveTitle);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    saveTitle();
                } else if (e.key === 'Escape') {
                    cancelEdit();
                }
            });
        }
        
        // 編集機能（詳細編集版）
        function editTodo(todo) {
            const newTitle = prompt('新しいタイトルを入力してください:', todo.title);
            if (!newTitle || newTitle === todo.title) return;
            const newDescription = prompt('新しい詳細説明を入力してください:', todo.description || '');
            const newReminderDate = prompt('リマインド日時を入力してください（YYYY-MM-DDTHH:MM形式、空欄で削除）:', (todo.reminder_date || ''));
            const currentPriority = (todo.priority || 'medium');
            const newPriority = prompt('優先度を入力してください（high, medium, low）:', currentPriority) || currentPriority;
            const newDue = prompt('締切日時を入力してください（YYYY-MM-DDTHH:MM形式、空欄で削除）:', (todo.due_date || ''));
            const newTags = prompt('タグ（カンマ区切り）を入力してください（空欄で削除）:', (todo.tags || ''));
            
            // フォームを作成して送信（未入力フィールドも明示的に送って更新を可能に）
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="${todo.id}">
                <input type="hidden" name="title" value="${newTitle}">
                <input type="hidden" name="description" value="${newDescription ?? ''}">
                <input type="hidden" name="reminder_date" value="${newReminderDate ?? ''}">
                <input type="hidden" name="priority" value="${newPriority}">
                <input type="hidden" name="due_date" value="${newDue ?? ''}">
                <input type="hidden" name="tags" value="${newTags ?? ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // プッシュ通知の許可を要求
        function requestNotificationPermission() {
            if ('Notification' in window) {
                if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function(permission) {
                        if (permission === 'granted') {
                            console.log('通知許可が得られました');
                        }
                    });
                }
            }
        }
        
        // 画面内トースト通知
        function showToast(message, opts = {}) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <span class="toast-title">リマインド</span>
                <span class="toast-body">${message}</span>
                <div class="toast-actions">
                    <button class="toast-btn" type="button">OK</button>
                </div>
            `;
            const remove = () => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            };
            toast.querySelector('.toast-btn').addEventListener('click', remove);
            container.appendChild(toast);
            setTimeout(remove, opts.duration || 5000);
        }
        
        // 通知API（許可があれば）+ フォールバック
        function sendNotification(title, body) {
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    const notification = new Notification(title, {
                        body: body,
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        tag: 'todo-reminder'
                    });
                    setTimeout(() => notification.close(), 7000);
                    return;
                } catch (e) {
                    // 続行してトースト
                }
            }
            // フォールバック
            showToast(body);
        }
        
        // リマインド通知機能（即時+スケジュール）
        function triggerReminder(element) {
            if (element.dataset.notified === '1') return; // 二重通知防止
            const title = element.dataset.title || 'ToDo';
            sendNotification('ToDoリマインド', `「${title}」のリマインド時間です`);
            element.style.backgroundColor = '#fff3cd';
            element.style.borderLeft = '4px solid #ffc107';
            element.dataset.notified = '1';
        }
        function scheduleReminderForElement(element) {
            const whenStr = element.dataset.reminder || element.dataset.due; // リマインドが無ければ締切で
            if (!whenStr) return;
            const when = new Date(whenStr);
            const now = new Date();
            const diff = when.getTime() - now.getTime();
            if (diff <= 0) {
                // 期限切れは即時通知（未通知なら）
                triggerReminder(element);
                return;
            }
            // 既存タイマーがあれば消す
            if (element._reminderTimer) {
                clearTimeout(element._reminderTimer);
            }
            // 最大遅延の範囲内でセット（約24日まで）
            element._reminderTimer = setTimeout(() => {
                triggerReminder(element);
            }, Math.min(diff, 0x7FFFFFFF));
        }
        function scheduleAllReminders() {
            document.querySelectorAll('.todo-item').forEach(scheduleReminderForElement);
        }
        function checkRemindersNow() {
            document.querySelectorAll('.todo-item').forEach(el => {
                const whenStr = el.dataset.reminder || el.dataset.due;
                if (!whenStr) return;
                const when = new Date(whenStr);
                if (when.getTime() <= Date.now()) triggerReminder(el);
            });
        }

        // 締切に応じたハイライト（期限超過/24時間以内）
        function highlightDueStatus() {
            const now = Date.now();
            const oneDay = 24 * 60 * 60 * 1000;
            document.querySelectorAll('.todo-item').forEach(el => {
                const dueStr = el.dataset.due;
                if (!dueStr) return;
                const due = new Date(dueStr).getTime();
                el.classList.remove('overdue', 'due-soon');
                if (due < now) {
                    el.classList.add('overdue');
                } else if (due - now <= oneDay) {
                    el.classList.add('due-soon');
                }
            });
        }
        
        // リマインド設定の切り替え
        function toggleReminderInput() {
            const reminderType = document.getElementById('reminder_type').value;
            const relativeDiv = document.getElementById('relative_reminder');
            const absoluteDiv = document.getElementById('absolute_reminder');
            
            relativeDiv.style.display = reminderType === 'relative' ? 'block' : 'none';
            absoluteDiv.style.display = reminderType === 'absolute' ? 'block' : 'none';
        }
        
        // 相対時間から絶対時間を計算（ローカルタイムで保存）
        function calculateReminderDate() {
            const reminderType = document.getElementById('reminder_type').value;
            const calculatedInput = document.getElementById('calculated_reminder_date');
            
            if (reminderType === 'relative') {
                const hours = parseInt(document.getElementById('reminder_hours').value) || 0;
                const minutes = parseInt(document.getElementById('reminder_minutes').value) || 0;
                
                // 現在時刻に時間と分を追加（ローカル）
                const reminderTime = new Date(Date.now() + (hours * 60 + minutes) * 60 * 1000);
                calculatedInput.value = formatLocalDateTime(reminderTime);
            } else if (reminderType === 'absolute') {
                calculatedInput.value = document.getElementById('reminder_date').value;
            } else {
                calculatedInput.value = '';
            }
        }
        function formatLocalDateTime(d) {
            const pad = (n) => String(n).padStart(2, '0');
            const yyyy = d.getFullYear();
            const mm = pad(d.getMonth() + 1);
            const dd = pad(d.getDate());
            const hh = pad(d.getHours());
            const min = pad(d.getMinutes());
            return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
        }
        
        // フォーム送信時にリマインド日時を計算
        document.addEventListener('DOMContentLoaded', function() {
            requestNotificationPermission();
            // 起動時に即時チェック＆スケジュール
            checkRemindersNow();
            scheduleAllReminders();
            highlightDueStatus();
            // モーダル: Escで閉じる（検索/絞り込み・作成オプション・編集）
            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') { closeFilters(); closeOptions(); closeEditModal(); }
            });
            
            // フォーム送信時にリマインド日時を計算
            const form = document.getElementById('create-form') || document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    calculateReminderDate();
                });
            }
            
            // 相対時間入力時にリアルタイム計算
            const hoursInput = document.getElementById('reminder_hours');
            const minutesInput = document.getElementById('reminder_minutes');
            if (hoursInput && minutesInput) {
                hoursInput.addEventListener('change', calculateReminderDate);
                minutesInput.addEventListener('change', calculateReminderDate);
            }
        });
        
        // 検索/絞り込みモーダルの開閉
        function openFilters(){
            const modal = document.getElementById('filters-modal');
            if (modal) modal.style.display = 'flex';
        }
        function closeFilters(){
            const modal = document.getElementById('filters-modal');
            if (modal) modal.style.display = 'none';
        }
        function backdropClose(e){
            if (e.target && e.target.id === 'filters-modal') closeFilters();
        }
        
        // 作成オプションモーダルの開閉
        function openOptions(){
            const modal = document.getElementById('options-modal');
            if (modal) modal.style.display = 'flex';
        }
        function closeOptions(){
            const modal = document.getElementById('options-modal');
            if (modal) modal.style.display = 'none';
        }
        function backdropCloseOptions(e){
            if (e.target && e.target.id === 'options-modal') closeOptions();
        }

        // 編集モーダルの開閉・入力制御
        function openEditModal(todo){
            if (!todo) return;
            // 必須
            document.getElementById('edit_id').value = todo.id;
            document.getElementById('edit_title').value = (todo.title || '').trim();
            document.getElementById('edit_description').value = (todo.description || '');
            // 優先度
            document.getElementById('edit_priority').value = (todo.priority || 'medium');
            // 締切
            document.getElementById('edit_due_date').value = toInputValue(todo.due_date || '');
            // タグ
            document.getElementById('edit_tags').value = (todo.tags || '');
            // リマインド
            const typeSel = document.getElementById('edit_reminder_type');
            const absInput = document.getElementById('edit_reminder_date');
            if (todo.reminder_date) {
                typeSel.value = 'absolute';
                absInput.value = toInputValue(todo.reminder_date);
            } else {
                typeSel.value = '';
                absInput.value = '';
            }
            toggleEditReminderInput();
            document.getElementById('edit_calculated_reminder_date').value = '';
            const modal = document.getElementById('edit-modal');
            if (modal) modal.style.display = 'flex';
        }
        function closeEditModal(){
            const modal = document.getElementById('edit-modal');
            if (modal) modal.style.display = 'none';
        }
        function backdropCloseEdit(e){
            if (e.target && e.target.id === 'edit-modal') closeEditModal();
        }
        function toggleEditReminderInput(){
            const type = document.getElementById('edit_reminder_type').value;
            document.getElementById('edit_relative_reminder').style.display = (type === 'relative') ? 'block' : 'none';
            document.getElementById('edit_absolute_reminder').style.display = (type === 'absolute') ? 'block' : 'none';
        }
        function calculateEditReminderDate(){
            const type = document.getElementById('edit_reminder_type').value;
            const out = document.getElementById('edit_calculated_reminder_date');
            if (type === 'relative') {
                const h = parseInt(document.getElementById('edit_reminder_hours').value) || 0;
                const m = parseInt(document.getElementById('edit_reminder_minutes').value) || 0;
                const d = new Date(Date.now() + (h * 60 + m) * 60 * 1000);
                out.value = formatLocalDateTime(d);
            } else if (type === 'absolute') {
                out.value = document.getElementById('edit_reminder_date').value || '';
            } else {
                out.value = '';
            }
        }
        function toInputValue(s){
            if (!s) return '';
            const t = String(s).replace(' ', 'T');
            return t.substring(0,16);
        }
        // 編集フォーム送信時にリマインド日時を計算
        (function(){
            const ef = document.getElementById('edit-form');
            if (!ef) return;
            ef.addEventListener('submit', function(){
                calculateEditReminderDate();
            });
        })();
    </script>
</body>
</html>

