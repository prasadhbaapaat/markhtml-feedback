document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('commentForm');
    const questionnaireForm = document.getElementById('questionnaireForm');
    const formMessage = document.getElementById('formMessage');
    const commentsList = document.getElementById('comments-list');
    const noCommentsMsg = document.getElementById('no-comments-msg');

    const formToSubmit = commentForm || questionnaireForm;

    if (formToSubmit) {
        formToSubmit.addEventListener('submit', function(e) {
            e.preventDefault();
            
            formMessage.innerHTML = '<span class="text-info">Submitting data...</span>';
            
            const formData = new FormData(formToSubmit);
            
            fetch('comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    formMessage.innerHTML = `<span class="text-success">${data.message}</span>`;
                    
                    // Create new comment element dynamically to avoid full page reload
                    const newComment = document.createElement('div');
                    newComment.className = 'comment-thread mb-4';
                    
                    const name = formData.get('name') || '';
                    const feedbackType = formData.get('feedback_type') || '';
                    // Optimistic update formatting based on form type
                    let commentText = formData.get('comment') || '';
                    let isHtml = false;
                    
                    const attachmentPaths = formData.getAll('attachment_paths[]') || [];
                    const attachmentNames = formData.getAll('attachment_original_names[]') || [];
                    
                    if (attachmentPaths.length > 0) {
                        for (let i = 0; i < attachmentPaths.length; i++) {
                            const path = attachmentPaths[i];
                            const name = attachmentNames[i] || 'Attachment';
                            commentText += `\n\n${formatAttachmentLink(name, path)}`;
                        }
                    }

                    if (!commentText) {
                        let hasCustomFields = false;
                        for (let key of formData.keys()) {
                            if (!['section_id', 'name', 'email', 'feedback_type', 'comment', 'website', 'g-recaptcha-response', 'is_questionnaire', 'attachment_paths[]', 'attachment_original_names[]'].includes(key)) {
                                hasCustomFields = true;
                                break;
                            }
                        }
                        if (hasCustomFields) {
                            commentText = "<em>Structured Information Submitted Successfully. Please refresh to view full details.</em>";
                            isHtml = true;
                        }
                    }
                    
                    const feedbackTypeBadge = feedbackType
                        ? `<span class="badge text-bg-primary ms-2">${escapeHTML(feedbackType)}</span>`
                        : '';
                    
                    newComment.innerHTML = `
                        <div class="comment-item p-3 bg-white rounded shadow-sm border-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 fw-bold">${escapeHTML(name)} <small class="text-muted fw-normal ms-2">Just now</small>${feedbackTypeBadge}</h6>
                                </div>
                                <!-- Refresh to reply -->
                            </div>
                            <p class="mb-0 mt-2 text-break" style="white-space: pre-line;">${isHtml ? commentText : parseCommentLinks(escapeHTML(commentText))}</p>
                        </div>
                    `;
                    
                    if (noCommentsMsg) {
                        noCommentsMsg.style.display = 'none';
                    }
                    
                    // Prepend new comment
                    commentsList.insertBefore(newComment, commentsList.firstChild);
                    
                    // Reset form and recaptcha
                    formToSubmit.reset();
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }
                    
                    // Clear uploaded files globally
                    document.querySelectorAll('.upload-message').forEach(msg => {
                        msg.innerHTML = '';
                    });
                    document.querySelectorAll('.file-upload-input').forEach(input => {
                        input.value = '';
                    });
                    
                    setTimeout(() => {
                        formMessage.innerHTML = '';
                    }, 5000);
                } else {
                    formMessage.innerHTML = `<span class="text-danger">${data.message}</span>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                formMessage.innerHTML = '<span class="text-danger">An error occurred while submitting. Please try again.</span>';
            });
        });
    }

    // Handle Reply Button Clicks
    if (commentsList) {
        commentsList.addEventListener('click', function(e) {
            if (e.target.classList.contains('reply-btn')) {
                const parentId = e.target.getAttribute('data-parent-id');
                const formContainer = document.getElementById(`reply-form-${parentId}`);
                if (formContainer) {
                    formContainer.classList.toggle('d-none');
                }
            }
            if (e.target.classList.contains('cancel-reply-btn')) {
                const formContainer = e.target.closest('.reply-form-container');
                if (formContainer) {
                    formContainer.classList.add('d-none');
                }
            }
        });

        // Handle Reply Form Submissions
        commentsList.addEventListener('submit', function(e) {
            if (e.target.classList.contains('replyForm')) {
                e.preventDefault();
                const replyForm = e.target;
                const formMessage = replyForm.querySelector('.replyFormMessage');
                const parentId = replyForm.querySelector('input[name="parent_id"]').value;
                const repliesContainer = document.getElementById(`replies-${parentId}`);
                
                formMessage.innerHTML = '<span class="text-info">Submitting...</span>';
                
                const formData = new FormData(replyForm);
                
                fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        formMessage.innerHTML = `<span class="text-success">${data.message}</span>`;
                        
                        const newReply = document.createElement('div');
                        newReply.className = 'comment-item mb-3 p-3 bg-light rounded shadow-sm border-0';
                        
                        const name = formData.get('name') || '';
                        let commentText = formData.get('comment') || '';
                        
                        newReply.innerHTML = `
                            <h6 class="mb-1 fw-bold">${escapeHTML(name)} <small class="text-muted fw-normal ms-2">Just now</small></h6>
                            <p class="mb-0 text-break" style="white-space: pre-line;">${parseCommentLinks(escapeHTML(commentText))}</p>
                        `;
                        
                        repliesContainer.appendChild(newReply);
                        
                        replyForm.reset();
                        setTimeout(() => {
                            formMessage.innerHTML = '';
                            replyForm.closest('.reply-form-container').classList.add('d-none');
                        }, 2000);
                    } else {
                        formMessage.innerHTML = `<span class="text-danger">${data.message}</span>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    formMessage.innerHTML = '<span class="text-danger">Error submitting reply.</span>';
                });
            }
        });
    }

    // Handle Inline Questionnaire Forms
    document.body.addEventListener('submit', function(e) {
        if (e.target.classList.contains('inline-questionnaire-form')) {
            e.preventDefault();
            const form = e.target;
            const formMessage = form.querySelector('.formMessage');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            formMessage.innerHTML = '<span class="text-info">Submitting...</span>';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch('comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                if (data.success) {
                    formMessage.innerHTML = `<span class="text-success">${data.message}</span>`;
                    
                    const questionId = formData.get('question_id');
                    const answersContainer = document.getElementById(`answers-list-${questionId}`);
                    
                    if (answersContainer) {
                        const newAnswer = document.createElement('div');
                        newAnswer.className = 'comment-item p-2 mb-2 bg-light rounded border border-light';
                        
                        const name = formData.get('name') || '';
                        let commentText = formData.get('comment') || '';
                        
                        newAnswer.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1 small fw-bold">${escapeHTML(name)}</h6>
                                <small class="text-muted" style="font-size: 0.7rem;">Just now</small>
                            </div>
                            <p class="mb-0 small" style="white-space: pre-line;">${parseCommentLinks(escapeHTML(commentText))}</p>
                        `;
                        
                        answersContainer.appendChild(newAnswer);
                    }
                    
                    form.reset();
                    
                    // Clear uploaded files globally
                    document.querySelectorAll('.upload-message').forEach(msg => {
                        msg.innerHTML = '';
                    });
                    document.querySelectorAll('.file-upload-input').forEach(input => {
                        input.value = '';
                    });
                    
                    setTimeout(() => {
                        formMessage.innerHTML = '';
                    }, 3000);
                } else {
                    formMessage.innerHTML = `<span class="text-danger">${data.message}</span>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                formMessage.innerHTML = '<span class="text-danger">Error submitting answer.</span>';
            });
        }
    });
    // Handle File Uploads via AJAX (Option A: Inject links into textarea)
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('trigger-upload-btn')) {
            const btn = e.target;
            const uploadBlock = btn.closest('.upload-block');
            const fileInput = uploadBlock.querySelector('.file-upload-input');
            const files = fileInput.files;
            const progressBarContainer = uploadBlock.querySelector('.upload-progress');
            const progressBar = progressBarContainer.querySelector('.progress-bar');
            const messageBox = uploadBlock.querySelector('.upload-message');
            
            if (files.length === 0) {
                messageBox.innerHTML = '<span class="text-warning">Please choose a file first.</span>';
                return;
            }
            
            const maxSize = 20 * 1024 * 1024; // 20MB
            const formData = new FormData();
            let validFiles = 0;

            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    messageBox.innerHTML = '<span class="text-danger">One or more files exceed 20MB.</span>';
                    fileInput.value = '';
                    return;
                }
                formData.append('attachment[]', files[i]);
                validFiles++;
            }
            
            if (validFiles === 0) return;
            
            // Find the nearest textarea to inject links into
            let targetTextarea = null;
            let container = uploadBlock.closest('li, .card-body');
            if (container) {
                const textareas = container.querySelectorAll('textarea[name="comment"]');
                if (textareas.length > 0) {
                    targetTextarea = textareas[textareas.length - 1]; 
                }
            }
            if (!targetTextarea) {
                targetTextarea = document.querySelector('#commentForm textarea[name="comment"]');
            }
            
            progressBarContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            messageBox.innerHTML = '<span class="text-info">Uploading ' + validFiles + ' file(s)...</span>';
            btn.disabled = true;
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api_upload.php', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            };
            
            xhr.onload = function() {
                progressBarContainer.classList.add('d-none');
                btn.disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        const files = Array.isArray(data.files) ? data.files : [];
                        if (data.success || files.length > 0) {
                            let msg = `<span class="text-success">Uploaded ${files.length} file(s) and attached below!</span>`;
                            if (data.errors && data.errors.length > 0) {
                                msg += `<br><span class="text-warning">${escapeHTML(data.errors.join(' '))}</span>`;
                            }
                            messageBox.innerHTML = msg;

                            files.forEach(f => {
                                let link = formatAttachmentLink(f.original_name, f.path);
                                if (targetTextarea) {
                                    targetTextarea.value += (targetTextarea.value ? '\n\n' : '') + link;
                                }
                            });
                            
                            fileInput.value = '';
                            setTimeout(() => { messageBox.innerHTML = ''; }, 4000);
                        } else {
                            messageBox.innerHTML = `<span class="text-danger">${escapeHTML(data.message)}</span>`;
                        }
                    } catch(err) {
                        messageBox.innerHTML = '<span class="text-danger">Invalid server response.</span>';
                    }
                } else {
                    messageBox.innerHTML = '<span class="text-danger">Upload failed.</span>';
                }
            };
            
            xhr.onerror = function() {
                progressBarContainer.classList.add('d-none');
                btn.disabled = false;
                messageBox.innerHTML = '<span class="text-danger">Network error occurred during upload.</span>';
            };
            
            xhr.send(formData);
        }
    });
});

// Simple HTML escaper to prevent basic XSS when rendering optimistic UI
function escapeHTML(str) {
    return String(str).replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

// Builds the markdown link used to embed an uploaded attachment in a comment.
function formatAttachmentLink(name, path) {
    return `[Attached File: ${name}](${path})`;
}

// Simple JS implementation of app_parse_comment_links
function parseCommentLinks(text) {
    return String(text).replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (match, label, url) {
        var u = url.trim();
        // Only allow http(s) absolute URLs or relative paths; block javascript:, data:, etc.
        var hasScheme = /^[a-z][a-z0-9+.\-]*:/i.test(u);
        var isSafe = hasScheme ? /^https?:\/\//i.test(u) : u.indexOf('//') !== 0;
        if (!isSafe) {
            return match; // leave as plain (already-escaped) text, no link
        }
        return '<a href="' + u + '" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium"><i class="me-1">📎</i>' + label + '</a>';
    });
}
