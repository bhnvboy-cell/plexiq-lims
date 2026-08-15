<?php $title = e($param['parameter_name']) . ' - SPC'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-graph-up me-2"></i><?= e($param['parameter_name']) ?>
        <small class="text-muted fs-6 ms-2">(<?= e($param['parameter_code']) ?>)</small>
    </h4>
    <a href="/spc" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-collection stat-icon"></i>
            <div class="stat-value"><?= $stats['n'] ?? 0 ?></div>
            <div class="stat-label">Readings</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-bar-chart stat-icon"></i>
            <div class="stat-value"><?= $stats['mean'] ?? '-' ?></div>
            <div class="stat-label">Mean (<?= e($param['unit']) ?>)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-arrow-left-right stat-icon"></i>
            <div class="stat-value"><?= $stats['stddev'] ?? '-' ?></div>
            <div class="stat-label">Std Dev</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-purple">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $cpk['cpk'] ?? '-' ?></div>
            <div class="stat-label">Cpk</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up me-1"></i>Control Chart</span>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="chartType" style="width:auto;">
                <option value="line">Line Chart</option>
                <option value="bar">Bar Chart</option>
                <option value="scatter">Scatter Plot</option>
                <option value="xbar">X-bar Chart</option>
                <option value="rchart">R Chart</option>
            </select>
            <button class="btn btn-sm btn-primary" id="calculateBtn"><i class="bi bi-calculator"></i> Calculate</button>
        </div>
    </div>
    <div class="card-body">
        <canvas id="spcChart" height="320"></canvas>
    </div>
</div>

<?php if (!empty($violations)): ?>
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle me-1"></i>Out-of-Control Alerts (Nelson Rules)
        <span class="badge bg-light text-danger ms-2"><?= count($violations) ?> violation(s)</span>
    </div>
    <div class="card-body">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Description</th>
                    <th>Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($violations as $v): ?>
                <tr>
                    <td><span class="badge bg-danger"><?= $v['rule'] ?></span></td>
                    <td><?= e($v['rule_text']) ?></td>
                    <td>#<?= $v['index'] + 1 ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <small class="text-muted mt-2 d-block">Refer to a CAPA or investigation when an out-of-control condition is confirmed.</small>
    </div>
</div>
<?php elseif (count($readings) >= 8): ?>
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <i class="bi bi-check-circle me-1"></i>Process In Control
    </div>
    <div class="card-body">
        <p class="mb-0">No Nelson rule violations detected across <?= count($readings) ?> readings.</p>
    </div>
</div>
<?php endif; ?>

