<?php
require_once __DIR__ . '/../config/database.php';
$errors = [];

$subject = '';
$categoryId = '';
$description = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if($subject ===''){
        $errors[] = 'Введите тему заявки.';
    }

    if($categoryId === ''){
        $errors[] = 'Выберите категорию.';
    }

    if($description === ''){
        $errors[] = 'Введите описание заявки.';
    }


    if ($errors === []) {
    $userId = 2;

    $statement = $pdo->prepare(
        'INSERT INTO tickets (user_id, category_id, subject, description)
         VALUES (:user_id, :category_id, :subject, :description)'
    );

    $statement->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'subject' => $subject,
        'description' => $description,
    ]);

    header('Location: index.php');
    exit;
}

}


?>


<!DOCTYPE html>

<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Создать заявку</title>
</head>


<body>
<h1>Создать заявку</h1>

<?php if ($errors !== []): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="create-ticket.php">

<div> 
    <label for="subject">Тема</label><br>
<input id="subject"  name="subject" type="text" placeholder="Кратко опишите проблему"><br>
</div>

<div>
 <label  for="category_id">Категория</label><br>   
<select id="category_id" name="category_id">
   <option value="" disabled <?= $categoryId === '' ? 'selected' : '' ?>>Выберите категорию</option>
    <option value="1" <?= $categoryId === '1' ? 'selected' : '' ?>>Почта</option>
    <option value="2" <?= $categoryId === '2' ? 'selected' : '' ?>>Авторизация</option>
    <option value="3" <?= $categoryId === '3' ? 'selected' : '' ?>>Оборудование</option>
    <option value="4" <?= $categoryId === '4' ? 'selected' : '' ?>>Приложения</option>
    <option value="5" <?= $categoryId === '5' ? 'selected' : '' ?>>Файлы</option>
</select>
</div>
<div>
<label for="description">Описание</label><br>
<textarea  id="description" name="description" placeholder="Подробно опишите суть проблемы..."><?= e($description) ?></textarea><br>
</div>
<button type="submit">Отправить заявку</button>

</form>
<p>
   <a href="index.php">Назад к списку</a>
</p>


</body>
</html>