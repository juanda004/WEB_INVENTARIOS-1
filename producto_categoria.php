<?php
// ver_todas_categorias.php
// Este script muestra todos los productos, organizados por categoría (tabla).

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$categorias = [];

// Obtener todas las categorías de la tabla 'categorias'
try {
    $stmt_categorias = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar las categorías: " . $e->getMessage() . "</p>";
}

?>
<div class="container main-content">
    <h2>Inventario General por Categoría</h2><br>
    <?php echo $mensaje; ?>
    <div class="table-responsive">
        <?php if (!empty($categorias)): ?>
            <?php foreach ($categorias as $categoria_nombre): ?>
                <h3>Categoría: <?php echo htmlspecialchars(ucfirst($categoria_nombre)); ?></h3>
                <?php
                $productos = [];
                try {
                    // Obtener todos los productos de la tabla de la categoría actual
                    $stmt_productos = $pdo->prepare("SELECT * FROM `$categoria_nombre` ORDER BY CODIGO ASC");
                    $stmt_productos->execute();
                    $productos = $stmt_productos->fetchAll();
                } catch (PDOException $e) {
                    echo "<p class='btn-danger'>❌ Error al cargar los productos de la categoría '" . htmlspecialchars($categoria_nombre) . "': " . $e->getMessage() . "</p>";
                }
                ?>

                <?php if (!empty($productos)): ?>
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>CÓDIGO</th>
                                <th>CÓDIGO DE BARRAS</th>
                                <th>DESCRIPCIÓN</th>
                                <th>CANTIDAD</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($producto['CODIGO']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($producto['CODIGO_BARRAS']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['PRODUCTO']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($producto['CANT']); ?></td>
                                    <td>
                                        <a href="editar_producto.php?categoria=<?php echo htmlspecialchars($categoria_nombre); ?>&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                            class="btn btn-warning">Editar</a>
                                        <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($categoria_nombre); ?>&action=eliminar&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                            class="btn btn-danger"
                                            onclick="return confirm('¿Está seguro de que desea eliminar este producto?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay productos registrados en esta categoría.</p>
                <?php endif; ?>
                <hr>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No hay categorías disponibles en el inventario.</p>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</div>