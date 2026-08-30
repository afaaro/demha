<?php
use System\Engine\Controller;
use System\Library\Notify;

class HifdhAdminSurahs extends Controller {
    public function indexAction() {
        $studentId = (int)($this->request->get('student_id', 'int', 0));
        $studentModel = $this->load->model('hifdh/student');
        $student = $studentModel->getById($studentId);
        if (!$student) { Notify::error('Student not found.'); redirect($this->url->to('hifdh/admin/student')); return; }

        $surahs = $this->db->query("
            SELECT s.id, s.number, s.name, s.total_ayahs, s.juz,
                COALESCE(SUM(CASE WHEN p.type = 'new' THEN p.to_ayah - p.from_ayah + 1 ELSE 0 END), 0) AS memorized_ayahs,
                CASE WHEN COALESCE(SUM(CASE WHEN p.type = 'new' THEN p.to_ayah - p.from_ayah + 1 ELSE 0 END), 0) >= s.total_ayahs THEN 'complete'
                     WHEN COALESCE(SUM(CASE WHEN p.type = 'new' THEN p.to_ayah - p.from_ayah + 1 ELSE 0 END), 0) > 0 THEN 'partial'
                     ELSE 'none' END AS status
            FROM #__hifdh_surahs s
            LEFT JOIN #__hifdh_progress p ON p.surah_id = s.id AND p.student_id = ? AND p.type = 'new'
            GROUP BY s.id, s.number, s.name, s.total_ayahs, s.juz
            ORDER BY s.number
        ", [$studentId])->rows;

        $complete = count(array_filter($surahs, fn($s) => $s['status'] === 'complete'));
        $partial = count(array_filter($surahs, fn($s) => $s['status'] === 'partial'));
        $pctComplete = round(($complete / 114) * 100, 1);

        $byJuz = [];
        foreach ($surahs as $s) {
            $byJuz[(int)$s['juz']][] = $s;
        }
        ksort($byJuz);

        echo $this->view->inline(function () use ($student, $byJuz, $complete, $partial, $pctComplete, $studentId) {
            echo "<div class=\"container py-4\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\"><div>";
            echo "<a href=\"" . $this->url->to('hifdh/admin/progress', ['student_id' => $studentId]) . "\" class=\"btn btn-sm btn-outline-secondary mb-2\">← Back to Progress</a>";
            echo "<h2>📖 Surah Completion: " . escape($student['name']) . "</h2></div>";
            echo "<a href=\"" . $this->url->to('hifdh/admin/progress/add', ['student_id' => $studentId]) . "\" class=\"btn btn-primary\">+ Add Progress</a></div>";

            echo "<div class=\"row g-3 mb-4 align-items-center\">";
            echo "<div class=\"col-md-6\"><div class=\"progress\" style=\"height:25px\"><div class=\"progress-bar bg-success\" style=\"width:{$pctComplete}%\">{$pctComplete}%</div></div>";
            echo "<p class=\"mt-1 mb-0\">{$complete}/114 Surahs Complete</p></div>";
            echo "<div class=\"col-md-2 text-center\"><div class=\"h4 text-success\">{$complete}</div>Complete</div>";
            echo "<div class=\"col-md-2 text-center\"><div class=\"h4 text-warning\">{$partial}</div>Partial</div>";
            echo "<div class=\"col-md-2 text-center\"><div class=\"h4 text-muted\">" . (114 - $complete - $partial) . "</div>Not Started</div></div>";

            foreach ($byJuz as $juzNum => $list) {
                echo "<div class=\"card mb-3\"><div class=\"card-header py-2\"><h5 class=\"mb-0\">📖 Juz {$juzNum}</h5></div><div class=\"card-body p-0\">";
                echo "<div class=\"table-responsive\"><table class=\"table table-sm mb-0\">";
                echo "<thead><tr><th>#</th><th>Surah</th><th class=\"text-center\" style=\"width:100px\">Total</th><th class=\"text-center\" style=\"width:140px\">Progress</th><th class=\"text-center\" style=\"width:100px\">Status</th><th></th></tr></thead><tbody>";
                foreach ($list as $s) {
                    $stClass = match($s['status']) { 'complete' => 'table-success', 'partial' => 'table-warning', default => '' };
                    $pct = $s['status'] !== 'none' ? round(($s['memorized_ayahs'] / $s['total_ayahs']) * 100) : 0;
                    $badgeText = match($s['status']) {
                        'complete' => '✅ Complete',
                        'partial' => "{$s['memorized_ayahs']}/{$s['total_ayahs']}",
                        default => '—'
                    };
                    $badgeClass = $s['status'] === 'complete' ? 'bg-success' : 'bg-secondary';
                    echo "<tr class=\"{$stClass}\">";
                    echo "<td class=\"fw-bold text-center\">{$s['number']}</td>";
                    echo "<td>" . escape($s['name']) . "</td>";
                    echo "<td class=\"text-center\">{$s['total_ayahs']}</td>";
                    echo "<td class=\"text-center\">";
                    if ($s['status'] !== 'none') echo "<div class=\"progress\" style=\"height:8px\"><div class=\"progress-bar\" style=\"width:{$pct}%\"></div></div><small>{$pct}%</small>";
                    echo "</td><td class=\"text-center\"><span class=\"badge {$badgeClass}\">{$badgeText}</span></td>";
                    echo "<td class=\"text-end\"><a href=\"" . $this->url->to('hifdh/admin/progress/add', ['student_id' => $studentId, 'surah_id' => $s['id']]) . "\" class=\"btn btn-sm btn-outline-primary\" title=\"Add progress for this Surah\">+</a></td></tr>";
                }
                echo "</tbody></table></div></div></div>";
            }
            echo "</div>";
        }, 'admin');
    }
}