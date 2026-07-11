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
$selectedTicket = $tickets[0] ?? null;
$selectedTicketId = $_GET['ticket_id'] ?? '';
$comments = [];
$message = '';

foreach ($tickets as $ticket) {
    if ((string) $ticket['id'] === $selectedTicketId) {
        $selectedTicket = $ticket;
        break;
    }
}

if ($selectedTicket !== null) {
    $commentStatement = $pdo->prepare(
        "SELECT
            ticket_comments.comment,
            ticket_comments.created_at,
            users.name AS user_name,
            users.role AS user_role
         FROM ticket_comments
         INNER JOIN users ON ticket_comments.user_id = users.id
         WHERE ticket_comments.ticket_id = :ticket_id
         ORDER BY ticket_comments.created_at ASC"
    );

    $commentStatement->execute([
        'ticket_id' => $selectedTicket['id'],
    ]);

    $comments = $commentStatement->fetchAll();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


$id = $_GET['id'] ?? '';

$allowedStatuses = ['new', 'in_progress', 'done', 'rejected'];

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$formType = $_POST['form_type'] ?? '';

if ($formType === 'change_status' && $isAdmin) {
    $status = $_POST['status'] ?? '';

    if (in_array($status, $allowedStatuses, true)) {
            $updateStatement = $pdo->prepare(    
                'UPDATE tickets SET status = :status WHERE id = :id'
            );

            $updateStatement->execute([
                'status' => $status,
                'id' => $id,
            ]);
    }

    header('Location: index.php');
    exit;
}



$errorsMessage = [];
$message = '';
if ($formType === 'add_comment') {
    $message = trim($_POST['message'] ?? '');

    if($message === ''){
        $errorsMessage[] = 'Введите описание заявки.';
    }


    if ($errorsMessage === []) {
    $userId = $_SESSION['user_id'];

    $statement = $pdo->prepare(
        'INSERT INTO ticket_comments (ticket_id, user_id, comment)
         VALUES (:ticket_id, :user_id, :comment)'

    );

    $statement->execute([
        'ticket_id'=> $selectedTicket['id'],
        'user_id' => $_SESSION['user_id'],
        'comment' => $message
    ]);
    header('Location: index.php?ticket_id='. $selectedTicket['id']);
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
    <form class="changeStatus" method="post" action="index.php?id=<?= e((string) $ticket['id']) ?>">
        <input type="hidden" name="form_type" value="change_status">
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


                
<?php if ($selectedTicket !== null): ?>
    <h1>Заявка #<?= e((string) $selectedTicket['id']) ?></h1>

<p>Тема: <?= e($selectedTicket['subject']) ?></p>
<p>Пользователь: <?= e($selectedTicket['user_name']) ?></p>
<p>Категория: <?= e($selectedTicket['category_name']) ?></p>
<p>Статус: <?= e($selectedTicket['status']) ?></p>
<p>Дата: <?= e($selectedTicket['created_at']) ?></p>
<p>Описание: <?= e($selectedTicket['description']) ?></p>


<?php else: ?>
    <p>У вас пока нет заявок.</p>
<?php endif; ?>


<!-- НИЖНИЯ ЧАСТЬ ЗАВКИ СПРАВА ЧАТ -->

<hr>
<h2>Чат поддержки</h2>

    <?php if($comments == null): ?>
        <p>Комментариев пока нет.</p>
    <?php else: ?> 
        
        <?php foreach ($comments as $comment): ?>
           <?php if($comment['user_role'] === 'admin'){
            $author = 'Служба поддержки';
            }
            else
            {$author = $comment['user_name'];}?>
                        
                            <a href="my-tickets.php?ticket_id=<?= e((string) $ticket['id']) ?>"></a>
                   <div class="comment">
                      <div class="comment-header">
                        <p><?= e($author) ?></p>
                        <p><?= e($comment['created_at']) ?></p>
                           </div>

                         <p><?= e($comment['comment']) ?></p>
                    </div>
                  
                <?php endforeach; ?>
           
<?php endif; ?>
 
             <form class="sentComment" method="post" action="index.php?ticket_id=<?= e((string) $selectedTicket['id']) ?>">
                <input type="hidden" name="form_type" value="add_comment">
                        <textarea id="message" name="message" placeholder="Написать службе поддержки..."><?= e($message) ?></textarea><br>
                            <button type="submit">Отправить</button>
                            </form>
            </aside>
</div>
</main>



</body>
</html>
