<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$userid = $_SESSION['user_id'] ?? '';

 // Fetch form fields
$form_groups = $pdo->prepare("SELECT * FROM form_group ORDER BY id DESC");
$form_groups->execute();
$form_group = $form_groups->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_name = $_POST['form_name'] ?? '';
    $webhook_url = $_POST['webhook_url'] ?? '';
    $webhook_url_2 = $_POST['webhook_url_2'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;

        $assignedUsers = $_POST['assigneduser'] ?? []; // no need for 'assigneduser[]' as PHP auto-parses
            $assignedUserStr = is_array($assignedUsers) ? implode(',', $assignedUsers) : '';

    $stmt = $pdo->prepare("INSERT INTO forms (form_name, assigneduser,webhook_url,webhook_url_2, is_public,user_id, created_at) VALUES (?,?, ?,?, ?,?, NOW())");
    $stmt->execute([$form_name, $assignedUserStr, $webhook_url,$webhook_url_2 , $is_public,$userid]);

    $form_id = $pdo->lastInsertId();

    if (isset($_POST['new_fields'])) {
        foreach ($_POST['new_fields'] as $field) {

         

            $stmt = $pdo->prepare("INSERT INTO form_fields (form_id, field_type, label, name, required, options) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $form_id,                
                $field['type'] ?? '',
                $field['label'] ?? '',
                $field['name'] ?? '',
                isset($field['required']) ? 1 : 0,
                $field['options'] ?? ''
            ]);
        }
    }

    header('Location: forms.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar.php'; ?>
<style>
        #user-select-wrapper{
display:none;
        }
    </style>  
<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            
            <h1 class="mb-4">Create New Form</h1>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Form Name</label>
                    <input type="text" name="form_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Webhook URL (optional)</label>
                    <input type="url" name="webhook_url" class="form-control">
                </div>
                 <div class="mb-3">
                    <label class="form-label">Webhook URL 2 (optional)</label>
                    <input type="url" name="webhook_url_2" class="form-control">
                </div>
                <div class="form-check mb-4">
                    <input type="checkbox" name="is_public" class="form-check-input" id="is_public" checked>
                    <label class="form-check-label" for="is_public">Make this form public (no login required)</label>
                </div>

                <div id="user-select-wrapper" style="<?= $form['is_public'] ? 'display:none;' : 'display:block' ?>">
                    Which user do you want to assign?
                    <select class="form-select" id="selectuser" name="assigneduser[]" multiple>
                        <?php   
                            $users = $pdo->prepare("SELECT * FROM users WHERE is_admin = '0' AND is_external = '1'");
                            $users->execute();

                            // Ensure assigneduser is an array
                            $assignedUsers = is_array($form['assigneduser']) ? $form['assigneduser'] : explode(',', $form['assigneduser']);

                            while ($user = $users->fetch(PDO::FETCH_ASSOC)) {
                                $selected = in_array($user['id'], $assignedUsers) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($user['id']) . "' $selected>" . htmlspecialchars($user['username']) . "</option>";
                            }
                        ?>
                    </select>

                </div>
                
                <h4 class="mt-5">Form Fields</h4>
                <div id="new-fields" class="sortable-fields"></div>

                <button type="button" class="btn btn-outline-primary mt-3" onclick="addNewField()">➕ Add Field</button>

                <div class="card-footer mt-4 mb-4">
                    <select id="groupidselection" class="form-select mb-2">
                        <?php
                            foreach ($form_group as $key => $value) {
                                echo "<option value='".$value['groupid']."'>".$value['name']."</option>";
                            }
                        ?>
                        
                    </select>
                    <button type="button" class="btn btn-outline-primary" onclick="addgroupField()">
                        ➕ Add Group fields
                    </button>
                </div>
                

                <div class="text-end mt-5">
                    <button type="submit" class="btn btn-success btn-lg">Save Form</button>
                </div>
            </form>

        </div>
        <div class="col-md-4">
            <h4 class="mt-4">Live Preview</h4>
            <div id="live-preview" class="border p-3 bg-light"></div>



        </div>
    </div>

</div>

