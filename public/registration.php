<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$errors = [];
$email = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') {
        $errors[] = 'Введите имя.';
    }

    if ($email === '') {
        $errors[] = 'Введите email.';
    }

    if ($password === '') {
        $errors[] = 'Введите пароль.';
    }

    if ($errors === []) {
        $statement = $pdo->prepare(
            'SELECT id FROM users WHERE email = :email'
        );

        $statement->execute([
            'email' => $email,
        ]);

        $user = $statement->fetch();

        if ($user !== false) {
            $errors[] = 'Пользователь с таким email уже существует.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStatement = $pdo->prepare(
                'INSERT INTO users (name, email, password, role)
                 VALUES (:name, :email, :password, :role)'
            );

            $insertStatement->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'role' => 'user',
            ]);

            header('Location: login.php');
            exit;
        }
    }
   
}




?>

<!DOCTYPE html>
<html>
<head>
   <title>Registration</title>
<link rel="stylesheet" href="css/style.css">
</head>


<body class="auth-page">
    <main class="auth-card">

    <img class="img-login" src="img/support.png">

<h1>Добро пожаловать!</h1>
<h4>Создайте свой аккаунт</h4>

    <?php if ($errors !== []): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<form method="post" action="registration.php">
<div>
    <label>Name</label><br>
    <input name="name" type="text" placeholder="Ваше имя..." value="<?= e($name ?? '') ?>"><br><br>

    <label>Email</label><br>
    <input name="email" type="email" placeholder="you@example.com" value="<?= e($email) ?>"><br>

</div>

    <label>Password</label><br>
    <input name="password" type="password" placeholder="Введите пароль" ><br><br><br>

    <button class="button-login" type="submit">Зарегистрироваться</button>

       <hr>
    <h3>Уже есть аккаунт? <a href="login.php">Войти</a></h3>  
</form>

 </main>
</body>

</html>
