<?php
// login.php
// Script para el formulario de inicio de sesión y la autenticación de usuarios.

session_start(); // Iniciar la sesión para almacenar el estado del usuario

// Uso de la variable mágica __DIR__ para una ruta más robusta
require_once __DIR__ . '/includes/db_users.php';
require_once __DIR__ . '/includes/header.php';

$mensaje = '';

// Si el usuario ya está logueado, redirigirlo
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Procesar el formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $mensaje = "<p class='btn-danger'>Por favor, ingrese su correo y contraseña.</p>";
    } else {
        try {
            // Buscar al usuario por su correo electrónico
            $sql = "SELECT id, email, password, role FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo_users->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Autenticación exitosa
                session_regenerate_id(true); // Buena práctica de seguridad para evitar fijación de sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role']; // Guardar el rol en la sesión
                header("Location: index.php");
                exit();
            } else {
                $mensaje = "<p class='btn-danger'>Correo o contraseña incorrectos.</p>";
            }
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error de base de datos: " . $e->getMessage() . "</p>";
        }
    }
}
?>
<div class="container main-content">
    <div class="row justify-content-center">
        <div class="col-md-6 mt-5">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Iniciar Sesión</h2>
                </div>
                <div class="card-body">
                    <?php echo $mensaje; ?>
                    <form action="login.php" method="POST">
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary btn-block">Iniciar Sesión</button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
                    <p><a href="password_reset.php">¿Olvidaste tu contraseña?</a></p>
                </div>
                <?php require_once 'includes/footer.php'; ?>
            </div>
        </div>
    </div>
    <br>