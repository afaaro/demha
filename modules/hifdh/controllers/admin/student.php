<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class HifdhAdminStudent extends Controller {
    private mixed $model;
    private mixed $teacherModel;

    public function __construct() {
        parent::__construct(Registry::getInstance());
        $this->model = $this->load->model('hifdh/student');
        $this->teacherModel = $this->load->model('hifdh/teacher');
    }

    public function indexAction() {
        $page = (int)($this->request->get('page', 'int', 1));
        $search = $this->request->get('search');
        $status = $this->request->get('status');
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($status) $filters['status'] = $status;
        $students = $this->model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($students) {
            echo "<div class=\"container py-4\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>🎓 Students</h2>";
            echo "<a href=\"" . $this->url->to('hifdh/admin/student/create') . "\" class=\"btn btn-primary\">+ Add Student</a></div>";

            echo "<div class=\"card\"><div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Name</th><th>Teacher</th><th>Target</th><th>Status</th><th></th></tr></thead><tbody>";
            if (empty($students)) echo "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">No students yet.</td></tr>";
            else foreach ($students as $s) {
                $badge = match($s['status']) {
                    'active' => 'bg-success', 'paused' => 'bg-warning',
                    'graduated' => 'bg-primary', 'suspended' => 'bg-danger',
                    default => 'bg-secondary'
                };
                echo "<tr><td><strong>" . escape($s['name']) . "</strong><br><small class=\"text-muted\">" . ucfirst($s['level']) . "</small></td>";
                echo "<td>" . escape($s['teacher_name'] ?? '— Unassigned') . "</td>";
                echo "<td>{$s['target_juz']} Juz</td>";
                echo "<td><span class=\"badge {$badge}\">" . ucfirst($s['status']) . "</span></td>";
                echo "<td class=\"text-end\">
                    <a href=\"" . $this->url->to('hifdh/admin/progress', ['student_id' => $s['id']]) . "\" class=\"btn btn-sm btn-outline-info\">Progress</a>
                    <a href=\"" . $this->url->to('hifdh/admin/student/edit', ['id' => $s['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a>
                </td></tr>";
            }
            echo "</tbody></table></div></div></div>";
        }, 'admin');
    }

    public function createAction() {
        $teachers = $this->teacherModel->getPairs();
        $this->form->setRules([
            'name' => 'required|max:100',
            'teacher_id' => 'required|exists:#__hifdh_teachers',
            'age' => 'nullable|int',
            'level' => 'required|in:beginner,intermediate,advanced,completed',
            'target_juz' => 'required|int|min:1',
            'start_date' => 'nullable|date',
            'status' => 'required|in:active,paused,graduated,suspended',
        ]);
        $this->form->fill(['level' => 'beginner', 'target_juz' => 30, 'status' => 'active', 'start_date' => date('Y-m-d')]);
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data);
            Notify::success('Student added!');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }
        $this->renderForm(null, $teachers);
    }

    public function editAction() {
        $id = (int)($this->request->get('id', 'int', 0));
        $student = $this->model->getById($id);
        if (!$student) { Notify::error('Student not found.'); redirect($this->url->to('hifdh/admin/student')); return; }
        $teachers = $this->teacherModel->getPairs();
        $this->form->fill($student);
        $this->form->setRules([
            'name' => 'required|max:100',
            'teacher_id' => 'required|exists:#__hifdh_teachers',
            'age' => 'nullable|int',
            'level' => 'required|in:beginner,intermediate,advanced,completed',
            'target_juz' => 'required|int|min:1',
            'start_date' => 'nullable|date',
            'status' => 'required|in:active,paused,graduated,suspended',
        ]);
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data, $id);
            Notify::success('Student updated!');
            redirect($this->url->to('hifdh/admin/student'));
            return;
        }
        $this->renderForm($id, $teachers);
    }

    protected function renderForm(?int $id = null, array $teachers = []) {
        $levels = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'completed' => 'Completed'];
        $statuses = ['active' => 'Active', 'paused' => 'Paused', 'graduated' => 'Graduated', 'suspended' => 'Suspended'];
        echo $this->view->inline(function () use ($id, $teachers, $levels, $statuses) {
            echo "<div class=\"container py-4\"><div class=\"card\"><div class=\"card-body\">";
            echo "<h2 class=\"mb-4\">" . ($id ? 'Edit' : 'Add') . " Student</h2>";
            echo $this->form->open();
            echo $this->form->input('name', ['label' => 'Student Name', 'required' => true]);
            echo "<div class=\"row mb-3\"><div class=\"col-md-6\">";
            echo $this->form->select('teacher_id', $teachers, null, ['label' => 'Assigned Teacher', 'required' => true]);
            echo "</div><div class=\"col-md-3\">";
            echo $this->form->input('age', ['label' => 'Age', 'type' => 'number']);
            echo "</div><div class=\"col-md-3\">";
            echo $this->form->input('start_date', ['label' => 'Start Date', 'type' => 'date']);
            echo "</div></div>";
            echo "<div class=\"row mb-3\"><div class=\"col-md-4\">";
            echo $this->form->select('level', $levels, null, ['label' => 'Level']);
            echo "</div><div class=\"col-md-4\">";
            echo $this->form->input('target_juz', ['label' => 'Target (Juz)', 'type' => 'number', 'min' => 1, 'required' => true]);
            echo "</div><div class=\"col-md-4\">";
            echo $this->form->select('status', $statuses, null, ['label' => 'Status']);
            echo "</div></div>";
            echo $this->form->textarea('notes', ['label' => 'Notes', 'rows' => 3]);
            echo "<div class=\"d-flex gap-2 mt-4\">";
            echo $this->form->submit($id ? 'Update' : 'Add Student');
            echo "<a href=\"" . $this->url->to('hifdh/admin/student') . "\" class=\"btn btn-outline-secondary\">Cancel</a></div>";
            echo $this->form->close();
            echo "</div></div></div>";
        }, 'admin');
    }
}