<script>
let fieldIndex = 0;
function addNewField() {
    const wrapper = document.getElementById('new-fields');
    const index = fieldIndex++;
    const template = `
    <div class="card p-3 mb-3 position-relative" data-index="${index}">

        <div class="drag-handle position-absolute top-0 start-0 m-2" title="Drag" style="cursor: grab;">☰</div>

        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-field" aria-label="Close"></button>
        <div class="row mt-4">
            <div class="col-md-3 mb-3">
                <label>Type</label>
                <select name="new_fields[${index}][type]" class="form-select field-type" data-index="${index}">
                    <option value="text">Text</option>
                    <option value="email">Email</option>
                    <option value="password">Password</option>
                    <option value="select">Select</option>
                    <option value="multiselect">Multi Select</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="date">Date</option>
                    <option value="textarea">Textarea</option>
                    <option value="upload">File Upload</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label>Label</label>
                <input type="text" class="form-control field-label" name="new_fields[${index}][label]" data-index="${index}">
            </div>
            <div class="col-md-3 mb-3" style="display:none;">
                <label>Name</label>
                <input type="text" class="form-control field-name" name="new_fields[${index}][name]" data-index="${index}">
            </div>
            <div class="col-md-4 mb-3">
                <label>Options (comma separated)</label>
                <input type="text" class="form-control field-options" name="new_fields[${index}][options]" data-index="${index}">
            </div>
            <div class="col-md-1 text-center">
                <label>Required</label><br>
                <input type="checkbox" class="form-check-input mt-2 field-required" name="new_fields[${index}][required]" data-index="${index}">
            </div>
        </div>
    </div>
    `;
    wrapper.insertAdjacentHTML('beforeend', template);
    bindPreviewEvents(index);

    
    
    const cards = wrapper.querySelectorAll('.card');
    const latestCard = cards[cards.length - 1];

    const labelInput = latestCard.querySelector('.field-label');
    const nameInput = latestCard.querySelector('.field-name');

    labelInput.addEventListener('input', () => {
        // Convert label to a slug for the name
        let slug = labelInput.value;
        nameInput.value = slug;
    });

}





function bindPreviewEvents(index) {
    const type = document.querySelector(`.field-type[data-index="${index}"]`);
    const label = document.querySelector(`.field-label[data-index="${index}"]`);
    const name = document.querySelector(`.field-name[data-index="${index}"]`);
    const options = document.querySelector(`.field-options[data-index="${index}"]`);
    const required = document.querySelector(`.field-required[data-index="${index}"]`);

    const updatePreview = () => {
        const typeVal = type.value;
        const labelVal = label.value || 'Label';
        const nameVal = name.value || `field_${index}`;
        const requiredAttr = required.checked ? 'required' : '';
        const optionsVal = options.value.split(',').map(opt => opt.trim());

        let fieldHTML = `<label>${labelVal}</label><br>`;

        switch (typeVal) {
            case 'text':
            case 'email':
            case 'password':
            case 'date':
                fieldHTML += `<input type="${typeVal}" name="${nameVal}" ${requiredAttr} class="form-control mb-2">`;
                break;
            case 'textarea':
                fieldHTML += `<textarea name="${nameVal}" ${requiredAttr} class="form-control mb-2"></textarea>`;
                break;
            case 'select':
                fieldHTML += `<select name="${nameVal}" ${requiredAttr} class="form-select mb-2">` +
                             optionsVal.map(opt => `<option>${opt}</option>`).join('') +
                             `</select>`;
                break;
            case 'multiselect':
                fieldHTML += `<select name="${nameVal}[]" multiple ${requiredAttr} class="form-select mb-2">` +
                             optionsVal.map(opt => `<option>${opt}</option>`).join('') +
                             `</select>`;
                break;
            case 'checkbox':
                fieldHTML += optionsVal.map(opt => `
                    <div class="form-check">
                        <input type="checkbox" name="${nameVal}[]" class="form-check-input" ${requiredAttr}>
                        <label class="form-check-label">${opt}</label>
                    </div>`).join('');
                break;
            case 'upload':
                fieldHTML += `<input type="file" name="${nameVal}" ${requiredAttr} class="form-control mb-2">`;
                break;
            default:
                fieldHTML += `<input type="text" name="${nameVal}" ${requiredAttr} class="form-control mb-2">`;
        }

        const previewContainer = document.getElementById('live-preview');
        let existing = previewContainer.querySelector(`[data-preview-index="${index}"]`);
        if (!existing) {
            existing = document.createElement('div');
            existing.setAttribute('data-preview-index', index);
            previewContainer.appendChild(existing);
        }
        existing.innerHTML = fieldHTML;
    };

    [type, label, name, options, required].forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    // Trigger initial render
    updatePreview();
}


