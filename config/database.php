<?php
// SQLite 接続（macOS は ~/Library/Application Support/Todo5/todo.sqlite、その他はプロジェクト直下）
$home = getenv('HOME') ?: '';
$isDarwin = (PHP_OS_FAMILY === 'Darwin');
if ($isDarwin && $home) {
    $dbDir = $home . '/Library/Application Support/Todo5';
    if (!is_dir($dbDir)) { @mkdir($dbDir, 0777, true); }
    $dbPath = $dbDir . '/todo.sqlite';
} else {
    $dbPath = __DIR__ . '/../todo.sqlite';
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// テーブル作成（存在しなければ）
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS todos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    reminder_date TEXT NULL,
    priority TEXT NOT NULL DEFAULT 'medium',
    due_date TEXT NULL,
    tags TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);

// 既存DBに不足カラムがあれば追加
function sqlite_column_exists(PDO $pdo, string $column): bool {
    $cols = $pdo->query("PRAGMA table_info(todos)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (!empty($c['name']) && $c['name'] === $column) return true;
    }
    return false;
}
$addCol = function(string $name, string $def) use ($pdo) {
    if (!sqlite_column_exists($pdo, $name)) {
        $pdo->exec("ALTER TABLE todos ADD COLUMN $name $def");
    }
};
$addCol('priority', "TEXT NOT NULL DEFAULT 'medium'");
$addCol('due_date', "TEXT NULL");
$addCol('tags', "TEXT NULL");
$addCol('reminder_date', "TEXT NULL");
$addCol('status', "TEXT NOT NULL DEFAULT 'pending'");
$addCol('created_at', "TEXT NOT NULL DEFAULT (datetime('now','localtime'))");