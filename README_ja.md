# TODO5 簡易セットアップ＆表示ガイド

このリポジトリの ToDo アプリ（todo5.php）をローカル/サーバで表示するための最短手順をまとめたドキュメントです。

## 対象
- Windows 10/11 + Visual Studio Code
- PHP 8.x 以上（pdo_sqlite など必要な拡張を有効化）
- Web サーバなしでも PHP 内蔵サーバで動作可

## 主要ファイル
- エントリーポイント: `todo5.php`
- ロジック: `todo5_logic.php`
- ビュー: `todo5_view.php`
- フロントエンド: `todo5.css`, `todo5.js`
- DB 設定: `config/database.php`（既定では SQLite を想定。必要に応じて接続先を編集）

## 1) 最速の起動（PHP 内蔵サーバ）
1. VS Code のターミナルを開く（Ctrl+J）
2. プロジェクトへ移動
   ```
   cd C:\project\todolist--division
   ```
3. PHP 内蔵サーバを起動
   ```
   php -S localhost:8000
   ```
4. ブラウザで表示
   - http://localhost:8000/todo5.php
   - （`index.html` がある場合は http://localhost:8000/ からアクセスしても可）

ポートを変えたい場合は `php -S localhost:8080` のように指定します。

## 2) Apache（XAMPP 等）で公開（任意）
- フォルダをドキュメントルート配下へ配置、または仮想ホストを設定
- PHP が有効で、`pdo_sqlite` 等の必要拡張が読み込まれていることを確認
- DB が SQLite の場合は、DB ファイルの保存先フォルダに Web サーバの書き込み権限を付与

仮想ホスト設定例（httpd-vhosts.conf）
```
<VirtualHost *:80>
    ServerName todo.local
    DocumentRoot "C:/project/todolist--division"
    <Directory "C:/project/todolist--division">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
- hosts に `127.0.0.1 todo.local` を追加し、http://todo.local/ へアクセス

## 3) データベース設定
- `config/database.php` を開き、接続先（SQLite ならファイルパス、他 DB なら DSN/ユーザー）を確認・変更
- SQLite を使う場合:
  - DB ファイルの置き場所が存在すること
  - そのフォルダに書き込み権限があること（エクスプローラー → プロパティ → セキュリティ）

## 4) トラブルシュート
- 404/アクセス不可
  - 内蔵サーバが起動中か、ポート競合（既に使用中）でないか確認
- 500/PHP エラー
  - 内蔵サーバであればターミナルにエラーログが出ます
  - 必要なら `php.ini` で `display_errors=On` を有効化（開発環境のみ）
- SQLite ドライバが見つからない
  - `php.ini` で `extension=pdo_sqlite` を有効化
- DB 書き込み不可
  - DB ファイル/フォルダの書き込み権限を付与

## 5) テストの実行（任意）
```
cd C:\project\todolist--division
vendor\bin\phpunit.bat
```

## 補足
- `todo5.php` を直接開いて表示できます
- 実行時に Composer は不要（開発用ツールは `vendor/` に同梱済み）