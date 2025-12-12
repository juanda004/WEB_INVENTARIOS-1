<?php
//descargar_inventario.php
//Script para generar y descargar el inventario en formato CSV

include 'includes/db.php'; // Configuración de la base de datos

$categoria_descarga = isset($_GET['categoria']) ?$_GET['categoria']: 'todos';
$mensaje = '';
$datos_inventario = [];

//Función para obtener la losuta de todos las tablas de categorías
function obtenerTodasCategorias($pdo){
    try {
        $stmt = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
        return $stmt->fetchAll (PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        //En caso de error, retornar un array vacío
        return [];
    }
}

// 1. Determinar qué tablas consultar
if ($categoria_descarga === 'todos') {
    $tablas_consultar = obtenerTodasCategorias($pdo);
    $nombre_archivo = 'Inventario_Completo_' . date('Ymd_His') . '.csv';
} else {
    //Validar que el nombre de la categoria sea seguro
    if (!preg_match('/^[a-z0-9_]+$/i', $categoria_descarga)) {
        $mensaje = "<p class='btn-danger'>Nombre de categoría inválido.</p>";
    } else {
        $tablas_consultar = [$categoria_descarga];
        $nombre_archivo = 'Inventario_' . ucfirst($categoria_descarga) . '_' . date('Ymd_His') . '.csv';
    }
}
if (empty($mensaje)) {
    //2. Recorrer las tablas y obtener los datos
    foreach ($tablas_consultar as $tablas) {
        try {
            // Consulta para onetenr todos los productos de la tabla
            // Se añade el nombre de la tabla/categoria para identidicar a dónde pertenecen los datos
            $stmt = $pdo->query("SELECT '$tabla' AS CATEGORIA, CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD FROM `$tablas`");
            $resultados_tabla = $stmt->fetchAll(PDO::FETCH_ASSOC);

            //Si hay resultados, fusionarlos
            if ($resultados_tabla) {
                $datos_inventario = array_merge($datos_inventario, $resultados_tabla);
            }
        } catch (PDOException $e) {
            // Ignorar tablas que no existen o dan error y continuar.
            continue;
        }
    }
    //3. Generar y descargar el archivo CSV
    if (!empty($datos_inventario)) {
        //Establecer las cabeceras HTTP para la descarga de CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

        $output = fopnen('php://output', 'w');
        // Cabecera del CSV (Nombre de las columans)
        // Se definen manualmente para asegurar el orden y un nombre amigable
        $columnas =['CATEGORIA', 'CODIGO', 'CODIGO_BARRAS', 'PRODUCTO', 'CANT', 'UNIDAD'];
        fputcsv($output, $columnas, ';'); // Usamos el punto y coma (;) como delimitador

        // Escribir las filas de datos
        foreach ($datos_inventario as $row) {
            // Aseguramos el orden de las columnas sea el mismo que en las cabeceras
            $fputcsv($output, [
                $row['CATEGORIA'],
                $row['CODIGO'],
                $row['CODIGO_BARRAS'],
                $row['PRODUCTO'],
                $row['CANT'],
                $row['UNIDAD']
            ], ';');
        }

        fclose($output);
        exit (); // Terminar el script después de enviar el archivo
    } else {
        $mensaje = "<p class='btn-warning'>No se encontraron productos para la categoría seleccionada o en el inventario completo.</p>";
    }
}
// Si hay un error y no se envió el archivo, mostramos un mensaje
if (!empty($mensake)){
    // Es seguro que este archivo se incluye en una página HTML válida
    echo "<h1>Error al generar inventario</h1>";
    echo "<p class='btn-danger'>$mensaje</p>";
    echo "<p><a href='categorias.php'>Volver a gestor de categorías</a></p>";
}
?>