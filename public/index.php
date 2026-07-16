<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$statusLabels = require __DIR__ . '/../config/statuses.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: my-tickets.php');
    exit;
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$categoryIds = array_map('strval', array_column($categories, 'id'));
$allowedStatuses = array_keys($statusLabels);

$filterCategoryId = $_GET['category_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterErrors = [];

if ($filterCategoryId !== '') {
    if (
        !is_string($filterCategoryId)
        || !ctype_digit($filterCategoryId)
        || (int) $filterCategoryId < 1
    ) {
        $filterErrors[] = 'Некорректная категория фильтра.';
        $filterCategoryId = '';
    } elseif (!in_array($filterCategoryId, $categoryIds, true)) {
        $filterErrors[] = 'Категория фильтра не существует.';
        $filterCategoryId = '';
    }
}

if ($filterStatus !== '') {
    if (
        !is_string($filterStatus)
        || !in_array($filterStatus, $allowedStatuses, true)
    ) {
        $filterErrors[] = 'Некорректный статус фильтра.';
        $filterStatus = '';
    }
}

$perPage = 6;
$rawPage = $_GET['page'] ?? '1';

if (
    !is_string($rawPage)
    || !ctype_digit($rawPage)
    || (int) $rawPage < 1
) {
    $filterErrors[] = 'Некорректный номер страницы.';
    $page = 1;
} else {
    $page = (int) $rawPage;
}




$whereSql = " WHERE 1 = 1";
$sqlParams = [];

if ($filterCategoryId !== '') {
    $whereSql .= " AND tickets.category_id = :category_id";
    $sqlParams['category_id'] = (int) $filterCategoryId;
}

if ($filterStatus !== '') {
    $whereSql .= " AND tickets.status = :status";
    $sqlParams['status'] = $filterStatus;
}

$countStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM tickets' . $whereSql
);
$countStatement->execute($sqlParams);

$totalTickets = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalTickets / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

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
" . $whereSql . "
    ORDER BY tickets.created_at DESC, tickets.id DESC
    LIMIT :limit OFFSET :offset
";

$statement = $pdo->prepare($sql);

