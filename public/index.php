<?php

require_once __DIR__ . '/../config/database.php';

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
    ORDER BY tickets.created_at DESC
";

$statement = $pdo->query($sql);
$tickets = $statement->fetchAll();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Service Desk</title>
</head>
<body>
<h1><a href="create-ticket.php">перейти на страницу создания заявки</a></h1>

<h1>Все заявки</h1>

<table border="1" cellpadding="8">
    <thead>
    <tr>
        <th>ID</th>
        <th>Тема</th>
        <th>Пользователь</th>
        <th>Категория</th>
        <th>Статус</th>
        <th>Дата</th>
    </tr>
    </thead>

    <tbody>
    <?php foreach ($tickets as $ticket): ?>
        <tr>
            <td><?= e((string) $ticket['id']) ?></td>
            <td><?= e($ticket['subject']) ?></td>
            <td><?= e($ticket['user_name']) ?></td>
            <td><?= e($ticket['category_name']) ?></td>
            <td><?= e($ticket['status']) ?></td>
            <td><?= e($ticket['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
