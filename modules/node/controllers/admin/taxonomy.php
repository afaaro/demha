<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NodeAdminTaxonomy extends Controller
{
    private mixed $taxonomyModel;
    private mixed $bundleModel;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->taxonomyModel = $this->load->model('node/taxonomy');
        $this->bundleModel = $this->load->model('node/node_bundle');
    }

    /**
     * List all vocabularies and their terms
     */
    public function indexAction(): void
    {
        $vocabularies = $this->db->query("SELECT * FROM #__node_taxonomy_vocabularies ORDER BY name")->rows;
        
        // Load terms for each vocabulary
        foreach ($vocabularies as &$vocab) {
            $vocab['terms'] = $this->taxonomyModel->getTerms($vocab['machine_name']);
        }

        echo $this->view->inline(function ($view) use ($vocabularies) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Taxonomies</h1>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('node/admin/taxonomy/create') . "'>Add Vocabulary</a>";
            echo "</div>";

            if (empty($vocabularies)) {
                echo "<div class='alert alert-info'>No taxonomies defined. Create one to get started.</div>";
                return;
            }

            foreach ($vocabularies as $vocab) {
                echo "<div class='card mb-4'>";
                echo "<div class='card-header d-flex justify-content-between align-items-center'>";
                echo "<h5 class='mb-0'>" . htmlspecialchars($vocab['name']) . " <small class='text-muted'>(" . htmlspecialchars($vocab['machine_name']) . ")</small></h5>";
                echo "<div>";
                echo "<a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('node/admin/taxonomy/edit', ['id' => $vocab['id']]) . "'><i class='bi bi-pencil'></i> Edit</a>";
                echo "<a class='btn btn-sm btn-outline-danger ms-1' onclick=\"return confirm('Delete this vocabulary and all its terms?')\" href='" . $view->url->to('node/admin/taxonomy/delete-vocab', ['id' => $vocab['id']]) . "'><i class='bi bi-trash'></i></a>";
                echo "</div>";
                echo "</div>";
                echo "<div class='card-body'>";
                
                if (!empty($vocab['description'])) {
                    echo "<p class='text-muted'>" . htmlspecialchars($vocab['description']) . "</p>";
                }

                // Add term form
                echo "<form method='post' action='" . $view->url->to('node/admin/taxonomy/add-term') . "' class='row g-3 mb-3'>";
                echo "<input type='hidden' name='vocabulary_id' value='{$vocab['id']}'>";
                echo "<div class='col-md-4'><input type='text' name='name' class='form-control' placeholder='Term name' required></div>";
                echo "<div class='col-md-4'><input type='text' name='slug' class='form-control' placeholder='Slug (optional)'></div>";
                echo "<div class='col-md-2'><button type='submit' class='btn btn-success'><i class='bi bi-plus'></i> Add Term</button></div>";
                echo "</form>";

                // List terms
                if (empty($vocab['terms'])) {
                    echo "<p class='text-muted'>No terms defined.</p>";
                } else {
                    echo "<ul class='list-group'>";
                    foreach ($vocab['terms'] as $term) {
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                        echo "<span><strong>" . htmlspecialchars($term['name']) . "</strong> <small class='text-muted'>(" . htmlspecialchars($term['slug']) . ")</small>";
                        if ($term['description']) {
                            echo " <span class='text-muted'>- " . htmlspecialchars($term['description']) . "</span>";
                        }
                        echo "</span>";
                        echo "<div>";
                        echo "<a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('admin/taxonomy/edit-term', ['id' => $term['id']]) . "'><i class='bi bi-pencil'></i></a>";
                        echo "<a class='btn btn-sm btn-outline-danger ms-1' onclick=\"return confirm('Delete this term?')\" href='" . $view->url->to('admin/taxonomy/delete-term', ['id' => $term['id']]) . "'><i class='bi bi-trash'></i></a>";
                        echo "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }
                
                echo "</div></div>";
            }
        }, 'admin');
    }

    /**
     * Create a new vocabulary
     */
    public function createAction(): void
    {
        $this->form->setRules([
            'name' => 'required|min:3|max:100|unique:node_taxonomy_vocabularies,name,NULL,id',
            'description' => 'max:255'
        ]);

        echo $this->view->inline(function ($view) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Add Taxonomy Vocabulary</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('admin/taxonomy') . "'>Back</a>";
            echo "</div>";

            echo $this->form->open();
            echo $this->form->input('name', ['label' => 'Name', 'placeholder' => 'e.g., Categories']);
            echo $this->form->input('machine_name', ['label' => 'Machine Name', 'placeholder' => 'e.g., categories', 'help' => 'Lowercase, alphanumeric, underscores/dashes only']);
            echo $this->form->textarea('description', ['label' => 'Description', 'rows' => 3]);
            echo $this->form->submit('Save Vocabulary');
            echo $this->form->close();
        }, 'admin');

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['machine_name'] = slug($data['name']);
            $this->db->insert('node_taxonomy_vocabularies', [
                'name' => $data['name'],
                'machine_name' => $data['machine_name'],
                'description' => $data['description'] ?? null,
            ]);
            Notify::success('Vocabulary created successfully.');
            redirect_to('node/admin/taxonomy');
        }
    }

    /**
     * Edit a vocabulary
     */
    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $vocab = $this->db->query("SELECT * FROM #__node_taxonomy_vocabularies WHERE id = ?", [$id])->row;
        if (!$vocab) {
            Notify::error('Vocabulary not found.');
            redirect_to('node/admin/taxonomy');
            return;
        }

        $this->form->setRules([
            'name' => 'required|min:3|max:100',
            'description' => 'max:255'
        ]);
        $this->form->fill($vocab);

        echo $this->view->inline(function ($view) use ($vocab) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit Vocabulary</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/taxonomy') . "'>Back</a>";
            echo "</div>";

            echo $this->form->open();
            echo $this->form->input('name', ['label' => 'Name']);
            echo $this->form->textarea('description', ['label' => 'Description', 'rows' => 3]);
            echo $this->form->submit('Update Vocabulary');
            echo $this->form->close();
        }, 'admin');

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $this->db->update('node_taxonomy_vocabularies', [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ], ['id' => $id]);
            Notify::success('Vocabulary updated successfully.');
            redirect_to('node/admin/taxonomy');
        }
    }

    /**
     * Delete a vocabulary
     */
    public function deleteVocabAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $this->db->query("DELETE FROM #__node_taxonomy_vocabularies WHERE id = ?", [$id]);
        Notify::success('Vocabulary deleted successfully.');
        redirect_to('node/admin/taxonomy');
    }

    /**
     * Add a term to a vocabulary
     */
    public function addTermAction(): void
    {
        $vocabularyId = (int) $this->request->post('vocabulary_id', 'int');
        $name = $this->request->post('name', 'string');
        $slug = $this->request->post('slug', 'slug');

        if (!$vocabularyId || !$name) {
            Notify::error('Term name is required.');
            redirect_to('node/admin/taxonomy');
            return;
        }

        $this->taxonomyModel->saveTerm([
            'name' => $name,
            'slug' => $slug,
            'vocabulary_id' => $vocabularyId,
        ]);

        Notify::success('Term added successfully.');
        redirect_to('node/admin/taxonomy');
    }

    /**
     * Edit a term
     */
    public function editTermAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $term = $this->taxonomyModel->getTerm($id);
        if (!$term) {
            Notify::error('Term not found.');
            redirect_to('node/admin/taxonomy');
            return;
        }

        $this->form = $this->load->form('taxonomy');
        $this->form->setRules([
            'name' => 'required|min:2|max:100',
            'slug' => 'required|alpha_dash|min:2|max:100',
        ]);
        $this->form->fill($term);

        echo $this->view->inline(function ($view) use ($term) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit Term</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/taxonomy') . "'>Back</a>";
            echo "</div>";

            echo $this->form->start();
            echo $this->form->input('name', ['label' => 'Name']);
            echo $this->form->input('slug', ['label' => 'Slug', 'help' => 'URL-friendly identifier']);
            echo $this->form->textarea('description', ['label' => 'Description', 'rows' => 3]);
            echo $this->form->submit('Update Term');
            echo $this->form->close();
        }, 'admin');

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['id'] = $id;
            $data['vocabulary_id'] = $term['vocabulary_id'];
            $this->taxonomyModel->saveTerm($data);
            Notify::success('Term updated successfully.');
            redirect_to('node/admin/taxonomy');
        }
    }

    /**
     * Delete a term
     */
    public function deleteTermAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $this->taxonomyModel->deleteTerm($id);
        Notify::success('Term deleted successfully.');
        redirect_to('node/admin/taxonomy');
    }
}