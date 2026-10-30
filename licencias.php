<?php
require "sesion.php";
$pagina = "licencias";

$error = "";

function aplicar_licencia($conexion, $id_estado, $inscripcion_id, $desde, $hasta, $aprobada) {
    $de   = $aprobada ? $id_estado["F"] : $id_estado["L"];
    $a    = $aprobada ? $id_estado["L"] : $id_estado["F"];
    $st = $conexion->prepare("UPDATE asistencia SET estado_id = ?, hora_llegada = NULL
                              WHERE inscripcion_id = ? AND fecha BETWEEN ? AND ? AND estado_id = ?");
    $st->bind_param("iissi", $a, $inscripcion_id, $desde, $hasta, $de);
    $st->execute();
    return $st->affected_rows;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    if ($accion === "nueva") {
        $insc   = (int) ($_POST["inscripcion_id"] ?? 0);
        $desde  = $_POST["fecha_inicio"] ?? "";
        $hasta  = $_POST["fecha_fin"] ?? "";
        $motivo = trim($_POST["motivo"] ?? "");
        $estado = ($_POST["estado"] ?? "") === "Aprobada" ? "Aprobada" : "Pendiente";

        if (!$insc || !fecha_valida($desde) || !fecha_valida($hasta) || $motivo === "") {
            $error = "Completa estudiante, fechas y motivo.";
        } elseif ($hasta < $desde) {
            $error = "La fecha final no puede ser anterior a la inicial.";
        } else {
            
            $st = $conexion->prepare("SELECT MIN(et.tutor_id) AS tutor_id FROM inscripcion i
                                      LEFT JOIN estudiante_tutor et ON et.estudiante_id = i.estudiante_id
                                      WHERE i.id = ? AND i.gestion_id = ?");
            $st->bind_param("ii", $insc, $gestion_id);
            $st->execute();
            $tutor_id = $st->get_result()->fetch_assoc()["tutor_id"] ?? null;

            $st = $conexion->prepare("INSERT INTO licencia (inscripcion_id, tutor_id, fecha_inicio, fecha_fin, motivo, estado, usuario_id)
                                      VALUES (?,?,?,?,?,?,?)");
            $st->bind_param("iissssi", $insc, $tutor_id, $desde, $hasta, $motivo, $estado, $usuario_id);
            $st->execute();

            $cambios = $estado === "Aprobada" ? aplicar_licencia($conexion, $id_estado, $insc, $desde, $hasta, true) : 0;
            header("Location: licencias.php?ok=nueva&cambios=$cambios");
            exit;
        }
    }

    if ($accion === "estado") {
        $id     = (int) ($_POST["id"] ?? 0);
        $estado = $_POST["estado"] ?? "";
        if (in_array($estado, ["Aprobada", "Rechazada"])) {
            $st = $conexion->prepare("SELECT inscripcion_id, fecha_inicio, fecha_fin, estado FROM licencia WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $lic = $st->get_result()->fetch_assoc();
            if ($lic && $lic["estado"] !== $estado) {
                $st = $conexion->prepare("UPDATE licencia SET estado = ? WHERE id = ?");
                $st->bind_param("si", $estado, $id);
                $st->execute();
                $cambios = aplicar_licencia($conexion, $id_estado, $lic["inscripcion_id"], $lic["fecha_inicio"], $lic["fecha_fin"], $estado === "Aprobada");
                header("Location: licencias.php?ok=" . strtolower($estado) . "&cambios=$cambios");
                exit;
            }
        }
        header("Location: licencias.php");
        exit;
    }
}

$st = $conexion->prepare(
    "SELECT i.id, " . SQL_CURSO . " AS curso,
            CONCAT(e.apellido_paterno, ' ', IFNULL(e.apellido_materno, ''), ', ', e.nombres) AS nombre
     FROM inscripcion i
     JOIN estudiante e ON e.id = i.estudiante_id
     JOIN curso c ON c.id = i.curso_id JOIN nivel n ON n.id = c.nivel_id
     WHERE i.gestion_id = ? AND i.estado = 'Efectivo'
     ORDER BY n.id, c.grado, c.paralelo, e.apellido_paterno, e.nombres"
);
$st->bind_param("i", $gestion_id);
$st->execute();
$inscritos = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$filtro = $_GET["filtro"] ?? "";
if (!in_array($filtro, ["Pendiente", "Aprobada", "Rechazada"])) $filtro = "";
$st = $conexion->prepare(
    "SELECT l.*, " . SQL_CURSO . " AS curso,
            CONCAT(e.apellido_paterno, ' ', IFNULL(e.apellido_materno, ''), ', ', e.nombres) AS nombre,
            CONCAT(t.nombres, ' ', t.apellidos) AS tutor, t.telefono,
            DATEDIFF(l.fecha_fin, l.fecha_inicio) + 1 AS dias
     FROM licencia l
     JOIN inscripcion i ON i.id = l.inscripcion_id
     JOIN estudiante e  ON e.id = i.estudiante_id
     JOIN curso c ON c.id = i.curso_id JOIN nivel n ON n.id = c.nivel_id
     LEFT JOIN tutor t ON t.id = l.tutor_id
     WHERE i.gestion_id = ? AND (? = '' OR l.estado = ?)
     ORDER BY l.estado = 'Pendiente' DESC, l.fecha_inicio DESC"
);
$st->bind_param("iss", $gestion_id, $filtro, $filtro);
$st->execute();
$licencias = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$mensajes = [
    "nueva"     => "Licencia registrada.",
    "aprobada"  => "Licencia aprobada.",
    "rechazada" => "Licencia rechazada.",
];
$ok = $_GET["ok"] ?? "";
$cambios = (int) ($_GET["cambios"] ?? 0);

include "encabezado.php";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 m-0">Licencias</h1>
    <a href="index.php" class="btn btn-outline-secondary">&larr; Volver al inicio</a>
</div>

<?php if (isset($mensajes[$ok])): ?>
    <div class="alert alert-success">
        <?= $mensajes[$ok] ?>
        <?php if ($cambios): ?>
            Se actualizaron <?= $cambios ?> día(s) de asistencia ya registrados
            (<?= $ok === "rechazada" ? "de Licencia a Falta" : "de Falta a Licencia" ?>).
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<!-- Registrar licencia -->
<form method="post" class="card card-body mb-4">
    <input type="hidden" name="accion" value="nueva">
    <h2 class="h5 mb-3">Registrar licencia</h2>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Estudiante</label>
            <select name="inscripcion_id" class="form-select" required>
                <option value="">-- Elige al estudiante --</option>
                <?php $grupo = null;
                foreach ($inscritos as $i):
                    if ($i["curso"] !== $grupo) { if ($grupo !== null) echo "</optgroup>"; $grupo = $i["curso"]; echo '<optgroup label="' . h($grupo) . '">'; } ?>
                    <option value="<?= $i["id"] ?>"><?= h($i["nombre"]) ?></option>
                <?php endforeach; if ($grupo !== null) echo "</optgroup>"; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Desde</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= date("Y-m-d") ?>" required></div>
        <div class="col-md-3"><label class="form-label">Hasta</label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= date("Y-m-d") ?>" required></div>
        <div class="col-md-6"><label class="form-label">Motivo</label>
            <input name="motivo" class="form-control" maxlength="200" required placeholder="Ej.: cita médica, viaje familiar"></div>
        <div class="col-md-3"><label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option>Aprobada</option>
                <option>Pendiente</option>
            </select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Registrar</button></div>
    </div>
    <p class="small text-muted mt-2 mb-0">Con una licencia aprobada, el estudiante aparece con "L" al pasar asistencia en esos días.</p>
</form>

<!-- Lista -->
<div class="d-flex gap-2 mb-2">
    <?php foreach (["" => "Todas", "Pendiente" => "Pendientes", "Aprobada" => "Aprobadas", "Rechazada" => "Rechazadas"] as $valor => $texto): ?>
        <a class="btn btn-sm <?= $filtro === $valor ? "btn-dark" : "btn-outline-dark" ?>" href="licencias.php?filtro=<?= $valor ?>"><?= $texto ?></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>Estudiante</th><th>Curso</th><th>Desde</th><th>Hasta</th><th class="text-center">Días</th>
                <th>Motivo</th><th>Tutor</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$licencias): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No hay licencias.</td></tr>
        <?php endif; ?>
        <?php foreach ($licencias as $l):
            $color = ["Pendiente" => "warning", "Aprobada" => "success", "Rechazada" => "danger"][$l["estado"]]; ?>
            <tr>
                <td><?= h($l["nombre"]) ?></td>
                <td><?= h($l["curso"]) ?></td>
                <td><?= fecha_es($l["fecha_inicio"]) ?></td>
                <td><?= fecha_es($l["fecha_fin"]) ?></td>
                <td class="text-center"><?= $l["dias"] ?></td>
                <td><?= h($l["motivo"]) ?></td>
                <td><?= h($l["tutor"]) ?><br><small class="text-muted"><?= h($l["telefono"]) ?></small></td>
                <td><span class="badge text-bg-<?= $color ?>"><?= $l["estado"] ?></span></td>
                <td class="text-end text-nowrap">
                    <?php foreach (["Aprobada" => ["Aprobar", "success"], "Rechazada" => ["Rechazar", "danger"]] as $nuevo => [$texto, $c]):
                        if ($l["estado"] === $nuevo) continue; ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id" value="<?= $l["id"] ?>">
                            <input type="hidden" name="estado" value="<?= $nuevo ?>">
                            <button class="btn btn-sm btn-outline-<?= $c ?>"><?= $texto ?></button>
                        </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include "pie.php"; ?>
