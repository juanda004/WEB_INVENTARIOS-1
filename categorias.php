<?php
// categorias.php
// Gestión de categorías (creación, listado y eliminación)
session_start(); // Iniciar la sesión
require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$categorias = [];
$user_role = $_SESSION['role'] ?? 'user'; // Obtener el rol del usuario, si no está definido, es un invitado

// --- Funciones auxiliares ---

/**
 * Valida el nombre de la categoría.
 */
function validarNombreCategoria($nombre)
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $nombre);
}

/**
 * Crea una tabla para la nueva categoría.
 */
function crearTablaCategoria(PDO $pdo, $nombre)
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `$nombre` (
            CODIGO VARCHAR(255) PRIMARY KEY,
            CODIGO_BARRAS VARCHAR(255),
            PRODUCTO VARCHAR(255) NOT NULL,
            CANT BIGINT(255) NOT NULL,
            UNIDAD VARCHAR(50)
        )";
    $pdo->exec($sql);
}

/**
 * Elimina la tabla de la categoría.
 */
function eliminarTablaCategoria(PDO $pdo, $nombre)
{
    $pdo->exec("DROP TABLE IF EXISTS `$nombre`");
}

// --- Procesamiento de formularios ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'admin') {
    // Crear categoría
    if (isset($_POST['nueva_categoria'])) {
        $nombre = trim($_POST['nueva_categoria_nombre']);
        if (!empty($nombre) && validarNombreCategoria($nombre)) {
            try {
                // Agregar la categoría a la tabla `categorias`
                $stmt = $pdo->prepare("INSERT INTO categorias (nombre_categoria) VALUES (?)");
                $stmt->execute([$nombre]);

                // Crear la tabla para la nueva categoría
                crearTablaCategoria($pdo, $nombre);
                $mensaje = "<p class='btn-success'>✅ Categoría '<b>" . htmlspecialchars($nombre) . "</b>' creada correctamente.</p>";
            } catch (PDOException $e) {
                $mensaje = "<p class='btn-danger'>❌ Error al crear la categoría: " . $e->getMessage() . "</p>";
            }
        } else {
            $mensaje = "<p class='btn-danger'>❌ Error: Nombre de categoría no válido o vacío.</p>";
        }
    }

    // Eliminar categoría
    if (isset($_POST['eliminar_categoria'])) {
        $nombre = trim($_POST['eliminar_categoria_nombre']);
        if (!empty($nombre) && validarNombreCategoria($nombre)) {
            try {
                // Eliminar la categoría de la tabla `categorias`
                $stmt = $pdo->prepare("DELETE FROM categorias WHERE nombre_categoria = ?");
                $stmt->execute([$nombre]);

                // Eliminar la tabla de la categoría
                eliminarTablaCategoria($pdo, $nombre);
                $mensaje = "<p class='btn-success'>✅ Categoría '<b>" . htmlspecialchars($nombre) . "</b>' eliminada correctamente.</p>";
            } catch (PDOException $e) {
                $mensaje = "<p class='btn-danger'>❌ Error al eliminar la categoría: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// --- Cargar categorías para la vista ---
try {
    $stmt = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar las categorías: " . $e->getMessage() . "</p>";
}

?>

<h2>Gestión de Categorías</h2>
<?php echo $mensaje; ?>

<?php if ($user_role === 'admin'): ?>
    <!-- Formulario para crear categoría - Visible solo para admin -->
    <h3>Crear Nueva Categoría</h3>
    <form action="categorias.php" method="POST">
        <label for="nueva_categoria_nombre">Nombre de la Categoría:</label>
        <input type="text" id="nueva_categoria_nombre" name="nueva_categoria_nombre" required>
        <button type="submit" name="nueva_categoria">Crear</button>
    </form>
    <hr>
<?php endif; ?>

<!-- Listado de categorías - Visible para ambos roles -->
<?php if ($categorias): ?>
    <ul>
        <?php foreach ($categorias as $cat): ?>
            <li>
                <?php echo htmlspecialchars(ucfirst($cat)); ?>
                <a class="btn btn-dark" style="margin-top: 10px" href="ver_productos.php?categoria=<?php echo urlencode($cat); ?>">Ver Productos</a>
                <?php if ($user_role === 'admin'): ?>
                    <!-- Opciones de gestión - Visible solo para admin -->
                    <a class="btn btn-success" href="agregar_producto.php?categoria=<?php echo urlencode($cat); ?>">Agregar
                        Producto</a>
                    <form action="categorias.php" method="POST" style="display:inline;"
                        onsubmit="return confirm('¿Eliminar la categoría <?php echo htmlspecialchars($cat); ?>? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="eliminar_categoria_nombre" value="<?php echo htmlspecialchars($cat); ?>">
                        <button class="btn btn-danger" type="submit" name="eliminar_categoria">Eliminar</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No hay categorías disponibles.</p>
<?php endif; ?>
<hr>

<?php include 'includes/footer.php'; ?>