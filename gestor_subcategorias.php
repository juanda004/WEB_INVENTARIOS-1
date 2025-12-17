<?php
// gestor_subcategorias.php
// Permite crear y eliminar tablas de productos que actúan como subcategorías.

session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$user_role = $_SESSION['role'] ?? 'user';
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$categoria_nombre = '';
$subcategorias = [];

// --- Funciones auxiliares (Copiadas de categorias.php) ---

/**
 * Valida el nombre de la subcategoría (será el nombre de la tabla).
 */
function validarNombreSubcategoria($nombre)
{
    // Solo letras, números y guiones bajos.
    return preg_match('/^[a-zA-Z0-9_]+$/', $nombre);
}

/**
 * Crea una tabla de productos para la nueva subcategoría.
 */
function crearTablaSubcategoria(PDO $pdo, $nombre_tabla)
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
            CODIGO VARCHAR(255) PRIMARY KEY,
            CODIGO_BARRAS VARCHAR(255),
            PRODUCTO VARCHAR(255) NOT NULL,
            CANT BIGINT(255) NOT NULL,
            UNIDAD VARCHAR(50)
        )";
    $pdo->exec($sql);
}

/**
 * Elimina la tabla de productos de la subcategoría.
 */
function eliminarTablaSubcategoria(PDO $pdo, $nombre_tabla)
{
    $pdo->exec("DROP TABLE IF EXISTS `$nombre_tabla`");
}

// --- Cargar Categoría Principal ---

if ($categoria_id) {
    try {
        $stmt_cat = $pdo->prepare("SELECT nombre_categoria FROM categorias WHERE id = ?");
        $stmt_cat->execute([$categoria_id]);
        $result_cat = $stmt_cat->fetch();
        
        if ($result_cat) {
            $categoria_nombre = $result_cat['nombre_categoria'];
        } else {
            $mensaje = "<p class='btn-danger'>❌ Error: Categoría principal no encontrada.</p>";
            $categoria_id = null;
        }
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>❌ Error al cargar la categoría: " . $e->getMessage() . "</p>";
        $categoria_id = null;
    }
}


