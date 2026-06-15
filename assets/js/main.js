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
                    
                    if (!commentText) {
                        let hasCustomFields = false;
                        for (let key of formData.keys()) {
                            if (!['section_id', 'name', 'email', 'feedback_type', 'comment', 'website', 'g-recaptcha-response', 'is_questionnaire'].includes(key)) {
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
                            <p class="mb-0 mt-2 text-break" style="white-space: pre-line;">${isHtml ? commentText : escapeHTML(commentText)}</p>
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
                            <p class="mb-0 text-break" style="white-space: pre-line;">${escapeHTML(commentText)}</p>
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
                            <p class="mb-0 small" style="white-space: pre-line;">${escapeHTML(commentText)}</p>
                        `;
                        
                        answersContainer.appendChild(newAnswer);
                    }
                    
                    form.reset();
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
