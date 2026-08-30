<?php
use System\Engine\Controller;

class HifdhAdminDashboard extends Controller {
    public function indexAction() {
        $db = $this->db;
        $totalTeachers = (int)$db->query("SELECT COUNT(*) AS c FROM #__hifdh_teachers")->row['c'];
        $totalStudents = (int)$db->query("SELECT COUNT(*) AS c FROM #__hifdh_students")->row['c'];
        $activeStudents = (int)$db->query("SELECT COUNT(*) AS c FROM #__hifdh_students WHERE status = 'active'")->row['c'];
        $totalEntries = (int)$db->query("SELECT COUNT(*) AS c FROM #__hifdh_progress")->row['c'];

        $topStudents = $db->query("
            SELECT s.id, s.name, s.target_juz, t.name AS teacher_name,
                SUM(CASE WHEN p.type = 'new' THEN p.to_ayah - p.from_ayah + 1 ELSE 0 END) AS total_ayahs,
                ROUND((SUM(CASE WHEN p.type = 'new' THEN p.to_ayah - p.from_ayah + 1 ELSE 0 END) / (s.target_juz * 343)) * 100, 1) AS progress_pct
            FROM #__hifdh_students s
            LEFT JOIN #__hifdh_progress p ON p.student_id = s.id AND p.type = 'new'
            LEFT JOIN #__hifdh_teachers t ON s.teacher_id = t.id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING total_ayahs > 0
            ORDER BY progress_pct DESC, total_ayahs DESC LIMIT 10
        ")->rows;

        $recent = $db->query("
            SELECT p.review_date, s.name AS student_name, su.name AS surah_name, p.from_ayah, p.to_ayah, p.type, t.name AS teacher_name
            FROM #__hifdh_progress p
            JOIN #__hifdh_students s ON p.student_id = s.id
            JOIN #__hifdh_surahs su ON p.surah_id = su.id
            LEFT JOIN #__hifdh_teachers t ON p.teacher_id = t.id
            ORDER BY p.review_date DESC, p.id DESC LIMIT 8
        ")->rows;

        echo $this->view->inline(function () use ($totalTeachers, $totalStudents, $activeStudents, $totalEntries, $topStudents, $recent) {
            echo "<div class=\"container py-4\"><h2 class=\"mb-4\">📊 Hifdh Dashboard</h2>";
            echo "<div class=\"row g-3 mb-4\">";
            echo "<div class=\"col-md-3 col-sm-6\"><div class=\"card text-center\"><div class=\"card-body\"><h3 class=\"text-primary\">{$totalStudents}</h3><p class=\"mb-0 text-muted\">Total Students</p></div></div></div>";
            echo "<div class=\"col-md-3 col-sm-6\"><div class=\"card text-center\"><div class=\"card-body\"><h3 class=\"text-success\">{$activeStudents}</h3><p class=\"mb-0 text-muted\">Active Students</p></div></div></div>";
            echo "<div class=\"col-md-3 col-sm-6\"><div class=\"card text-center\"><div class=\"card-body\"><h3 class=\"text-info\">{$totalTeachers}</h3><p class=\"mb-0 text-muted\">Teachers</p></div></div></div>";
            echo "<div class=\"col-md-3 col-sm-6\"><div class=\"card text-center\"><div class=\"card-body\"><h3 class=\"text-warning\">{$totalEntries}</h3><p class=\"mb-0 text-muted\">Progress Entries</p></div></div></div></div>";

            echo "<div class=\"row\"><div class=\"col-md-6 mb-4\"><div class=\"card h-100\"><div class=\"card-header\"><h5 class=\"mb-0\">🏆 Top Progress</h5></div><div class=\"card-body p-0\">";
            if (empty($topStudents)) echo "<div class=\"p-4 text-center text-muted\">No progress recorded yet.</div>";
            else {
                echo "<table class=\"table mb-0\"><thead><tr><th>Student</th><th>Ayahs</th><th>Progress</th></tr></thead><tbody>";
                foreach ($topStudents as $s) {
                    $p = $s['progress_pct']; $barClass = $p >= 80 ? 'bg-success' : ($p >= 50 ? 'bg-primary' : 'bg-info');
                    echo "<tr><td><strong>" . escape($s['name']) . "</strong><br><small class=\"text-muted\">" . escape($s['teacher_name'] ?? 'Unassigned') . "</small></td>";
                    echo "<td>" . number_format($s['total_ayahs']) . "</td>";
                    echo "<td><div class=\"progress\" style=\"height:8px\"><div class=\"progress-bar {$barClass}\" style=\"width:{$p}%\"></div></div>{$p}%</td></tr>";
                }
                echo "</tbody></table>";
            }
            echo "</div></div></div>";

            echo "<div class=\"col-md-6 mb-4\"><div class=\"card h-100\"><div class=\"card-header\"><h5 class=\"mb-0\">🕐 Recent Activity</h5></div><div class=\"card-body p-0\">";
            if (empty($recent)) echo "<div class=\"p-4 text-center text-muted\">No recent entries.</div>";
            else {
                echo "<table class=\"table mb-0\"><thead><tr><th>Date</th><th>Student</th><th>Surah</th></tr></thead><tbody>";
                foreach ($recent as $e) {
                    $badge = match($e['type']) {
                        'new'         => ['bg-primary', '🕌 Hifdh'],
                        'revision'    => ['bg-success', '🔄 Revision'],
                        'correction'  => ['bg-warning text-dark', '✏️ Correction'],
                        'qaida'       => ['bg-info text-white', '📖 Qa\'idah'],
                        'tajweed'     => ['bg-primary text-white', '🔤 Tajweed'],
                        'qiraah'      => ['bg-secondary text-white', '🗣️ Qira\'ah'],
                        'listening'   => ['bg-light text-dark border', '👂 Listening'],
                        default       => ['bg-secondary text-white', '📝 Other']
                    };
                    echo "<tr><td><small>" . escape($e['review_date']) . "</small></td>";
                    echo "<td>" . escape($e['student_name']) . "</td>";
                    echo "<td><span class=\"badge {$badge[0]} me-1\">" . $badge[1] . "</span>" . escape($e['surah_name']) . " {$e['from_ayah']}–{$e['to_ayah']}</td></tr>";
                }
                echo "</tbody></table>";
            }
            echo "</div></div></div></div></div>";
        }, 'admin');
    }
}