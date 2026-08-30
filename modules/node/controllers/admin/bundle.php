<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NodeAdminBundle extends Controller {
    private $bundleModel;
    private $nodeModel;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->bundleModel = $this->load->model('node/node_bundle');
        $this->nodeModel = $this->load->model('node/node');
    }

    /**
     * List all bundles (content types)
     */
    public function indexAction(): void
    {
        $bundles = $this->bundleModel->getBundles();

        echo $this->view->inline(function ($view) use ($bundles) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Content Types</h1>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('node/admin/bundle/create') . "'>
                    <i class='bi bi-plus-lg me-1'></i>Add Content Type
                </a>";
            echo "</div>";

            if (empty($bundles)) {
                echo "<div class='alert alert-info'>No content types defined. Create one to get started.</div>";
                return;
            }

            echo "<div class='table-responsive'>";
            echo "<table class='table table-striped table-hover'>";
            echo "<thead><tr>
                    <th>Name</th>
                    <th>Machine Name</th>
                    <th>Description</th>
                    <th>Fields</th>
                    <th>Actions</th>
                </tr></thead>";
            echo "<tbody>";

            foreach ($bundles as $bundle) {
                $fieldCount = $this->db->query(
                    "SELECT COUNT(*) AS count FROM #__node_fields WHERE bundle = ?",
                    [$bundle['machine_name']]
                )->row['count'] ?? 0;

                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($bundle['name']) . "</strong></td>";
                echo "<td><code>" . htmlspecialchars($bundle['machine_name']) . "</code></td>";
                echo "<td>" . htmlspecialchars($bundle['description'] ?? '') . "</td>";
                echo "<td><span class='badge bg-info'>" . $fieldCount . " fields</span></td>";
                echo "<td>";
                echo "<a class='btn btn-sm btn-outline-primary me-1' 
                        href='" . $view->url->to('node/admin/bundle/edit', ['id' => $bundle['id']]) . "' 
                        title='Edit'>
                        <i class='bi bi-pencil'></i>
                    </a>";
                echo "<a class='btn btn-sm btn-outline-info me-1' 
                        href='" . $view->url->to('node/admin/field', ['bundle' => $bundle['machine_name']]) . "' 
                        title='Manage Fields'>
                        <i class='bi bi-grid'></i>
                    </a>";
                echo "<a class='btn btn-sm btn-outline-danger' 
                        onclick=\"return confirm('Delete this content type? This will also delete all related content!')\" 
                        href='" . $view->url->to('node/admin/bundle/delete', ['id' => $bundle['id']]) . "' 
                        title='Delete'>
                        <i class='bi bi-trash'></i>
                    </a>";
                echo "</td>";
                echo "</tr>";
            }

            echo "</tbody></table>";
            echo "</div>";
        }, 'admin');
    }

    /**
     * Create a new bundle.
     */
    public function createAction(): void
    {
        // --- Set validation rules ---
        $this->form->setRules([
            'name' => 'required|min:3|max:100',
            'machine_name' => 'required|min:3|max:50|alpha_dash|unique:node_bundles,machine_name',
            'description' => 'max:255'
        ]);

        // --- PROCESS SUBMISSION FIRST ---
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $result = $this->bundleModel->saveBundle($data);
            
            if ($result['success']) {
                Notify::success('Content type created successfully.');
                redirect_to('node/admin/bundle');
            } else {
                $this->form->setErrors($result['errors'] ?? ['Unable to save content type.']);
            }
        }

        // --- RENDER VIEW ---
        echo $this->view->inline(function ($view) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Add Content Type</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/bundle') . "'>
                    <i class='bi bi-arrow-left me-1'></i>Back
                </a>";
            echo "</div>";

            echo $this->form->start();
            echo $this->form->input('name', [
                'label' => 'Name',
                'placeholder' => 'e.g., Article'
            ]);
            echo $this->form->input('machine_name', [
                'label' => 'Machine Name',
                'placeholder' => 'e.g., article',
                'help' => 'Lowercase, alphanumeric, underscores/dashes only'
            ]);
            echo $this->form->textarea('description', [
                'label' => 'Description',
                'rows' => 3
            ]);
            echo $this->form->submit('Save Content Type', ['class' => 'btn btn-primary']);
            echo $this->form->close();
        }, 'admin');
    }

    /**
     * Edit an existing bundle.
     */
    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $bundle = $this->bundleModel->getBundleById($id);
        
        if (!$bundle) {
            Notify::error('Content type not found.');
            redirect_to('node/admin/bundle');
            return;
        }

        // --- PROCESS SUBMISSION FIRST ---
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['id'] = $id;
            $data['machine_name'] = $bundle['machine_name']; // Preserve original
            
            $result = $this->bundleModel->saveBundle($data);
            
            if ($result['success']) {
                Notify::success('Content type updated successfully.');
                redirect_to('node/admin/bundle');
            } else {
                $this->form->setErrors($result['errors'] ?? ['Unable to update content type.']);
            }
        }

        // --- Set validation rules ---
        $this->form->setRules([
            'name' => 'required|min:3|max:100',
            'description' => 'max:255'
        ]);
        $this->form->fill($bundle);

        // --- RENDER VIEW ---
        echo $this->view->inline(function ($view) use ($bundle) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit Content Type</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/bundle') . "'>
                    <i class='bi bi-arrow-left me-1'></i>Back
                </a>";
            echo "</div>";

            echo $this->form->open();
            echo "<div class='alert alert-info'>
                    Machine Name: <code>" . htmlspecialchars($bundle['machine_name']) . "</code> 
                    (cannot be changed)
                </div>";
            echo $this->form->input('name', ['label' => 'Name']);
            echo $this->form->textarea('description', [
                'label' => 'Description',
                'rows' => 3
            ]);
            echo $this->form->submit('Update Content Type', ['class' => 'btn btn-primary']);
            echo $this->form->close();
        }, 'admin');
    }

    /**
     * Delete a bundle.
     */
    public function deleteAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        
        if (!$id) {
            Notify::error('Invalid content type ID.');
            redirect_to('node/admin/bundle');
            return;
        }

        // Check if any entities use this bundle
        $bundle = $this->bundleModel->getBundleById($id);
        if ($bundle) {
            $count = $this->db->query(
                "SELECT COUNT(*) AS count FROM #__node_entities WHERE bundle = ?",
                [$bundle['machine_name']]
            )->row['count'] ?? 0;
            
            if ($count > 0) {
                Notify::error("Cannot delete: There are {$count} content items using this type. Delete them first.");
                redirect_to('node/admin/bundle');
                return;
            }
        }

        $result = $this->bundleModel->deleteBundle($id);
        
        if ($result) {
            Notify::success('Content type deleted successfully.');
        } else {
            Notify::error('Failed to delete content type.');
        }
        
        redirect_to('node/admin/bundle');
    }
}