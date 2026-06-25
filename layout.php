<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - <?= h($config['title_suffix']) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        <?= app_theme_style($config['theme'], $docTheme ?? []) ?>
    </style>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <?php if (!empty($config['recaptcha']['enabled'])): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>

<body class="d-flex flex-column min-vh-100">

    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm sticky-top app-navbar">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="?">
                <?php if (!empty($config['logo_path'])): ?>
                    <img src="<?= h(app_asset($config['logo_path'])) ?>" alt="<?= h($config['logo_alt']) ?>" height="30"
                        class="me-2">
                <?php else: ?>
                    <?= h($config['brand_name']) ?>
                <?php endif; ?>
            </a>
            <div class="sidebar-title px-3 pt-2 mb-3">
                <div class="text-white">Document Heading: <?= h($documentTitle) ?>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="text-white me-3 d-none d-md-inline">Welcome,
                    <?= h($_SESSION['user_name'] ?? 'User') ?></span>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                    <a href="admin.php" class="btn btn-sm btn-outline-warning me-2">Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-light btn-sm me-2">Logout</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu"
                    aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid flex-grow-1 d-flex flex-column">
        <div class="row flex-grow-1">
            <!-- Sidebar Navigation -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow-sm">
                <div class="position-sticky pt-3">
                    <?php if (count($availableDocuments) > 1): ?>
                        <div class="px-3 mb-3">
                            <label class="form-label small text-uppercase fw-bold mb-1 px-2 py-1 rounded text-white w-100"
                                style="background-color: var(--brand-primary);">Select Document</label>
                            <select class="form-select form-select-sm document-switcher"
                                onchange="window.location.href='?doc=' + this.value">
                                <?php foreach ($availableDocuments as $docKey => $docConfig): ?>
                                    <option value="<?= h($docKey) ?>" <?= $docKey === $documentId ? 'selected' : '' ?>>
                                        <?= h($docConfig['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <h4 class="ps-3">Navigation</h4>
                    <ul class="nav flex-column mb-auto">
                        <?php foreach ($navigation as $slug => $navItem): ?>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-start gap-2 <?= $slug === $currentPageSlug ? 'active' : '' ?>"
                                    href="<?= h(app_page_url($slug, $documentId)) ?>">
                                    <?php if (!empty($config['content']['show_page_numbers'])): ?>
                                        <span class="nav-number"><?= (int) $navItem['order'] ?></span>
                                    <?php endif; ?>
                                    <span class="flex-grow-1"><?= h($navItem['title']) ?></span>
                                    <?php if (!empty($commentCounts[$slug])): ?>
                                        <span class="badge rounded-pill text-bg-light"><?= (int) $commentCounts[$slug] ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php if (!empty($pageError)): ?>
                    <div class="alert alert-danger shadow-sm border-0 mt-4">
                        <h4 class="alert-heading">Document Error</h4>
                        <p>The document you are trying to access cannot be loaded. It may have been deleted or the
                            configuration is incorrect.</p>
                        <hr>
                        <p class="mb-0 small"><strong>System Message:</strong> <?= h($pageError) ?></p>
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm mb-4 border-0">
                        <div class="card-body document-content">
                            <?= $htmlContent ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($pageError) && !empty($config['content']['show_previous_next'])): ?>
                    <div class="d-flex justify-content-between gap-3 mb-4">
                        <div>
                            <?php if (!empty($previousNext['previous'])): ?>
                                <a class="btn btn-secondary"
                                    href="<?= h(app_page_url($previousNext['previous']['slug'], $documentId)) ?>">&larr;
                                    <?= h($previousNext['previous']['title']) ?></a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!empty($previousNext['next'])): ?>
                                <a class="btn btn-secondary"
                                    href="<?= h(app_page_url($previousNext['next']['slug'], $documentId)) ?>"><?= h($previousNext['next']['title']) ?>
                                    &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Comments Section -->
                <?php if (empty($pageError) && !($format === 'questionnaire' && !empty($hasQuestions))): ?>
                    <div class="card shadow-sm border-0 mt-5 comments-panel">
                        <div class="card-header border-0 pt-4 pb-0 comments-panel-header">
                            <h4 class="mb-0">Reviewer Comments</h4>
                        </div>
                        <div class="card-body">

                            <!-- Display Comments -->
                            <div id="comments-list" class="mb-4">
                                <?php if (empty($comments)): ?>
                                    <p class="text-muted" id="no-comments-msg">No comments yet. Be the first to suggest changes!
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($comments as $c): ?>
                                        <div class="comment-thread mb-4">
                                            <div class="comment-item p-3 bg-white rounded shadow-sm border-0"
                                                data-comment-id="<?= $c['id'] ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1 fw-bold">
                                                            <?= h($c['name']) ?>
                                                            <small
                                                                class="text-muted fw-normal ms-2"><?= h($c['created_at']) ?></small>
                                                            <?php if (!empty($c['feedback_type'])): ?>
                                                                <span
                                                                    class="badge text-bg-primary ms-2"><?= h($c['feedback_type']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($c['resolved'])): ?>
                                                                <span class="badge text-bg-success ms-2">Resolved</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <?php if (!empty($c['email'])): ?>
                                                            <div class="small text-muted mb-2"><?= h($c['email']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-secondary reply-btn"
                                                        data-parent-id="<?= $c['id'] ?>">Reply</button>
                                                </div>
                                                <p class="mb-0 mt-2 text-break" style="white-space: pre-line;">
                                                    <?= app_parse_comment_links(h($c['comment'])) ?></p>
                                            </div>

                                            <!-- Replies Container -->
                                            <div class="replies-container ms-4 mt-3" id="replies-<?= $c['id'] ?>">
                                                <?php if (!empty($c['replies'])): ?>
                                                    <?php foreach ($c['replies'] as $reply): ?>
                                                        <div class="comment-item mb-3 p-3 bg-light rounded shadow-sm border-0">
                                                            <h6 class="mb-1 fw-bold">
                                                                <?= h($reply['name']) ?>
                                                                <small
                                                                    class="text-muted fw-normal ms-2"><?= h($reply['created_at']) ?></small>
                                                            </h6>
                                                            <p class="mb-0 text-break" style="white-space: pre-line;">
                                                                <?= app_parse_comment_links(h($reply['comment'])) ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Inline Reply Form -->
                                            <div class="reply-form-container d-none ms-4 mt-2" id="reply-form-<?= $c['id'] ?>">
                                                <form class="replyForm">
                                                    <input type="hidden" name="section_id" value="<?= h($currentPageSlug) ?>">
                                                    <input type="hidden" name="document_id" value="<?= h($documentId) ?>">
                                                    <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                                                    <input type="text" name="website" class="d-none" tabindex="-1"
                                                        autocomplete="off" aria-hidden="true">
                                                    <div class="row g-2 mb-2">
                                                        <?php if (isset($_SESSION['user_name'])): ?>
                                                            <input type="hidden" name="name" value="<?= h($_SESSION['user_name']) ?>">
                                                            <input type="hidden" name="email"
                                                                value="<?= h($_SESSION['user_email'] ?? '') ?>">
                                                            <div class="col-12 mb-2 text-muted small">
                                                                Replying as: <strong><?= h($_SESSION['user_name']) ?></strong>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="col-md-6">
                                                                <input type="text" class="form-control form-control-sm" name="name"
                                                                    required placeholder="Your Name">
                                                            </div>
                                                            <?php if (!empty($config['comments']['collect_email'])): ?>
                                                                <div class="col-md-6">
                                                                    <input type="email" class="form-control form-control-sm" name="email"
                                                                        placeholder="Email (optional)">
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-2">
                                                        <textarea class="form-control form-control-sm" name="comment" rows="2"
                                                            required placeholder="Write a reply..."></textarea>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="replyFormMessage small"></div>
                                                        <div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-light cancel-reply-btn">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary">Post Reply</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <hr>

                            <?php if (empty($config['comments']['enabled'])): ?>
                                <p class="text-muted mb-0">Comments are currently disabled for this document.</p>
                            <?php elseif (isset($config['special_forms'][$currentPageSlug])): ?>
                                <?php include $config['special_forms'][$currentPageSlug]; ?>
                            <?php else: ?>
                                <!-- Add Comment Form -->
                                <h5 class="mt-4 mb-3">Add Your Feedback</h5>
                                <form id="commentForm">
                                    <input type="hidden" name="section_id" id="section_id" value="<?= h($currentPageSlug) ?>">
                                    <input type="hidden" name="document_id" id="document_id" value="<?= h($documentId) ?>">
                                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off"
                                        aria-hidden="true">
                                    <?php if (isset($_SESSION['user_name'])): ?>
                                        <input type="hidden" name="name" value="<?= h($_SESSION['user_name']) ?>">
                                        <input type="hidden" name="email" value="<?= h($_SESSION['user_email'] ?? '') ?>">
                                        <div class="mb-3 text-muted">
                                            Commenting as: <strong><?= h($_SESSION['user_name']) ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Your Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required
                                                placeholder="Please add name">
                                        </div>
                                        <?php if (!empty($config['comments']['collect_email'])): ?>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email <span
                                                        class="text-muted fw-normal">(optional)</span></label>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    placeholder="john@example.com">
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($config['comments']['collect_feedback_type'])): ?>
                                        <div class="mb-3">
                                            <label for="feedback_type" class="form-label">Feedback Type</label>
                                            <select class="form-select" id="feedback_type" name="feedback_type">
                                                <option value="">Select feedback type</option>
                                                <?php foreach ($config['comments']['feedback_types'] as $typeOption): ?>
                                                    <option value="<?= h($typeOption) ?>"><?= h($typeOption) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="4"
                                            placeholder="Enter your suggestions here..."></textarea>
                                    </div>

                                    <?php if (!empty($config['recaptcha']['enabled'])): ?>
                                        <div class="mb-3">
                                            <div class="g-recaptcha" data-sitekey="<?= h($config['recaptcha']['site_key']) ?>">
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-primary px-4">Submit Comment</button>
                                    <div id="formMessage" class="mt-2"></div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?> <!-- End if empty pageError and not hiding for questionnaire -->
            </main>
        </div>
    </div>

    <footer class="text-white text-end py-3 mt-auto pe-4 app-footer">
        <small>&copy; <?= h($config['footer_text']) ?></small>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>

</html>