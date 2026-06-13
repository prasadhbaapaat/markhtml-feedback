<?php
// Example of a custom questionnaire array that can be dynamically rendered
$modules = [
    'Frontend Interface',
    'Backend API',
    'Database Schema',
    'Authentication Flow',
    'Deployment Pipeline'
];

$questions = [
    "1. How would you rate the overall clarity of this documentation?",
    "2. Are there any critical edge-cases we missed?",
    "3. Does this implementation align with our security guidelines?",
    "4. Do you foresee any performance bottlenecks?",
    "5. Which dependencies should we consider updating before launch?"
];
?>
<!-- Custom Questionnaire Form -->
<h5 class="mt-4 mb-3 text-info">Project Feedback Survey</h5>
<form id="questionnaireForm">
    <!-- Hidden fields required by the system to associate feedback with the current document/section -->
    <input type="hidden" name="section_id" id="section_id" value="<?= h($currentPageSlug) ?>">
    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

    <?php if (isset($_SESSION['user_name'])): ?>
        <input type="hidden" name="name" value="<?= h($_SESSION['user_name']) ?>">
    <?php else: ?>
        <div class="mb-4">
            <label for="name" class="form-label fw-bold">Your Name</label>
            <input type="text" class="form-control" id="name" name="name" required placeholder="Please enter your name">
        </div>
    <?php endif; ?>

    <h6 class="fw-bold mb-3 text-primary">Component Responsibility</h6>
    <div class="table-responsive mb-4 border rounded">
        <table class="table table-bordered table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 25%">Component</th>
                    <th>Primary Owner</th>
                    <th>Secondary Reviewer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $index => $module): ?>
                    <tr>
                        <td class="text-muted small fw-bold"><?= h($module) ?></td>
                        <td><input type="text" class="form-control form-control-sm border-0"
                                name="components[<?= $index ?>][owner]" placeholder="Name"></td>
                        <td><input type="text" class="form-control form-control-sm border-0"
                                name="components[<?= $index ?>][reviewer]" placeholder="Name"></td>
                        <td>
                            <select class="form-select form-select-sm border-0 text-muted" name="components[<?= $index ?>][status]">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Approved">Approved</option>
                            </select>
                        </td>
                        <input type="hidden" name="components[<?= $index ?>][name]" value="<?= h($module) ?>">
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h6 class="fw-bold mb-3 text-primary">Detailed Assessment</h6>
    <?php foreach ($questions as $index => $question): ?>
        <div class="mb-4">
            <label class="form-label text-dark fw-medium small"><?= h($question) ?></label>
            <textarea class="form-control bg-light" name="questions[<?= $index ?>][answer]" rows="2"></textarea>
            <input type="hidden" name="questions[<?= $index ?>][question]" value="<?= h($question) ?>">
        </div>
    <?php endforeach; ?>

    <?php if (!empty($config['recaptcha']['enabled'])): ?>
        <div class="mb-3 mt-4">
            <div class="g-recaptcha" data-sitekey="<?= h($config['recaptcha']['site_key']) ?>"></div>
        </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary px-4 shadow-sm">Submit Survey</button>
    <div id="formMessage" class="mt-2"></div>
</form>
