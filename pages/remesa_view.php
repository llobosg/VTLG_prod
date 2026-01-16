<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

$pdo = getDBConnection();

// Cargar remesa si hay ID
$id_rms = $_GET['id'] ?? null;
$remesa = null;
if ($id_rms && is_numeric($id_rms)) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                CASE 
                    WHEN r.mercancia_nombre IS NOT NULL AND r.mercancia_nombre != '' 
                    THEN r.mercancia_nombre
                    WHEN m.mercancia_mrcc IS NOT NULL 
                    THEN m.mercancia_mrcc
                    ELSE ''
                END AS mercancia_display
            FROM remesa r
            LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
            WHERE r.id_rms = ?
        ");
        $stmt->execute([(int)$id_rms]);
        $remesa = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al cargar remesa: " . $e->getMessage());
        $remesa = null;
    }
}

// === Obtener valores del ENUM para aduana_rms ===
$enumQuery = "SHOW COLUMNS FROM remesa LIKE 'aduana_rms'";
$enumResult = $pdo->query($enumQuery)->fetch();
$aduanas = [];
if ($enumResult && preg_match("/^enum\((.+)\)$/", $enumResult['Type'], $matches)) {
    preg_match_all("/'([^']+)'/", $matches[1], $enumMatches);
    $aduanas = $enumMatches[1];
}

