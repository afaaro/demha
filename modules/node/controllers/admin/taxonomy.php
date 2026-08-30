<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

/**
 * Manage Taxonomy Vocabularies and Terms — Admin Interface
 */
class NodeAdminTaxonomy extends Controller
{
    /**
     * Taxonomy Model instance
     *
     * @var \System\Model\NodeTaxonomyModel
     */
    private $taxonomyModel;

    /**
     * Node Bundle Model instance
     *
     * @var \System\Model\NodeBundleModel
     */
    private $bundleModel;

    /**
     * Constructor — initialize framework and load models
     */
    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->taxonomyModel = $this->load->model('node/taxonomy');
        $this->bundleModel   = $this->load->model('node/node_bundle');
    }

    /**
     * List all vocabularies with their associated terms
     *
     * @route node/admin/taxonomy
     */
    public function indexAction(): void
    {
        $vocabularies = $this->db->query(
            "SELECT * FROM `#__node_taxonomy_vocabularies` ORDER BY `name` ASC"
        )->rows ?? [];

        // Load terms for each vocabulary
        foreach ($vocabularies as &$vocab) {
            $vocab['terms'] = $this->taxonomyModel->getTerms($vocab['machine_name'] ?? '');
        }
        unset($vocab); // Break reference

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
                $name = htmlspecialchars($vocab['name'] ?? '');
                $machine = htmlspecialchars($vocab['machine_name'] ?? '');
                $desc = htmlspecialchars($vocab['description'] ?? '');
                $vocabId = (int) ($vocab['id'] ?? 0);
                ?>
                <div class='card mb-4'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h5 class='mb-0'><?= $name ?> <small class='text-muted'>(<?= $machine ?>)</small></h5>
                        <div>
                            <a class='btn btn-sm btn-outline-primary' href='<?= $view->url->to('node/admin/taxonomy/edit', ['id' => $vocabId]) ?>'>
                                <i class='bi bi-pencil'></i> Edit
                            </a>
                            <a class='btn btn-sm btn-outline-danger ms-1' onclick="return confirm('Delete this vocabulary and all its terms?')"
                               href='<?= $view->url->to('node/admin/taxonomy/delete-vocab', ['id' => $vocabId]) ?>'>
                                <i class='bi bi-trash'></i>
                            </a>
                        </div>
                    </div>
                    <div class='card-body'>
                        <?php if ($desc): ?>
                            <p class='text-muted'><?= $desc ?></p>
                        <?php endif; ?>

                        <!-- Add Term Form -->
                        <form method='post' action='<?= $view->url->to('node/admin/taxonomy/add-term') ?>' class='row g-3 mb-3'>
                            <input type='hidden' name='vocabulary_id' value='<?= $vocabId ?>'>
                            <div class='col-md-4'>
                                <input type='text' name='name' class='form-control' placeholder='Term name' required>
                            </div>
                            <div class='col-md-4'>
                                <input type='text' name='slug' class='form-control' placeholder='Slug (optional)'>
                            </div>
                            <div class='col-md-2'>
                                <button type='submit' class='btn btn-success'><i class='bi bi-plus'></i> Add Term</button>
                            </div>
                        </form>

                        <!-- Terms List -->
                        <?php if (empty($vocab['terms'])): ?>
                            <p class='text-muted'>No terms defined.</p>
                        <?php else: ?>
                            <ul class='list-group'>
                                <?php foreach ($vocab['terms'] as $term):
                                    $termName = htmlspecialchars($term['name'] ?? '');
                                    $termSlug = htmlspecialchars($term['slug'] ?? '');
                                    $termDesc = htmlspecialchars($term['description'] ?? '');
                                    $termId = (int) ($term['id'] ?? 0);
                                ?>
                                    <li class='list-group-item d-flex justify-content-between align-items-center'>
                                        <span>
                                            <strong><?= $termName ?></strong> <small class='text-muted'>(<?= $termSlug ?>)</small>
                                            <?php if ($termDesc): ?> — <span class='text-muted'><?= $termDesc ?></span><?php endif; ?>
                                        </span>
                                        <div>
                                            <a class='btn btn-sm btn-outline-primary' href='<?= $view->url->to('node/admin/taxonomy/edit-term', ['id' => $termId]) ?>'>
                                                <i class='bi bi-pencil'></i>
                                            </a>
                                            <a class='btn btn-sm btn-outline-danger ms-1' onclick="return confirm('Delete this term?')"
                                               href='<?= $view->url->to('node/admin/taxonomy/delete-term', ['id' => $termId]) ?>'>
                                                <i class='bi bi-trash'></i>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }, 'admin');
    }

    /**
     * Create a new vocabulary
     *
     * @route node/admin/taxonomy/create
     */
    public function createAction(): void
    {
        $this->form->setRules([
            'name'        => 'required|min:3|max:100|unique:#__node_taxonomy_vocabularies,name,NULL,id',
            'machine_name' => 'required|slug|unique:#__node_taxonomy_vocabularies,machine_name,NULL,id',
            'description' => 'max:255'
        ]);

        echo $this->view->inline(function ($view) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Add Taxonomy Vocabulary</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/taxonomy') . "'>Back</a>";
            echo "</div>";
            echo $this->form->open();
            echo $this->form->input('name', ['label' => 'Name', 'placeholder' => 'e.g., Categories']);
            echo $this->form->input('machine_name', ['label' => 'Machine Name', 'placeholder' => 'e.g., categories', 'help' => 'Lowercase letters, numbers, underscores only']);
            echo $this->form->textarea('description', ['label' => 'Description', 'rows' => 3]);
            echo $this->form->submit('Save Vocabulary');
            echo $this->form->close();
        }, 'admin');

        if ($this->form->isValid()) {
            $data = $this->form->validated();

            $this->db->insert('#__node_taxonomy_vocabularies', [
                'name'         => $data['name'],
                'machine_name' => $data['machine_name'],
                'description'  => $data['description'] ?? null,
            ]);

            Notify::success('Vocabulary created successfully.');
            redirect('node/admin/taxonomy');
        }
    }

    /**
     * Edit an existing vocabulary
     *
     * @route node/admin/taxonomy/edit
     */
    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int');

        $vocab = $this->db->query(
            "SELECT * FROM `#__node_taxonomy_vocabularies` WHERE `id` = ?",
            [$id]
        )->row ?? [];

        if (empty($vocab)) {
            Notify::error('Vocabulary not found.');
            redirect('node/admin/taxonomy');
            return;
        }

        $this->form->setRules([
            'name'        => 'required|min:3|max:100|unique:#__node_taxonomy_vocabularies,name,' . $id . ',id',
            'description' => 'max:255'
        ]);

        $this->form->fill($vocab);

        echo $this->view->inline(function ($view) {
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

            $this->db->update('#__node_taxonomy_vocabularies', [
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ], ['id' => $id]);

            Notify::success('Vocabulary updated successfully.');
            redirect('node/admin/taxonomy');
        }
    }

    /**
     * Delete a vocabulary and all its terms
     *
     * @route node/admin/taxonomy/delete-vocab
     */
    public function deleteVocabAction(): void
    {
        $id = (int) $this->request->get('id', 'int');

        if ($id <= 0) {
            Notify::error('Invalid vocabulary ID.');
            redirect('node/admin/taxonomy');
            return;
        }

        // TODO: Consider cascading delete in model/service
        $this->db->query("DELETE FROM `#__node_taxonomy_vocabularies` WHERE `id` = ?", [$id]);

        Notify::success('Vocabulary deleted successfully.');
        redirect('node/admin/taxonomy');
    }

    /**
     * Add a new term to a vocabulary
     *
     * @route node/admin/taxonomy/add-term
     */
    public function addTermAction(): void
    {
        $vocabularyId = (int) $this->request->post('vocabulary_id', 'int');
        $name         = trim((string) $this->request->post('name', 'string'));
        $slug         = trim((string) $this->request->post('slug', 'slug'));

        if (!$vocabularyId || $name === '') {
            Notify::error('Term name is required.');
            redirect('node/admin/taxonomy');
            return;
        }

        $this->taxonomyModel->saveTerm([
            'vocabulary_id' => $vocabularyId,
            'name'          => $name,
            'slug'          => $slug,
        ]);

        Notify::success('Term added successfully.');
        redirect('node/admin/taxonomy');
    }

    /**
     * Edit an existing term
     *
     * @route node/admin/taxonomy/edit-term
     */
    public function editTermAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $term = $this->taxonomyModel->getTerm($id);

        if (empty($term)) {
            Notify::error('Term not found.');
            redirect('node/admin/taxonomy');
            return;
        }

        $this->form->setRules([
            'name'        => 'required|min:2|max:100',
            'slug'        => 'required|alpha_dash|min:2|max:100',
            'description' => 'max:255'
        ]);

        $this->form->fill($term);

        echo $this->view->inline(function ($view) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit Term</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/taxonomy') . "'>Back</a>";
            echo "</div>";
            echo $this->form->open();
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
            redirect('node/admin/taxonomy');
        }
    }

    /**
     * Delete a term
     *
     * @route node/admin/taxonomy/delete-term
     */
    public function deleteTermAction(): void
    {
        $id = (int) $this->request->get('id', 'int');

        if ($id <= 0) {
            Notify::error('Invalid term ID.');
            redirect('node/admin/taxonomy');
            return;
        }

        $this->taxonomyModel->deleteTerm($id);

        Notify::success('Term deleted successfully.');
        redirect('node/admin/taxonomy');
    }
}