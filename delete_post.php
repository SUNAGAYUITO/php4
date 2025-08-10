<?php
session_start();
require_once("db_connect.php");

if (!isset($_POST['post_id']) || !isset($_SESSION['user_id'])) {
    exit("不正なアクセスです");
}

$post_id = (int)$_POST['post_id'];
$user_id = $_SESSION['user_id'];

$sql = "UPDATE sns_posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $post_id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

header("Location: profile.php");
exit();