// === Cargar últimos 10 registros con JOINs ===
$stmt = $pdo->query("
    SELECT 
        r.*,
        c.nombre_clt AS cliente_nombre,
        m.mercancia_mrcc AS mercancia_nombre,
        t.transporte_trnsprt AS transporte_nombre
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
    LEFT JOIN transporte t ON r.cia_transp_rms = t.id_trnsprt
    ORDER BY r.fecha_rms DESC, r.id_rms DESC
    LIMIT 10
");
$remesas = $stmt->fetchAll();

// Opciones estáticas
$estados = ['confección', 'solicitada', 'transferencia OK', 'Rendida', 'Nota Cobranza enviada', 'Nota Cobranza pagada', 'Cerrada OK', 'Cerrada con observaciones'];
$tramites = [
    'imp. ctdo anticip', 'imp. ctdo normal', 'exp. Normal', 'exp. sin valor comercial',
    'exp. Servicios', 'Reexportacion', 'Salida temporal', 'Reingreso',
    'Admision temporal', 'Almacen Particular'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Solicitudes de Remesa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-ship"></i> Solicitudes de Remesa
        </h2>
        <button class="btn-add" onclick="abrirSubmodalRemesa()"><i class="fas fa-plus"></i> Agregar Solicitud Remesa</button>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Cliente</th>
                        <th>Despacho</th>
                        <th>Trámite</th>
                        <th>Mercancía</th>
                        <th>Tesorería2</th>
                        <th>G.Oper.2</th>
                        <th>Transferir</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($remesas as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['mes_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['fecha_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['estado_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['despacho_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['tramite_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['mercancia_nombre'] ?? '') ?></td>
                        <td><?= number_format($r['total_tesoreria2_rms'] ?? 0, 0, ',', '.') ?></td>
                        <td><?= number_format($r['total_gastos_operacionales2_rms'] ?? 0, 0, ',', '.') ?></td>
                        <td><?= number_format($r['total_transferir_rms'] ?? 0, 0, ',', '.') ?></td>
                        <td style="text-align: center;">
                            <a href="#" class="btn-edit" title="Editar" onclick="cargarRemesa(<?= $r['id_rms'] ?>)">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(<?= $r['id_rms'] ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                            <a href="/pages/generar_pdf.php?id=<?= $r['id_rms'] ?>" target="_blank" class="btn-comment" title="PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SUBMODAL REMESA -->
<div id="submodal-remesa" class="submodal">
    <div class="submodal-content" style="max-width: 1305px; padding: 1.8rem; position: relative;">
        <!-- X de cierre -->
        <button class="close-prospecto" style="position: absolute; top: 1.2rem; right: 1.2rem;" onclick="cerrarSubmodalRemesa()">×</button>

        <!-- LOGO + TÍTULO + TIPO (alineados arriba) -->
        <div style="display: flex; align-items: flex-start; gap: 2rem; margin-bottom: 1.2rem;">
            <img src="/includes/LogoLG.jpeg" alt="Logo SIGA_LG" style="height: 60px;">

            <div style="display: flex; align-items: center; gap: 2rem;">
                <h3 style="margin: 0; font-size: 1.4rem;">SOLICITUD DE REMESA</h3>

                <div style="font-weight: bold; font-size: 1.4rem;">
                    TIPO:
                    <select id="tipo_rms"
                            style="font-weight: bold; text-transform: uppercase;
                                   margin-left: 0.4rem; padding: 0.3rem 0.6rem;
                                   border: 1px solid #ccc; border-radius: 4px;">
                        <option value="importación">Importación</option>
                        <option value="exportación">Exportación</option>
                    </select>
                </div>
            </div>
        </div>

        <form id="form-remesa">
            <input type="hidden" id="id_rms">
            <!-- SECCIÓN SUPERIOR – 8 COLUMNAS -->
            <div style="
                display: grid;
                grid-template-columns:
                    auto
                    15ch
                    auto
                    17ch
                    auto
                    20ch
                    auto
                    20ch;
                column-gap: 3ch;
                row-gap: 0.8rem;
                align-items: center;
                margin-bottom: 1.2rem;
            ">

                <!-- FECHA -->
                <div><label>FECHA:</label></div>
                <div><input type="date" id="fecha_rms" class="erp-input" required onchange="actualizarMes()"></div>

                <!-- ESTADO -->
                <div><label>ESTADO:</label></div>
                <div style="width: 140px; text-align: center; height: 2.0rem;>
                    <select id="estado_rms" class="erp-input">
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- CLIENTE -->
                <div><label>SRES.:</label></div>
                <div>
                    <select id="cliente_rms" name="cliente_rms" class="erp-input" onchange="cargarContactoDesdeAPI()">
                        <option value="">Seleccionar</option>
                        <?php
                        $stmt2 = $pdo->query("SELECT id_clt, nombre_clt FROM clientes ORDER BY nombre_clt");
                        while ($row = $stmt2->fetch()): ?>
                            <option value="<?= $row['id_clt'] ?>"><?= htmlspecialchars($row['nombre_clt']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- ATENCIÓN -->
                <div><label>ATN.:</label></div>
                <div><input type="text" id="contacto_rms" class="erp-input" readonly></div>

            </div>

            <!-- Fila MES y T/C -->
            <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 1.5rem; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label>MES:</label>
                    <input type="text" id="mes_rms" class="erp-input" readonly style="width: 120px; text-align: center; height: 2.0rem;">
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label>T/C:</label>
                    <input type="number" id="tipo_cambio_rms" class="erp-input" step="0.01" value="0.00" style="width: 120px; text-align: center; height: 2.0rem;">
                </div>
            </div>

            <!-- Sección Media: 4 columnas × 17 filas -->
            <div style="margin: 1.5rem 0;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">

                    <!-- FILAS NUEVAS (1-5) -->
                    <div>DESPACHO</div>
                    <div><input type="text" id="despacho_rms" class="erp-input" style="height: 2.0rem;"></div>
                    <div>TRÁMITE</div>
                    <div>
                        <select id="tramite_rms" class="erp-input" style="height: 2.0rem;">
                            <?php foreach ($tramites as $t): ?>
                                <option><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>REF.CLTE.</div>
                    <div style="grid-column: span 2;"><input type="text" id="ref_clte_rms" class="erp-input" style="height: 2.0rem;"></div>
                    <div></div>

                    <div>ADUANA</div>
                    <div>
                        <select id="aduana_rms" class="erp-input" style="height: 2.0rem;">
                            <option value="">Seleccionar</option>
                            <?php foreach ($aduanas as $a): ?>
                                <option><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>CIA.TRANSP.M/N</div>
                    <div>
                        <select id="cia_transp_rms" class="erp-input" style="height: 2.0rem;">
                            <option value="">Seleccionar</option>
                            <?php
                            // ✅ CORREGIDO: id_trnsprt
                            $stmt2 = $pdo->query("SELECT id_trnsprt, transporte_trnsprt FROM transporte ORDER BY transporte_trnsprt");
                            while ($row = $stmt2->fetch()): ?>
                                <option value="<?= $row['id_trnsprt'] ?>"><?= htmlspecialchars($row['transporte_trnsprt']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php
                    // === DIAGNÓSTICO: Logging de valores de mercancía ===
                    $mercancia_id = $remesa['mercancia_rms'] ?? null;
                    $mercancia_nombre_cat = null;
                    $mercancia_nombre_libre = $remesa['mercancia_nombre'] ?? null;
                    $mercancia_display_final = '';

                    // Obtener nombre del catálogo si existe ID
                    if ($mercancia_id) {
                        try {
                            $mercStmt = $pdo->prepare("SELECT mercancia_mrcc FROM mercancias WHERE id_mrcc = ?");
                            $mercStmt->execute([$mercancia_id]);
                            $mercancia_nombre_cat = $mercStmt->fetchColumn();
                        } catch (Exception $e) {
                            error_log("Error al buscar mercancía en catálogo: " . $e->getMessage());
                        }
                    }

                    // Determinar valor final a mostrar
                    if (!empty($mercancia_nombre_libre)) {
                        $mercancia_display_final = $mercancia_nombre_libre;
                    } elseif (!empty($mercancia_nombre_cat)) {
                        $mercancia_display_final = $mercancia_nombre_cat;
                    } else {
                        $mercancia_display_final = '';
                    }

                    // === LOGGING DETALLADO ===
                    error_log("DIAGNÓSTICO MERCANCÍA - ID: " . json_encode($mercancia_id) . 
                            ", Nombre Catálogo: " . json_encode($mercancia_nombre_cat) . 
                            ", Nombre Libre: " . json_encode($mercancia_nombre_libre) . 
                            ", Display Final: " . json_encode($mercancia_display_final));

                    // === CAMPO DE MERCANCÍA ===
                    ?>
                    <!-- Campo Mercancía -->
                    <div>MERCANCÍA:</div>
                    <div style="position: relative; flex: 1;">
                        <input type="text" 
                            id="mercancia_rms" 
                            value="<?= htmlspecialchars($mercancia_display_final) ?>"
                            placeholder="Escriba o seleccione una mercancía..."
                            style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem;">
                        <div id="resultados-mercancia" 
                            style="position: absolute; z-index: 1000; background: white; border: 1px solid #ccc; 
                                    border-top: none; max-height: 200px; overflow-y: auto; width: 100%; 
                                    box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: none;"></div>
                    </div>
                    <div>MOTONAVE</div>
                    <div><input type="text" id="motonave_rms" class="erp-input" style="height: 2.0rem;"></div>

                    <!-- Fila 5: vacía -->
                    <div></div><div></div><div></div><div></div>

                    <!-- Fila 6: títulos de sección -->
                    <div style="grid-column: span 2; text-align: center; font-weight: bold;">TESORERÍA GENERAL DE LA REPÚBLICA</div>
                    <div style="grid-column: span 2; text-align: center; font-weight: bold;">GASTOS OPERACIONALES</div>

                    <!-- FILAS ORIGINALES (7-21) -->
                    <div></div><div></div><div></div><div></div>
                    <div>Dº AD-VALOREM</div><div><input type="text" id="d_ad_valores_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>GASTOS AGA</div><div><input type="text" id="gastos_aga_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>IMPTO. ADICIONAL</div><div><input type="text" id="impto_adicional_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>HONORARIOS</div><div><input type="text" id="honorarios_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>ALMACENAJE</div><div><input type="text" id="almacenaje_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>TRANSM. EDI</div><div><input type="text" id="transm_edi_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>I.V.A.</div><div><input type="text" id="iva_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div>GASTO LOCAL</div><div><input type="text" id="gasto_local_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div style="font-weight: bold;">TOTAL TESORERÍA</div><div><input type="text" id="total_tesoreria_rms" value="0" readonly class="input-number" style="max-width: 13ch;height: 2.0rem;"></div>
                    <div>FLETE LOCAL</div><div><input type="text" id="flete_local_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div></div><div></div><div>GATE IN</div><div><input type="text" id="gate_in_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div style="font-weight: bold;">VALOR CIF US$</div><div><input type="number" id="valor_cif_rms" step="0.01" value="0.00" class="erp-input" style="width: 100%; max-width: 13ch; height: 2.0rem;"></div>
                    <div>GASTOS OPERATIVOS</div><div><input type="text" id="gastos_operativos_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div></div><div></div><div>PÓLIZA CONTENEDOR</div><div><input type="text" id="poliza_contenedor_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div></div><div></div><div>SEGURO CARGA</div><div><input type="text" id="seguro_carga_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div></div><div></div><div>GICONA</div><div><input type="text" id="gicona_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div></div><div></div><div>OTROS</div><div><input type="text" id="otros_rms" value="0" class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    
                    <div style="font-weight: bold;">TOTAL TESORERÍA</div>
                    <div><input type="text" id="total_tesoreria2_rms" value="0" readonly class="input-number" style="max-width: 13ch; height: 2.0rem;"></div>
                    <div style="font-weight: bold;">SUB-TOTAL</div>
                    <div><input type="text" id="subtotal_gastos_operacionales_rms" value="0" readonly class="input-number" style="max-width: 13ch; font-weight: bold; height: 2.0rem;"></div>

                    <div style="font-weight: bold;">GASTOS OPERACIONALES</div>
                    <div><input type="text" id="total_gastos_operacionales2_rms" value="0" readonly class="input-number" style="max-width: 13ch; font-weight: bold; height: 2.0rem;"></div>
                    <div style="font-weight: bold;">I.V.A.</div>
                    <div><input type="text" id="iva_gastos_operacionales_rms" value="0" readonly class="input-number" style="max-width: 13ch; font-weight: bold; height: 2.0rem;"></div>
                    
                    <div style="font-weight: bold;">TOTAL A TRANSFERIR</div>
                    <div><input type="text" id="total_transferir_rms" value="0" readonly class="input-number" style="max-width: 13ch; font-weight: bold; height: 2.0rem;"></div>
                    <div style="font-weight: bold;">TOTAL</div>
                    <div><input type="text" id="total_gastos_operacionales_rms" value="0" readonly class="input-number" style="max-width: 13ch; font-weight: bold; height: 2.0rem;"></div>
                </div>
            </div>

            <!-- Sección Inferior: oculta en submodal -->
            <div class="section-inferior-submodal" style="display: none;"></div>

            <!-- Botones: subidos una fila -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 0.8rem;">
                <button type="button" class="btn-warning" onclick="abrirSubmodalAdjuntos()"><i class="fas fa-paperclip"></i> Adjuntos</button>
                <button type="button" class="btn-primary" onclick="guardarRemesa()"><i class="fas fa-save"></i> Guardar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- === SUBMODAL ADJUNTOS COMPLETO === -->
<div id="submodal-adjuntos" class="submodal">
    <div class="submodal-content" style="max-width: 600px;">
        <span class="submodal-close" onclick="cerrarSubmodalAdjuntos()">×</span>
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-paperclip"></i> Documentos Adjuntos</h3>

        <!-- Formulario de subida -->
        <form id="form-adjuntos-upload" enctype="multipart/form-data" style="margin-bottom: 1.2rem;">
            <input type="hidden" id="id_rms_adjunto">
            <div style="margin-bottom: 0.8rem;">
                <label style="display: block; margin-bottom: 0.4rem;">Subir nuevo archivo (PDF, JPG, PNG):</label>
                <input type="file" id="archivo_adjunto" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <button type="button" class="btn-primary" style="font-size: 0.9rem;" onclick="subirAdjunto()">
                <i class="fas fa-upload"></i> Subir Archivo
            </button>
        </form>

        <!-- Lista de adjuntos -->
        <div class="card" style="padding: 0.8rem;">
            <h4 style="margin: 0 0 0.8rem; font-size: 0.95rem;">Archivos actuales:</h4>
            <div class="table-container">
                <table class="data-table" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lista-adjuntos">
                        <tr><td colspan="3" style="text-align: center;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmación eliminar -->
<div id="modal-confirm" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h3 style="color: #ff9900; margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
        <p>¿Estás seguro de eliminar esta solicitud de remesa?</p>
        <form method="GET" action="/pages/remesa_logic.php" style="margin-top: 1.2rem; display: flex; gap: 1rem; justify-content: center;">
            <input type="hidden" name="delete" id="id-eliminar" value="">
            <button type="submit" class="btn-delete">Eliminar</button>
            <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<script>
let datosModificados = false;

function formatNumber(val, decimals = 0) {
    if (typeof val === 'string') val = parseFloat(val.replace(/\./g, '').replace(',', '.'));
    if (isNaN(val)) val = 0;
    return val.toLocaleString('es-CL', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function parseNumber(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.input-number').forEach(el => {
        el.value = formatNumber(el.value);
    });

    document.querySelectorAll('.input-number').forEach(el => {
        el.addEventListener('blur', function() {
            this.value = formatNumber(this.value);
            calcularTotales();
        });
        el.addEventListener('focus', function() {
            this.value = parseNumber(this.value).toString();
        });
        el.addEventListener('input', () => datosModificados = true);
    });
});

function actualizarMes() {
    const fecha = document.getElementById('fecha_rms').value;
    const mesInput = document.getElementById('mes_rms');
    if (fecha) {
        const mesNum = new Date(fecha).getMonth();
        const nombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        mesInput.value = nombres[mesNum];
    } else {
        mesInput.value = '';
    }
    datosModificados = true;
}

function cargarContactoDesdeAPI() {
    const idCliente = document.getElementById('cliente_rms').value;
    const contactoField = document.getElementById('contacto_rms');
    
    if (!idCliente) {
        contactoField.value = '';
        return;
    }
    
    fetch(`/api/get_contacto.php?id=${idCliente}`)
        .then(r => r.json())
        .then(data => {
            contactoField.value = data.contacto || '';
        })
        .catch(err => {
            contactoField.value = '';
            console.error('Error al cargar contacto:', err);
        });
}

function calcularTotales() {
    const getNum = id => parseNumber(document.getElementById(id)?.value) || 0;

    const d_ad_valores = getNum('d_ad_valores_rms');
    const impto_adicional = getNum('impto_adicional_rms');
    const almacenaje = getNum('almacenaje_rms');
    const iva = getNum('iva_rms');
    const total_tesoreria = d_ad_valores + impto_adicional + almacenaje + iva;

    const gastos = [
        'gastos_aga_rms', 'honorarios_rms', 'transm_edi_rms', 'gasto_local_rms',
        'flete_local_rms', 'gate_in_rms', 'gastos_operativos_rms',
        'poliza_contenedor_rms', 'seguro_carga_rms', 'gicona_rms', 'otros_rms'
    ];
    const subtotal = gastos.reduce((sum, id) => sum + getNum(id), 0);
    const iva_gastos = subtotal * 0.19;
    const total_gastos = subtotal + iva_gastos;

    const valor_cif = parseFloat(document.getElementById('valor_cif_rms')?.value) || 0;

    const set = (id, val, decimals = 0) => {
        document.getElementById(id).value = formatNumber(val, decimals);
    };

    set('total_tesoreria_rms', total_tesoreria);
    set('subtotal_gastos_operacionales_rms', subtotal);
    set('iva_gastos_operacionales_rms', iva_gastos);
    set('total_gastos_operacionales_rms', total_gastos);
    set('total_tesoreria2_rms', total_tesoreria + valor_cif);
    set('total_gastos_operacionales2_rms', total_gastos);
    set('total_transferir_rms', total_tesoreria + valor_cif + total_gastos);
}

function abrirSubmodalRemesa() {
    limpiarFormRemesa();
    document.getElementById('submodal-remesa').style.display = 'flex';
}

function cerrarSubmodalRemesa() {
    if (!datosModificados || confirm('¿Cerrar? Perderás los datos no guardados.')) {
        document.getElementById('submodal-remesa').style.display = 'none';
        datosModificados = false;
    }
}

function limpiarFormRemesa() {
    document.querySelectorAll('#form-remesa input, #form-remesa select').forEach(el => {
        if (el.type === 'number') {
            el.value = '0.00';
        } else if (el.type === 'date') {
            el.value = '';
        } else if (el.classList.contains('input-number')) {
            el.value = '0';
        } else {
            el.value = '';
        }
    });

    const setDefault = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };

    setDefault('estado_rms', 'confección');
    setDefault('tipo_rms', 'importación');
    setDefault('id_rms', '');

    datosModificados = false;
}

function cargarRemesa(id) {
    fetch(`/pages/remesa_logic.php?edit=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data) {
                for (let key in data) {
                    const el = document.getElementById(key);
                    if (!el) continue;
                    
                    // ✅ CORRECCIÓN ESPECIAL PARA MERCANCÍA
                    if (key === 'mercancia_rms') {
                        const valor = data.mercancia_display || data[key] || '';
                        el.value = valor;
                    } else if (el.type === 'number') {
                        el.value = data[key] ?? '0.00';
                    } else if (el.classList.contains('input-number')) {
                        const val = parseFloat(data[key]) || 0;
                        el.value = formatNumber(val);
                    } else {
                        el.value = data[key] ?? '';
                    }
                }
                datosModificados = false;
                document.getElementById('submodal-remesa').style.display = 'flex';
            }
        })
        .catch(err => {
            console.error('Error al cargar:', err);
            alert('Error al cargar la remesa.');
        });
}

function guardarRemesa() {
    const formData = new FormData();
    const id_rms = document.getElementById('id_rms').value;
    
    // Validaciones básicas
    const requiredFields = ['cliente_rms', 'mercancia_rms', 'despacho_rms', 'fecha_rms'];
    for (const field of requiredFields) {
        const element = document.getElementById(field);
        if (!element || !element.value.trim()) {
            alert('❌ El campo ' + field + ' es obligatorio.');
            return;
        }
    }

    // Obtener datos de mercancía
    const mercanciaInputValue = (document.getElementById('mercancia_rms')?.value || '').trim();
    const mercanciaSeleccionada = window.getMercanciaSeleccionada?.() || null;

    let mercanciaNombre = '';
    if (mercanciaSeleccionada) {
        mercanciaNombre = mercanciaSeleccionada.mercancia_mrcc;
    } else if (mercanciaInputValue) {
        mercanciaNombre = mercanciaInputValue;
    }

    formData.append('mercancia_rms', mercanciaNombre);

    // Preparar datos para envío
    formData.append('action', id_rms ? 'actualizar_remesa' : 'crear_remesa');
    formData.append('cliente_rms', document.getElementById('cliente_rms').value);
    formData.append('mercancia_rms', mercanciaNombre); // Enviar nombre, no ID
    formData.append('despacho_rms', document.getElementById('despacho_rms').value);
    formData.append('ref_clte_rms', document.getElementById('ref_clte_rms').value || '');
    formData.append('fecha_rms', document.getElementById('fecha_rms').value);
    formData.append('mes_rms', document.getElementById('mes_rms').value);
    formData.append('contacto_rms', document.getElementById('contacto_rms').value || '');
    formData.append('aduana_rms', document.getElementById('aduana_rms').value || '');
    formData.append('motonave_rms', document.getElementById('motonave_rms').value || '');
    formData.append('tramite_rms', document.getElementById('tramite_rms').value || '');
    formData.append('cia_transp_rms', document.getElementById('cia_transp_rms').value || '');

    if (id_rms) {
        formData.append('id_rms', id_rms);
    }

    fetch('/pages/remesa_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Verificar si se ingresó una mercancía nueva
                if (mercanciaInputValue && !mercanciaSeleccionada) {
                    if (confirm('¿Desea agregar "' + mercanciaInputValue + '" al catálogo de mercancías?')) {
                        // Guardar nueva mercancía
                        const newMercData = new FormData();
                        newMercData.append('action', 'crear_mercancia_catalogo');
                        newMercData.append('mercancia_mrcc', mercanciaInputValue);
                        
                        fetch('/pages/mercancias_logic.php', { method: 'POST', body: newMercData })
                            .then(r => r.json())
                            .then(mercData => {
                                if (mercData.success) {
                                    alert('✅ Nueva mercancía agregada al catálogo.');
                                } else {
                                    alert('⚠️ ' + mercData.message);
                                }
                                // Redirigir
                                if (data.id_rms) {
                                    window.location.href = `/pages/remesa_view.php?id=${data.id_rms}`;
                                } else {
                                    window.location.reload();
                                }
                            })
                            .catch(err => {
                                console.error('Error al guardar mercancía:', err);
                                // Redirigir igual
                                if (data.id_rms) {
                                    window.location.href = `/pages/remesa_view.php?id=${data.id_rms}`;
                                } else {
                                    window.location.reload();
                                }
                            });
                    } else {
                        // No guardar, solo redirigir
                        if (data.id_rms) {
                            window.location.href = `/pages/remesa_view.php?id=${data.id_rms}`;
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    // Mercancía existente o vacía
                    if (data.id_rms) {
                        window.location.href = `/pages/remesa_view.php?id=${data.id_rms}`;
                    } else {
                        window.location.reload();
                    }
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar.'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('❌ Error de conexión.');
        });
}

function abrirSubmodalAdjuntos() {
    const id = document.getElementById('id_rms').value;
    if (!id || id === '') {
        alert('Guarda primero la remesa antes de gestionar adjuntos.');
        return;
    }
    document.getElementById('id_rms_adjunto').value = id;
    cargarListaAdjuntos(id);
    document.getElementById('submodal-adjuntos').style.display = 'flex';
}

function cerrarSubmodalAdjuntos() {
    document.getElementById('submodal-adjuntos').style.display = 'none';
    // Limpiar input de archivo
    document.getElementById('archivo_adjunto').value = '';
}

function cargarListaAdjuntos(id_rms) {
    fetch(`/api/get_adjuntos.php?id=${id_rms}`)
        .then(r => r.json())
        .then(adjuntos => {
            const tbody = document.getElementById('lista-adjuntos');
            if (adjuntos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">Sin archivos adjuntos.</td></tr>';
                return;
            }
            tbody.innerHTML = adjuntos.map(a => `
                <tr>
                    <td>
                        <i class="fas fa-file-${a.nombre.endsWith('.pdf') ? 'pdf' : 'image'}" style="color: #e74c3c; margin-right: 0.4rem;"></i>
                        <a href="/uploads/${a.nombre}" target="_blank" style="text-decoration: none;">${a.nombre}</a>
                    </td>
                    <td>${a.fecha}</td>
                    <td>
                        <button class="btn-delete" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;" 
                                onclick="eliminarAdjunto(${a.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(err => {
            console.error('Error al cargar adjuntos:', err);
            document.getElementById('lista-adjuntos').innerHTML = '<tr><td colspan="3" style="text-align: center;">Error al cargar.</td></tr>';
        });
}

function subirAdjunto() {
    const id_rms = document.getElementById('id_rms_adjunto').value;
    const fileInput = document.getElementById('archivo_adjunto');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Selecciona un archivo.');
        return;
    }

    const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
        alert('Tipo de archivo no permitido. Usa PDF, JPG o PNG.');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('El archivo excede el límite de 5MB.');
        return;
    }

    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('id_rms', id_rms);

    fetch('/api/upload_adjunto.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Archivo subido correctamente.');
            cargarListaAdjuntos(id_rms);
            fileInput.value = ''; // reset
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error al subir:', err);
        alert('Error de conexión. Revisa la consola F12.');
    });
}

function eliminarAdjunto(id_adjunto) {
    if (!confirm('¿Eliminar este archivo? Esta acción no se puede deshacer.')) return;

    fetch('/api/delete_adjunto.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id_adjunto})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const id_rms = document.getElementById('id_rms_adjunto').value;
            cargarListaAdjuntos(id_rms);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error al eliminar:', err);
        alert('Error de conexión.');
    });
}

function confirmarEliminar(id) {
    document.getElementById('id-eliminar').value = id;
    document.getElementById('modal-confirm').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modal-confirm').style.display = 'none';
}

// === BÚSQUEDA INTELIGENTE MERCANCÍAS ===
(function() {
    const input = document.getElementById('mercancia_rms');
    if (!input) return;

    let resultadosDiv = null;
    let mercanciaSeleccionada = null;

    // Crear contenedor de resultados
    function crearResultados() {
        if (resultadosDiv) return;
        resultadosDiv = document.createElement('div');
        resultadosDiv.style.cssText = `
            position: absolute; z-index: 1000; background: white; border: 1px solid #ccc;
            border-top: none; max-height: 200px; overflow-y: auto; width: 100%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: none;
        `;
        input.parentNode.appendChild(resultadosDiv);
    }

    // Cerrar resultados
    function cerrarResultados() {
        if (resultadosDiv) {
            resultadosDiv.style.display = 'none';
        }
    }

    // Manejar selección
    function seleccionarMercancia(id, nombre) {
        mercanciaSeleccionada = { id_mrcc: id, mercancia_mrcc: nombre };
        input.value = nombre;
        cerrarResultados();
    }

    // Evento de escritura
    input.addEventListener('input', function() {
        const term = this.value.trim();
        cerrarResultados();

        if (term.length < 2) {
            mercanciaSeleccionada = null;
            return;
        }

        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`/api/get_mercancias_busqueda.php?term=${encodeURIComponent(term)}`)
                .then(res => res.json())
                .then(data => {
                    crearResultados();
                    if (!Array.isArray(data) || data.length === 0) {
                        resultadosDiv.style.display = 'none';
                        return;
                    }

                    resultadosDiv.innerHTML = '';
                    data.forEach(item => {
                        const el = document.createElement('div');
                        el.style.cssText = `
                            padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;
                            font-size: 0.95rem;
                        `;
                        el.textContent = item.mercancia_mrcc;
                        el.onclick = () => seleccionarMercancia(item.id_mrcc, item.mercancia_mrcc);
                        resultadosDiv.appendChild(el);
                    });
                    resultadosDiv.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error en búsqueda de mercancías:', err);
                    cerrarResultados();
                });
        }, 300);
    });

    // Al perder foco, verificar si es nuevo
    input.addEventListener('blur', function() {
        setTimeout(() => {
            if (resultadosDiv) {
                resultadosDiv.style.display = 'none';
            }
        }, 200);
    });

    // Guardar valor original para detectar cambios
    input.dataset.valorOriginal = input.value;

    // Exponer variable global para guardarRemesa()
    window.getMercanciaSeleccionada = function() {
        return mercanciaSeleccionada;
    };

    window.getMercanciaInputValue = function() {
        return input.value;
    };
})();

