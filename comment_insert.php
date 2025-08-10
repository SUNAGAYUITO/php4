<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB接続


try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

// コメント投稿処理
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['content']) && isset($_POST['post_id'])) {
    $content = trim($_POST['content']);
    $post_id = intval($_POST['post_id']);
    
    if (!empty($content) && $post_id > 0) {
        try {
            // 投稿が存在し、閲覧権限があるかチェック
            $stmt = $pdo->prepare("SELECT * FROM sns_posts WHERE id = :post_id AND (is_private = 0 OR user_id = :user_id) AND is_deleted = 0");
            $stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                // コメントを挿入
                $stmt = $pdo->prepare("INSERT INTO sns_comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)");
                $stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->execute();
                
                // 成功メッセージをセッションに保存
                $_SESSION['comment_success'] = "コメントを投稿しました！";
            } else {
                $_SESSION['comment_error'] = "投稿が見つからないか、コメント権限がありません。";
            }
        } catch (PDOException $e) {
            $_SESSION['comment_error'] = "コメントの投稿に失敗しました: " . $e->getMessage();
        }
    } else {
        $_SESSION['comment_error'] = "コメント内容を入力してください。";
    }
}

// リダイレクト先を決定
$redirect_url = $_POST['redirect_url'] ?? 'home.php';
header("Location: " . $redirect_url);
exit();
?>
