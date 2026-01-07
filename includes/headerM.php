<?php
// headerM.php --Header modificado para Mantenimeinto
// Incluye el inicio de la sesión si no ha sido iniciado ya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener el nombre del archivo de la página actual
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRgcW54AkpoPfFPQacImJCIwpJEctdfJh4t0g&s"
        type="image/png">
    <title>Mantenimeinto NCS</title>
    <!-- Incluir Bootstrap CSS para un diseño limpio y responsive -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: rgba(0, 0, 0, 0.8);
            padding-top: 20px;
            background-image: url("https://lh3.googleusercontent.com/p/AF1QipPdNg6Gxx_ME31mCbA8JyoMMLrlZ3qSO6PTVRFA=s1360-w1360-h1020-rw");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px 0;
            flex-wrap: wrap;
        }

        .btn-logout {
            background-color: #dc3545;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="header container">
        <div style="display: flexbox;">
            <a href="mantenimiento.php"><h1 style="font-weight: bold; color: black;">Mantenimiento NCS</h1></a>
        </div>
        <li class="nav-item"><a class="nav-link" href="index.php">Inventario</a></li>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        
    </div>
</body>
<br>