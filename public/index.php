<?php
session_start();
require_once __DIR__ . '/../config/database.php';
    if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

    if($_SESSION['user_role'] !== 'admin'){
        header('Location: my-tickets.php');
        exit;
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
$selectedTicket = null;
$selectedTicketError = '';
$selectedTicketId = 0;
$comments = [];
$errorsMessage = [];
$message = '';
$formType = $_POST['form_type'] ?? '';


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
    ";

    $selectedTicketStatement = $pdo->prepare($selectedTicketSql);
    $selectedTicketStatement->execute([
        'ticket_id' => $selectedTicketId,
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

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}




$allowedStatuses = ['new', 'in_progress', 'done', 'rejected'];

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if ($formType === 'change_status' && $isAdmin) {
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if($ticketId > 0 && in_array($status, $allowedStatuses, true)){
     $updateStatement = $pdo->prepare(    
                'SELECT id FROM tickets WHERE id = :id');

                 $updateStatement->execute([
                'id' => $ticketId,
            ]);

      $existingTicket = $updateStatement->fetch();
      
     if($existingTicket !== false){
          $updateStatement = $pdo->prepare(    
                'UPDATE tickets SET status = :status WHERE id = :id'
            );

            $updateStatement->execute([
                'status' => $status,
                'id' => $ticketId,
            ]);
     
        header('Location: index.php?ticket_id=' . $ticketId);
        exit;
    }
    }
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

        header('Location: index.php?ticket_id=' . $selectedTicket['id']);
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
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </nav>
</header>
<main class="dashboard-content">
<div class="admin-grid">
<div class="cabinet-down">
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
             <a href="index.php?ticket_id=<?= e((string) $ticket['id']) ?>">
             <?= e((string) $ticket['id']) ?>
                </a>
        </td>
            <td><?= e($ticket['subject']) ?></td>
            <td><?= e($ticket['user_name']) ?></td>
            <td><?= e($ticket['category_name']) ?></td>

            <td><?php if ($isAdmin): ?>
    <form class="changeStatus" method="post" action="index.php">
        <input type="hidden" name="form_type" value="change_status">
        <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
    <select id="status" name="status">
        <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>>
                <?= e($status) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Сохранить</button>
</form>
<?php endif; ?></td> 

            <td><?= e($ticket['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

                <aside class="ticket-preview">
                    <!-- правая карточка завки -->


                
 <?php if ($selectedTicketError !== ''): ?>
                    <p><?= e($selectedTicketError) ?></p>
                <?php elseif ($selectedTicket !== null): ?>
                    <h1>Заявка #<?= e((string) $selectedTicket['id']) ?></h1>

                    <p>Тема: <?= e($selectedTicket['subject']) ?></p>
                    <p>Пользователь: <?= e($selectedTicket['user_name']) ?></p>
                    <p>Категория: <?= e($selectedTicket['category_name']) ?></p>
                    <p>Статус: <?= e($selectedTicket['status']) ?></p>
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

                    <form
                        class="sentComment"
                        method="post"
                        action="index.php?ticket_id=<?= e((string) $selectedTicket['id']) ?>"
                    >
                        <input type="hidden" name="form_type" value="add_comment">
                        <input type="hidden" name="ticket_id" value="<?= e((string) $selectedTicket['id']) ?>">
                        <textarea
                            id="message"
                            name="message"
                            placeholder="Написать службе поддержки..."
                        ><?= e($message) ?></textarea>
                        <button type="submit">Отправить</button>
                    </form>
                <?php else: ?>
                    <p>Заявок пока нет.</p>
                <?php endif; ?>
            </aside>
</div>
</main>



</body>
</html>
