<?php

use System\Engine\Controller;
use System\Engine\Registry;

class PeopleAdminMember extends Controller {
    protected object $model;

    public function __construct(Registry $registry) {
        parent::__construct($registry);
        $this->model = $this->load->model('people/admin/member');
    }

    public function ajaxAction() {
        header('Content-Type: application/json');

        $name = trim($this->request->get('name', 'string', ''));
        $gender = $this->request->get('gender', 'string', '');
        if (strlen($name) < 2) {
            echo json_encode(["error" => "Please enter at least 2 characters."]);
            return;
        }
        $name = substr($name, 0, 100);

        $sql = "SELECT id, fullname FROM #__people_member WHERE fullname LIKE ?";
        $params = ["%$name%"];
        if (!empty($gender)) {
            $sql .= " AND gender = ?";
            $params[] = $gender;
        }
        $query = $this->db->query($sql, $params);

        if ($query->num_rows) {
            $results = [];
            foreach ($query->rows as $row) {
                $results[] = [
                    'id'       => (int) $row['id'],
                    'fullname' => escape($row['fullname'])
                ];
            }
            echo json_encode($results);
        } else {
            echo json_encode(["error" => "No records found."]);
        }
    }

    public function indexAction() {
        $member_id = $this->request->get('member_id', 'int', 0);
        $member = $this->model->getMemberById($member_id);
        if (!$member) {
            $member = [
                'id'        => 0,
                'fullname'  => '',
                'name'      => '',
                'father_id' => -1,
                'mother_id' => -1,
                'gender'    => '',
                'dob'       => 0,
                'dod'       => 0,
            ];
        }

        echo $this->view->inline(function($view) use ($member) {
            echo $this->form->open(['method' => 'GET']);
            echo $this->form->input('member', ['label' => 'Search Names', 'id' => 'member']);
            echo "<div id='lookup_records'></div>\n";
            echo $this->form->close();

            // Generate base URL for JavaScript
            $baseUrl = $this->url->to('people/admin/member/view');
            $this->doc->addInlineJs("
                $(function() {
                    var timer;
                    $('#member').on('keyup', function() {
                        clearTimeout(timer);
                        var name = $(this).val();
                        if (name.length < 2) {
                            $('#lookup_records').empty();
                            return;
                        }
                        timer = setTimeout(function() {
                            $.ajax({
                                url: '{$this->url->to('people/admin/member/ajax')}',
                                method: 'GET',
                                data: { name: name },
                                success: function(response) {
                                    var container = $('#lookup_records').empty();
                                    if (response.error) {
                                        container.text(response.error);
                                    } else {
                                        var ul = $('<ul>');
                                        response.forEach(function(item) {
                                            var a = $('<a>')
                                                .attr('href', '{$baseUrl}?member_id=' + item.id)
                                                .text(item.fullname);
                                            var li = $('<li>').append(a);
                                            ul.append(li);
                                        });
                                        container.append(ul);
                                    }
                                }
                            });
                        }, 300);
                    });
                });
            ");
        }, 'admin');
    }

    public function viewAction() {
        $member_id = $this->request->get('member_id', 'int', 0);
        $member = $this->model->getMemberById($member_id);
        if (!$member) {
            echo "<div class='alert alert-warning'>Member not found.</div>";
            return;
        }

        // Fetch related data once
        $father = $member['father_id'] > 0 ? $this->model->get_father($member['father_id']) : null;
        $mother = $member['mother_id'] > 0 ? $this->model->get_mother($member['mother_id']) : null;
        $children = $this->model->get_children($member['id']);
        $siblings = $this->model->get_siblings($member['id']);
        $spouses = $this->model->getSpouseDetails($member['id']);

        echo $this->view->inline(function() use ($member, $father, $mother, $children, $siblings, $spouses) {
            // Determine gender badge class
            $genderBadge = ($member['gender'] ?? '') === 'male' ? 'badge bg-primary' : 'badge bg-danger';
            $maritalBadge = 'badge bg-secondary';
            if (isset($member['marital_status'])) {
                $status = $member['marital_status'];
                if ($status === 'married') $maritalBadge = 'badge bg-success';
                elseif ($status === 'divorced') $maritalBadge = 'badge bg-warning';
                elseif ($status === 'widowed') $maritalBadge = 'badge bg-dark';
            }

            echo '<div class="container mt-4">';

            // Member profile card
            echo '<div class="card shadow-sm mb-4">';
            echo '  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">';
            echo '    <h3 class="mb-0">' . escape($member['fullname']) . '</h3>';
            echo '    <span class="' . $genderBadge . '">' . escape($member['gender'] ?? 'Unknown') . '</span>';
            echo '  </div>';
            echo '  <div class="card-body">';

            // Personal details in a grid
            echo '    <div class="row">';
            echo '      <div class="col-md-6">';
            echo '        <p><strong>Date of Birth:</strong> ' . escape($member['dob'] ?: 'Unknown') . '</p>';
            echo '      </div>';
            echo '      <div class="col-md-6">';
            echo '        <p><strong>Date of Death:</strong> ' . escape($member['dod'] ?: 'Unknown') . '</p>';
            echo '      </div>';
            echo '    </div>';

            echo '    <div class="row">';
            echo '      <div class="col-md-6">';
            echo '        <p><strong>Marital Status:</strong> <span class="' . $maritalBadge . '">' . escape($member['marital_status'] ?? 'Unknown') . '</span></p>';
            echo '      </div>';
            echo '      <div class="col-md-6">';
            echo '        <p><strong>ID:</strong> #' . (int)$member['id'] . '</p>';
            echo '      </div>';
            echo '    </div>';

            // Action buttons
            echo '    <div class="mt-3">';
            echo '      <a href="' . $this->url->to('people/admin/member/edit', ['member_id' => $member['id']]) . '" class="btn btn-primary me-2">';
            echo '        <i class="bi bi-pencil"></i> Edit Member';
            echo '      </a>';
            echo '      <a href="' . $this->url->to('people/admin/member') . '" class="btn btn-secondary">';
            echo '        <i class="bi bi-search"></i> Back to Search';
            echo '      </a>';
            echo '      <a href="' . $this->url->to('people/admin/member/tree', ['member_id' => $member['id']]) . '" class="btn btn-secondary">';
            echo '        <i class="bi bi-diagram-3"></i> View Family Tree';
            echo '      </a>';
            echo '    </div>';

            echo '  </div>'; // .card-body
            echo '</div>'; // .card

            // Family sections in a grid
            echo '<div class="row g-4">';

            // Parents
            echo '  <div class="col-md-6 col-lg-4">';
            echo '    <div class="card h-100">';
            echo '      <div class="card-header bg-light"><h5 class="mb-0">Parents</h5></div>';
            echo '      <div class="card-body">';
            echo '        <p><strong>Father:</strong> ' . ($father ? '<a href="' . $this->url->to('people/admin/member/view', ['member_id' => $father['id']]) . '">' . escape($father['fullname']) . '</a>' : 'Unknown') . '</p>';
            echo '        <p><strong>Mother:</strong> ' . ($mother ? '<a href="' . $this->url->to('people/admin/member/view', ['member_id' => $mother['id']]) . '">' . escape($mother['fullname']) . '</a>' : 'Unknown') . '</p>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            // Children
            echo '  <div class="col-md-6 col-lg-4">';
            echo '    <div class="card h-100">';
            echo '      <div class="card-header bg-light"><h5 class="mb-0">Children <span class="badge bg-secondary">' . count($children) . '</span></h5></div>';
            echo '      <div class="card-body">';
            if (count($children) > 0) {
                echo '        <ul class="list-unstyled">';
                foreach ($children as $child) {
                    echo '          <li><a href="' . $this->url->to('people/admin/member/view', ['member_id' => $child['id']]) . '">' . escape($child['fullname']) . '</a></li>';
                }
                echo '        </ul>';
            } else {
                echo '        <p class="text-muted">No children found.</p>';
            }
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            // Spouses
            echo '  <div class="col-md-6 col-lg-4">';
            echo '    <div class="card h-100">';
            echo '      <div class="card-header bg-light"><h5 class="mb-0">Spouse(s) <span class="badge bg-secondary">' . count($spouses) . '</span></h5></div>';
            echo '      <div class="card-body">';
            if (count($spouses) > 0) {
                echo '        <ul class="list-unstyled">';
                foreach ($spouses as $spouse) {
                    echo '          <li><a href="' . $this->url->to('people/admin/member/view', ['member_id' => $spouse['id']]) . '">' . escape($spouse['fullname']) . '</a></li>';
                }
                echo '        </ul>';
            } else {
                echo '        <p class="text-muted">No spouses found.</p>';
            }
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            // Siblings
            echo '  <div class="col-md-6 col-lg-4">';
            echo '    <div class="card h-100">';
            echo '      <div class="card-header bg-light"><h5 class="mb-0">Siblings <span class="badge bg-secondary">' . count($siblings) . '</span></h5></div>';
            echo '      <div class="card-body">';
            if (count($siblings) > 0) {
                echo '        <ul class="list-unstyled">';
                foreach ($siblings as $sibling) {
                    echo '          <li><a href="' . $this->url->to('people/admin/member/view', ['member_id' => $sibling['id']]) . '">' . escape($sibling['fullname']) . '</a></li>';
                }
                echo '        </ul>';
            } else {
                echo '        <p class="text-muted">No siblings found.</p>';
            }
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            echo '</div>'; // .row
            echo '</div>'; // .container
        }, 'admin');
    }

    public function editAction() {
        $member_id = $this->request->get('member_id', 'int', 0);

        if (!empty($member_id)) {
            $member = $this->model->getMemberById($member_id);
        } else {
            $member = [
                'id'             => 0,
                'fullname'       => '',
                'name'           => '',
                'father_id'      => -1,
                'mother_id'      => -1,
                'root'           => 0,
                'dob'            => 0,
                'dod'            => 0,
                'gender'         => 'male',
                'visible'        => '',
                'marital_status' => 'single'
            ];
        }

        $error = "";
        // Handle POST submission
        if ($this->request->isPost()) {
            // Validate CSRF token
            if (!$this->form->checkToken()) {
                // Handle invalid CSRF – redirect or show error
                redirect($this->url->to('people/admin/member/edit', ['member_id' => $member['id']]));
                return;
            }

            $postData = [
                'fullname'       => $this->request->post('fullname', 'string', ''),
                'father_id'      => $this->request->post('father_id', 'int', -1),
                'mother_id'      => $this->request->post('mother_id', 'int', -1),
                'dob'            => $this->request->post('dob', 'string', ''),
                'dod'            => $this->request->post('dod', 'string', ''),
                'gender'         => $this->request->post('gender', 'string', 'male'),
                'visible'        => $this->request->post('visible', 'string', ''),
                'marital_status' => $this->request->post('marital_status', 'string', 'single')
            ];

            // Save the member (model should handle validation)
            $saved = $this->model->saveMember($member['id'], $postData);
            if ($saved === false) {
                // Optionally set an error flash message
                // For now, we stay on the edit page and show a generic error
                // You can use session flash messages here
                $error = "Failed to save member. Please try again.";
            } else {
                // After saving, ensure the relationship between father and mother exists
                $newFatherId = $postData['father_id'];
                $newMotherId = $postData['mother_id'];
                if ($newFatherId != -1 && $newMotherId != -1) {
                    $this->model->ensureRelationship($newFatherId, $newMotherId);
                }
                redirect($this->url->to('people/admin/member', ['member_id' => $member['id']]));
                return;
            }
        }

        // Render the edit form
        echo $this->view->inline(function($view) use ($member, $error) {
            echo $this->form->open();

            // Display error if any
            if (!empty($error)) {
                echo "<div class='alert alert-danger'>" . escape($error) . "</div>";
            }

            echo openslide('Member');
            echo $this->form->input('fullname', ['value' => $member['fullname'], 'label' => 'Fullname']);

            // Mother field
            if ($member['mother_id'] != -1) {
                $mother = $this->model->get_mother($member['mother_id']);
                echo $this->form->input('mother_name', ['value' => $mother['fullname'], 'label' => 'Mother Name', 'disabled' => true]);
                echo $this->form->hidden('mother_id', $mother['id']);
            } else {
                echo $this->form->input('mother_name', ['label' => 'Mother Name']);
                echo "<div id='mother_records'></div>\n";
            }

            // Father field
            if ($member['father_id'] != -1) {
                $father = $this->model->get_father($member['father_id']);
                echo $this->form->input('father_name', ['value' => $father['fullname'], 'label' => 'Father Name', 'disabled' => true]);
                echo $this->form->hidden('father_id', $father['id']);
            } else {
                echo $this->form->input('father_name', ['label' => 'Father Name']);
                echo "<div id='father_records'></div>\n";
            }

            echo "<div class='row'>";
                echo "<div class='col-md-6'>";
                    echo $this->form->input('dob', ['value' => $member['dob'], 'label' => 'Date of Birth']);
                echo "</div>";
                echo "<div class='col-md-6'>";
                    echo $this->form->input('dod', ['value' => $member['dod'], 'label' => 'Date of Death']);
                echo "</div>";
            echo "</div>";

            echo "<div class='row'>";
                echo "<div class='col-md-4'>";
                    echo $this->form->select('visible', ['0' => 'No', '1' => 'Yes'], $member['visible']);
                echo "</div>";
                echo "<div class='col-md-4'>";
                    echo $this->form->select('gender', ['male' => 'Male', 'female' => 'Female'], $member['gender']);
                echo "</div>";
                echo "<div class='col-md-4'>";
                    echo $this->form->select('marital_status', ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'], $member['marital_status']);
                echo "</div>";
            echo "</div>";

            echo closeslide($this->form->submit('Save'));

            // Autocomplete JavaScript
            $this->doc->addInlineJs("
                function setupAutocomplete(inputSelector, recordsSelector, gender) {
                    var timer;
                    $(inputSelector).keyup(function() {
                        clearTimeout(timer);
                        var name = $(this).val();
                        if (name.length >= 2) {
                            timer = setTimeout(function() {
                                $.ajax({
                                    url: '{$this->url->to('people/admin/member/ajax')}',
                                    type: 'GET',
                                    data: { name: name, gender: gender },
                                    success: function(response) {
                                        var container = $(recordsSelector).empty();
                                        if (response.error) {
                                            container.html('<p>' + response.error + '</p>');
                                        } else {
                                            var ul = $('<ul>');
                                            response.forEach(function(item) {
                                                var a = $('<a>')
                                                    .attr('href', '#')
                                                    .addClass('select-' + gender)
                                                    .data('id', item.id)
                                                    .data('name', item.fullname)
                                                    .text(item.fullname);
                                                var li = $('<li>').append(a);
                                                ul.append(li);
                                            });
                                            container.append(ul);
                                        }
                                    }
                                });
                            }, 300);
                        }
                    });

                    $(inputSelector).on('input', function() {
                        if ($(this).val() === '') {
                            var hiddenId = inputSelector.replace('_name', '_id_hidden');
                            $(hiddenId).val('');
                            $(recordsSelector).empty();
                        }
                    });
                }

                $(document).on('click', '.select-male, .select-female', function(e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    var name = $(this).data('name');
                    var isMale = $(this).hasClass('select-male');
                    var inputSelector = isMale ? '#father_name' : '#mother_name';
                    var hiddenSelector = isMale ? '#father_id_hidden' : '#mother_id_hidden';
                    var recordsSelector = isMale ? '#father_records' : '#mother_records';

                    $(inputSelector).val(name);
                    if ($(hiddenSelector).length === 0) {
                        var nameAttr = isMale ? 'father_id' : 'mother_id';
                        $('<input>').attr({
                            type: 'hidden',
                            id: hiddenSelector.replace('#', ''),
                            name: nameAttr
                        }).insertAfter(inputSelector);
                    }
                    $(hiddenSelector).val(id);
                    $(recordsSelector).empty();
                });

                // Initialize autocomplete for mother and father
                setupAutocomplete('#mother_name', '#mother_records', 'female');
                setupAutocomplete('#father_name', '#father_records', 'male');
            ");
        }, 'admin');
    }

    /**
     * Display a family tree for a member as a nested list.
     * Supports print via window.print().
     */
    public function treeAction() {
        $member_id = $this->request->get('member_id', 'int', 0);
        $member = $this->model->getMemberById($member_id);
        if (!$member) {
            echo "<div class='alert alert-warning'>Member not found.</div>";
            return;
        }

        echo $this->view->inline(function() use ($member) {
            $treeHtml = $this->renderTree($member['id']);

            echo '<div class="container tree-container mt-4">';

            // Header
            echo '<div class="d-flex justify-content-between align-items-center mb-4">';
            echo '  <h2>Family Tree of ' . escape($member['fullname']) . '</h2>';
            echo '  <div class="no-print">';
            echo '    <button class="btn btn-success me-2" onclick="window.print()">';
            echo '      <i class="bi bi-printer"></i> Print';
            echo '    </button>';
            echo '    <a href="' . $this->url->to('people/admin/member/view', ['member_id' => $member['id']]) . '" class="btn btn-secondary">';
            echo '      Back to Profile';
            echo '    </a>';
            echo '  </div>';
            echo '</div>';

            // The tree
            echo '<div class="tree">';
            echo '  <ul>';
            echo      $treeHtml;
            echo '  </ul>';
            echo '</div>';

            echo '</div>'; // .container

            // ============================================================
            // CSS – embedded directly with final print styles (no headers/footers)
            // ============================================================
            echo '<style>
                .tree ul {
                    padding-left: 30px;
                    list-style: none;
                    position: relative;
                }
                .tree ul ul {
                    padding-left: 30px;
                }
                .tree li {
                    position: relative;
                    padding: 8px 0 0 20px;
                    border-left: 2px solid #cbd5e1;
                    list-style: none;
                }
                .tree > ul > li {
                    border-left: none;
                    padding-left: 0;
                }
                .tree > ul > li::before {
                    display: none;
                }
                .tree li::before {
                    content: "";
                    position: absolute;
                    left: 0;
                    top: 18px;
                    width: 20px;
                    border-top: 2px solid #cbd5e1;
                }
                .tree > ul > li::after,
                .tree > ul > li:last-child::after {
                    display: none !important;
                }
                .tree li:last-child {
                    border-left: 2px solid transparent;
                }
                .tree li:last-child::before {
                    top: 18px;
                }
                .tree li:last-child::after {
                    content: "";
                    position: absolute;
                    left: -2px;
                    top: 0;
                    height: 18px;
                    border-left: 2px solid #cbd5e1;
                }
                .tree .tree-node {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: #ffffff;
                    padding: 6px 14px;
                    border-radius: 6px;
                    border: 1px solid #e2e8f0;
                    margin-bottom: 4px;
                    box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05);
                    transition: all 0.2s ease;
                }
                .tree .tree-node:hover {
                    background: #f8fafc;
                    border-color: #0ea5e9;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
                    transform: translateY(-1px);
                }
                .tree .tree-node a {
                    text-decoration: none;
                    color: #2563eb;
                    font-weight: 500;
                }
                .tree .tree-node a:hover {
                    text-decoration: underline;
                }
                .tree .tree-node .badge {
                    font-size: 10px;
                    padding: 2px 8px;
                }
                @media print {
                    @page {
                        margin: 10mm;
                    }
                    body {
                        background: #fff !important;
                    }
                    .no-print { display: none !important; }
                    .tree-container {
                        margin: 0 !important;
                        padding: 0 !important;
                        box-shadow: none !important;
                        border: none !important;
                    }
                    .tree ul { padding-left: 15px; }
                    .tree li { border-color: #000; }
                    .tree .tree-node { background: #fff !important; border: 1px solid #000 !important; box-shadow: none !important; }
                    .tree .tree-node a { color: #000 !important; }
                }
            </style>';

        }, 'admin');
    }

    /**
     * Recursively render a family tree starting from a given member.
     *
     * @param int   $member_id
     * @param array $visited   Used to prevent circular references.
     * @return string HTML of nested <li> elements.
     */
    private function renderTree(int $member_id, array $visited = []): string {
        // Prevent infinite recursion
        if (in_array($member_id, $visited)) {
            return '';
        }
        $visited[] = $member_id;

        $member = $this->model->getMemberById($member_id);
        if (!$member) {
            return '';
        }

        $html = '<li>';

        // Node box with link and gender badge
        $html .= '<div class="tree-node">';
        $html .= '  <a href="' . $this->url->to('people/admin/member/view', ['member_id' => $member['id']]) . '">';
        $html .=      escape($member['name'] ?: $member['fullname']);
        $html .= '  </a>';
        if (!empty($member['gender'])) {
            $badgeClass = ($member['gender'] === 'male') ? 'badge bg-primary' : 'badge bg-danger';
            $html .= ' <span class="' . $badgeClass . '">' . escape($member['gender']) . '</span>';
        }
        $html .= '</div>';

        // Recursively get children
        $children = $this->model->get_children($member['id']);
        if (count($children) > 0) {
            $html .= '<ul>';
            foreach ($children as $child) {
                $html .= $this->renderTree($child['id'], $visited);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }
}