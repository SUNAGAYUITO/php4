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

$message = "";
$message_type = "";
$redirect_group_id = 0; // リダイレクト先のグループIDを初期化

// グループ投稿削除処理
if (isset($_GET['post_id']) && isset($_GET['group_id'])) {
    $post_id = intval($_GET['post_id']);
    $group_id = intval($_GET['group_id']);
    $redirect_group_id = $group_id; // リダイレクト先を設定

    try {
        // 自分の投稿であり、かつ指定されたグループに属する投稿かチェックして論理削除
        $stmt = $pdo->prepare("UPDATE sns_posts SET is_deleted = 1 WHERE id = :post_id AND user_id = :user_id AND group_id = :group_id");
        $stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $message = "投稿をゴミ箱に移動しました。";
            $message_type = "success";
        } else {
            $message = "投稿の削除に失敗しました。権限がないか、投稿が見つかりません。";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "投稿の削除中にエラーが発生しました: " . $e->getMessage();
        $message_type = "error";
    }
} else {
    $message = "不正なリクエストです。";
    $message_type = "error";
}

// グループ詳細ページへリダイレクト
header("Location: group_detail.php?group_id=" . $redirect_group_id . "&message=" . urlencode($message) . "&type=" . $message_type);
exit();
?>
