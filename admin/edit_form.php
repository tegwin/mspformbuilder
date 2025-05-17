<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userid = $_SESSION['user_id'] ?? '';

// Logging function
function logError($message) {
    // $log_path = __DIR__ . '/form_edit_error_log.txt';
    // file_put_contents($log_path, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

try {
    $form_id = $_GET['id'] ?? null;

    if (!$form_id) {
        throw new Exception("No form ID provided");
    }

    // Fetch form details
    $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();

    if (!$form) {
        throw new Exception("Form not found");
    }

    // Fetch form fields
    $stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$form_id]);
    $fields = $stmt->fetchAll();

     // Fetch form fields
    $form_groups = $pdo->prepare("SELECT * FROM form_group ORDER BY id DESC");
    $form_groups->execute();
    $form_group = $form_groups->fetchAll();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log POST data for debugging
        logError("POST Data: " . print_r($_POST, true));

        // Validate form name
        $form_name = $_POST['form_name'] ?? '';
        if (empty($form_name)) {
            throw new Exception("Form name cannot be empty");
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update form details
        $assignedUsers = $_POST['assigneduser'] ?? []; // no need for 'assigneduser[]' as PHP auto-parses
        $assignedUserStr = is_array($assignedUsers) ? implode(',', $assignedUsers) : '';
        $stmt = $pdo->prepare("UPDATE forms SET 
        form_name = ?, 
        webhook_url = ?, 
        webhook_url_2 = ?, 
        assigneduser = ?, 
        is_public = ?, 
        user_id = ?, 
        updated_at = NOW()
        WHERE id = ?");
    $stmt->execute([
        $form_name,
        $_POST['webhook_url'] ?? '',
        $_POST['webhook_url_2'] ?? '',
        $assignedUserStr,
        isset($_POST['is_public']) ? 1 : 0,
        $userid,
        $form_id
    ]);
    
        

        // Handle existing field updates
        if (isset($_POST['fields'])) {
            foreach ($_POST['fields'] as $field_id => $field_data) {
               $stmt = $pdo->prepare("UPDATE form_fields SET 
                    label = ?, 
                    name = ?, 
                    field_type = ?, 
                    required = ?, 
                    options = ? ,
                    halo_field = ?,
                    sort_order = ?
                    WHERE id = ?");
                $stmt->execute([
                    $field_data['label'],
                    $field_data['name'],
                    $field_data['type'],
                    isset($field_data['required']) ? 1 : 0,
                    $field_data['options'] ?? '',
                    $field_data['halo_field'] ?? null,
                    $field_data['sort_order'] ?? 0,
                    $field_id
                ]);

            }
        }

        // Handle new fields
        if (isset($_POST['new_fields'])) {
            foreach ($_POST['new_fields'] as $new_field) {
                $stmt = $pdo->prepare("INSERT INTO form_fields 
                    (form_id, field_type, label, name, required, options,halo_field) 
                    VALUES (?, ?, ?, ?, ?, ?,?)");
                $stmt->execute([
                    $form_id,
                    $new_field['type'],
                    $new_field['label'],
                    $new_field['name'],
                    isset($new_field['required']) ? 1 : 0,
                    $new_field['options'] ?? '',
                    $new_field['halo_field'] ?? null
                ]);
            }
        }

         // Handle deleted fields
         if (!empty($_POST['deleted_fields'])) {
            $deleted_ids = explode(',', $_POST['deleted_fields']);
            $placeholders = implode(',', array_fill(0, count($deleted_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM form_fields WHERE id IN ($placeholders)");
            $stmt->execute($deleted_ids);
        }
        
        // Commit transaction
        $pdo->commit();

        // Redirect with success message
        header("Location: forms.php?updated=1");
        exit;
    }
} catch (Exception $e) {
    // Roll back transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error
    logError("Error: " . $e->getMessage());

    // Display error
    die("An error occurred: " . $e->getMessage());
}


$groupsave = isset($_GET['groupsave']) ? $_GET['groupsave'] : '';
if($groupsave=='1'){
    $name = isset($_GET['name']) ? $_GET['name'] : '';
    $groups = isset($_GET['group']) ? $_GET['group'] : [];
    $groupString = implode(',', $groups); 

    try{

         $stmt = $pdo->prepare("INSERT INTO form_group (name, groupid) VALUES (?,?)");
        $stmt->execute([$name, $groupString]);

        header("Location: forms.php?updated=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log error
        logError("Error: " . $e->getMessage());

        // Display error
        die("An error occurred: " . $e->getMessage());
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #user-select-wrapper{
            display:none;
        }
        .makegroup {
            position: absolute;
            left: -37px;
            margin-top: 54px;
        }
        .makegroup input {
            width: 18px;
            height: 18px;
        }
    </style>    
</head>
<body>
<?php include '../includes/navbar.php'; ?>


<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <h1 class="mb-4">Edit Form: <?= htmlspecialchars($form['form_name']) ?></h1>
            <form method="post">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Form Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Form Name</label>
                            <input type="text" name="form_name" class="form-control" 
                                value="<?= htmlspecialchars($form['form_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="text" name="webhook_url" class="form-control" 
                                value="<?= htmlspecialchars($form['webhook_url'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Webhook URL 2</label>
                            <input type="text" name="webhook_url_2" class="form-control" 
                                value="<?= htmlspecialchars($form['webhook_url_2'] ?? '') ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_public" class="form-check-input" id="is_public"
                                <?= $form['is_public'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_public">
                                Make this form public
                            </label>
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
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">Current Fields</div>
                    <div class="card-body" id="new-fields-container">
                        <?php foreach ($fields as $field): ?>

                            
                            <div class="card mb-3 existing-field" data-field-id="<?= $field['id'] ?>">
                                <div class="makegroup">
                                    <input type="checkbox" value="<?php echo $field['id']; ?>">
                                </div>

                                <div class="card-body mt-4">
                                    <div class="drag-handle position-absolute top-0 start-0 m-2" title="Drag" style="cursor: grab;">☰</div>
                                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-existing-field" aria-label="Close"></button>

                                    <input type="hidden" name="fields[<?= $field['id'] ?>][id]" 
                                        value="<?= $field['id'] ?>">

                                    <input type="hidden" name="fields[<?= $field['id'] ?>][sort_order]" class="field-sort-order" value="0">

                                    <div class="row">
                                        <div class="col-md-3 mb-2">
                                            <label>Label</label>
                                            <input type="text" name="fields[<?= $field['id'] ?>][label]" 
                                                class="form-control field-label" 
                                                value="<?= htmlspecialchars($field['label']) ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-2" style="display:none;">
                                            <label>Name</label>
                                            <input type="text" name="fields[<?= $field['id'] ?>][name]" 
                                                class="form-control field-name" 
                                                value="<?= htmlspecialchars($field['name']) ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label>Type</label>
                                            <select name="fields[<?= $field['id'] ?>][type]" class="form-select">
                                                <?php 
                                                $types = ['text', 'email', 'password', 'textarea', 'select', 'multiselect', 'checkbox', 'date', 'upload'];
                                                foreach ($types as $type): 
                                                ?>
                                                    <option value="<?= $type ?>" 
                                                        <?= $field['field_type'] === $type ? 'selected' : '' ?>>
                                                        <?= ucfirst($type) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5 mb-2">
                                            <label>Options</label>
                                            <input type="text" name="fields[<?= $field['id'] ?>][options]" 
                                                class="form-control" 
                                                value="<?= htmlspecialchars($field['options'] ?? '') ?>" 
                                                placeholder="Comma separated">
                                        </div>
                                        <div class="col-md-1 text-center">
                                            <label>Required</label><br>
                                            <input type="checkbox" 
                                                name="fields[<?= $field['id'] ?>][required]" 
                                                class="form-check-input mt-2" 
                                                <?= $field['required'] ? 'checked' : '' ?>>
                                        </div>
                                        <!-- <div class="col-md-3 mb-2">
                                        <label>Halo Field</label>
                                        <input type="text" 
                                            name="fields[<?= $field['id'] ?>][halo_field]" 
                                            class="form-control" 
                                            value="<?= htmlspecialchars($field['halo_field'] ?? '') ?>" 
                                            placeholder="e.g. Summary, Details">
                                    </div> -->


                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="deleted-fields" name="deleted_fields" value="">

                <div class="card shadow-sm mb-4">
                    <div class="card-header">Add New Fields</div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-outline-primary" onclick="acreategroupField()">
                            ➕ Create Group Fields
                        </button>

                        <button type="button" class="btn btn-outline-primary" onclick="addNewField()">
                            ➕ Add Field
                        </button>
                    </div>
                     <div class="card-footer">
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

                        <button type="button" class="btn btn-outline-primary" onclick="removegroupField()">
                            ➖ Remove Group fields
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <a href="forms.php" class="btn btn-secondary">Cancel</a>
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
    
let newFieldIndex = 0;

function addNewField() {
    const container = document.getElementById('new-fields-container');
    const fieldTypes = [
        'text', 'email', 'password', 'textarea', 
        'select', 'multiselect', 'checkbox', 'date', 'upload'
    ];

    const fieldHtml = `
        <div class="card mb-3">
        <div class="card-body">
        <div class="drag-handle position-absolute top-0 start-0 m-2" title="Drag" style="cursor: grab;">☰</div>
        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-field" aria-label="Close"></button>
        <div class="row mt-4">
        <div class="col-md-3 mb-2">
                        <label>Label</label>
                        <input type="text" name="new_fields[${newFieldIndex}][label]" 
                            class="form-control field-label" required>
                    </div>
                    <div class="col-md-3 mb-2" style="display:none;">
                        <label>Name</label>
                        <input type="text" name="new_fields[${newFieldIndex}][name]" 
                            class="form-control field-name" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>Type</label>
                        <select name="new_fields[${newFieldIndex}][type]" class="form-select">
                            ${fieldTypes.map(type => 
                                `<option value="${type}">${type.charAt(0).toUpperCase() + type.slice(1)}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-md-5 mb-2">
                        <label>Options</label>
                        <input type="text" name="new_fields[${newFieldIndex}][options]" 
                            class="form-control" placeholder="Comma separated">
                    </div>
                    <div class="col-md-1 text-center">
                        <label>Required</label><br>
                        <input type="checkbox" name="new_fields[${newFieldIndex}][required]" 
                            class="form-check-input mt-2">
                    </div>

                   


                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', fieldHtml);
    newFieldIndex++;



     const cards = container.querySelectorAll('.card');
    const latestCard = cards[cards.length - 1];

    const labelInput = latestCard.querySelector('.field-label');
    const nameInput = latestCard.querySelector('.field-name');

    labelInput.addEventListener('input', () => {
        // Convert label to a slug for the name
        let slug = labelInput.value;
        nameInput.value = slug;
    });
}
document.getElementById('new-fields-container').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-field')) {
        e.target.closest('.card').remove();
        buildLivePreview();
    }
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-existing-field')) {
        const card = e.target.closest('.existing-field');
        const fieldId = card.getAttribute('data-field-id');
        const deletedInput = document.getElementById('deleted-fields');

        if (deletedInput) {
            let deleted = deletedInput.value ? deletedInput.value.split(',') : [];
            deleted.push(fieldId);
            deletedInput.value = deleted.join(',');
        }

        card.remove(); // Remove the field card from the DOM
        buildLivePreview();
    }
});

const checkbox = document.getElementById('is_public');
const userSelectWrapper = document.getElementById('user-select-wrapper');

checkbox.addEventListener('change', function () {
    userSelectWrapper.style.display = this.checked ? 'none' : 'block';
});


function acreategroupField() {
    const checkedValues = [];
    document.querySelectorAll('.makegroup input[type="checkbox"]:checked').forEach(checkbox => {
        checkedValues.push(checkbox.value);
    });

    if(checkedValues.length!=0){
        var getname = prompt("Give a name");
        if(getname!=''){
            const params = new URLSearchParams();
            checkedValues.forEach(val => params.append('group[]', val));
             window.location.href = 'edit_form.php?id=<?php echo $form_id; ?>&groupsave=1&name='+getname+'&' + params.toString();
        }
    }
    // Example: Log or use the values
    console.log("Checked Values:", checkedValues);

}


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
        buildLivePreview();
        } else {
        console.error(data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
    
}

function removegroupField(){
    var getids = document.getElementById('groupidselection').value;
    // console.log(getids);

    fetch('remove_fields.php', {
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
       var select = document.getElementById('groupidselection');
        var optionToRemove = select.querySelector('option[value="'+getids+'"]');
        if (optionToRemove) {
            optionToRemove.remove();
        }

        } else {
        console.error(data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
    
}


function addFieldFromData(field) {
    const container = document.getElementById('new-fields-container');
    const fieldTypes = [
        'text', 'email', 'password', 'textarea',
        'select', 'multiselect', 'checkbox', 'date', 'upload'
    ];

    const selectedTypeOptions = fieldTypes.map(type =>
        `<option value="${type}" ${type === field.field_type ? 'selected' : ''}>${type.charAt(0).toUpperCase() + type.slice(1)}</option>`
    ).join('');

    const fieldHtml = `
        <div class="card mb-3">
        <div class="card-body">
        <div class="drag-handle position-absolute top-0 start-0 m-2" title="Drag" style="cursor: grab;">☰</div>
        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-field" aria-label="Close"></button>
        <div class="row mt-4">
            <div class="col-md-3 mb-2">
                <label>Label</label>
                <input type="text" name="new_fields[${newFieldIndex}][label]" 
                    class="form-control field-label" value="${field.label}" required>
            </div>
            <div class="col-md-3 mb-2" style="display:none;">
                <label>Name</label>
                <input type="text" name="new_fields[${newFieldIndex}][name]" 
                    class="form-control field-name" value="${field.name}" required>
            </div>
            <div class="col-md-3 mb-2">
                <label>Type</label>
                <select name="new_fields[${newFieldIndex}][type]" class="form-select">
                    ${selectedTypeOptions}
                </select>
            </div>
            <div class="col-md-5 mb-2">
                <label>Options</label>
                <input type="text" name="new_fields[${newFieldIndex}][options]" 
                    class="form-control" value="${field.options || ''}" placeholder="Comma separated">
            </div>
            <div class="col-md-1 text-center">
                <label>Required</label><br>
                <input type="checkbox" name="new_fields[${newFieldIndex}][required]" 
                    class="form-check-input mt-2" ${field.required == 1 ? 'checked' : ''}>
            </div>
        </div>
        </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', fieldHtml);
    newFieldIndex++;
}
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    function renderPreview() {
        const previewContainer = document.getElementById('live-preview');
        previewContainer.innerHTML = ''; // Clear previous content

        // Combine existing fields and new fields
        const allFields = document.querySelectorAll('#new-fields-container .card');

        allFields.forEach(card => {
            const label = card.querySelector('input[name*="[label]"]')?.value || '';
            const name = card.querySelector('input[name*="[name]"]')?.value || '';
            const type = card.querySelector('select[name*="[type]"]')?.value || '';
            const optionsRaw = card.querySelector('input[name*="[options]"]')?.value || '';
            const required = card.querySelector('input[name*="[required]"]')?.checked;

            const fieldWrapper = document.createElement('div');
            fieldWrapper.className = 'mb-3';

            const labelEl = document.createElement('label');
            labelEl.textContent = label + (required ? ' *' : '');
            labelEl.className = 'form-label';
            fieldWrapper.appendChild(labelEl);

            let inputEl;

            if (type === 'textarea') {
                inputEl = document.createElement('textarea');
                inputEl.className = 'form-control';
            } else if (type === 'select' || type === 'multiselect') {
                inputEl = document.createElement('select');
                inputEl.className = 'form-select';
                if (type === 'multiselect') inputEl.multiple = true;

                const options = optionsRaw.split(',').map(opt => opt.trim()).filter(opt => opt);
                options.forEach(opt => {
                    const optEl = document.createElement('option');
                    optEl.textContent = opt;
                    inputEl.appendChild(optEl);
                });
            } else if (type === 'checkbox') {
                inputEl = document.createElement('input');
                inputEl.type = 'checkbox';
                inputEl.className = 'form-check-input';
                labelEl.className = 'form-check-label ms-2';
                fieldWrapper.className = 'form-check mb-3';
            } else if (type === 'upload') {
                inputEl = document.createElement('input');
                inputEl.type = 'file';
                inputEl.className = 'form-control';
            } else {
                inputEl = document.createElement('input');
                inputEl.type = type || 'text';
                inputEl.className = 'form-control';
            }

            inputEl.name = name;
            inputEl.required = required;
            fieldWrapper.appendChild(inputEl);
            previewContainer.appendChild(fieldWrapper);
        });

    }

    // Attach change event listeners
    document.addEventListener('input', function (e) {
        if (
            e.target.closest('#new-fields-container') &&
            (e.target.matches('input') || e.target.matches('select') || e.target.matches('textarea'))
        ) {
            renderPreview();
        }
    });

    // Initial render
    document.addEventListener('DOMContentLoaded', renderPreview);

    // Also re-render after adding new field
    document.querySelector('[onclick="addNewField()"]').addEventListener('click', () => {
        setTimeout(renderPreview, 100); // Slight delay to ensure DOM update
    });
</script>



<script>
function buildLivePreview() {
    const preview = document.getElementById('live-preview');
    preview.innerHTML = ''; // Clear existing

    const fields = document.querySelectorAll('#new-fields-container .card');

    fields.forEach(field => {
        const label = field.querySelector('[name*="[label]"]')?.value || 'Label';
        const name = field.querySelector('[name*="[name]"]')?.value || 'name';
        const type = field.querySelector('[name*="[type]"]')?.value || 'text';
        const required = field.querySelector('[name*="[required]"]')?.checked;
        const options = field.querySelector('[name*="[options]"]')?.value || '';

        const formGroup = document.createElement('div');
        formGroup.classList.add('mb-3');

        const labelEl = document.createElement('label');
        labelEl.classList.add('form-label');
        labelEl.innerText = label + (required ? ' *' : '');
        formGroup.appendChild(labelEl);

        if (['text', 'email', 'password', 'date'].includes(type)) {
            const input = document.createElement('input');
            input.type = type;
            input.className = 'form-control';
            input.name = name;
            if (required) input.required = true;
            formGroup.appendChild(input);
        } else if (type === 'textarea') {
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control';
            textarea.name = name;
            if (required) textarea.required = true;
            formGroup.appendChild(textarea);
        } else if (['select', 'multiselect'].includes(type)) {
            const select = document.createElement('select');
            select.className = 'form-select';
            select.name = name + (type === 'multiselect' ? '[]' : '');
            if (type === 'multiselect') select.multiple = true;

            const opts = options.split(',').map(opt => opt.trim());
            opts.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.innerText = opt;
                select.appendChild(option);
            });
            formGroup.appendChild(select);
        } else if (type === 'checkbox') {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.name = name;
            formGroup.appendChild(checkbox);
        } else if (type === 'upload') {
            const input = document.createElement('input');
            input.type = 'file';
            input.className = 'form-control';
            input.name = name;
            formGroup.appendChild(input);
        }

        preview.appendChild(formGroup);
    });
}

// Update preview whenever a field changes
document.getElementById('new-fields-container').addEventListener('input', buildLivePreview);
document.getElementById('new-fields-container').addEventListener('change', buildLivePreview);
</script>

<script>
new Sortable(document.getElementById('new-fields-container'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: () => {
        buildLivePreview();
updateFieldOrder();

    }
});
function updateFieldOrder() {
    const cards = document.querySelectorAll('#new-fields-container .card');
    cards.forEach((card, index) => {
        const sortInput = card.querySelector('.field-sort-order');
        if (sortInput) {
            sortInput.value = index;
        }
    });
}
</script>

<script>
function attachLabelToNameListeners() {
    const cards = document.querySelectorAll('#new-fields-container .card');

    cards.forEach(card => {
        const labelInput = card.querySelector('.field-label');
        const nameInput = card.querySelector('.field-name');

        if (labelInput && nameInput && !labelInput.dataset.listenerAttached) {
            labelInput.addEventListener('input', () => {
                let slug = labelInput.value;
                nameInput.value = slug;
            });
            // Prevent double-binding
            labelInput.dataset.listenerAttached = 'true';
        }
    });
}

// Call once for existing fields
attachLabelToNameListeners();

// Call again after adding a new field dynamically
document.getElementById('add-field-btn')?.addEventListener('click', () => {
    setTimeout(() => {
        attachLabelToNameListeners();
        buildLivePreview();
    }, 100); // slight delay to ensure new card is added
});
</script>

</body>
</html>