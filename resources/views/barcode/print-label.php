<?php $title = 'Print Label'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Label: <?= e($entity[$codeField] ?? '') ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f8f9fa; }
    .label { width: 320px; border: 1px solid #212529; border-radius: 6px; padding: 14px; text-align: center; background: #fff; }
    .label .brand { font-size: 13px; font-weight: bold; letter-spacing: 2px; color: #0d6efd; text-transform: uppercase; }
    .label .entity-type { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
    .label .name { font-size: 15px; font-weight: bold; margin: 8px 0 2px; word-break: break-word; }
    .label .code { font-size: 12px; color: #495057; margin-bottom: 8px; font-family: 'Courier New', monospace; }
    .label .barcode { margin: 6px 0; }
    .label .footer { font-size: 10px; color: #adb5bd; margin-top: 6px; }
</style>
</head>
<body>
<div class="label">
    <div class="brand">PlexiQ LIMS</div>
    <div class="entity-type"><?= e(ucfirst($entityType)) ?></div>
    <div class="name"><?= e($entity[$nameField] ?? '') ?></div>
    <div class="code"><?= e($entity[$codeField] ?? '') ?></div>
    <div class="barcode">
        <svg id="barcodeSvg" width="290" height="60"></svg>
    </div>
    <div class="footer"><?= e($entity[$codeField] ?? '') ?></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
const barcodeData = <?= $barcodeData ?>;
JsBarcode('#barcodeSvg', barcodeData.code || 'PLEXIQ-' + barcodeData.id, {
    format: 'CODE128',
    displayValue: false,
    width: 2,
    height: 50
});
window.print();
</script>
</body>
</html>
