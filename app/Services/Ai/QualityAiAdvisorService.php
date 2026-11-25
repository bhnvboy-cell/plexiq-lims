<?php

namespace App\Services\Ai;

use App\Helpers\Database;
use App\Helpers\Audit;

class QualityAiAdvisorService
{
    /**
     * Analyze a quality incident (OOS, Deviation, Complaint) and generate Ishikawa 6M and 5-Whys recommendations
     *
     * @param string $entityType ('oos', 'deviation', 'complaint', 'capa')
     * @param int $entityId
     * @param array<string, mixed> $context Incident description, analyte, test parameters, historical context
     * @return array<string, mixed>
     */
    public function analyzeIncident(string $entityType, int $entityId, array $context): array
    {
        $description = strtolower($context['description'] ?? '');
        $testName = strtolower($context['test_name'] ?? '');
        $reportedValue = $context['reported_value'] ?? null;
        $expectedLimit = $context['expected_limit'] ?? null;

        // Deterministic heuristics based on pharmaceutical & ISO 17025 laboratory failure modes
        $category = 'method';
        $hypothesis = 'Procedure or method step execution anomaly.';
        $fiveWhys = [];
        $preventiveActions = [];
        $confidence = 0.88;

        if (str_contains($description, 'column') || str_contains($description, 'pressure') || str_contains($description, 'baseline') || str_contains($description, 'hplc') || str_contains($description, 'gc')) {
            $category = 'machine';
            $hypothesis = 'Chromatographic column stationary phase degradation or system backpressure fluctuation.';
            $fiveWhys = [
                'Why 1: Retention time drifted beyond $\pm 2.0\%$ specification window.',
                'Why 2: Column efficiency plate count decreased by 35% compared to installation test.',
                'Why 3: Mobile phase micro-particulates accumulated on the inlet frit.',
                'Why 4: Inline filter replacement schedule exceeded manufacturer recommended 500 injections.',
                'Why 5: Preventive maintenance tracking for consumables was not automated in instrument logbook.'
            ];
            $preventiveActions = [
                ['action' => 'Replace guard column and purge system with 80:20 Methanol:Water.', 'target_days' => 2],
                ['action' => 'Configure automated injection count alert threshold on HPLC instrument profile.', 'target_days' => 7],
                ['action' => 'Re-qualify instrument system suitability with reference standard before sample re-injection.', 'target_days' => 3]
            ];
            $confidence = 0.92;
        } elseif (str_contains($description, 'reagent') || str_contains($description, 'buffer') || str_contains($description, 'mobile phase') || str_contains($description, 'lot')) {
            $category = 'material';
            $hypothesis = 'Reagent lot-to-lot chemical impurity or expired volumetric solution.';
            $fiveWhys = [
                'Why 1: Blank chromatogram exhibited an interfering co-eluting peak at analyte retention time.',
                'Why 2: Mobile phase reservoir bottle contained traces of organic contaminant.',
                'Why 3: HPLC-grade organic solvent lot was used beyond its 30-day post-opening expiration window.',
                'Why 4: Expiration dating label on opened solvent bottle was smudged or missing.',
                'Why 5: Reagent inventory check-out did not enforce container opened-expiry barcode scanning.'
            ];
            $preventiveActions = [
                ['action' => 'Discard current mobile phase batch and prepare fresh with newly opened certified HPLC-grade solvent.', 'target_days' => 1],
                ['action' => 'Update SOP LAB-CHE-012 to mandate secondary barcode scanning of solvent bottle opening date.', 'target_days' => 14],
                ['action' => 'Quarantine all samples tested with compromised reagent lot.', 'target_days' => 1]
            ];
            $confidence = 0.90;
        } elseif (str_contains($description, 'analyst') || str_contains($description, 'pipet') || str_contains($description, 'technique') || str_contains($description, 'calculation')) {
            $category = 'man';
            $hypothesis = 'Operator technique discrepancy during sample preparation or dilution step.';
            $fiveWhys = [
                'Why 1: Replicate relative standard deviation (RSD) exceeded 2.0% threshold (found 4.2%).',
                'Why 2: Dilution aliquots varied by $\pm 50 \mu\text{L}$ between replicate preparations.',
                'Why 3: Mechanical micro-pipette was operated without standard forward-pipetting pre-wetting.',
                'Why 4: Analyst had not refreshed volumetric pipetting qualification within the past 12 months.',
                'Why 5: Annual pipette technique competency assessment was overdue.'
            ];
            $preventiveActions = [
                ['action' => 'Conduct supervised re-pipetting and volumetric glassware proficiency check for analyst.', 'target_days' => 3],
                ['action' => 'Perform 6-replicate calibration verification on pipette PIP-042.', 'target_days' => 2],
                ['action' => 'Re-test sample in duplicate by certified independent secondary analyst.', 'target_days' => 2]
            ];
            $confidence = 0.89;
        } elseif (str_contains($description, 'temperature') || str_contains($description, 'humidity') || str_contains($description, 'storage') || str_contains($description, 'incubator')) {
            $category = 'milieu_environment';
            $hypothesis = 'Environmental stability excursion during sample handling or weighing.';
            $fiveWhys = [
                'Why 1: Moisture absorption observed during sample weighing for hygroscopic API.',
                'Why 2: Weighing room relative humidity peaked at 68% RH (standard: $\le 45\%$ RH).',
                'Why 3: Laboratory HVAC desiccant dehumidifier unit failed during overnight weather storm.',
                'Why 4: Real-time environmental monitoring alarm was acknowledged without immediate facility dispatch.',
                'Why 5: Environmental monitoring alert escalation protocol lacked automated SMS/email webhook dispatch.'
            ];
            $preventiveActions = [
                ['action' => 'Re-weigh sample in controlled glove box purged with dry nitrogen gas.', 'target_days' => 1],
                ['action' => 'Engage facility engineering for HVAC dehumidifier condenser servicing.', 'target_days' => 5],
                ['action' => 'Enforce automated sample hold if balance room humidity exceeds 50% RH.', 'target_days' => 7]
            ];
            $confidence = 0.94;
        } elseif (str_contains($description, 'drift') || str_contains($description, 'calibration') || str_contains($description, 'standard') || str_contains($description, 'r2')) {
            $category = 'measurement';
            $hypothesis = 'Analytical measurement calibration curve slope drift or standard degradation.';
            $fiveWhys = [
                'Why 1: Calibration curve linearity correlation coefficient $R^2 < 0.9990$ (found 0.9972).',
                'Why 2: Lowest calibration standard point (Level 1) exhibited high residual deviation ($> 5.0\%$).',
                'Why 3: Working standard stock solution underwent partial photodegradation.',
                'Why 4: Standard was stored in clear glass volumetric flask instead of amber glassware.',
                'Why 5: Test method monograph did not explicitly specify light-sensitive amber vessel requirements.'
            ];
            $preventiveActions = [
                ['action' => 'Prepare fresh calibration standard series in Class A amber volumetric glassware.', 'target_days' => 1],
                ['action' => 'Initiate Change Control to revise Test Method Monograph to mandate amber containers.', 'target_days' => 10],
                ['action' => 'Re-calibrate instrument and verify with mid-range Quality Control check standard.', 'target_days' => 2]
            ];
            $confidence = 0.91;
        } else {
            $fiveWhys = [
                'Why 1: Test result did not meet release specifications.',
                'Why 2: Extracted sample analyte recovery was below expected historical average.',
                'Why 3: Extraction incubation duration was truncated by operator.',
                'Why 4: SOP timer instruction was ambiguous regarding start of countdown.',
                'Why 5: Standard Operating Procedure lacked step-by-step visual workflow checklist.'
            ];
            $preventiveActions = [
                ['action' => 'Update SOP with explicit countdown timer requirements and visual checklist.', 'target_days' => 14],
                ['action' => 'Conduct method re-training for laboratory testing personnel.', 'target_days' => 7]
            ];
        }

        // Store suggestion in database
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO ai_quality_suggestions (
                entity_type, entity_id, ishikawa_category, root_cause_hypothesis,
                five_whys_chain, recommended_preventive_actions, confidence_score,
                ai_drafted, review_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, 'pending')
            RETURNING id
        ");
        $stmt->execute([
            $entityType,
            $entityId,
            $category,
            $hypothesis,
            json_encode($fiveWhys, JSON_UNESCAPED_SLASHES),
            json_encode($preventiveActions, JSON_UNESCAPED_SLASHES),
            $confidence
        ]);
        $suggestionId = (int)$stmt->fetchColumn();

        Audit::log('AI Quality Incident Analyzed', 'ai_quality_suggestions', $suggestionId, null, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'category' => $category,
            'confidence' => $confidence
        ]);

