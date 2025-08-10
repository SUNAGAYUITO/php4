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

// 投稿処理
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['content']) && isset($_POST['group_id'])) {
    $content = $_POST['content'] ?? '';
    $color = $_POST['color'] ?? '#fef7cd';
    $tags = $_POST['tags'] ?? '';
    $group_id = intval($_POST['group_id']); // グループIDを取得
    $is_private = 0; // グループ投稿は通常公開（グループ内）

    // 画像アップロード処理
    $imagePath = null;
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $imagePath = $targetFile;
        }
    }

    if (!empty($content) && $group_id > 0) {
        try {
            // ユーザーがそのグループのメンバーであることを確認
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_group_members WHERE group_id = :group_id AND user_id = :user_id");
            $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $is_member = $stmt->fetchColumn() > 0;

            if ($is_member) {
                $stmt = $pdo->prepare("INSERT INTO sns_posts (user_id, group_id, content, color, image_path, tags, is_private) 
                                       VALUES (:user_id, :group_id, :content, :color, :image, :tags, :is_private)");
                $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT); // group_id をバインド
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->bindValue(':color', $color, PDO::PARAM_STR);
                $stmt->bindValue(':image', $imagePath, PDO::PARAM_STR);
                $stmt->bindValue(':tags', $tags, PDO::PARAM_STR);
                $stmt->bindValue(':is_private', $is_private, PDO::PARAM_INT);
                $stmt->execute();
                $message = "グループに投稿しました！";
                $message_type = "success";
            } else {
                $message = "このグループに投稿する権限がありません。";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "投稿エラー: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "投稿内容またはグループが不正です。";
        $message_type = "error";
    }
} else {
    $message = "不正なリクエストです。";
    $message_type = "error";
}

// グループ詳細ページへリダイレクト
header("Location: group_detail.php?group_id=" . $group_id . "&message=" . urlencode($message) . "&type=" . $message_type);
exit();
?>
