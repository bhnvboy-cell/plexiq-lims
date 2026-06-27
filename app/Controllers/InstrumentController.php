<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Instrument;
use App\Models\InstrumentResult;
use App\Services\InstrumentImportService;

class InstrumentController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $instruments = Instrument::all();
        return $this->render('instruments.index', ['instruments' => $instruments]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        return $this->render('instruments.form', ['instrument' => null]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');
        Instrument::create([
            'instrument_code' => $_POST['instrument_code'],
            'instrument_name' => $_POST['instrument_name'],
            'model' => $_POST['model'] ?: null,
            'manufacturer' => $_POST['manufacturer'] ?: null,
            'interface_type' => $_POST['interface_type'],
            'parser_config' => $_POST['parser_config'] ?: '{}',
            'host' => $_POST['host'] ?: null,
            'port' => $_POST['port'] ?: null,
            'file_watch_path' => $_POST['file_watch_path'] ?: null,
            'auto_import' => isset($_POST['auto_import']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Instrument Created', 'instruments');
        session_flash('success', 'Instrument added successfully.');
        redirect('/instruments');
    }

    public function edit(int $id): string
    {
        Auth::requireRole('Admin');
        $instrument = Instrument::find($id);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); redirect('/instruments'); }
        return $this->render('instruments.form', ['instrument' => $instrument]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('Admin');
        Instrument::update($id, [
            'instrument_code' => $_POST['instrument_code'],
            'instrument_name' => $_POST['instrument_name'],
            'model' => $_POST['model'] ?: null,
            'manufacturer' => $_POST['manufacturer'] ?: null,
            'interface_type' => $_POST['interface_type'],
            'parser_config' => $_POST['parser_config'] ?: '{}',
            'host' => $_POST['host'] ?: null,
            'port' => $_POST['port'] ?: null,
            'file_watch_path' => $_POST['file_watch_path'] ?: null,
            'auto_import' => isset($_POST['auto_import']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Instrument Updated', 'instruments', $id);
        session_flash('success', 'Instrument updated.');
        redirect('/instruments');
    }

    public function import(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $instrument = Instrument::find($id);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); redirect('/instruments'); }
        return $this->render('instruments.import', ['instrument' => $instrument]);
    }

    public function upload(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $instrument = Instrument::find($id);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); redirect('/instruments'); }

        if (!isset($_FILES['instrument_file']) || $_FILES['instrument_file']['error'] !== UPLOAD_ERR_OK) {
            session_flash('error', 'File upload failed.');
            redirect('/instruments/' . $id . '/import');
        }

        $file = $_FILES['instrument_file'];
        $format = $instrument['interface_type'];

        try {
            $service = new InstrumentImportService();
            $parsed = $service->parseFile($file['tmp_name'], $format);
            $result = $service->importResults($id, $parsed, Auth::id());

            // Auto-match if enabled
            if ($instrument['auto_import'] === 't' || $instrument['auto_import'] === true) {
                $matchResult = $service->autoMatchAll();
                session_flash('success', "File parsed: {$result['imported']} rows. Auto-matched: {$matchResult['matched']}.");
            } else {
                session_flash('success', "File parsed: {$result['imported']} rows imported. Go to Instrument Results to match.");
            }

            Audit::log('Instrument File Imported', 'instruments', $id, null, [
                'file' => $file['name'],
                'format' => $format,
                'imported' => $result['imported'],
            ]);
        } catch (\Exception $e) {
            session_flash('error', 'Import error: ' . $e->getMessage());
        }

        redirect('/instruments/' . $id . '/import');
    }

    public function results(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $pending = InstrumentResult::pending();
        return $this->render('instruments.results', ['results' => $pending]);
    }

    public function match(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $service = new InstrumentImportService();
        if ($service->matchToSampleTest($id)) {
            session_flash('success', 'Result matched and imported to sample.');
        } else {
            session_flash('error', 'Could not match. Check sample_code and test_code.');
        }
        redirect('/instruments/results');
    }

    public function matchAll(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $service = new InstrumentImportService();
        $result = $service->autoMatchAll();
        session_flash('success', "Auto-match: {$result['matched']} of {$result['total']} matched.");
        redirect('/instruments/results');
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        Instrument::delete($id);
        Audit::log('Instrument Deleted', 'instruments', $id);
        session_flash('success', 'Instrument removed.');
        redirect('/instruments');
    }
}
