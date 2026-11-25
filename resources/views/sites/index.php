<?php
layout('app');
$title = 'Multi-Site Quality Facilities';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Multi-Site Quality Facilities & Tenant Scoping</h1>
            <p class="text-muted mb-0">Multi-facility governance, geographical isolation, localized timezones and regulatory frameworks</p>
        </div>
        <div>
            <span class="badge bg-primary p-2"><i class="bi bi-geo-alt"></i> Active Multi-Site Engine</span>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($sites as $site): ?>
            <?php $isActive = ($activeSiteId === (int)$site['id']); ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-start border-4 <?= $isActive ? 'border-success' : 'border-secondary' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold text-dark mb-0"><?= htmlspecialchars($site['name']) ?></h5>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success">Current Context</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-2">Code: <code><?= htmlspecialchars($site['site_code']) ?></code></p>
                        <p class="small text-secondary mb-2"><i class="bi bi-building"></i> <?= htmlspecialchars($site['facility_type']) ?></p>
                        <p class="small text-secondary mb-3"><i class="bi bi-clock"></i> Timezone: <?= htmlspecialchars($site['timezone']) ?></p>
                        
                        <div class="mb-3">
                            <span class="badge bg-light text-dark border"><i class="bi bi-flag"></i> <?= htmlspecialchars($site['country_code']) ?></span>
                            <span class="badge bg-light text-primary border"><?= htmlspecialchars($site['regulatory_regime']) ?></span>
                        </div>

                        <?php if (!$isActive): ?>
                            <form action="/sites/switch/<?= $site['id'] ?>" method="POST">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-arrow-repeat"></i> Switch to this Facility
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm w-100" disabled>
                                <i class="bi bi-check2-circle"></i> Active Facility
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
