<?php
//password_reset.php
//Script para formulario de recuperación de contraseña.

require_once 'includes/db_users.php';
require_once 'includes/header.php';

$mensaje = '';

//Procesar el formulario de recuperación.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $mensaje = "<p class='btn-danger'>Todos los campos son obligatorios.</p>";
    } elseif ($new_password !== $confirm_password) {
        $mensaje = "<p class='btn-danger'>Las contraseñas no coinciden.</p>";
    } else {
        try {
            //Verificar si el correo existe.
            $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo_users->prepare($sql); //Usa la nueva variable de conexión pdo declarada.
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                //Hashear la nueva contraseña.
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                //Actualizar la contraseña del usuario.
                $sql_update = "UPDATE users SET password = :password WHERE email = :email";
                $stmt_update = $pdo_users->prepare($sql_update); //Usa la nueva variable de conexión
                $stmt_update->execute([':password' => $hashed_password, ':email' => $email]);

                $mensaje = "<p class='btn-success'>Tu contraseña ha sido actualizada. Ahora puedes <a href='login.php'>Iniciar sesión</a>.</p>";
            } else {
                $mensaje = "<p class='btn-danger'>El correo no se encunetra registrado.</p>";
            }
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al actualizar la contraseña: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<>
    <div class="row justify-content-center">
        <div class="col-md-6 mt-5">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Recuperar Contraseña</h2>
                </div>
                <div class="card-body">
                    <p>Ingresa tu correo y la nueva contraseña para actualizarla.</p>
                    <?php echo $mensaje; ?>
                    <form action="password_reset.php" method="POST">
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Contraseña:</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                required>
                        </div>
                        <button type="submit" name="reset_password"
                            class="btn btn-outline-success btn-block">Restablecer Contraseña</button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p><a href="login.php">Regresar a iniciar sesión</a></p>
                </div>
            </div>
        </div>
    </div>