document.getElementById('new-fields').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-field')) {
        const card = e.target.closest('.card');
        const index = card.getAttribute('data-index');
        card.remove();

        // Also remove the preview element
        const previewItem = document.querySelector(`#live-preview [data-preview-index="${index}"]`);
        if (previewItem) {
            previewItem.remove();
        }
    }
});


 const checkbox = document.getElementById('is_public');
    const userSelectWrapper = document.getElementById('user-select-wrapper');

    checkbox.addEventListener('change', function () {
        userSelectWrapper.style.display = this.checked ? 'none' : 'block';
    });



function addgroupField(){
    var getids = document.getElementById('groupidselection').value;
    // console.log(getids);

    fetch('get_fields.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'groupids=' + encodeURIComponent(getids)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
        // console.log('Form Groups:', data.data);
        data.data.forEach(field => {
            addFieldFromData(field);
        });
        } else {
        console.error(data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
    
}

function addFieldFromData(field) {
    const wrapper = document.getElementById('new-fields');
    const index = fieldIndex++;
    const isRequired = field.required ? 'checked' : '';
    const optionsValue = field.options || '';

    const template = `
    <div class="card p-3 mb-3 position-relative" data-index="${index}">

        <div class="drag-handle position-absolute top-0 start-0 m-2" title="Drag" style="cursor: grab;">☰</div>

        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-field" aria-label="Close"></button>
        <div class="row mt-4">
            <div class="col-md-3 mb-3">
                <label>Type</label>
                <select name="new_fields[${index}][type]" class="form-select field-type" data-index="${index}">
                    <option value="text">Text</option>
                    <option value="email">Email</option>
                    <option value="password">Password</option>
                    <option value="select">Select</option>
                    <option value="multiselect">Multi Select</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="date">Date</option>
                    <option value="textarea">Textarea</option>
                    <option value="upload">File Upload</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label>Label</label>
                <input type="text" class="form-control field-label" name="new_fields[${index}][label]" value="${field.label}" data-index="${index}">
            </div>
            <div class="col-md-3 mb-3" style="display:none;">
                <label>Name</label>
                <input type="text" class="form-control field-name" name="new_fields[${index}][name]" value="${field.name}" data-index="${index}">
            </div>
            <div class="col-md-4 mb-3">
                <label>Options (comma separated)</label>
                <input type="text" class="form-control field-options" name="new_fields[${index}][options]" value="${optionsValue}" data-index="${index}">
            </div>
            <div class="col-md-1 text-center">
                <label>Required</label><br>
                <input type="checkbox" class="form-check-input mt-2 field-required" name="new_fields[${index}][required]" ${isRequired} data-index="${index}">
            </div>
        </div>
    </div>
    `;

    wrapper.insertAdjacentHTML('beforeend', template);
    bindPreviewEvents(index);

    const latestCard = wrapper.querySelector(`.card[data-index="${index}"]`);
    const typeSelect = latestCard.querySelector('.field-type');
    if (typeSelect) {
        typeSelect.value = field.field_type || 'text';
    }

    const labelInput = latestCard.querySelector('.field-label');
    const nameInput = latestCard.querySelector('.field-name');

    labelInput.addEventListener('input', () => {
        let slug = labelInput.value;
        nameInput.value = slug;
    });
}


</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>


<script>
    Sortable.create(document.getElementById('new-fields'), {
    animation: 150,
    handle: '.drag-handle', 
        onEnd: function () {
            syncPreviewOrder();
        }
    });

    function syncPreviewOrder() {
        const fieldCards = document.querySelectorAll('#new-fields .card');
        const previewContainer = document.getElementById('live-preview');
        const newPreviewOrder = [];

        fieldCards.forEach(card => {
            const index = card.getAttribute('data-index');
            const preview = previewContainer.querySelector(`[data-preview-index="${index}"]`);
            if (preview) {
                newPreviewOrder.push(preview);
            }
        });

        // Re-append in new order
        previewContainer.innerHTML = '';
        newPreviewOrder.forEach(preview => previewContainer.appendChild(preview));
    }

</script>
</body>
</html>