// === BÚSQUEDA INTELIGENTE MERCANCÍAS ===
(function() {
    const input = document.getElementById('mercancia_rms');
    if (!input) return;

    const resultadosDiv = document.getElementById('resultados-mercancia');
    
    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !resultadosDiv.contains(e.target)) {
            resultadosDiv.style.display = 'none';
        }
    });

    // Manejar selección
    window.seleccionarMercancia = function(id, nombre) {
        input.value = nombre;
        resultadosDiv.style.display = 'none';
        window.mercanciaSeleccionadaActual = { id_mrcc: id, mercancia_mrcc: nombre };
    };

    // Evento de escritura
    input.addEventListener('input', function() {
        const term = this.value.trim();
        resultadosDiv.style.display = 'none';
        resultadosDiv.innerHTML = '';
        window.mercanciaSeleccionadaActual = null;

        if (term.length < 2) return;

        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`/api/get_mercancias_busqueda.php?term=${encodeURIComponent(term)}`)
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) return;
                    
                    resultadosDiv.innerHTML = '';
                    data.forEach(item => {
                        const el = document.createElement('div');
                        el.style.cssText = `
                            padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;
                            font-size: 0.95rem;
                        `;
                        el.textContent = item.mercancia_mrcc;
                        el.onclick = () => window.seleccionarMercancia(item.id_mrcc, item.mercancia_mrcc);
                        resultadosDiv.appendChild(el);
                    });
                    resultadosDiv.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error en búsqueda de mercancías:', err);
                    resultadosDiv.style.display = 'none';
                });
        }, 300);
    });

    // Función para obtener la selección actual
    window.getMercanciaSeleccionada = function() {
        return window.mercanciaSeleccionadaActual || null;
    };
})();
</script>
</body>
</html>