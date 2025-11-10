<?php
use PHPUnit\Framework\TestCase;

class HttpServerTest extends TestCase
{
    private static $process;
    
    public static function setUpBeforeClass(): void
    {
        // プロジェクトルートをドキュメントルートとして指定
        $docRoot = __DIR__ . '/..';
        
        self::$process = proc_open(
            "php -S localhost:8000 -t \"$docRoot\"",
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w']
            ],
            $pipes,
            $docRoot  // 作業ディレクトリも指定
        );
        sleep(1);
    }
    
    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$process);
    }
    
    public function testTodoPageLoads()
    {
        $html = @file_get_contents('http://localhost:8000/todo5.php');
        
        // HTMLが取得できたか確認
        $this->assertNotFalse($html, 'ページの読み込みに失敗しました');
        
        // エラーメッセージが含まれていないか確認
        $this->assertStringNotContainsString('Fatal error', $html, 'PHPエラーが発生しています');
        $this->assertStringNotContainsString('Warning', $html, 'PHPワーニングが発生しています');
        
        // 正常な内容が含まれているか確認
        $this->assertStringContainsString('ToDoアプリケーション', $html);
    }
    
    public function testTodoPageHasTitle()
    {
        $html = @file_get_contents('http://localhost:8000/todo5.php');
        $this->assertStringContainsString('<title>ToDoアプリケーション</title>', $html);
    }
}