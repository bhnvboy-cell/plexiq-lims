<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Helpers\TenantContext;

class SiteController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();
        $sites = $db->query("SELECT * FROM sites ORDER BY site_code ASC")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('sites.index', [
            'sites' => $sites,
            'activeSiteId' => TenantContext::getSiteId()
        ]);
    }

    public function switchSite(int $id): void
    {
        Auth::requireAuth();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM sites WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            TenantContext::setSiteId($id);
            session_flash('success', 'Active testing facility switched.');
        } else {
            session_flash('error', 'Selected site not found or inactive.');
        }

        $this->back();
    }
}
