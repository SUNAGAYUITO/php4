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

// コメント削除処理
if (isset($_GET['comment_id'])) {
    $comment_id = intval($_GET['comment_id']);
    
    try {
        // 自分のコメントかチェックして削除
        $stmt = $pdo->prepare("UPDATE sns_comments SET is_deleted = 1 WHERE id = :comment_id AND user_id = :user_id");
        $stmt->bindValue(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['comment_success'] = "コメントを削除しました。";
        } else {
            $_SESSION['comment_error'] = "コメントの削除に失敗しました。";
        }
    } catch (PDOException $e) {
        $_SESSION['comment_error'] = "コメントの削除に失敗しました: " . $e->getMessage();
    }
}

// リダイレクト先を決定
$redirect_url = $_GET['redirect_url'] ?? 'home.php';
header("Location: " . $redirect_url);
exit();
?>
