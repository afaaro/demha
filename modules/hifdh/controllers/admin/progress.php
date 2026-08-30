<?php
use System\Engine\Registry;
use System\Engine\Controller;
use System\Library\Notify;

class HifdhAdminProgress extends Controller
{
    private mixed $model;
    private mixed $studentModel;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->model = $this->load->model('hifdh/progress');
        $this->studentModel = $this->load->model('hifdh/student');
    }

    public function indexAction()
    {
        $studentId = (int)($this->request->get('student_id', 'int', 0));
        $student = $this->studentModel->getById($studentId);
        if (!$student) {
            Notify::error('Student not found.');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }

        $page = (int)($this->request->get('page', 'int', 1));
        $filterType = $this->request->get('type');

        // Get entries with correct parameter order
        $entries = $this->model->getAllForStudent($studentId, $filterType, $page, 20);

        // Calculate progress
        $totalAyahs = $this->model->getTotalAyahsMemorized($studentId);
        $targetJuz = (int)($student['target_juz'] ?? 30);
        $targetAyahs = $targetJuz * 343; // ~343 avg ayahs per Juz
        $progressPercent = $targetAyahs > 0 ? round(($totalAyahs / $targetAyahs) * 100, 1) : 0;

        echo $this->view->inline(function () use ($student, $entries, $totalAyahs, $targetJuz, $targetAyahs, $progressPercent, $studentId, $filterType) {
            echo "<div class=\"container py-4\">";

            // Header & Buttons
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<div>";
            echo "<a href=\"" . escape($this->url->to('hifdh/admin/student')) . "\" class=\"btn btn-sm btn-outline-secondary mb-2\">← Back to Students</a><br>";
            echo "<h2 class=\"mt-2\">Progress: " . escape($student['name']) . "</h2>";
            echo "</div>";
            echo "<div class=\"d-flex gap-2\">";
            echo "<a href=\"" . escape($this->url->to('hifdh/admin/progress/add', ['student_id' => $studentId])) . "\" class=\"btn btn-primary\">+ Add Progress Entry</a>";
            echo "<a href=\"" . escape($this->url->to('hifdh/admin/progress/report', ['student_id' => $studentId])) . "\" target=\"_blank\" class=\"btn btn-success\">📄 Print Report</a>";
            echo "</div></div>";

            // Progress Summary Card
            echo "<div class=\"card mb-4\"><div class=\"card-body\"><div class=\"row align-items-center\">";
            echo "<div class=\"col-md-4\">";
            echo "<h5 class=\"mb-1\">Total Memorized</h5>";
            echo "<h3 class=\"text-primary\">" . number_format($totalAyahs) . " Ayahs</h3>";
            echo "<p class=\"text-muted mb-0\">Target: {$targetJuz} Juz (~" . number_format($targetAyahs) . " ayahs)</p>";
            echo "</div>";
            echo "<div class=\"col-md-8\">";
            echo "<div class=\"d-flex justify-content-between mb-1\">";
            echo "<span>Progress towards {$targetJuz} Juz target</span><strong>{$progressPercent}%</strong></div>";
            echo "<div class=\"progress\" style=\"height:25px\"><div class=\"progress-bar bg-success\" style=\"width:{$progressPercent}%\">{$progressPercent}%</div></div>";
            echo "</div></div></div>";

            // Filter Dropdown
            $filterUrlBase = escape($this->url->to('hifdh/admin/progress', ['student_id' => $studentId]));
            echo "<div class=\"card mb-4\"><div class=\"card-body py-2\">";
            echo "<form method=\"get\" class=\"d-flex align-items-center gap-2\">";
            echo "<input type=\"hidden\" name=\"student_id\" value=\"{$studentId}\">";
            echo "<label class=\"fw-bold mb-0\">Filter by Type:</label>";
            echo "<select name=\"type\" class=\"form-select form-select-sm w-auto\" onchange=\"this.form.submit()\">";
            echo "<option value=\"\"" . (!$filterType ? ' selected' : '') . ">— All Activities —</option>";
            echo "<option value=\"new,revision,correction\"" . ($filterType === 'new,revision,correction' ? ' selected' : '') . ">Hifdh (New/Rev/Corr)</option>";
            echo "<option value=\"qaida\"" . ($filterType === 'qaida' ? ' selected' : '') . ">Qa'idah</option>";
            echo "<option value=\"tajweed\"" . ($filterType === 'tajweed' ? ' selected' : '') . ">Tajweed</option>";
            echo "<option value=\"qiraah\"" . ($filterType === 'qiraah' ? ' selected' : '') . ">Qira'ah</option>";
            echo "<option value=\"listening\"" . ($filterType === 'listening' ? ' selected' : '') . ">Listening</option>";
            echo "</select>";
            if ($filterType) echo "<a href=\"{$filterUrlBase}\" class=\"btn btn-sm btn-outline-secondary\">Clear</a>";
            echo "</form></div></div>";

            // Entries Table
            echo "<div class=\"card\"><div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Date</th><th>Surah</th><th>Juz</th><th>Type</th><th>Rating</th><th>Teacher</th><th width=\"120\"></th></tr></thead><tbody>";

            if (empty($entries)) {
                $emptyText = $filterType ? 'No entries matching this filter.' : 'No progress entries yet.';
                echo "<tr><td colspan=\"7\" class=\"text-center text-muted py-4\">{$emptyText}</td></tr>";
            } else {
                foreach ($entries as $e) {
                    $badge = match($e['type']) {
                        'new' => ['bg-primary', 'Hifdh'],
                        'revision' => ['bg-success', 'Revision'],
                        'correction' => ['bg-warning text-dark', 'Correction'],
                        'qaida' => ['bg-info text-white', 'Qa\'idah'],
                        'tajweed' => ['bg-primary text-white', 'Tajweed'],
                        'qiraah' => ['bg-secondary text-white', 'Qira\'ah'],
                        'listening' => ['bg-light text-dark border', 'Listening'],
                        default => ['bg-secondary text-white', 'Other']
                    };
                    $rating = $e['rating'] ? ucwords(str_replace('_', ' ', $e['rating'])) : '—';
                    $surahName = escape($e['surah_name'] ?? "Surah #{$e['surah_id']}");
                    $juzLabel = !empty($e['juz_number']) ? "Juz {$e['juz_number']}" : '—';

                    echo "<tr>";
                    echo "<td>" . escape($e['review_date']) . "</td>";
                    echo "<td><strong>{$surahName}</strong> <small class=\"text-muted\">({$e['from_ayah']}–{$e['to_ayah']})</small></td>";
                    echo "<td>{$juzLabel}</td>";
                    echo "<td><span class=\"badge {$badge[0]}\">{$badge[1]}</span></td>";
                    echo "<td>{$rating}</td>";
                    echo "<td>" . escape($e['teacher_name'] ?? '—') . "</td>";
                    echo "<td class=\"text-end\">
                        <a href=\"" . escape($this->url->to('hifdh/admin/progress/edit', ['id' => $e['id']])) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a>
                        <a href=\"" . escape($this->url->to('hifdh/admin/progress/delete', ['id' => $e['id']])) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this entry?')\">×</a>
                    </td>";
                    echo "</tr>";
                }
            }
            echo "</tbody></table></div></div></div>";
        }, 'admin');
    }

    public function addAction()
    {
        $studentId = (int)($this->request->get('student_id', 'int', 0));
        $student = $this->studentModel->getById($studentId);
        if (!$student) {
            Notify::error('Student not found.');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }

        $this->form->fill([
            'student_id'   => $studentId,
            'teacher_id'   => $student['teacher_id'],
            'review_date'  => date('Y-m-d'),
            'type'         => 'new',
        ]);

        $this->form->setRules([
            'student_id'   => 'required',
            'teacher_id'   => 'required',
            'surah_id'     => 'required',
            'from_ayah'    => 'required|min:1',
            'to_ayah'      => 'required|gt_field:from_ayah',
            'type'         => 'required',
            'rating'       => 'nullable',
            'review_date'  => 'required',
        ]);

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data); // Auto-resolves juz_id!
            Notify::success('Progress entry added!');
            redirect($this->url->to('hifdh/admin/progress', ['student_id' => $studentId]));
            return;
        }

        $this->renderForm(null, $studentId);
    }

    public function editAction()
    {
        $id = (int)($this->request->get('id', 'int', 0));
        $entry = $this->model->getById($id);
        if (!$entry) {
            Notify::error('Entry not found.');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }

        $studentId = $entry['student_id'];
        $this->form->fill($entry);

        $this->form->setRules([
            'surah_id'     => 'required',
            'from_ayah'    => 'required|min:1',
            'to_ayah'      => 'required|gt_field:from_ayah',
            'type'         => 'required',
            'rating'       => 'nullable',
            'review_date'  => 'required',
            'teacher_id'   => 'required',
        ]);

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data, $id); // Auto-resolves juz_id!
            Notify::success('Progress entry updated!');
            redirect($this->url->to('hifdh/admin/progress', ['student_id' => $studentId]));
            return;
        }

        $this->renderForm($id, $studentId);
    }

    public function deleteAction()
    {
        $id = (int)($this->request->get('id', 'int', 0));
        $entry = $this->model->getById($id);
        if (!$entry) {
            Notify::error('Entry not found.');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }

        if ($this->model->delete($id)) {
            Notify::success('Entry deleted.');
        } else {
            Notify::error('Delete failed.');
        }

        redirect($this->url->to('hifdh/admin/progress', ['student_id' => $entry['student_id']]));
    }

    protected function renderForm(?int $id = null, ?int $studentId = null)
    {
        // Get Surahs — NO juz column reference!
        $surahs = $this->db->query("
            SELECT s.id, s.number, s.name, s.total_ayahs,
                   MIN(js.juz_id) as juz_id, MIN(j.number) as juz_number
            FROM #__hifdh_surahs s
            LEFT JOIN #__hifdh_juz_surahs js ON s.id = js.surah_id
            LEFT JOIN #__hifdh_juz j ON js.juz_id = j.id
            GROUP BY s.id
            ORDER BY s.number
        ")->rows;

        $surahOptions = [];
        foreach ($surahs as $s) {
            $juzLabel = !empty($s['juz_number']) ? " — Juz {$s['juz_number']}" : '';
            $surahOptions[$s['id']] = "{$s['number']}. {$s['name']}{$juzLabel} ({$s['total_ayahs']} Ayahs)";
        }

        $teachers = $this->db->query("SELECT id, name FROM #__hifdh_teachers WHERE status = 'active' ORDER BY name")->pairs;

        $types = [
            'new'         => 'New Memorization (Hifdh)',
            'revision'    => 'Revision',
            'correction'  => 'Correction',
            'qaida'       => 'Qa\'idah / Basic Reading',
            'tajweed'     => 'Tajweed Practice',
            'qiraah'      => 'Recitation / Qira\'ah',
            'listening'   => 'Listening & Follow-Along',
        ];

        $ratings = [
            ''            => '— Not Rated —',
            'excellent'   => 'Excellent',
            'good'        => 'Good',
            'fair'        => 'Fair',
            'weak'        => 'Weak',
            'very_weak'   => 'Very Weak',
        ];

        echo $this->view->inline(function () use ($id, $studentId, $surahOptions, $teachers, $types, $ratings) {
            echo "<div class=\"container py-4\"><div class=\"card\"><div class=\"card-body\">";
            echo "<h2 class=\"mb-4\">" . ($id ? 'Edit' : 'Add') . " Progress Entry</h2>";
            echo $this->form->open();

            if (!$id) echo $this->form->hidden('student_id');

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $this->form->select('surah_id', $surahOptions, null, ['label' => 'Surah']) . "</div>";
            echo "<div class=\"col-md-3\">" . $this->form->input('from_ayah', ['label' => 'From Ayah', 'type' => 'number', 'min' => 1]) . "</div>";
            echo "<div class=\"col-md-3\">" . $this->form->input('to_ayah', ['label' => 'To Ayah', 'type' => 'number', 'min' => 1]) . "</div>";
            echo "</div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-3\">" . $this->form->input('review_date', ['label' => 'Review Date', 'type' => 'date']) . "</div>";
            echo "<div class=\"col-md-3\">" . $this->form->select('type', $types, null, ['label' => 'Entry Type']) . "</div>";
            echo "<div class=\"col-md-3\">" . $this->form->select('rating', $ratings, null, ['label' => 'Retention Rating']) . "</div>";
            echo "<div class=\"col-md-3\">" . $this->form->select('teacher_id', $teachers, null, ['label' => 'Reviewed By']) . "</div>";
            echo "</div>";

            echo $this->form->textarea('notes', ['label' => 'Notes / Comments', 'rows' => 3, 'placeholder' => 'Mistakes, quality notes, next steps...']);

            echo "<div class=\"d-flex gap-2 mt-4\">";
            echo $this->form->submit($id ? 'Update Entry' : 'Add Entry');
            $cancelUrl = $studentId
                ? $this->url->to('hifdh/admin/progress', ['student_id' => $studentId])
                : $this->url->to('hifdh/admin/student');
            echo "<a href=\"" . escape($cancelUrl) . "\" class=\"btn btn-outline-secondary\">Cancel</a>";
            echo "</div>";

            echo $this->form->close();
            echo "</div></div></div>";
        }, 'admin');
    }

    public function reportAction()
    {
        $studentId = (int)($this->request->get('student_id', 'int', 0));
        $student = $this->studentModel->getById($studentId);
        if (!$student) {
            Notify::error('Student not found.');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }

        $entries = $this->model->getAllForStudent($studentId, null, 1, 500);
        $totalAyahs = $this->model->getTotalAyahsMemorized($studentId);
        $targetJuz = (int)($student['target_juz'] ?? 30);
        $targetAyahs = $targetJuz * 343;
        $progressPercent = $targetAyahs > 0 ? round(($totalAyahs / $targetAyahs) * 100, 1) : 0;

        $summary = [
            'hifdh_new' => 0, 'hifdh_revision' => 0, 'qaida' => 0,
            'tajweed' => 0, 'qiraah' => 0, 'listening' => 0,
        ];

        foreach ($entries as $e) {
            $k = match($e['type']) {
                'new' => 'hifdh_new', 'revision' => 'hifdh_revision',
                'qaida' => 'qaida', 'tajweed' => 'tajweed',
                'qiraah' => 'qiraah', 'listening' => 'listening',
                default => null
            };
            if ($k && isset($summary[$k])) $summary[$k]++;
        }

        $reportHtml = $this->generateReportHtml($student, $entries, $totalAyahs, $targetJuz, $progressPercent, $summary);

        echo $this->view->inline(function () use ($reportHtml, $student) {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Progress Report — <?= escape($student['name'] ?? 'Student') ?></title>
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #333; padding: 25px; background: #fff; }
                    .print-btn { position: fixed; top: 15px; right: 15px; z-index: 100; }
                    .print-btn button { padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
                    .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px; }
                    .header h1 { margin: 0; color: #1e40af; font-size: 22px; }
                    .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                    .info-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
                    .info-table td:first-child { font-weight: bold; width: 35%; background: #f8fafc; }
                    .progress-bar { height: 26px; background: #e2e8f0; border-radius: 13px; overflow: hidden; margin: 10px 0; }
                    .progress-fill { height: 100%; background: linear-gradient(90deg, #16a34a, #15803d); border-radius: 13px; text-align: center; color: #fff; font-weight: bold; line-height: 26px; font-size: 12px; }
                    h3 { margin: 25px 0 12px; color: #1e293b; font-size: 15px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
                    .summary-box { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0 20px; }
                    .summary-item { flex: 1; min-width: 110px; background: #f8fafc; padding: 12px 8px; border-radius: 8px; text-align: center; }
                    .summary-num { font-size: 22px; font-weight: bold; color: #1e40af; }
                    .summary-label { font-size: 11px; color: #64748b; margin-top: 4px; }
                    table.data { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                    .data th, .data td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
                    .data th { background: #f1f5f9; font-weight: bold; }
                    .badge { padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; white-space: nowrap; }
                    .badge-hifdh { background: #2563eb; }
                    .badge-rev { background: #16a34a; }
                    .badge-qaida { background: #0891b2; }
                    .badge-tajweed { background: #7c3aed; }
                    .badge-qiraah { background: #475569; }
                    .badge-listen { background: #f59e0b; color: #1f2937; }
                    .note-box { margin-top: 25px; padding: 12px 15px; background: #fef3c7; border-radius: 6px; font-size: 12px; color: #92400e; }
                    .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8; }
                    @media print { .print-btn { display: none; } }
                </style>
            </head>
            <body>
                <div class="print-btn"><button onclick="window.print()">Save as PDF / Print</button></div>
                <?= $reportHtml ?>
                <div class="footer">Generated by Hifdh Tracking System — <?= date('F j, Y g:i A') ?></div>
            </body>
            </html>
            <?php
                    }, '', compact('student', 'reportHtml'));
                }

                protected function generateReportHtml(array $student, array $entries, int $totalAyahs, int $targetJuz, float $progressPercent, array $summary): string
                {
                    $targetAyahs = $targetJuz * 343;
                    $levelLabel = ucfirst($student['level'] ?? 'beginner');
                    $statusLabel = ucfirst($student['status'] ?? 'active');
                    $teacherName = escape($student['teacher_name'] ?? 'Not Assigned');
                    $studentName = escape($student['name'] ?? 'Student');

                    ob_start();
            ?>
                <div class="header">
                    <h1>Student Progress Report</h1>
                    <p>Hifdh & Quranic Learning Tracking</p>
                </div>
                <table class="info-table">
                    <tr><td>Student Name</td><td><strong><?= $studentName ?></strong></td></tr>
                    <tr><td>Assigned Teacher</td><td><?= $teacherName ?></td></tr>
                    <tr><td>Level / Status</td><td><?= $levelLabel ?> / <?= $statusLabel ?></td></tr>
                    <tr><td>Target</td><td><?= $targetJuz ?> Juz (~<?= number_format($targetAyahs) ?> Ayahs)</td></tr>
                    <tr><td>Start Date</td><td><?= escape($student['start_date'] ?? 'Not Set') ?></td></tr>
                </table>

                <h3>Hifdh Progress Toward Target</h3>
                <p><strong>Total Memorized:</strong> <?= number_format($totalAyahs) ?> Ayahs</p>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $progressPercent ?>%"><?= $progressPercent ?>%</div></div>
                <p style="color:#64748b; font-size:12px; margin-top:4px;"><?= number_format($totalAyahs) ?> of <?= number_format($targetAyahs) ?> Ayahs</p>

                <h3>Activity Summary</h3>
                <div class="summary-box">
                    <div class="summary-item"><div class="summary-num"><?= $summary['hifdh_new'] ?></div><div class="summary-label">New Hifdh</div></div>
                    <div class="summary-item"><div class="summary-num"><?= $summary['hifdh_revision'] ?></div><div class="summary-label">Revisions</div></div>
                    <div class="summary-item"><div class="summary-num"><?= $summary['qaida'] ?></div><div class="summary-label">Qa'idah</div></div>
                    <div class="summary-item"><div class="summary-num"><?= $summary['tajweed'] ?></div><div class="summary-label">Tajweed</div></div>
                    <div class="summary-item"><div class="summary-num"><?= $summary['qiraah'] ?></div><div class="summary-label">Qira'ah</div></div>
                    <div class="summary-item"><div class="summary-num"><?= $summary['listening'] ?></div><div class="summary-label">Listening</div></div>
                </div>

                <h3>Recent Progress Entries</h3>
                <table class="data">
                    <thead><tr><th>Date</th><th>Surah / Portion</th><th>Type</th><th>Rating</th><th>Teacher</th></tr></thead>
                    <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#64748b;">No progress entries recorded yet.</td></tr>
                    <?php else: foreach ($entries as $e):
                        $badgeClass = match($e['type']) {
                            'new' => 'badge-hifdh', 'revision' => 'badge-rev',
                            'qaida' => 'badge-qaida', 'tajweed' => 'badge-tajweed',
                            'qiraah' => 'badge-qiraah', 'listening' => 'badge-listen',
                            default => ''
                        };
                        $typeLabel = match($e['type']) {
                            'new' => 'Hifdh', 'revision' => 'Revision',
                            'qaida' => 'Qa\'idah', 'tajweed' => 'Tajweed',
                            'qiraah' => 'Qira\'ah', 'listening' => 'Listening',
                            default => ucfirst($e['type'])
                        };
                        $rating = $e['rating'] ? ucwords(str_replace('_', ' ', $e['rating'])) : '—';
                        $surahName = escape($e['surah_name'] ?? "Surah #{$e['surah_id']}");
                        $portion = "{$surahName} (Ayahs {$e['from_ayah']}–{$e['to_ayah']})";
                    ?>
                        <tr>
                            <td><?= escape($e['review_date']) ?></td>
                            <td><?= $portion ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $typeLabel ?></span></td>
                            <td><?= $rating ?></td>
                            <td><?= escape($e['teacher_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <div class="note-box">
                    <strong>Notes:</strong> Only <strong>New Memorization (Hifdh)</strong> entries count toward the Juz target.
                    Revision, Qa'idah, Tajweed, Qira'ah, and Listening are tracked for skill development but do not affect memorization percentage.
                </div>
            <?php
        return ob_get_clean();
    }
}