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

    header('Location: index.php');
    exit;
}

}
}

?>

<!DOCTYPE html>
<html>
<head>

</head>


<body>
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
   <div>
    <label>Email</label><br>
    <input name="email" type="email" placeholder="you@example.com" value="<?= e($email) ?>">

</div>

    <label>Password</label><br>
    <input name="password" type="password" placeholder="Введите пароль" ><br>

    <button type="submit">Войти</button>
    <hr>
    <h3><a href="registration.php">Зарегистрироваться</a></h3>        

</form>


</body>

</html>