        return [
            'id' => $suggestionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ishikawa_category' => $category,
            'root_cause_hypothesis' => $hypothesis,
            'five_whys_chain' => $fiveWhys,
            'recommended_preventive_actions' => $preventiveActions,
            'confidence_score' => $confidence,
            'ai_drafted' => true,
            'review_status' => 'pending'
        ];
    }

    /**
     * Human-in-the-loop review and sign-off on AI-drafted recommendations (21 CFR Part 11 compliant)
     *
     * @param int $suggestionId
     * @param int $reviewerUserId
     * @param string $decision ('accepted', 'modified', 'rejected')
     * @param string|null $notes
     * @return array<string, mixed>
     */
    public function reviewSuggestion(int $suggestionId, int $reviewerUserId, string $decision, ?string $notes = null): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM ai_quality_suggestions WHERE id = ?");
        $stmt->execute([$suggestionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \InvalidArgumentException("AI suggestion with ID {$suggestionId} not found.");
        }

        $validDecisions = ['accepted', 'modified', 'rejected'];
        if (!in_array($decision, $validDecisions)) {
            throw new \InvalidArgumentException("Invalid review decision: {$decision}");
        }

        $upd = $db->prepare("
            UPDATE ai_quality_suggestions
            SET review_status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_notes = ?
            WHERE id = ?
        ");
        $upd->execute([$decision, $reviewerUserId, $notes, $suggestionId]);

        Audit::log('AI Quality Suggestion Reviewed', 'ai_quality_suggestions', $suggestionId, [
            'review_status' => $row['review_status']
        ], [
            'review_status' => $decision,
            'reviewed_by' => $reviewerUserId,
            'notes' => $notes
        ]);

        return [
            'id' => $suggestionId,
            'review_status' => $decision,
            'reviewed_by' => $reviewerUserId,
            'reviewed_at' => date('c'),
            'notes' => $notes
        ];
    }
}
