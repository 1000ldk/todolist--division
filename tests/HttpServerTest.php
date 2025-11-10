<?php
use PHPUnit\Framework\TestCase;

class HttpServerTest extends TestCase
{
    private static $process;
    
    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        
        self::$process = proc_open(
            "php -S localhost:8000 -t \"$docRoot\"",
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w']
            ],
            $pipes,
            $docRoot
        );
        sleep(2);
    }
    
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }
    
    public function testTodoPageLoads()
    {
        $html = @file_get_contents('http://localhost:8000/todo5.php');
        
        $this->assertNotFalse($html, 'ページの読み込みに失敗しました');
    }
    
    public function testTodoPageHasTitle()
    {
        $html = @file_get_contents('http://localhost:8000/todo5.php');
        
        $this->assertStringContainsString('ToDoアプリケーション', $html);
    }
    
    public function testCreateTodo()
    {
        // テスト用のToDoデータ
        $postData = http_build_query([
            'action' => 'create',
            'title' => 'テストToDo',
            'description' => 'これはテストです',
            'priority' => 'high'
        ]);
        
        // POSTリクエストを送信
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData
            ]
        ]);
        
        $html = @file_get_contents('http://localhost:8000/todo5.php', false, $context);
        
        $this->assertNotFalse($html, 'ToDo作成リクエストに失敗しました');
        
        // 作成したToDoが表示されているか確認
        $this->assertStringContainsString('テストToDo', $html);
        $this->assertStringContainsString('これはテストです', $html);
    }
}