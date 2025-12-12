<?php
// categorias.php
// Gestión de categorías (creación, listado, eliminación y enlace a subcategorías)
session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$categorias_data = []; // Cambiado a $categorias_data para manejar ID y nombre
$user_role = $_SESSION['role'] ?? 'user';

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
    // Esta función DEBE permanecer aquí ya que gestor_subcategorias.php también la usa.
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
        $nombre = strtolower(trim($_POST['nueva_categoria_nombre'])); // Usar minúsculas
        if (!empty($nombre) && validarNombreCategoria($nombre)) {
            try {
                // Agregar la categoría a la tabla `categorias`
                $stmt = $pdo->prepare("INSERT INTO categorias (nombre_categoria) VALUES (?)");
                $stmt->execute([$nombre]);

                // Crear la tabla para la nueva categoría
                crearTablaCategoria($pdo, $nombre);
                $mensaje = "<p class='btn-success'>✅ Categoría '<b>" . htmlspecialchars($nombre) . "</b>' creada correctamente.</p>";
            } catch (PDOException $e) {
                 if ($e->getCode() == 23000) {
                     $mensaje = "<p class='btn-danger'>❌ Error: Ya existe una categoría con ese nombre.</p>";
                } else {
                    $mensaje = "<p class='btn-danger'>❌ Error al crear la categoría: " . $e->getMessage() . "</p>";
                }
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
                // Eliminar la categoría de la tabla `categorias` (esto activará ON DELETE CASCADE en subcategorias_logicas)
                $stmt = $pdo->prepare("DELETE FROM categorias WHERE nombre_categoria = ?");
                $stmt->execute([$nombre]);

                // Eliminar la tabla de la categoría
                eliminarTablaCategoria($pdo, $nombre);
                $mensaje = "<p class='btn-success'>✅ Categoría '<b>" . htmlspecialchars($nombre) . "</b>' eliminada correctamente. Se eliminaron todas las subcategorías asociadas.</p>";
            } catch (PDOException $e) {
                $mensaje = "<p class='btn-danger'>❌ Error al eliminar la categoría: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// --- Cargar categorías para la vista ---
try {
    // Ahora obtenemos el ID para pasarlo al gestor de subcategorías
    $stmt = $pdo->query("SELECT id, nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar las categorías: " . $e->getMessage() . "</p>";
}

?>

<h2>Gestión de Categorías</h2>
<?php echo $mensaje; ?>

<?php if ($user_role === 'admin'): ?>
    <h3>Crear Nueva Categoría</h3>
    <form action="categorias.php" method="POST">
        <label for="nueva_categoria_nombre">Nombre de la Categoría (ej: ferreteria):</label>
        <input type="text" id="nueva_categoria_nombre" name="nueva_categoria_nombre" required>
        <button type="submit" name="nueva_categoria" class="btn btn-primary">Crear</button>
    </form>
    <hr>
<?php endif; ?>

<?php if ($categorias_data): ?>
    <ul class="list-group">
        <?php foreach ($categorias_data as $cat): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo htmlspecialchars(ucfirst($cat['nombre_categoria'])); ?>
                <div>
                    <?php if ($user_role === 'admin'): ?>
                        <a class="btn btn-light btn-sm" href="descargar_inventario.php?categoria_id=<?php echo urlencode($cat['id']); ?>">Descargar Inventario</a>
                         <a class="btn btn-info btn-sm" href="gestor_subcategorias.php?categoria_id=<?php echo urlencode($cat['id']); ?>">Gestionar Subcategorías</a>
                        
                        <a class="btn btn-dark btn-sm" href="ver_productos.php?categoria=<?php echo urlencode($cat['nombre_categoria']); ?>">Ver Productos</a>                        <a class="btn btn-success btn-sm" href="agregar_producto.php?categoria=<?php echo urlencode($cat['nombre_categoria']); ?>">Agregar Producto</a>
                        
                        <form action="categorias.php" method="POST" style="display:inline;"
                            onsubmit="return confirm('¿Eliminar la categoría <?php echo htmlspecialchars($cat['nombre_categoria']); ?>? Esto eliminará la tabla y todas las subcategorías asociadas.');">
                            <input type="hidden" name="eliminar_categoria_nombre" value="<?php echo htmlspecialchars($cat['nombre_categoria']); ?>">
                            <button class="btn btn-danger btn-sm" type="submit" name="eliminar_categoria">Eliminar</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-info btn-sm" href="gestor_subcategorias.php?categoria_id=<?php echo urlencode($cat['id']); ?>">Ver Subcategorías</a>
                        <a class="btn btn-dark btn-sm" href="ver_productos.php?categoria=<?php echo urlencode($cat['nombre_categoria']); ?>">Ver Productos</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No hay categorías disponibles.</p>
<?php endif; ?>
<hr>

<?php include 'includes/footer.php'; ?>