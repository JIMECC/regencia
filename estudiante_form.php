<?php
require "sesion.php";
$pagina = "estudiantes";

$id    = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$error = "";

$d = [
    "rude" => "", "ci" => "", "nombres" => "", "apellido_paterno" => "", "apellido_materno" => "",
    "fecha_nacimiento" => "", "genero" => "", "direccion" => "",
    "curso_id" => "", "estado" => "Efectivo",
    "tutor_id" => "", "t_ci" => "", "t_nombres" => "", "t_apellidos" => "", "t_telefono" => "", "parentesco" => "Madre",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($d as $campo => $v) $d[$campo] = trim($_POST[$campo] ?? "");

    if ($d["nombres"] === "" || $d["apellido_paterno"] === "" || !(int) $d["curso_id"]) {
        $error = "Completa al menos nombres, apellido paterno y curso.";
    } elseif ($d["t_nombres"] !== "" xor $d["t_apellidos"] !== "") {
        $error = "Para registrar al tutor escribe su nombre y sus apellidos.";
    } else {
        $rude   = nulo($d["rude"]);   $ci = nulo($d["ci"]);
        $ap_mat = nulo($d["apellido_materno"]);
        $fnac   = fecha_valida($d["fecha_nacimiento"]) ? $d["fecha_nacimiento"] : null;
        $genero = in_array($d["genero"], ["M", "F"]) ? $d["genero"] : null;
        $dir    = nulo($d["direccion"]);
        $curso  = (int) $d["curso_id"];
        $estado = in_array($d["estado"], ["Efectivo", "Retirado", "Trasladado"]) ? $d["estado"] : "Efectivo";

        $conexion->begin_transaction();
        try {
            // Estudiante
            if ($id) {
                $st = $conexion->prepare("UPDATE estudiante SET rude=?, ci=?, nombres=?, apellido_paterno=?, apellido_materno=?,
                                          fecha_nacimiento=?, genero=?, direccion=? WHERE id=?");
                $st->bind_param("ssssssssi", $rude, $ci, $d["nombres"], $d["apellido_paterno"], $ap_mat, $fnac, $genero, $dir, $id);
                $st->execute();
            } else {
                $st = $conexion->prepare("INSERT INTO estudiante (rude, ci, nombres, apellido_paterno, apellido_materno,
                                          fecha_nacimiento, genero, direccion) VALUES (?,?,?,?,?,?,?,?)");
                $st->bind_param("ssssssss", $rude, $ci, $d["nombres"], $d["apellido_paterno"], $ap_mat, $fnac, $genero, $dir);
                $st->execute();
                $id = $conexion->insert_id;
            }

            $hoy = date("Y-m-d");
            $st = $conexion->prepare("INSERT INTO inscripcion (estudiante_id, curso_id, gestion_id, fecha_inscripcion, estado)
                                      VALUES (?,?,?,?,?)
                                      ON DUPLICATE KEY UPDATE curso_id = VALUES(curso_id), estado = VALUES(estado)");
            $st->bind_param("iiiss", $id, $curso, $gestion_id, $hoy, $estado);
            $st->execute();

            if ($d["t_nombres"] !== "") {
                $t_ci  = nulo($d["t_ci"]);
                $t_tel = nulo($d["t_telefono"]);
                $tutor_id = (int) $d["tutor_id"];

                if (!$tutor_id && $t_ci) {
                    $st = $conexion->prepare("SELECT id FROM tutor WHERE ci = ?");
                    $st->bind_param("s", $t_ci);
                    $st->execute();
                    $tutor_id = (int) ($st->get_result()->fetch_assoc()["id"] ?? 0);
                }
                if ($tutor_id) {
                    $st = $conexion->prepare("UPDATE tutor SET ci=?, nombres=?, apellidos=?, telefono=? WHERE id=?");
                    $st->bind_param("ssssi", $t_ci, $d["t_nombres"], $d["t_apellidos"], $t_tel, $tutor_id);
                    $st->execute();
                } else {
                    $st = $conexion->prepare("INSERT INTO tutor (ci, nombres, apellidos, telefono) VALUES (?,?,?,?)");
                    $st->bind_param("ssss", $t_ci, $d["t_nombres"], $d["t_apellidos"], $t_tel);
                    $st->execute();
                    $tutor_id = $conexion->insert_id;
                }
                $parentesco = nulo($d["parentesco"]) ?? "Tutor";
                $st = $conexion->prepare("INSERT INTO estudiante_tutor (estudiante_id, tutor_id, parentesco) VALUES (?,?,?)
                                          ON DUPLICATE KEY UPDATE parentesco = VALUES(parentesco)");
                $st->bind_param("iis", $id, $tutor_id, $parentesco);
                $st->execute();
            }

            $conexion->commit();
            header("Location: estudiantes.php?ok=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            if (!(int) ($_POST["id"] ?? 0)) $id = 0;   // si era nuevo, sigue siendo nuevo
            $error = $e->getCode() == 1062
                ? "Ya existe un registro con ese RUDE o CI. Revisa los datos."
                : "No se pudo guardar: " . $e->getMessage();
        }
    }
}

elseif ($id) {
    $st = $conexion->prepare(
        "SELECT e.*, i.curso_id, i.estado,
                t.id AS tutor_id, t.ci AS t_ci, t.nombres AS t_nombres, t.apellidos AS t_apellidos,
                t.telefono AS t_telefono, et.parentesco
         FROM estudiante e
         LEFT JOIN inscripcion i ON i.estudiante_id = e.id AND i.gestion_id = ?
         LEFT JOIN estudiante_tutor et ON et.estudiante_id = e.id
              AND et.tutor_id = (SELECT MIN(tutor_id) FROM estudiante_tutor WHERE estudiante_id = e.id)
         LEFT JOIN tutor t ON t.id = et.tutor_id
         WHERE e.id = ?"
    );
    $st->bind_param("ii", $gestion_id, $id);
    $st->execute();
    $fila = $st->get_result()->fetch_assoc();
    if (!$fila) { header("Location: estudiantes.php"); exit; }
    foreach ($d as $campo => $v) $d[$campo] = $fila[$campo] ?? $v;
}

include "encabezado.php";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 m-0"><?= $id ? "Editar estudiante" : "Nuevo estudiante" ?></h1>
    <a href="estudiantes.php" class="btn btn-outline-secondary">&larr; Volver a la lista</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="tutor_id" value="<?= h($d["tutor_id"]) ?>">

    <div class="card mb-4">
        <div class="card-header">Datos del estudiante</div>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">Nombres *</label>
                <input name="nombres" class="form-control" maxlength="60" required value="<?= h($d["nombres"]) ?>"></div>
            <div class="col-md-4"><label class="form-label">Apellido paterno *</label>
                <input name="apellido_paterno" class="form-control" maxlength="40" required value="<?= h($d["apellido_paterno"]) ?>"></div>
            <div class="col-md-4"><label class="form-label">Apellido materno</label>
                <input name="apellido_materno" class="form-control" maxlength="40" value="<?= h($d["apellido_materno"]) ?>"></div>
            <div class="col-md-3"><label class="form-label">Código RUDE</label>
                <input name="rude" class="form-control" maxlength="20" value="<?= h($d["rude"]) ?>"></div>
            <div class="col-md-3"><label class="form-label">CI</label>
                <input name="ci" class="form-control" maxlength="15" value="<?= h($d["ci"]) ?>"></div>
            <div class="col-md-3"><label class="form-label">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control" value="<?= h($d["fecha_nacimiento"]) ?>"></div>
            <div class="col-md-3"><label class="form-label">Género</label>
                <select name="genero" class="form-select">
                    <option value="">—</option>
                    <option value="F" <?= $d["genero"] === "F" ? "selected" : "" ?>>Femenino</option>
                    <option value="M" <?= $d["genero"] === "M" ? "selected" : "" ?>>Masculino</option>
                </select></div>
            <div class="col-12"><label class="form-label">Dirección</label>
                <input name="direccion" class="form-control" maxlength="150" value="<?= h($d["direccion"]) ?>"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Inscripción · Gestión <?= h($gestion["anio"]) ?></div>
        <div class="card-body row g-3">
            <div class="col-md-8"><label class="form-label">Curso *</label>
                <select name="curso_id" class="form-select" required>
                    <option value="">-- Elige el curso --</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c["id"] ?>" <?= $c["id"] == $d["curso_id"] ? "selected" : "" ?>><?= h($c["nombre"] . " - " . $c["turno"]) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <?php foreach (["Efectivo", "Retirado", "Trasladado"] as $op): ?>
                        <option <?= $d["estado"] === $op ? "selected" : "" ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Los retirados o trasladados ya no aparecen al pasar asistencia.</div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Padre, madre o tutor</div>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">Nombres</label>
                <input name="t_nombres" class="form-control" maxlength="60" value="<?= h($d["t_nombres"]) ?>"></div>
            <div class="col-md-4"><label class="form-label">Apellidos</label>
                <input name="t_apellidos" class="form-control" maxlength="80" value="<?= h($d["t_apellidos"]) ?>"></div>
            <div class="col-md-4"><label class="form-label">Parentesco</label>
                <select name="parentesco" class="form-select">
                    <?php foreach (["Madre", "Padre", "Abuelo(a)", "Tío(a)", "Hermano(a)", "Tutor"] as $op): ?>
                        <option <?= $d["parentesco"] === $op ? "selected" : "" ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label">CI</label>
                <input name="t_ci" class="form-control" maxlength="15" value="<?= h($d["t_ci"]) ?>">
                <div class="form-text">Si ya está registrado (por un hermano), se usa el mismo tutor.</div></div>
            <div class="col-md-4"><label class="form-label">Teléfono / celular</label>
                <input name="t_telefono" class="form-control" maxlength="20" value="<?= h($d["t_telefono"]) ?>"></div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-lg">Guardar</button>
        <a href="estudiantes.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php include "pie.php"; ?>
