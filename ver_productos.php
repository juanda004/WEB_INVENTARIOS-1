<?php
// ver_productos.php
// Este script muestra los productos de una categoría específica y maneja la eliminación.

session_start();
// Control de acceso: requiere autenticación
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$categoria_seleccionada = isset($_GET['categoria']) ? $_GET['categoria'] : null;
$productos = [];
$user_role = $_SESSION['role'] ?? 'user';
// --- Validacion Inicial de la categoría seleccionada ---
if (empty($categoria_seleccionada)) {
    echo "<p class='btn-danger'>❌ Error: No se ha especificado una categoría para mostrar productos.</p>";
    include 'includes/footer.php';
    exit();
} else {
    // Asegurar de que el nombre de la tabla sea seguro para evitar SQL Injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $categoria_seleccionada)) {
        echo "<p class='btn-danger'>❌ Error: Nombre de categoría no válido.</p>";
        include 'includes/footer.php';
        exit();
    }
}

// --- Lógica para eliminar un producto (solo para admin) ---
if (isset($_GET['action']) && $_GET['action'] == 'eliminar' && isset($_GET['id'])) {
    // Verificar si el usuario es administrador
    if ($_SESSION['role'] !== 'admin') {
        $mensaje = "<p class='btn-danger'>❌ Error: No tienes permisos para realizar esta acción.</p>";
    } else {
        $CODIGO = $_GET['id'];
        try {
            // La consulta de eliminación del producto
            $stmt = $pdo->prepare("DELETE FROM `$categoria_seleccionada` WHERE CODIGO = ?");
            $stmt->execute([$CODIGO]);
            $mensaje = "<p class='btn-success'>✅ Producto eliminado correctamente.</p>";
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>❌ Error al eliminar el producto: " . $e->getMessage() . "</p>";
        }
    }
}

// --- Obtener productos de la categoría seleccionada ---
try {
    $stmt = $pdo->prepare("SELECT * FROM `$categoria_seleccionada` ORDER BY CODIGO ASC");
    $stmt->execute();
    $productos = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar los productos: " . $e->getMessage() . "</p>";
}

?>

<div class="container mt-4">
    <h2>Productos de la Categoría: <?php echo htmlspecialchars(ucfirst($categoria_seleccionada)); ?></h2>
    <?php echo $mensaje; ?>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="agregar_producto.php?categoria=<?php echo urlencode($categoria_seleccionada); ?>"
            class="btn btn-success mb-3">Agregar Nuevo Producto</a>
    <?php endif; ?>

    <?php if (!empty($productos)): ?>
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th style="text-align: center; vertical-align: middle;">CODIGO</th>
                    <th style="text-align: center; vertical-align: middle;">CODIGO DE BARRAS</th>
                    <th style="text-align: center; vertical-align: middle;">DESCRIPCION</th>
                    <th style="text-align: center; vertical-align: middle;">CANTIDAD</th>
                    <?php if ($user_role === 'admin'): ?>
                        <th style="text-align: center; vertical-align: middle;">ACCIONES</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td style="text-align: center; vertical-align: middle;">
                            <?php echo htmlspecialchars($producto['CODIGO']); ?>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <?php echo htmlspecialchars($producto['CODIGO_BARRAS']); ?>
                        </td>
                        <td style="vertical-align: middle;"><?php echo htmlspecialchars($producto['PRODUCTO']); ?></td>
                        <td style="text-align: center; vertical-align: middle;">
                            <?php echo htmlspecialchars($producto['CANT']); ?>
                        </td>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <td>
                                <div style="display: flex; flex-direction: column;"   >
                                    <a href="editar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-warning">Editar</a>
                                    <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&action=eliminar&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm ('¿Está seguro de que desea eliminar el producto?');">Eliminar</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay productos registrados en el inventario para la categoría
            '<?php echo htmlspecialchars($categoria_seleccionada); ?>'.</p>
    <?php endif; ?>
    <div><a class="btn btn-dark" href="categorias.php">Volver a Categorías</a></div>
</div>

<?php include 'includes/footer.php'; ?>