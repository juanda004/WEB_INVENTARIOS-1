<?php
// index.php
// Este script muestra un resumen dinámico del inventario,
// consultando todas las categorías existentes en la base de datos, e incluyendo
// el conteo de subcategorías y el total de productos.

include 'includes/db.php';
include 'includes/headerM.php';

$mensaje = '';
?>

<body>
    <div class="container main-content">
        <h2>Reportes</h2><br>
        <?php echo $mensaje; ?>
        <div class="dashborad-stats">

        </div>
        <hr>
        <?php include 'includes/footer.php'; ?>
    </div>
