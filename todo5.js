// Extracted from todo5.php - JavaScript

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
