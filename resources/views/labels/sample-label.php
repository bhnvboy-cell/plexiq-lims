<?php $title = 'Sample Label'; ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<div class="text-center mb-3 no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Label</button>
    <a href="/samples/<?= $sample['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="d-flex justify-content-center">
<div class="label-container" style="width:3in;border:2px solid #333;padding:0.25in;font-family:'Courier New',monospace;font-size:12px;background:#fff;">
    <div style="text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:8px;">
        <strong style="font-size:16px;">PlexiQ LIMS</strong><br>
        <span style="font-size:10px;">Certificate of Analysis</span>
    </div>

    <table style="width:100%;font-size:10px;border-collapse:collapse;">
        <tr><td style="padding:2px 0;"><strong>Sample:</strong></td><td style="padding:2px 0;text-align:right;"><?= e($sample['sample_code']) ?></td></tr>
        <tr><td style="padding:2px 0;"><strong>Batch:</strong></td><td style="padding:2px 0;text-align:right;"><?= e($sample['batch_number'] ?? '—') ?></td></tr>
        <tr><td style="padding:2px 0;"><strong>Product:</strong></td><td style="padding:2px 0;text-align:right;"><?= e($sample['product_name'] ?? '—') ?></td></tr>
        <tr><td style="padding:2px 0;"><strong>Customer:</strong></td><td style="padding:2px 0;text-align:right;"><?= e($sample['customer_name'] ?? '—') ?></td></tr>
        <tr><td style="padding:2px 0;"><strong>Date:</strong></td><td style="padding:2px 0;text-align:right;"><?= date('d-M-Y') ?></td></tr>
        <tr><td style="padding:2px 0;"><strong>Status:</strong></td><td style="padding:2px 0;text-align:right;"><?= e($sample['status']) ?></td></tr>
    </table>

    <div style="text-align:center;margin-top:10px;padding-top:8px;border-top:1px solid #999;">
        <svg id="barcode"></svg>
    </div>
    <div style="text-align:center;font-size:9px;margin-top:4px;"><?= e($sample['sample_code']) ?></div>
</div>
</div>
<script>
try { JsBarcode("#barcode", "<?= e($sample['sample_code']) ?>", { format: "CODE128", width: 1.5, height: 40, displayValue: false, margin: 0 }); } catch(e) {}
</script>
<style>
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 0; background: #fff; }
    .label-container { border: 2px solid #000 !important; }
}
</style>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