// --- Procesamiento de formularios (Solo para Admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'admin' && $categoria_id) {
    
    // Crear Subcategoría
    if (isset($_POST['nueva_subcategoria'])) {
        $nombre_sub_tabla = strtolower(trim($_POST['nombre_subcategoria'])); // Usar minúsculas para el nombre de la tabla

        if (!empty($nombre_sub_tabla) && validarNombreSubcategoria($nombre_sub_tabla)) {
            try {
                // 1. Crear la tabla de productos física en la base de datos
                crearTablaSubcategoria($pdo, $nombre_sub_tabla);
                
                // 2. Registrar la subcategoría lógicamente en 'subcategorias_logicas'
                $stmt = $pdo->prepare("INSERT INTO subcategorias_logicas (nombre_tabla, categoria_id) VALUES (?, ?)");
                $stmt->execute([$nombre_sub_tabla, $categoria_id]);

                $mensaje = "<p class='btn-success'>✅ Subcategoría/Tabla '<b>" . htmlspecialchars($nombre_sub_tabla) . "</b>' creada correctamente.</p>";
            } catch (PDOException $e) {
                // El código 23000 es por violación de unicidad (ya existe una subcategoría con ese nombre)
                if ($e->getCode() == 23000) {
                     $mensaje = "<p class='btn-danger'>❌ Error: Ya existe una subcategoría/tabla con el nombre '<b>" . htmlspecialchars($nombre_sub_tabla) . "</b>'.</p>";
                } else {
                    $mensaje = "<p class='btn-danger'>❌ Error al crear la subcategoría: " . $e->getMessage() . "</p>";
                    // Intentar eliminar la tabla si se creó y falló la inserción lógica (limpieza)
                    eliminarTablaSubcategoria($pdo, $nombre_sub_tabla); 
                }
            }
        } else {
            $mensaje = "<p class='btn-danger'>❌ Error: Nombre de subcategoría no válido. Solo se permiten letras, números y guion bajo.</p>";
        }
    }

    // Eliminar Subcategoría
    if (isset($_POST['eliminar_subcategoria'])) {
        $nombre_sub_tabla_eliminar = trim($_POST['nombre_sub_tabla_eliminar']);
        
        if (!empty($nombre_sub_tabla_eliminar) && validarNombreSubcategoria($nombre_sub_tabla_eliminar)) {
            try {
                // 1. Eliminar el registro lógico
                $stmt = $pdo->prepare("DELETE FROM subcategorias_logicas WHERE nombre_tabla = ? AND categoria_id = ?");
                $stmt->execute([$nombre_sub_tabla_eliminar, $categoria_id]);

                if ($stmt->rowCount()) {
                    // 2. Eliminar la tabla de productos física
                    eliminarTablaSubcategoria($pdo, $nombre_sub_tabla_eliminar);
                    $mensaje = "<p class='btn-success'>✅ Subcategoría y su tabla de productos eliminadas correctamente.</p>";
                } else {
                    $mensaje = "<p class='btn-warning'>⚠️ Advertencia: No se encontró la subcategoría para eliminar.</p>";
                }
            } catch (PDOException $e) {
                 $mensaje = "<p class='btn-danger'>❌ Error al eliminar la subcategoría: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// --- Cargar listado de subcategorías para la vista ---
if ($categoria_id) {
    try {
        $stmt_listado = $pdo->prepare("SELECT id, nombre_tabla FROM subcategorias_logicas WHERE categoria_id = ? ORDER BY nombre_tabla");
        $stmt_listado->execute([$categoria_id]);
        $subcategorias = $stmt_listado->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>❌ Error al cargar listado: " . $e->getMessage() . "</p>";
    }
}
?>

<div class="container main-content">
<h2>Gestión de Subcategorías para: "<?php echo htmlspecialchars(ucfirst($categoria_nombre)); ?>"</h2>
<?php echo $mensaje; ?>

<?php if ($categoria_id && $user_role === 'admin'): ?>
    <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
        <form action="gestor_subcategorias.php?categoria_id=<?php echo htmlspecialchars($categoria_id); ?>" method="POST">
            <div class="form-group">
                <label for="nombre_subcategoria" style="font-size: larger;">Crea la Subcategoría (el nombre de la tabla):</label>
                <input type="text" id="nombre_subcategoria" name="nombre_subcategoria" required>
            </div>
            <button type="submit" name="nueva_subcategoria" class="btn btn-primary">Crear Subcategoría/Tabla</button>
        </form>
    </div>
<?php endif; ?>

    <h3>Subcategorías/Tablas Creadas</h3>
    <?php if ($subcategorias): ?>
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Nombre de la Tabla (Subcategoría)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subcategorias as $subcat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(ucfirst($subcat['nombre_tabla'])); ?></td>
                        <td>
                            <a class="btn btn-dark btn-sm" href="ver_productos.php?categoria=<?php echo urlencode($subcat['nombre_tabla']); ?>">Ver Productos</a>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a class="btn btn-success btn-sm" href="agregar_producto.php?categoria=<?php echo urlencode($subcat['nombre_tabla']); ?>">Agregar Producto</a>
                            <form action="gestor_subcategorias.php?categoria_id=<?php echo htmlspecialchars($categoria_id); ?>" method="POST" style="display:inline;"
                                onsubmit="return confirm('¿Está seguro de eliminar la Subcategoría/Tabla <?php echo htmlspecialchars($subcat['nombre_tabla']); ?>? Se eliminarán todos sus productos.');">
                                <input type="hidden" name="nombre_sub_tabla_eliminar" value="<?php echo htmlspecialchars($subcat['nombre_tabla']); ?>">
                                <button class="btn btn-danger btn-sm" type="submit" name="eliminar_subcategoria">Eliminar</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay subcategorías creadas para esta categoría.</p>
    <?php endif; ?>

<a class="btn btn-dark" href="categorias.php">Volver a Categorías</a>

<?php include 'includes/footer.php'; ?>