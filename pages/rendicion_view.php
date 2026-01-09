<?php
require_once __DIR__ . '/../config/database.php';

$modo = $_GET['modo'] ?? 'insert';
$id_rms = isset($_GET['id_rms']) ? (int)$_GET['id_rms'] : null;

$remesa = null;
$rendiciones = [];

if ($modo === 'edit' && $id_rms) {
    try {

        // Remesa
        $stmt = $pdo->prepare("
            SELECT r.*, c.nombre_clt, m.mercancia_mrcc
            FROM remesa r
            LEFT JOIN clientes c ON c.id_clt = r.cliente_rms
            LEFT JOIN mercancias m ON m.id_mrcc = r.mercancia_rms
            WHERE r.id_rms = ?
            LIMIT 1
        ");
        $stmt->execute([$id_rms]);
        $remesa = $stmt->fetch(PDO::FETCH_ASSOC);

        // Rendiciones
        $stmt = $pdo->prepare("
            SELECT *
            FROM rendicion
            WHERE id_rms = ?
            ORDER BY id_rndcn ASC
        ");
        $stmt->execute([$id_rms]);
        $rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        error_log('[RENDICION_VIEW] ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rendición</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>

<h1>
    <?= $modo === 'insert' ? 'Nueva Rendición' : 'Editar Rendición' ?>
</h1>

<section id="bloque-remesa">
    <?php if ($modo === 'edit' && $remesa): ?>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($remesa['nombre_clt']) ?></p>
        <p><strong>Despacho:</strong> <?= htmlspecialchars($remesa['despacho_rms']) ?></p>
        <p><strong>Ref Cliente:</strong> <?= htmlspecialchars($remesa['ref_clte_rms']) ?></p>
        <p><strong>Mercancía:</strong> <?= htmlspecialchars($remesa['mercancia_mrcc']) ?></p>
    <?php else: ?>
        <p>Seleccione una remesa para comenzar.</p>
    <?php endif; ?>
</section>

<hr>

<section id="bloque-rendiciones">
    <h2>Conceptos Rendidos</h2>

    <table class="tabla">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Monto Cliente</th>
                <th>Monto Agencia</th>
                <th>IVA</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rendiciones): ?>
            <?php foreach ($rendiciones as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['concepto_rndcn'] ?? '') ?></td>
                    <td>$<?= number_format($r['monto_pago_rndcn'],0,',','.') ?></td>
                    <td>$<?= number_format($r['monto_gastos_agencia_rndcn'],0,',','.') ?></td>
                    <td>$<?= number_format($r['monto_iva_rndcn'],0,',','.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">No hay conceptos aún</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<script>
(function () {

    console.group('[RENDICION_VIEW]');
    console.log('Modo:', '<?= $modo ?>');
    console.log('ID RMS:', <?= $id_rms ?: 'null' ?>);
    console.groupEnd();

    function safeQuery(selector) {
        const el = document.querySelector(selector);
        if (!el) console.warn('[DOM] No encontrado:', selector);
        return el;
    }

    document.addEventListener('DOMContentLoaded', () => {
        safeQuery('#bloque-remesa');
        safeQuery('#bloque-rendiciones');
    });

})();
</script>

</body>
</html>