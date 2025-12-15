<?php
// descargar_inventario.php
// Permite al usuario seleccionar una categoría principal para descargar su inventario
// junto con todas sus subcategorías (tablas con el mismo prefijo), o descargar todo el inventario.
// Formato de salida: CSV (compatible con Excel).

include 'includes/db.php';

$mensaje = '';
$categorias_disponibles = [];

// --- Paso 1: Obtener todas las categorías (tablas) de la base de datos ---
// ESTO ES DINÁMICO: Consulta la tabla de control 'categorias' para saber qué tablas existen.
    try {
        $stmt = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
        $categorias_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Esto es un error crítico si la tabla 'categorias' no existe
        $mensaje = "<p class='btn-danger'>Error al cargar las categorías: " . $e->getMessage() . "</p>";
    }

    // --- Paso 2: Lógica de Descarga (Si se recibe el formulario) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['categoria_base'])) {

        $categoria_base = $_POST['categoria_base'];
        $tablas_a_consultar = [];
        $nombre_archivo = '';

        // A) Caso: Descargar TODO
        if ($categoria_base === 'todos') {
            $tablas_a_consultar = $categorias_disponibles;
            // Cambiado a .csv
            $nombre_archivo = 'Inventario_Completo_' . date('Ymd_His') . '.csv';
        }
        // B) Caso: Descargar Categoría y sus Subcategorías
        else {
            // Validación de seguridad para el nombre base
            if (!preg_match('/^[a-z0-9_]+$/i', $categoria_base)) {
                $mensaje = "Error: Nombre de categoría base no válido.";
                goto end_script;
            }

            // Determinar qué tablas consultar: la categoría base + las subcategorías (basado en el prefijo)
            // ESTO ES DINÁMICO: Se verifica contra la lista actual de categorías.
            foreach ($categorias_disponibles as $cat_name) {
                // Un nombre es subcategoría si es exactamente el nombre base O si empieza con 'nombre_base_'
                if ($cat_name === $categoria_base || strpos($cat_name, $categoria_base . '_') === 0) {
                    $tablas_a_consultar[] = $cat_name;
                }
            }

            if (empty($tablas_a_consultar)) {
                $mensaje = "Error: No se encontraron tablas para la categoría base seleccionada.";
                goto end_script;
            }
            // Cambiado a .csv
            $nombre_archivo = 'Inventario_' . ucfirst($categoria_base) . '_' . date('Ymd_His') . '.csv';
        }

        $datos_inventario = [];

        // C) Recorrer las tablas seleccionadas y obtener los datos
        foreach ($tablas_a_consultar as $tabla) {
            try {
                // Consulta para obtener todos los productos de la tabla
                // Se añade el nombre de la tabla/categoría para identificar a dónde pertenece el producto
                $stmt = $pdo->query("SELECT '$tabla' AS CATEGORIA, CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD FROM `$tabla`");
                $resultados_tabla = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($resultados_tabla) {
                    $datos_inventario = array_merge($datos_inventario, $resultados_tabla);
                }
            } catch (PDOException $e) {
                // Si la tabla existe en 'categorias' pero no en la BD, lo ignoramos para no detener la descarga
                continue;
            }
        }

        // D) Generar y enviar el archivo CSV
        if (!empty($datos_inventario)) {
            // ************************************************
            // CONFIGURACIÓN PARA CSV (.csv)
            // ************************************************
            // 1. Cabecera Content-Type para CSV
            header('Content-Type: text/csv; charset=utf-8');
            // 2. Cabecera con la extensión .csv
            header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

            $output = fopen('php://output', 'w');

            // Cabeceras del archivo CSV (Nombres de las columnas)
            $columnas = ['CATEGORIA', 'CODIGO_INTERNO', 'CODIGO_BARRAS', 'PRODUCTO', 'CANTIDAD', 'UNIDAD'];
            // Se usa el punto y coma (;) como delimitador para mejorar la compatibilidad con Excel.
            fputcsv($output, $columnas, ';');

            // Escribir las filas de datos
            foreach ($datos_inventario as $row) {
                fputcsv($output, [
                    $row['CATEGORIA'],
                    $row['CODIGO'],
                    $row['CODIGO_BARRAS'],
                    $row['PRODUCTO'],
                    $row['CANT'],
                    $row['UNIDAD']
                ], ';');
            }

            fclose($output);
            exit(); // Detener el script después de la descarga
        } else {
            $mensaje = "No se encontraron productos en las categorías seleccionadas.";
        }
    }

// Etiqueta para manejar el flujo de errores y evitar el envío de cabeceras de descarga
end_script:
?>