foreach ($sqlParams as $paramName => $paramValue) {
    $statement->bindValue(
        ':' . $paramName,
        $paramValue,
        is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}

$statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$statement->bindValue(':offset', $offset, PDO::PARAM_INT);
$statement->execute();
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




$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if ($formType === 'change_status' && $isAdmin) {
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($ticketId > 0 && in_array($status, $allowedStatuses, true)) {
        $updateStatement = $pdo->prepare(
            'SELECT id FROM tickets WHERE id = :id'
        );

        $updateStatement->execute([
            'id' => $ticketId,
        ]);

        $existingTicket = $updateStatement->fetch();

        if ($existingTicket !== false) {
            $updateStatement = $pdo->prepare(
                'UPDATE tickets SET status = :status WHERE id = :id'
            );

            $updateStatement->execute([
                'status' => $status,
                'id' => $ticketId,
            ]);

            $redirectQuery = [
                'ticket_id' => $ticketId,
                'page' => $page,
            ];

            if ($filterCategoryId !== '') {
                $redirectQuery['category_id'] = $filterCategoryId;
            }

            if ($filterStatus !== '') {
                $redirectQuery['status'] = $filterStatus;
            }

            header('Location: index.php?' . http_build_query($redirectQuery));
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

        $redirectQuery = [
            'ticket_id' => $selectedTicket['id'],
            'page' => $page,
        ];

        if ($filterCategoryId !== '') {
            $redirectQuery['category_id'] = $filterCategoryId;
        }

        if ($filterStatus !== '') {
            $redirectQuery['status'] = $filterStatus;
        }

        header('Location: index.php?' . http_build_query($redirectQuery));
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
                <div class="tickets-toolbar">
                    <h1>Все заявки</h1>

                    <form class="ticket-filters" method="get" action="index.php">
                        <label class="visually-hidden" for="filter_status">Статус</label>

                        <select id="filter_status" name="status">
                            <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>
                                Все статусы
                            </option>

                            <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                                <option value="<?= e($statusValue) ?>" <?= $filterStatus === $statusValue ? 'selected' : '' ?>>
                                    <?= e($statusLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="visually-hidden" for="filter_category_id">Категория</label>

                        <select id="filter_category_id" name="category_id">
                            <option value="" <?= $filterCategoryId === '' ? 'selected' : '' ?>>
                                Все категории
                            </option>

                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= $filterCategoryId === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">Применить</button>
                        <a class="filter-reset" href="index.php">Сбросить</a>
                    </form>
                </div>

                <?php if ($filterErrors !== []): ?>
                    <ul>
                        <?php foreach ($filterErrors as $filterError): ?>
                            <li><?= e($filterError) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

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
                        <?php if ($tickets === []): ?>
                            <tr>
                                <td colspan="6">Заявок по выбранным фильтрам нет.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                $ticketQuery = [
                                    'ticket_id' => $ticket['id'],
                                    'page' => $page,
                                ];

                                if ($filterCategoryId !== '') {
                                    $ticketQuery['category_id'] = $filterCategoryId;
                                }

                                if ($filterStatus !== '') {
                                    $ticketQuery['status'] = $filterStatus;
                                }
                                ?>

                                <tr>
                                    <td>
                                        <a href="<?= e('index.php?' . http_build_query($ticketQuery)) ?>">
                                            <?= e((string) $ticket['id']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($ticket['subject']) ?></td>
                                    <td><?= e($ticket['user_name']) ?></td>
                                    <td><?= e($ticket['category_name']) ?></td>

                                    <td><?php if ($isAdmin): ?>
                                            <form class="changeStatus" method="post"
                                                action="<?= e('index.php?' . http_build_query($ticketQuery)) ?>">
                                                <input type="hidden" name="form_type" value="change_status">
                                                <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                                                <select name="status"
                                                    class="status status-<?= e(str_replace('_', '-', $ticket['status'])) ?>">
                                                    <?php foreach ($allowedStatuses as $status): ?>
                                                        <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>>
                                                            <?= e($statusLabels[$status]) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <button type="submit">Сохранить</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= e($ticket['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Страницы заявок">
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <?php
                            $pageQuery = [
                                'page' => $pageNumber,
                            ];

                            if ($filterCategoryId !== '') {
                                $pageQuery['category_id'] = $filterCategoryId;
                            }

                            if ($filterStatus !== '') {
                                $pageQuery['status'] = $filterStatus;
                            }
                            ?>

                            <a
                                class="pagination-link<?= $pageNumber === $page ? ' active' : '' ?>"
                                href="<?= e('index.php?' . http_build_query($pageQuery)) ?>"
                                <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>
                            >
                                <?= e((string) $pageNumber) ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </div>
            <script>
             window.addEventListener('load', function () {
                const commentsList = document.querySelector('.comments-list');

            if (commentsList !== null) {
                  commentsList.scrollTop = commentsList.scrollHeight;
            }
         });
        </script>
            <aside class="ticket-preview">
                <!-- правая карточка завки -->



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
                        <div class="comments-list">
                            <?php if ($comments === []): ?>
                                <p>Комментариев пока нет.</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <?php
                                    $isSupport = $comment['user_role'] === 'admin';

                                    $author = $isSupport
                                        ? 'Служба поддержки'
                                        : $comment['user_name'];

                                    $commentClass = $isSupport
                                        ? 'comment-support'
                                        : 'comment-user';
                                    ?>
                                    <div class="comment <?= e($commentClass) ?>">
                                        <div class="comment-header">
                                            <p><?= e($author) ?></p>
                                            <p><?= e($comment['created_at']) ?></p>
                                        </div>
                                        <p><?= e($comment['comment']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <script>
                            window.addEventListener('load', function () {
                                const commentsList = document.querySelector('.comments-list');

                                if (commentsList !== null) {
                                    commentsList.scrollTop = commentsList.scrollHeight;
                                }
                            });
                        </script>

                    <?php
                    $commentQuery = [
                        'ticket_id' => $selectedTicket['id'],
                        'page' => $page,
                    ];

                    if ($filterCategoryId !== '') {
                        $commentQuery['category_id'] = $filterCategoryId;
                    }

                    if ($filterStatus !== '') {
                        $commentQuery['status'] = $filterStatus;
                    }
                    ?>

                    <form class="sentComment" method="post"
                        action="<?= e('index.php?' . http_build_query($commentQuery)) ?>">
                        <input type="hidden" name="form_type" value="add_comment">
                        <input type="hidden" name="ticket_id" value="<?= e((string) $selectedTicket['id']) ?>">
                        <textarea id="message" name="message"
                            placeholder="Написать службе поддержки..."><?= e($message) ?></textarea>
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
