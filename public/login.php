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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '') {
        $errors[] = 'Введите email.';
    }

    if ($password === '') {
        $errors[] = 'Введите пароль.';
    }

    if ($errors === []) {
    $statement = $pdo->prepare(
        'SELECT id, name, email, password, role FROM users WHERE email = :email');

    $statement->execute([
        'email' => $email,
    ]);

    $user = $statement->fetch();


    if ($user === false || !password_verify($password, $user['password'])) {
    $errors[] = 'Неверный email или пароль.';
    }else {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];

    if ($user['role'] === 'user') {
    header('Location: my-tickets.php');
    }else if ($user['role'] === 'admin') {
    header('Location: index.php');
    }
    exit;
}

}
}

?>

<!DOCTYPE html>
<html>
    <head>
            <title>Login</title>
            <link rel="stylesheet" href="css/style.css">
    </head>


<body class="auth-page">
    <main class="auth-card">

    <img class="img-login" src="img/support.png">
<h1>Добро пожаловать!</h1>
<h4>Войдите в свой аккаунт</h4>

    <?php if ($errors !== []): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<form method="post" action="login.php">
   
    <label>Email</label><br>
    <input name="email" type="email" placeholder="you@example.com" value="<?= e($email) ?>"><br><br>



    <label>Password</label><br>
    <input name="password" type="password" placeholder="Введите пароль" ><br><br><br>

    <button class="button-login" type="submit">Войти</button>
    <hr>
    <h3>Нет аккаунт? <a href="registration.php">Зарегистрироваться</a></h3>        
         </main>
</form>
</div>

</body>

</html>