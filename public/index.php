<?php
session_start();
require_once __DIR__ . '/../config/database.php';
    if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

    if($_SESSION['user_role'] !== 'admin'){
        header('Location: my-tickets.php');
    }

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
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="topbar">
    <div>
        <strong>Service Desk</strong>
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="user-info">
                <?= e($_SESSION['user_name']) ?> (<?= e($_SESSION['user_role']) ?>)
            </span>
        <?php endif; ?>
    </div>

    <nav class="nav-links">
        <a href="index.php">Все заявки</a>
        <a href="my-tickets.php">Мои заявки</a>
        <a href="create-ticket.php">Создать заявку</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </nav>
</header>

<h1>Все заявки</h1>

<table>
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
        <td>
             <a href="ticket.php?id=<?= e((string) $ticket['id']) ?>">
             <?= e((string) $ticket['id']) ?>
                </a>
        </td>
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
