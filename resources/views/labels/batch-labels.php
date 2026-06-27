<?php $title = 'Batch Labels'; ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<div class="text-center mb-3 no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print All Labels</button>
    <a href="/batches/<?= $batchId ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Batch</a>
</div>
<div class="row g-3 justify-content-center">
    <?php foreach ($samples as $sample): ?>
    <div class="col-auto">
        <div class="label-container" style="width:3in;border:2px solid #333;padding:0.2in;font-family:'Courier New',monospace;font-size:10px;background:#fff;margin-bottom:0.1in;page-break-inside:avoid;">
            <div style="text-align:center;border-bottom:2px solid #000;padding-bottom:6px;margin-bottom:6px;">
                <strong style="font-size:14px;">PlexiQ LIMS</strong>
            </div>
            <table style="width:100%;font-size:9px;border-collapse:collapse;">
                <tr><td style="padding:1px 0;"><strong>Sample:</strong></td><td style="padding:1px 0;text-align:right;"><?= e($sample['sample_code']) ?></td></tr>
                <tr><td style="padding:1px 0;"><strong>Batch:</strong></td><td style="padding:1px 0;text-align:right;"><?= e($sample['batch_number'] ?? '—') ?></td></tr>
                <tr><td style="padding:1px 0;"><strong>Product:</strong></td><td style="padding:1px 0;text-align:right;"><?= e($sample['product_name'] ?? '—') ?></td></tr>
                <tr><td style="padding:1px 0;"><strong>Date:</strong></td><td style="padding:1px 0;text-align:right;"><?= date('d-M-Y') ?></td></tr>
            </table>
            <div style="text-align:center;margin-top:6px;padding-top:6px;border-top:1px solid #999;">
                <svg class="barcode" data-code="<?= e($sample['sample_code']) ?>"></svg>
            </div>
            <div style="text-align:center;font-size:8px;margin-top:3px;"><?= e($sample['sample_code']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.barcode').forEach(function(svg) {
    try { JsBarcode(svg, svg.dataset.code, { format: "CODE128", width: 1.5, height: 35, displayValue: false, margin: 0 }); } catch(e) {}
});
</script>
<style>
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 0.25in; background: #fff; }
    .label-container { border: 1px solid #000 !important; page-break-inside: avoid; }
}
</style>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
