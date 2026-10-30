<?php
date_default_timezone_set("America/La_Paz");

$host     = "127.0.0.1";
$usuario  = "root";
$clave    = "";
$base     = "regencia_ue";
$puerto   = 3305;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli($host, $usuario, $clave, $base, $puerto);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("<h3>No se pudo conectar a la base de datos</h3>"
      . "<p>Revisa que MySQL esté encendido en XAMPP, que el puerto sea $puerto "
      . "y que hayas importado el archivo regencia_ue.sql.</p>"
      . "<p>Detalle: " . htmlspecialchars($e->getMessage()) . "</p>");
}


function h($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, "UTF-8");
}


function nulo($texto) {
    $texto = trim((string) $texto);
    return $texto === "" ? null : $texto;
}

function fecha_valida($fecha) {
    $d = DateTime::createFromFormat("Y-m-d", (string) $fecha);
    return $d && $d->format("Y-m-d") === $fecha;
}

function fecha_es($fecha) {
    return $fecha ? date("d/m/Y", strtotime($fecha)) : "";
}

function dia_semana($fecha) {
    $dias = ["", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"];
    return $dias[(int) date("N", strtotime($fecha))];
}

const SQL_CURSO = "CONCAT(c.grado, '° ', n.nombre, ' \"', c.paralelo, '\"')";

const NOMBRE_UE = "Unidad Educativa Guadalupe";
