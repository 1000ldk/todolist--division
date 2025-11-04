<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ToDoアプリケーション</title>
    <link rel="stylesheet" href="./todo5.css">
    <script src="./todo5.js" defer></script>
    <!-- 外部CSS/JSへ分割 -->
  </head>
  <body>
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
  </body>
  </html>
