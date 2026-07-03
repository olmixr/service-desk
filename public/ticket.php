<?php
require_once __DIR__ . '/../config/database.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$id = $_GET['id'] ?? '';

$sql = "
    SELECT
        tickets.id,
        tickets.subject,
        tickets.description,
        tickets.status,
        tickets.created_at,
        users.name AS user_name,
        categories.name AS category_name
    FROM tickets
    INNER JOIN users ON tickets.user_id = users.id
    INNER JOIN categories ON tickets.category_id = categories.id
    WHERE tickets.id = :id
";

$statement = $pdo->prepare($sql);
$statement->execute([
    'id' => $id,
]);
$ticket = $statement->fetch();

if ($ticket === false) {
    echo 'Заявка не найдена.';
    exit;
}

?>


<!DOCTYPE html>

<html lang="ru">
<head>
<meta charset="UTF-8">
<title>показать заявку</title>
</head>


<body>
<h1>Заявка #<?= e((string) $ticket['id']) ?></h1>

<p>Тема: <?= e($ticket['subject']) ?></p>
<p>Пользователь: <?= e($ticket['user_name']) ?></p>
<p>Категория: <?= e($ticket['category_name']) ?></p>
<p>Статус: <?= e($ticket['status']) ?></p>
<p>Дата: <?= e($ticket['created_at']) ?></p>
<p>Описание: <?= e($ticket['description']) ?></p>

<p><a href="index.php">Назад к списку</a></p>

</body>
</html>