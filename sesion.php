<?php
session_start();
require_once "conexion.php";

if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
$usuario_id = (int) $_SESSION["usuario_id"];

$st = $conexion->prepare("SELECT nombre, rol FROM usuario WHERE id = ? AND activo = 1");
$st->bind_param("i", $usuario_id);
$st->execute();
$yo = $st->get_result()->fetch_assoc();
if (!$yo) {
    session_destroy();
    header("Location: login.php?inactivo=1");
    exit;
}
$_SESSION["nombre"] = $yo["nombre"];
$_SESSION["rol"]    = $yo["rol"];
$rol = $yo["rol"];

function solo_para(...$roles) {
    global $rol;
    if (!in_array($rol, $roles, true)) {
        header("Location: index.php?sin_permiso=1");
        exit;
    }
}

$gestion = $conexion->query("SELECT id, anio FROM gestion WHERE activa = 1 LIMIT 1")->fetch_assoc();
if (!$gestion) {
    die("No hay una gestión activa. Marca una gestión como activa en la tabla 'gestion'.");
}
$gestion_id = (int) $gestion["id"];

$estados = $conexion->query("SELECT id, codigo, descripcion FROM estado_asistencia ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$id_estado = array_column($estados, "id", "codigo");

$cursos = $conexion->query(
    "SELECT c.id, " . SQL_CURSO . " AS nombre, c.turno
     FROM curso c JOIN nivel n ON n.id = c.nivel_id
     ORDER BY n.id, c.grado, c.paralelo, c.turno"
)->fetch_all(MYSQLI_ASSOC);
