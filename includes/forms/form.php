<?php
require_once __DIR__ . '/../includes/db.php';

$form_id = $_GET['form_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$form_id]);
$form = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY id ASC");
$stmt->execute([$form_id]);
$fields = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string)($form['form_name'] ?? '')) ?> - Public Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <h1 class="mb-4"><?= htmlspecialchars((string)($form['form_name'] ?? '')) ?></h1>

    <form method="post" action="submit_form.php?form_id=<?= $form_id ?>" enctype="multipart/form-data">
        <?php foreach ($fields as $field): ?>
            <?php
            $isRequired = !empty($field['required']) ? 'required' : '';
            $name = htmlspecialchars((string)$field['name']);
            $label = htmlspecialchars((string)$field['label']);
            ?>

            <div class="mb-3">
                <label class="form-label"><?= $label ?></label>

                <?php if (in_array($field['field_type'], ['text', 'email', 'password', 'date'])): ?>
                    <input type="<?= $field['field_type'] ?>" name="<?= $name ?>" class="form-control" <?= $isRequired ?>>

                <?php elseif ($field['field_type'] === 'textarea'): ?>
                    <textarea name="<?= $name ?>" class="form-control" <?= $isRequired ?>></textarea>

                <?php elseif ($field['field_type'] === 'select'): ?>
                    <select name="<?= $name ?>" class="form-select" <?= $isRequired ?>>
                        <?php foreach (explode(',', $field['options']) as $opt): ?>
                            <option value="<?= trim($opt) ?>"><?= trim($opt) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($field['field_type'] === 'multiselect'): ?>
                    <select name="<?= $name ?>[]" class="form-select" multiple <?= $isRequired ?>>
                        <?php foreach (explode(',', $field['options']) as $opt): ?>
                            <option value="<?= trim($opt) ?>"><?= trim($opt) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($field['field_type'] === 'checkbox'): ?>
                    <?php foreach (explode(',', $field['options']) as $opt): ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="<?= $name ?>[]" value="<?= trim($opt) ?>" id="<?= $name . '_' . trim($opt) ?>">
                            <label class="form-check-label" for="<?= $name . '_' . trim($opt) ?>"><?= trim($opt) ?></label>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ($field['field_type'] === 'upload'): ?>
                    <input type="file" name="<?= $name ?>" class="form-control" <?= $isRequired ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-success">Submit</button>
    </form>
</div>
</body>
</html>
