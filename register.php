<?php
// register.php
// Script para el formulario de registro de nuevos usuarios.

session_start();
require_once 'includes/db_users.php'; // Usa el nuevo archivo de conexión
require_once 'includes/header.php';

$mensaje = '';

// Si el usuario ya está logueado, redirigirlo
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $mensaje = "<p class='btn-danger'>Todos los campos son obligatorios.</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "<p class='btn-danger'>El formato del correo es inválido.</p>";
    } elseif ($password !== $confirm_password) {
        $mensaje = "<p class='btn-danger'>Las contraseñas no coinciden.</p>";
    } else {
        try {
            // Verificar si el correo ya existe en la base de datos
            $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo_users->prepare($sql); // Usa la nueva variable de conexión
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $mensaje = "<p class='btn-danger'>Este correo ya está registrado.</p>";
            } else {
                // Hashear la contraseña de forma segura
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insertar el nuevo usuario en la base de datos con el rol 'user'
                $sql = "INSERT INTO users (email, password) VALUES (:email, :password)";
                $stmt = $pdo_users->prepare($sql); // Usa la nueva variable de conexión
                $stmt->execute([':email' => $email, ':password' => $hashed_password]);

                $mensaje = "<p class='btn-success'>¡Registro exitoso! Ahora puedes <a href='login.php'>iniciar sesión</a>.</p>";
            }
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al registrar el usuario: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<body>
    <div class="row justify-content-center">
        <div class="col-md-6 mt-5">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Crear una cuenta</h2>
                </div>
                <div class="card-body">
                    <?php echo $mensaje; ?>
                    <form action="register.php" method="POST">
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Contraseña:</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                required>
                        </div>
                        <button type="submit" name="register" class="btn btn-success btn-block">Registrarse</button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
                </div>
                <?php require_once 'includes/footer.php'; ?>
            </div>
        </div>
    </div>