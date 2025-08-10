<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['content'])) {
    $content = $_POST['content'] ?? '';
    $color = $_POST['color'] ?? '#ffffff';
    $tags = $_POST['tags'] ?? '';
    $is_private = isset($_POST['is_private']) ? 1 : 0;

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

    try {
        $stmt = $pdo->prepare("INSERT INTO sns_posts (user_id, content, color, image_path, tags, is_private) 
                               VALUES (:user_id, :content, :color, :image, :tags, :is_private)");
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->bindValue(':color', $color, PDO::PARAM_STR);
        $stmt->bindValue(':image', $imagePath, PDO::PARAM_STR);
        $stmt->bindValue(':tags', $tags, PDO::PARAM_STR);
        $stmt->bindValue(':is_private', $is_private, PDO::PARAM_INT);
        $stmt->execute();

        header("Location: home.php");
        exit();
    } catch (PDOException $e) {
        echo "投稿エラー: " . $e->getMessage();
    }
}
?>
