<?php
// index.php
// Este script muestra un resumen dinámico del inventario,
// consultando todas las categorías existentes en la base de datos, e incluyendo
// el conteo de subcategorías y el total de productos.

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$estadisticas_categorias = [];
$conteo_subcategorias_db = []; // Nuevo array para almacenar el conteo real desde la tabla logica
$categorias_base = [];

try {
    // 1. Obtener todas las categorías base (asumimos que la tabla 'categorias' solo lista tablas)
    $stmt_categorias = $pdo->query("SELECT id, nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $todas_las_categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener el conteo real de subcategorías desde la tabla 'subcategorias_logicas'
    // Asume que subcategorias_logicas tiene una columna 'categoria_id'
    $stmt_sub_count = $pdo->query("
        SELECT categoria_id, COUNT(id) as sub_count
        FROM subcategorias_logicas
        GROUP BY categoria_id
    ");
    // Mapear el resultado para acceso rápido (categoria_id => conteo)
    foreach ($stmt_sub_count->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $conteo_subcategorias_db[$row['categoria_id']] = $row['sub_count'];
    }

    // 3. Procesar las categorías para determinar el conteo de productos totales (incluyendo subtablas)
    if (!empty($todas_las_categorias)) {

        $estadisticas_productos_por_tabla = [];

        foreach ($todas_las_categorias as $cat_data) {
            $cat_id = $cat_data['id'];
            $cat_nombre = $cat_data['nombre_categoria'];

            // Asegurarse de que el nombre de la tabla sea seguro para la consulta
            if (!preg_match('/^[a-z0-9_]+$/i', $cat_nombre)) {
                continue;
            }

            // B) Contar productos para CADA tabla (categoría o subcategoría)
            try {
                $stmt_productos = $pdo->query("SELECT COUNT(*) FROM `$cat_nombre`");
                $conteo_productos = $stmt_productos->fetchColumn();
                $estadisticas_productos_por_tabla[$cat_nombre] = $conteo_productos;
            } catch (PDOException $e) {
                // Ignorar si la tabla no existe físicamente
                $estadisticas_productos_por_tabla[$cat_nombre] = 0;
            }

            // Solo las categorías base son relevantes para el dashboard principal
            if (strpos($cat_nombre, '_') === false) {
                $categorias_base[] = $cat_data;
            }
        }

        // 4. Consolidar estadísticas: Sumar productos de subcategorías a su base y añadir conteo de subcategorías real
        $estadisticas_finales = [];

        foreach ($categorias_base as $cat_base_data) {
            $cat_base_id = $cat_base_data['id'];
            $cat_base_nombre = $cat_base_data['nombre_categoria'];

            $total_productos = $estadisticas_productos_por_tabla[$cat_base_nombre] ?? 0;

            // El conteo de subcategorías ahora viene de la tabla subcategorias_logicas
            $conteo_sub = $conteo_subcategorias_db[$cat_base_id] ?? 0;

            // Sumar los productos de las subcategorías (aún usando la convención de prefijos, ya que las tablas sí siguen ese patrón)
            foreach ($estadisticas_productos_por_tabla as $tabla_nombre => $productos_count) {
                // Si el nombre de la tabla no es la base y empieza con el prefijo de la base (Ej: papeleria_libretas)
                if ($tabla_nombre !== $cat_base_nombre && strpos($tabla_nombre, $cat_base_nombre . '_') === 0) {
                    $total_productos += $productos_count;
                }
            }

            $estadisticas_finales[$cat_base_nombre] = [
                'productos' => $total_productos,
                'subcategorias' => $conteo_sub // Usamos el conteo real de la tabla logica
            ];
        }
        $estadisticas_categorias = $estadisticas_finales;
    }

} catch (PDOException $e) {
    // En caso de error, mostrar un mensaje claro
    $mensaje = "<p class='error'>❌ Error al cargar el dashboard: " . $e->getMessage() . "</p>";
    $estadisticas_categorias = [];
}
?>

<body>
    <div class="container main-content">
        <h2>Resumen del Inventario</h2><br>
        <?php echo $mensaje; ?>
        <div class="dashborad-stats">
            <?php if (!empty($estadisticas_categorias)): ?>
                <ul class="list-group">
                    <?php foreach ($estadisticas_categorias as $categoria_nombre => $datos): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="ver_productos.php?categoria=<?php echo urlencode($categoria_nombre); ?>">
                                <p style="font-size: x-large; margin: 0;">
                                    <!-- Nombre de la Categoría -->
                                    <strong><?php echo htmlspecialchars(ucfirst($categoria_nombre)); ?></strong>
                                </p>
                            </a>
                            <div class="d-flex gap-3">
                                <!-- Conteo de Subcategorías (Ahora de la tabla logica) -->
                                <span class="badge bg-info rounded-pill p-2" title="Total de Subcategorías">
                                    Subcategorías: <?php echo htmlspecialchars($datos['subcategorias']); ?>
                                </span>
                                <!-- Conteo de Productos -->
                                <span class="badge bg-primary rounded-pill p-2"
                                    title="Total de Productos (Incluyendo Subcategorías)">
                                    Productos: <?php echo htmlspecialchars($datos['productos']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No hay categorías o productos disponibles en el inventario.</p>
            <?php endif; ?>
        </div>
        <hr>
        <?php include 'includes/footer.php'; ?>
    </div>
