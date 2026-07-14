<?php
session_start();

require_once __DIR__ . '/../config/database.php';
$statusLabels = require __DIR__ . '/../config/statuses.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$errorsMessage = [];
$subject = '';
$categoryId = '';
$description = '';
$message = '';
$formType = $_POST['form_type'] ?? '';


$categories = $pdo->query('SELECT id , name FROM categories ORDER BY name')->fetchAll();
$categoryIds = array_map('strval', array_column($categories, 'id'));

if ($formType === 'add_ticket') {
    $subject = trim($_POST['subject'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($subject === '') {
        $errors[] = 'Введите тему заявки.';
    }

    if ($categoryId === '') {
        $errors[] = 'Выберите категорию.';
    } elseif (
        !is_string($categoryId)
        || !ctype_digit($categoryId)
        || (int) $categoryId < 1
    ) {
        $errors[] = 'Некорректная категория.';
    } elseif (!in_array($categoryId, $categoryIds, true)) {
        $errors[] = 'Выбранная категория не существует.';
    }


    if ($description === '') {
        $errors[] = 'Введите описание заявки.';
    }

    if ($errors === []) {
        $statement = $pdo->prepare(
            'INSERT INTO tickets (user_id, category_id, subject, description)
             VALUES (:user_id, :category_id, :subject, :description)'
        );

        $statement->execute([
            'user_id' => $_SESSION['user_id'],
            'category_id' => $categoryId,
            'subject' => $subject,
            'description' => $description,
        ]);

        header('Location: my-tickets.php');
        exit;
    }
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
    WHERE tickets.user_id = :user_id
    ORDER BY tickets.created_at DESC
";

$statement = $pdo->prepare($sql);
$statement->execute([
    'user_id' => $_SESSION['user_id'],
]);

$tickets = $statement->fetchAll();
$selectedTicket = null;
$selectedTicketError = '';
$selectedTicketId = 0;
$comments = [];

if ($formType === 'add_comment') {
    $rawTicketId = $_POST['ticket_id'] ?? '';
} else {
    $rawTicketId = $_GET['ticket_id'] ?? null;
}

if ($rawTicketId === null) {
    if ($tickets !== []) {
        $selectedTicketId = (int) $tickets[0]['id'];
    }
} elseif (is_string($rawTicketId) && ctype_digit($rawTicketId) && (int) $rawTicketId > 0) {
    $selectedTicketId = (int) $rawTicketId;
} else {
    $selectedTicketError = 'Некорректный номер заявки.';
}

if ($selectedTicketId > 0 && $selectedTicketError === '') {
    $selectedTicketSql = "
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
        WHERE tickets.id = :ticket_id
          AND tickets.user_id = :user_id
    ";

    $selectedTicketStatement = $pdo->prepare($selectedTicketSql);
    $selectedTicketStatement->execute([
        'ticket_id' => $selectedTicketId,
        'user_id' => $_SESSION['user_id'],
    ]);

    $selectedTicket = $selectedTicketStatement->fetch();

    if ($selectedTicket === false) {
        $selectedTicket = null;
        $selectedTicketError = 'Заявка не найдена или недоступна.';
    }
}

if ($selectedTicket !== null) {
    $sqlComment = "
        SELECT
            ticket_comments.comment,
            ticket_comments.created_at,
            users.name AS user_name,
            users.role AS user_role
        FROM ticket_comments
        INNER JOIN users ON ticket_comments.user_id = users.id
        WHERE ticket_comments.ticket_id = :ticket_id
        ORDER BY ticket_comments.created_at ASC
    ";

    $statement = $pdo->prepare($sqlComment);
    $statement->execute([
        'ticket_id' => $selectedTicket['id'],
    ]);

    $comments = $statement->fetchAll();
}

if ($formType === 'add_comment') {
    $message = trim($_POST['message'] ?? '');

    if ($selectedTicket === null) {
        $errorsMessage[] = 'Заявка не найдена или недоступна.';
    }

    if ($message === '') {
        $errorsMessage[] = 'Введите комментарий.';
    }

    if ($errorsMessage === []) {
        $statement = $pdo->prepare(
            'INSERT INTO ticket_comments (ticket_id, user_id, comment)
             VALUES (:ticket_id, :user_id, :comment)'
        );

        $statement->execute([
            'ticket_id' => $selectedTicket['id'],
            'user_id' => $_SESSION['user_id'],
            'comment' => $message,
        ]);

        header('Location: my-tickets.php?ticket_id=' . $selectedTicket['id']);
        exit;
    }
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
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h2 class="sidebar-title">Service Desk</h2>

            <div class="sidebar-user">
                <img class="img-my-tickets" src="img/user-img.png" alt="User avatar">

                <div>
                    <strong><?= e($_SESSION['user_name']) ?></strong>
                    <p><?= e($_SESSION['user_email'] ?? $_SESSION['user_role']) ?></p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a class="sidebar-link active" href="my-tickets.php">Главная</a>
            </nav>

            <a class="sidebar-link mylog" href="logout.php">Выйти</a>
        </aside>

        <main class="dashboard-content">
            <div class="cabinet-grid">
                <div class="cabinet-left">
                    <section>
                        <h1>Создать заявку</h1>

                        <?php if ($errors !== []): ?>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" action="my-tickets.php">
                            <input type="hidden" name="form_type" value="add_ticket">

                            <div>
                                <label for="subject">Тема</label>
                                <input id="subject" name="subject" type="text" placeholder="Кратко опишите проблему"
                                    value="<?= e($subject) ?>">
                            </div>

                            <div>
                                <label for="category_id">Категория</label>
                                <select id="category_id" name="category_id">
                                    <option value="" disabled <?= $categoryId === '' ? 'selected' : '' ?>>
                                        Выберите категорию
                                    </option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (string) $category['id'] ? 'selected' : '' ?>>
                                            <?= e($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>



                                </select>
                            </div>

                            <div>
                                <label for="description">Описание</label>
                                <textarea id="description" name="description"
                                    placeholder="Подробно опишите суть проблемы..."><?= e($description) ?></textarea>
                            </div>

                            <button type="submit">Отправить заявку</button>
                        </form>
                    </section>

                    <section class="cabinet-down">
                        <h1>Мои заявки</h1>

                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Тема</th>
                                    <th>Категория</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <a href="my-tickets.php?ticket_id=<?= e((string) $ticket['id']) ?>">
                                                <?= e((string) $ticket['id']) ?>
                                            </a>
                                        </td>
                                        <td><?= e($ticket['subject']) ?></td>
                                        <td><?= e($ticket['category_name']) ?></td>
                                        <td>
                                            <span class="status status-<?= e(str_replace('_', '-', $ticket['status'])) ?>">
                                                <?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= e($ticket['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>

                <aside class="ticket-preview">
                    <?php if ($selectedTicketError !== ''): ?>
                        <p><?= e($selectedTicketError) ?></p>
                    <?php elseif ($selectedTicket !== null): ?>
                        <h1>Заявка #<?= e((string) $selectedTicket['id']) ?></h1>

                        <p>Тема: <?= e($selectedTicket['subject']) ?></p>
                        <p>Пользователь: <?= e($selectedTicket['user_name']) ?></p>
                        <p>Категория: <?= e($selectedTicket['category_name']) ?></p>
                        <p>
                            Статус:
                            <span class="status status-<?= e(str_replace('_', '-', $selectedTicket['status'])) ?>">
                                <?= e($statusLabels[$selectedTicket['status']] ?? $selectedTicket['status']) ?>
                            </span>
                        </p>
                        <p>Дата: <?= e($selectedTicket['created_at']) ?></p>
                        <p>Описание: <?= e($selectedTicket['description']) ?></p>

                        <hr>
                        <h2>Чат поддержки</h2>

                        <?php if ($comments === []): ?>
                            <p>Комментариев пока нет.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <?php
                                $author = $comment['user_role'] === 'admin'
                                    ? 'Служба поддержки'
                                    : $comment['user_name'];
                                ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <p><?= e($author) ?></p>
                                        <p><?= e($comment['created_at']) ?></p>
                                    </div>

                                    <p><?= e($comment['comment']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($errorsMessage !== []): ?>
                            <ul>
                                <?php foreach ($errorsMessage as $errorMessage): ?>
                                    <li><?= e($errorMessage) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form class="sentComment" method="post"
                            action="my-tickets.php?ticket_id=<?= e((string) $selectedTicket['id']) ?>">
                            <input type="hidden" name="form_type" value="add_comment">
                            <input type="hidden" name="ticket_id" value="<?= e((string) $selectedTicket['id']) ?>">
                            <textarea id="message" name="message"
                                placeholder="Написать службе поддержки..."><?= e($message) ?></textarea>
                            <button type="submit">Отправить</button>
                        </form>
                    <?php else: ?>
                        <p>У вас пока нет заявок.</p>
                    <?php endif; ?>
                </aside>
            </div>
        </main>
    </div>
</body>

</html>