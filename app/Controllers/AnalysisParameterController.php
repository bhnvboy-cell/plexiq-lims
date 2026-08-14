<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\JobQueue;
use App\Helpers\Pagination;
use App\Models\AnalysisParameter;
use App\Models\Instrument;
use App\Models\InstrumentParameterMapping;
use App\Models\Sample;
use App\Models\SampleAnalysisParameter;
use App\Services\AnalysisParameterService;

class AnalysisParameterController extends BaseController
{
    // ============================================================
    // PARAMETER MASTER
    // ============================================================

    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $search = trim($_GET['q'] ?? '');
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE ap.parameter_code ILIKE ? OR ap.parameter_name ILIKE ?';
            $params = ["%{$search}%", "%{$search}%"];
        }
        $pagination = Pagination::run($db, "
            SELECT ap.*,
                (SELECT COUNT(*) FROM sample_analysis_parameters sap WHERE sap.parameter_id = ap.id) AS sample_count,
                (SELECT COUNT(*) FROM instrument_parameter_mapping imp WHERE imp.parameter_id = ap.id) AS mapping_count
            FROM analysis_parameters ap
            {$where}
        ", "SELECT COUNT(*) FROM analysis_parameters ap {$where}", $params, 25, 'ap.parameter_code');
        return $this->render('analysis-parameters.index', [
            'parameters' => $pagination['items'],
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        return $this->render('analysis-parameters.form', ['parameter' => null]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');
        AnalysisParameter::create([
            'parameter_code' => $_POST['parameter_code'],
            'parameter_name' => $_POST['parameter_name'],
            'unit' => $_POST['unit'] ?: null,
            'category' => $_POST['category'] ?: 'General',
            'data_type' => $_POST['data_type'] ?? 'numeric',
            'decimal_places' => (int)($_POST['decimal_places'] ?? 2),
            'spec_min' => $_POST['spec_min'] !== '' ? $_POST['spec_min'] : null,
            'spec_max' => $_POST['spec_max'] !== '' ? $_POST['spec_max'] : null,
            'spec_target' => $_POST['spec_target'] !== '' ? $_POST['spec_target'] : null,
            'specification_text' => $_POST['specification_text'] ?: null,
            'method' => $_POST['method'] ?: null,
        ]);
        Audit::log('Analysis Parameter Created', 'analysis_parameters');
        session_flash('success', 'Analysis parameter created.');
        redirect('/analysis-parameters');
    }

    public function edit(int $id): string
    {
        Auth::requireRole('Admin');
        $parameter = AnalysisParameter::find($id);
        if (!$parameter) { session_flash('error', 'Parameter not found.'); redirect('/analysis-parameters'); }
        return $this->render('analysis-parameters.form', ['parameter' => $parameter]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('Admin');
        AnalysisParameter::update($id, [
            'parameter_code' => $_POST['parameter_code'],
            'parameter_name' => $_POST['parameter_name'],
            'unit' => $_POST['unit'] ?: null,
            'category' => $_POST['category'] ?: 'General',
            'data_type' => $_POST['data_type'] ?? 'numeric',
            'decimal_places' => (int)($_POST['decimal_places'] ?? 2),
            'spec_min' => $_POST['spec_min'] !== '' ? $_POST['spec_min'] : null,
            'spec_max' => $_POST['spec_max'] !== '' ? $_POST['spec_max'] : null,
            'spec_target' => $_POST['spec_target'] !== '' ? $_POST['spec_target'] : null,
            'specification_text' => $_POST['specification_text'] ?: null,
            'method' => $_POST['method'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Analysis Parameter Updated', 'analysis_parameters', $id);
        session_flash('success', 'Analysis parameter updated.');
        redirect('/analysis-parameters');
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        AnalysisParameter::delete($id);
        Audit::log('Analysis Parameter Deleted', 'analysis_parameters', $id);
        session_flash('success', 'Analysis parameter deleted.');
        redirect('/analysis-parameters');
    }

    // ============================================================
    // SAMPLE ASSIGNMENT & RESULT WORKFLOW
    // ============================================================

    public function assignPage(int $sampleId): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $sample = Sample::find($sampleId);
        if (!$sample) { session_flash('error', 'Sample not found.'); redirect('/samples'); }
        $parameters = AnalysisParameter::active();
        $assigned = SampleAnalysisParameter::forSample($sampleId);
        $assignedIds = array_column($assigned, 'parameter_id');
        return $this->render('analysis-parameters.assign', [
            'sample' => $sample,
            'parameters' => $parameters,
            'assignedIds' => $assignedIds,
        ]);
    }

    public function assign(int $sampleId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $service = new AnalysisParameterService();
        $ids = isset($_POST['parameter_ids']) && is_array($_POST['parameter_ids'])
            ? array_map('intval', $_POST['parameter_ids'])
            : [];
        $result = $service->assignToSample($sampleId, $ids);
        session_flash('success', "Assigned {$result['inserted']} parameter(s).");
        redirect('/samples/' . $sampleId . '/parameters/entries');
    }

    public function samplePage(int $sampleId): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $sample = Sample::find($sampleId);
        if (!$sample) { session_flash('error', 'Sample not found.'); redirect('/samples'); }
        $rows = SampleAnalysisParameter::forSample($sampleId);
        return $this->render('analysis-parameters.sample', [
            'sample' => $sample,
            'rows' => $rows,
        ]);
    }

    public function recordResult(int $sapId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $service = new AnalysisParameterService();
        try {
            $sap = $service->recordResult(
                $sapId,
                $_POST['result_value'] ?? '',
                $_POST['result_text'] ?? null,
                $_POST['analyst_notes'] ?? null
            );
            session_flash('success', 'Result recorded.' . ($sap['is_within_spec'] === false ? ' OOS flagged.' : ''));
        } catch (\Exception $e) {
            session_flash('error', $e->getMessage());
        }
        redirect('/samples/' . (int)($_POST['sample_id'] ?? 0) . '/parameters/entries');
    }

    public function review(int $sapId): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        $service = new AnalysisParameterService();
        try {
            $service->review($sapId);
            session_flash('success', 'Result reviewed.');
        } catch (\Exception $e) {
            session_flash('error', $e->getMessage());
        }
        redirect('/samples/' . (int)($_POST['sample_id'] ?? 0) . '/parameters/entries');
    }

    public function approve(int $sapId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $service = new AnalysisParameterService();
        try {
            $service->approve($sapId);
            session_flash('success', 'Result approved and fed to SPC.');
        } catch (\Exception $e) {
            session_flash('error', $e->getMessage());
        }
        redirect('/samples/' . (int)($_POST['sample_id'] ?? 0) . '/parameters/entries');
    }

    // ============================================================
    // INSTRUMENT MAPPING & AUTO-FETCH
    // ============================================================

    public function mappings(int $instrumentId): string
    {
        Auth::requireRole('Admin');
        $instrument = Instrument::find($instrumentId);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); redirect('/instruments'); }
        $mappings = InstrumentParameterMapping::forInstrument($instrumentId);
        $parameters = AnalysisParameter::active();
        return $this->render('instruments.mappings', [
            'instrument' => $instrument,
            'mappings' => $mappings,
            'parameters' => $parameters,
        ]);
    }

    public function storeMapping(int $instrumentId): void
    {
        Auth::requireRole('Admin');
        InstrumentParameterMapping::create([
            'instrument_id' => $instrumentId,
            'source_column' => trim($_POST['source_column']),
            'parameter_id' => (int)$_POST['parameter_id'],
            'conversion_factor' => $_POST['conversion_factor'] !== '' ? $_POST['conversion_factor'] : 1,
            'unit' => $_POST['unit'] ?: null,
        ]);
        Audit::log('Instrument Parameter Mapping Added', 'instrument_parameter_mapping', null, null, [
            'instrument_id' => $instrumentId,
            'source_column' => $_POST['source_column'],
        ]);
        session_flash('success', 'Mapping added.');
        redirect('/instruments/' . $instrumentId . '/mappings');
    }

    public function deleteMapping(int $id): void
    {
        Auth::requireRole('Admin');
        $mapping = InstrumentParameterMapping::find($id);
        $instrumentId = $mapping['instrument_id'] ?? 0;
        InstrumentParameterMapping::delete($id);
        Audit::log('Instrument Parameter Mapping Removed', 'instrument_parameter_mapping', $id);
        session_flash('success', 'Mapping removed.');
        redirect('/instruments/' . $instrumentId . '/mappings');
    }

    /**
     * Upload a file and enqueue an async import job (instead of blocking).
     */
    public function upload(int $instrumentId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $instrument = Instrument::find($instrumentId);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); redirect('/instruments'); }
        if (!isset($_FILES['instrument_file']) || $_FILES['instrument_file']['error'] !== UPLOAD_ERR_OK) {
            session_flash('error', 'File upload failed.');
            redirect('/instruments/' . $instrumentId . '/import');
        }

        $dir = storage_path('instrument-imports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $safeName = date('Ymd_His_') . basename($_FILES['instrument_file']['name']);
        $dest = $dir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($_FILES['instrument_file']['tmp_name'], $dest)) {
            session_flash('error', 'Could not store uploaded file.');
            redirect('/instruments/' . $instrumentId . '/import');
        }

        JobQueue::dispatch(\App\Jobs\ImportInstrumentFile::class, [
            'instrument_id' => (int)$instrumentId,
            'file_path' => $dest,
            'original_name' => $_FILES['instrument_file']['name'],
            'requested_by' => Auth::id() ?: 1,
        ], 'imports');

        Audit::log('Instrument File Queued', 'instruments', $instrumentId, null, [
            'file' => $_FILES['instrument_file']['name'],
            'queued' => 'imports',
        ]);
        session_flash('success', 'File queued for background import. Run the queue worker to process it.');
        redirect('/instruments/' . $instrumentId . '/import');
    }

    public function scanWatchPaths(): void
    {
        Auth::requireRole('Admin');
        JobQueue::dispatch(\App\Jobs\WatchInstrumentDirectories::class, [], 'imports');
        session_flash('success', 'Watch-directory scan queued.');
        redirect('/instruments');
    }

    public function importedResults(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $pagination = Pagination::run($db, "
            SELECT ir.*, i.instrument_code, i.instrument_name,
                   ap.parameter_code, ap.parameter_name,
                   s.sample_code AS resolved_sample, sap.status AS sap_status
            FROM instrument_results ir
            JOIN instruments i ON ir.instrument_id = i.id
            LEFT JOIN analysis_parameters ap ON ap.parameter_code = ir.test_code
            LEFT JOIN samples s ON ir.sample_code = s.sample_code
            LEFT JOIN sample_analysis_parameters sap ON sap.sample_id = s.id AND sap.parameter_id = ap.id
        ", "
            SELECT COUNT(*)
            FROM instrument_results ir
            JOIN instruments i ON ir.instrument_id = i.id
            LEFT JOIN analysis_parameters ap ON ap.parameter_code = ir.test_code
            LEFT JOIN samples s ON ir.sample_code = s.sample_code
            LEFT JOIN sample_analysis_parameters sap ON sap.sample_id = s.id AND sap.parameter_id = ap.id
        ", [], 25, 'ir.created_at DESC');
        return $this->render('analysis-parameters.imports', [
            'results' => $pagination['items'],
            'pagination' => $pagination,
        ]);
    }
}