<?php if ($cpk): ?>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-table me-1"></i>Statistical Summary</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="detail-label">Sample Size (n)</div>
                <div class="detail-value"><?= $cpk['n'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Mean</div>
                <div class="detail-value"><?= $cpk['mean'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Std Deviation</div>
                <div class="detail-value"><?= $cpk['stddev'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Range</div>
                <div class="detail-value"><?= $cpk['min'] ?> – <?= $cpk['max'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">USL (Spec Max)</div>
                <div class="detail-value"><?= $cpk['usl'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">LSL (Spec Min)</div>
                <div class="detail-value"><?= $cpk['lsl'] ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Target</div>
                <div class="detail-value"><?= $cpk['target'] ?? 'N/A' ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Cp</div>
                <div class="detail-value"><?= $cpk['cp'] ?? 'N/A' ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Cpk</div>
                <div class="detail-value"><?= $cpk['cpk'] ?? 'N/A' ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">CPU</div>
                <div class="detail-value"><?= $cpk['cpu'] ?? 'N/A' ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">CPL</div>
                <div class="detail-value"><?= $cpk['cpl'] ?? 'N/A' ?></div>
            </div>
            <div class="col-md-3">
                <div class="detail-label">Cpm</div>
                <div class="detail-value"><?= $cpk['cpm'] ?? 'N/A' ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-1"></i>Readings</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reading Date</th>
                    <th>Value (<?= e($param['unit']) ?>)</th>
                    <th>Batch</th>
                    <th>Entered By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($readings as $i => $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($r['reading_date'])) ?></td>
                    <td>
                        <span class="fw-bold <?= ($param['spec_min'] !== null && $r['value'] < $param['spec_min']) || ($param['spec_max'] !== null && $r['value'] > $param['spec_max']) ? 'text-danger' : 'text-success' ?>">
                            <?= e($r['value']) ?>
                        </span>
                    </td>
                    <td><?= e($r['batch_id'] ?? '-') ?></td>
                    <td><?= e($r['entered_by_name'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= e($r['notes'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($readings)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No readings recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const readings = <?= json_encode(array_reverse($readings)) ?>;
const param = <?= json_encode($param) ?>;
const violations = <?= json_encode($violations) ?>;
const violationIdx = new Set(violations.map(v => v.index));
let spcChart = null;

const labels = readings.map(r => new Date(r.reading_date).toLocaleDateString('en-IN', {day:'2-digit', month:'2-digit'}));
const values = readings.map(r => parseFloat(r.value));

function buildChart(type) {
    const ctx = document.getElementById('spcChart').getContext('2d');
    if (spcChart) spcChart.destroy();

    const hasSpecMin = param.spec_min !== null;
    const hasSpecMax = param.spec_max !== null;
    const hasUcl = param.ucl !== null;
    const hasLcl = param.lcl !== null;
    const hasTarget = param.spec_target !== null;

    let datasets = [];

    if (type === 'rchart') {
        const ranges = [];
        for (let i = 1; i < values.length; i++) {
            ranges.push(Math.abs(values[i] - values[i-1]));
        }
        const rLabels = labels.slice(1);
        const rMean = ranges.reduce((a, b) => a + b, 0) / ranges.length;
        const rUcl = rMean * 3.267;
        const rLcl = Math.max(0, rMean * 0);

        datasets.push({
            label: 'Moving Range',
            data: ranges,
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231,76,60,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
        });
        datasets.push({
            label: 'R̄ (' + rMean.toFixed(4) + ')',
            data: Array(rLabels.length).fill(rMean),
            borderColor: '#2b7be4',
            borderDash: [6, 3],
            pointRadius: 0,
            borderWidth: 2,
        });
        datasets.push({
            label: 'UCL_R',
            data: Array(rLabels.length).fill(rUcl),
            borderColor: '#e74c3c',
            borderDash: [4, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        });
        if (rLcl > 0) {
            datasets.push({
                label: 'LCL_R',
                data: Array(rLabels.length).fill(rLcl),
                borderColor: '#e74c3c',
                borderDash: [4, 4],
                pointRadius: 0,
                borderWidth: 1.5,
            });
        }

        spcChart = new Chart(ctx, {
            type: 'line',
            data: { labels: rLabels, datasets },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'R Chart - Moving Range', font: { size: 14 } },
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Moving Range (' + (param.unit || '') + ')' } },
                    x: { title: { display: true, text: 'Reading Date' } }
                }
            }
        });
        return;
    }

    if (type === 'xbar') {
        const subgroupSize = param.subgroup_size || 3;
        const subgroups = [];
        const subLabels = [];
        for (let i = 0; i < values.length; i += subgroupSize) {
            const group = values.slice(i, i + subgroupSize);
            if (group.length > 0) {
                subgroups.push(group.reduce((a, b) => a + b, 0) / group.length);
                subLabels.push(labels[Math.min(i + Math.floor(subgroupSize/2), labels.length - 1)]);
            }
        }
        const grandMean = subgroups.reduce((a, b) => a + b, 0) / subgroups.length;
        const avgRange = (() => {
            let total = 0, count = 0;
            for (let i = 0; i < values.length; i += subgroupSize) {
                const group = values.slice(i, i + subgroupSize);
                if (group.length > 1) {
                    total += Math.max(...group) - Math.min(...group);
                    count++;
                }
            }
            return count > 0 ? total / count : 0;
        })();
        const d2 = {2:1.128,3:1.693,4:2.059,5:2.326}[Math.min(subgroupSize,5)] || 1.693;
        const sigma = avgRange / d2;
        const a2 = {2:1.88,3:1.023,4:0.729,5:0.577}[Math.min(subgroupSize,5)] || 1.023;
        const xbarUcl = grandMean + a2 * avgRange;
        const xbarLcl = grandMean - a2 * avgRange;

        datasets.push({
            label: 'X̄ (Subgroup Mean)',
            data: subgroups,
            borderColor: '#2b7be4',
            backgroundColor: 'rgba(43,123,228,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
        });
        datasets.push({
            label: 'X̄̄ (Grand Mean: ' + grandMean.toFixed(4) + ')',
            data: Array(subgroups.length).fill(grandMean),
            borderColor: '#11998e',
            borderDash: [6, 3],
            pointRadius: 0,
            borderWidth: 2,
        });
        datasets.push({
            label: 'UCL',
            data: Array(subgroups.length).fill(xbarUcl),
            borderColor: '#e74c3c',
            borderDash: [4, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        });
        datasets.push({
            label: 'LCL',
            data: Array(subgroups.length).fill(xbarLcl),
            borderColor: '#e74c3c',
            borderDash: [4, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        });

        spcChart = new Chart(ctx, {
            type: 'line',
            data: { labels: subLabels, datasets },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'X-bar Chart (subgroup n=' + subgroupSize + ')', font: { size: 14 } },
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                },
                scales: {
                    y: { title: { display: true, text: param.parameter_name + ' (' + (param.unit || '') + ')' } },
                    x: { title: { display: true, text: 'Subgroup' } }
                }
            }
        });
        return;
    }

    const chartType = type === 'scatter' ? 'scatter' : type;
    const chartData = type === 'scatter'
        ? values.map((v, i) => ({ x: i + 1, y: v }))
        : values;

    datasets.push({
        label: param.parameter_name + ' (' + (param.unit || '') + ')',
        data: chartData,
        borderColor: '#2b7be4',
        backgroundColor: type === 'bar'
            ? values.map(v => {
                const outOfSpec = (hasSpecMin && v < param.spec_min) || (hasSpecMax && v > param.spec_max);
                return outOfSpec ? 'rgba(231,76,60,0.7)' : 'rgba(43,123,228,0.7)';
              })
            : 'rgba(43,123,228,0.15)',
        fill: type !== 'scatter' && type !== 'bar',
        tension: 0.3,
        pointRadius: type === 'scatter' ? 6 : 4,
        pointBackgroundColor: type === 'scatter'
            ? values.map(v => {
                const outOfSpec = (hasSpecMin && v < param.spec_min) || (hasSpecMax && v > param.spec_max);
                return outOfSpec ? '#e74c3c' : '#2b7be4';
              })
            : values.map((_, i) => violationIdx.has(i) ? '#e74c3c' : '#2b7be4'),
        pointBorderColor: values.map((_, i) => violationIdx.has(i) ? '#c0392b' : '#2b7be4'),
        pointRadius: values.map((_, i) => violationIdx.has(i) ? 7 : 4),
        showLine: type !== 'scatter',
    });

    if (hasSpecMin) {
        datasets.push({
            label: 'LSL (' + param.spec_min + ')',
            data: type === 'scatter' ? Array(values.length).fill({x:1,y:param.spec_min}).map((d,i)=>({x:i+1,y:param.spec_min})) : Array(labels.length).fill(param.spec_min),
            borderColor: '#f5a623',
            borderDash: [4, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        });
    }
    if (hasSpecMax) {
        datasets.push({
            label: 'USL (' + param.spec_max + ')',
            data: type === 'scatter' ? Array(values.length).fill({x:1,y:param.spec_max}).map((d,i)=>({x:i+1,y:param.spec_max})) : Array(labels.length).fill(param.spec_max),
            borderColor: '#e74c3c',
            borderDash: [4, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        });
    }
    if (hasTarget) {
        datasets.push({
            label: 'Target (' + param.spec_target + ')',
            data: type === 'scatter' ? Array(values.length).fill({x:1,y:param.spec_target}).map((d,i)=>({x:i+1,y:param.spec_target})) : Array(labels.length).fill(param.spec_target),
            borderColor: '#11998e',
            borderDash: [6, 3],
            pointRadius: 0,
            borderWidth: 1.5,
        });
    }
    if (hasUcl) {
        datasets.push({
            label: 'UCL (' + param.ucl + ')',
            data: type === 'scatter' ? Array(values.length).fill({x:1,y:param.ucl}).map((d,i)=>({x:i+1,y:param.ucl})) : Array(labels.length).fill(param.ucl),
            borderColor: '#e74c3c',
            borderDash: [8, 4],
            pointRadius: 0,
            borderWidth: 1,
        });
    }
    if (hasLcl) {
        datasets.push({
            label: 'LCL (' + param.lcl + ')',
            data: type === 'scatter' ? Array(values.length).fill({x:1,y:param.lcl}).map((d,i)=>({x:i+1,y:param.lcl})) : Array(labels.length).fill(param.lcl),
            borderColor: '#e74c3c',
            borderDash: [8, 4],
            pointRadius: 0,
            borderWidth: 1,
        });
    }

    spcChart = new Chart(ctx, {
        type: chartType,
        data: { labels: type === 'scatter' ? undefined : labels, datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: (type.charAt(0).toUpperCase() + type.slice(1)) + ' - ' + param.parameter_name, font: { size: 14 } },
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
            },
            scales: type !== 'scatter' ? {
                y: { title: { display: true, text: param.parameter_name + ' (' + (param.unit || '') + ')' } },
                x: { title: { display: true, text: 'Reading Date' } }
            } : {
                x: { title: { display: true, text: 'Reading #' } },
                y: { title: { display: true, text: param.parameter_name + ' (' + (param.unit || '') + ')' } }
            }
        }
    });
}

document.getElementById('chartType').addEventListener('change', function() {
    buildChart(this.value);
});

document.getElementById('calculateBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Calculating...';

    fetch('/spc/' + param.id + '/calculate')
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        let html = '<div class="card mb-4"><div class="card-header"><i class="bi bi-table me-1"></i>Statistical Summary</div><div class="card-body"><div class="row g-3">';
        const fields = [
            ['Sample Size (n)', data.n],
            ['Mean', data.mean],
            ['Std Deviation', data.stddev],
            ['Range', data.min + ' – ' + data.max],
            ['USL (Spec Max)', data.usl],
            ['LSL (Spec Min)', data.lsl],
            ['Target', data.target || 'N/A'],
            ['Cp', data.cp || 'N/A'],
            ['Cpk', data.cpk || 'N/A'],
            ['CPU', data.cpu || 'N/A'],
            ['CPL', data.cpl || 'N/A'],
            ['Cpm', data.cpm || 'N/A'],
        ];
        fields.forEach(f => {
            html += '<div class="col-md-3"><div class="detail-label">' + f[0] + '</div><div class="detail-value">' + f[1] + '</div></div>';
        });
        html += '</div></div></div>';

        const existing = document.querySelector('.card.mb-4 .card-header .bi-table');
        if (existing) {
            existing.closest('.card').outerHTML = html;
        } else {
            document.querySelector('.card.mb-4').insertAdjacentHTML('afterend', html);
        }
    })
    .catch(err => alert('Calculation error: ' + err.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-calculator"></i> Calculate';
    });
});

buildChart('line');
</script>
