<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class HifdhAdminTeacher extends Controller {
    private mixed $model;

    public function __construct() {
        parent::__construct(Registry::getInstance());
        $this->model = $this->load->model('hifdh/teacher');
    }

    public function indexAction() {
        $page = (int)($this->request->get('page', 'int', 1));
        $search = $this->request->get('search', 'string', '');
        $status = $this->request->get('status', 'string', 'active');

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($status) $filters['status'] = $status;
        $teachers = $this->model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($teachers) {
            echo "<div class=\"container py-4\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>👤 Teachers</h2>";
            echo "<a href=\"" . $this->url->to('hifdh/admin/teacher/create') . "\" class=\"btn btn-primary\">+ Add Teacher</a></div>";

            echo "<div class=\"card\"><div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead><tbody>";
            if (empty($teachers['data'])) echo "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">No teachers yet.</td></tr>";
            else foreach ($teachers['data'] as $t) {
                $badge = $t['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                echo "<tr><td><strong>" . escape($t['name']) . "</strong></td>";
                echo "<td>" . escape($t['email'] ?? '—') . "</td>";
                echo "<td>" . escape($t['phone'] ?? '—') . "</td>";
                echo "<td><span class=\"badge {$badge}\">" . ucfirst($t['status']) . "</span></td>";
                echo "<td class=\"text-end\">
                    <a href=\"" . $this->url->to('hifdh/admin/teacher/edit', ['id' => $t['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a>
                </td></tr>";
            }
            echo "</tbody></table></div></div></div>";
        }, 'admin');
    }

    public function createAction() {
        $this->form->setRules([
            'name' => 'required|max:100',
            'email' => 'nullable|email|unique:#__hifdh_teachers,email',
            'phone' => 'nullable',
            'bio' => 'nullable',
            'status' => 'required|in:active,inactive',
        ]);
        $this->form->fill(['status' => 'active']);
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data);
            Notify::success('Teacher added!');
            redirect($this->url->to('hifdh/admin/teacher'));
            return;
        }
        $this->renderForm($id ?? null);
    }

    public function editAction() {
        $id = (int)($this->request->get('id', 'int', 0));
        $teacher = $this->model->getById($id);
        if (!$teacher) { Notify::error('Teacher not found.'); redirect($this->url->to('hifdh/admin/teacher')); return; }
        $this->form->fill($teacher);
        $this->form->setRules([
            'name' => 'required|max:100',
            'email' => 'nullable|email|unique:#__hifdh_teachers,email,' . $id,
            'phone' => 'nullable',
            'bio' => 'nullable',
            'status' => 'required|in:active,inactive',
        ]);
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->model->save($data, $id);
            Notify::success('Teacher updated!');
            redirect($this->url->to('hifdh/admin/teacher'));
            return;
        }
        $this->renderForm();
    }

    protected function renderForm($id = null) {
        echo $this->view->inline(function () use ($id) {
            echo "<div class=\"container py-4\"><div class=\"card\"><div class=\"card-body\">";
            echo "<h2 class=\"mb-4\">" . ($id ? 'Edit' : 'Add') . " Teacher</h2>";
            echo $this->form->open();
            echo $this->form->input('name', ['label' => 'Full Name', 'required' => true]);
            echo "<div class=\"row\"><div class=\"col-md-6\">";
            echo $this->form->input('email', ['label' => 'Email', 'type' => 'email']);
            echo "</div><div class=\"col-md-6\">";
            echo $this->form->input('phone', ['label' => 'Phone']);
            echo "</div></div>";
            echo $this->form->textarea('bio', ['label' => 'Bio / Notes', 'rows' => 3]);
            echo $this->form->select('status', ['active' => 'Active', 'inactive' => 'Inactive'], null, ['label' => 'Status']);
            echo "<div class=\"d-flex gap-2 mt-4\">";
            echo $this->form->submit($id ? 'Update' : 'Add Teacher');
            echo "<a href=\"" . $this->url->to('hifdh/admin/teacher') . "\" class=\"btn btn-outline-secondary\">Cancel</a></div>";
            echo $this->form->close();
            echo "</div></div></div>";
        }, 'admin');
    }
}