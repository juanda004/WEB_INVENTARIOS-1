<?php
// index.php
// Este script muestra un resumen dinámico del inventario,
// consultando todas las categorías existentes en la base de datos.

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$estadisticas_categorias = [];


try {
    // Primero, obtener todas las categorías de la tabla 'categorias'
    $stmt_categorias = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);

    // Si hay categorías, iterar sobre ellas para obtener el conteo de productos de cada una
    if (!empty($categorias)) {
        foreach ($categorias as $cat) {
            // Asegurarse de que el nombre de la tabla sea seguro para la consulta
            if (preg_match('/^[a-z0-9_]+$/i', $cat)) {
                $stmt_productos = $pdo->query("SELECT COUNT(*) FROM `$cat`");
                $conteo = $stmt_productos->fetchColumn();
                $estadisticas_categorias[$cat] = $conteo;
            }
        }
    }

} catch (PDOException $e) {
    // En caso de error, mostrar un mensaje claro
    $mensaje = "<p class='error'>❌ Error al cargar el dashboard: " . $e->getMessage() . "</p>";
    $estadisticas_categorias = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRgcW54AkpoPfFPQacImJCIwpJEctdfJh4t0g&s"
        type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCS INVENTARIO</title>
</head>

<body>
    <h2>Resumen del Inventario</h2><br>
    <?php echo $mensaje; ?>
    <div class="dashborad-stats">
        <?php if (!empty($estadisticas_categorias)): ?>
            <?php foreach ($estadisticas_categorias as $categoria_nombre => $conteo): ?>
                <ul>
                    <li>
                        <p style="font-size: x-large;">Productos de <?php echo htmlspecialchars(ucfirst($categoria_nombre)); ?>:
                            <strong><?php echo htmlspecialchars($conteo); ?></strong></p>
                    </li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No hay categorías o productos disponibles en el inventario.</p>
        <?php endif; ?>
    </div>
    <hr>
    <?php include 'includes/footer.php'; ?>