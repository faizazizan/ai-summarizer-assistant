(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        console.log('AI Summarizer v1.1.2 loaded');

        const toggleBtn = document.getElementById('aisummarizer-toggle');
        const modal = document.getElementById('aisummarizer-modal');
        const closeBtn = document.getElementById('aisummarizer-close');
        const backdrop = document.querySelector('.aisummarizer-backdrop');
        const actionBtns = document.querySelectorAll('.aisummarizer-btn');

        if (!toggleBtn || !modal) {
            console.warn('AI Summarizer: UI elements not found');
            return;
        }

        // --- Modal Logic ---
        function openModal() {
            modal.classList.remove('aisummarizer-hidden');
            closeBtn.focus();
        }

        function closeModal() {
            modal.classList.add('aisummarizer-hidden');
            toggleBtn.focus();
        }

        toggleBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('aisummarizer-hidden')) {
                closeModal();
            }
        });

        // --- Content Extraction Logic ---
        async function getContent() {
            return new Promise(async (resolve, reject) => {
                // Timeout after 5 seconds
                const timeout = setTimeout(() => {
                    console.warn('Content fetch timed out');
                    resolve(null); // Resolve null to handle gracefully
                }, 5000);

                let content = '';

                try {
                    // 1. Gutenberg
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor')) {
                        try {
                            content = wp.data.select('core/editor').getEditedPostContent();
                            if (content) { clearTimeout(timeout); resolve(content); return; }
                        } catch (e) { console.log('Gutenberg fetch failed', e); }
                    }

                    // 2. Elementor Editor
                    if (window.elementor) {
                        try {
                            const previewFrame = document.getElementById('elementor-preview-iframe');
                            if (previewFrame && previewFrame.contentDocument) {
                                const previewBody = previewFrame.contentDocument.body;
                                if (previewBody) {
                                    const article = previewBody.querySelector('article') || previewBody.querySelector('.elementor-section-wrap') || previewBody;
                                    content = article.innerHTML;
                                    if (content) { clearTimeout(timeout); resolve(content); return; }
                                }
                            }
                        } catch (e) { console.log('Elementor fetch failed', e); }
                    }

                    // 3. Classic Editor (TinyMCE)
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                        content = tinyMCE.activeEditor.getContent();
                        if (content) { clearTimeout(timeout); resolve(content); return; }
                    }

                    // 4. Textarea fallback
                    const contentTextarea = document.querySelector('#content');
                    if (contentTextarea && contentTextarea.value) {
                        content = contentTextarea.value;
                        if (content) { clearTimeout(timeout); resolve(content); return; }
                    }

                    // 5. REST API
                    if (typeof AISummarizer !== 'undefined' && AISummarizer.post_id && AISummarizer.rest_url) {
                        try {
                            const response = await fetch(`${AISummarizer.rest_url}${AISummarizer.post_id}`, {
                                headers: { 'X-WP-Nonce': AISummarizer.rest_nonce }
                            });
                            if (response.ok) {
                                const data = await response.json();
                                if (data.content && data.content.rendered) {
                                    clearTimeout(timeout);
                                    resolve(data.content.rendered);
                                    return;
                                }
                            }
                        } catch (e) { console.log('REST fetch failed', e); }
                    }

                    // 6. Frontend DOM Fallback
                    const article = document.querySelector('article') || document.querySelector('.entry-content') || document.querySelector('#content') || document.querySelector('main');
                    if (article) {
                        content = article.innerHTML;
                    } else {
                        content = document.body.innerHTML;
                    }

                    clearTimeout(timeout);
                    resolve(content);

                } catch (err) {
                    clearTimeout(timeout);
                    reject(err);
                }
            });
        }

        // --- Sanitization ---
        function sanitizeContent(html) {
            const div = document.createElement('div');
            div.innerHTML = html;

            // Remove dangerous/UI elements
            const dangerous = div.querySelectorAll('script, style, iframe, object, embed, form, button, input, #aisummarizer-container, .aisummarizer-hidden');
            dangerous.forEach(el => el.remove());

            // Remove event handlers
            const elements = div.querySelectorAll('*');
            elements.forEach(el => {
                Array.from(el.attributes).forEach(attr => {
                    if (attr.name.startsWith('on')) el.removeAttribute(attr.name);
                });
            });

            // Get text content
            let text = div.innerText;

            // Normalize whitespace:
            // 1. Collapse multiple spaces/tabs into single space
            text = text.replace(/[ \t]+/g, ' ');
            // 2. Collapse 3+ newlines into 2 (preserve paragraph breaks)
            text = text.replace(/\n\s*\n\s*\n+/g, '\n\n');
            // 3. Trim
            text = text.trim();

            // Truncate to ~1500 chars for URL safety
            const maxLength = 1500;
            if (text.length > maxLength) {
                console.warn('AI Summarizer: Content truncated to fit URL limit.');
                text = text.substring(0, maxLength) + '... [Content Truncated]';
            }

            return text;
        }

        // --- Action Handlers ---
        actionBtns.forEach(btn => {
            btn.addEventListener('click', async function () {
                const provider = this.getAttribute('data-provider');
                const originalText = this.innerHTML;

                // 1. Open window IMMEDIATELY to bypass popup blockers
                const newWindow = window.open('', '_blank');
                if (newWindow) {
                    newWindow.document.write('<html><head><title>AI Summarizer</title><style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f0f0f0;color:#333;}</style></head><body><h2>Preparing AI Summary...</h2></body></html>');
                } else {
                    alert('Please allow popups for this site to use the AI Summarizer.');
                    return;
                }

                // Show loading state on button
                this.innerHTML = 'Processing...';
                this.disabled = true;

                try {
                    let rawContent = await getContent();

                    if (!rawContent) {
                        if (newWindow) newWindow.close();
                        alert('Could not detect content. Please ensure you are on a post or page.');
                        return;
                    }

                    // Prepare Full Text for Clipboard (Cleaned but not truncated)
                    const div = document.createElement('div');
                    div.innerHTML = rawContent;
                    // Clean up for clipboard too
                    const dangerous = div.querySelectorAll('script, style, iframe, object, embed, form, button, input, #aisummarizer-container');
                    dangerous.forEach(el => el.remove());
                    let fullText = div.innerText;
                    fullText = fullText.replace(/[ \t]+/g, ' ').replace(/\n\s*\n\s*\n+/g, '\n\n').trim();

                    // Cap at 16000 characters as requested
                    if (fullText.length > 16000) {
                        fullText = fullText.substring(0, 16000) + '... [Content Truncated to 16000 chars]';
                    }

                    const currentUrl = window.location.href;
                    const fullPrompt = `Summarize this article ${currentUrl} ;\n\n${fullText}`;

                    // Copy to clipboard
                    try {
                        await navigator.clipboard.writeText(fullPrompt);
                    } catch (err) {
                        console.error('Clipboard failed', err);
                    }

                    // Prepare URL (Truncated)
                    const sanitizedTruncated = sanitizeContent(rawContent);
                    const urlPrompt = `Summarize this article ${currentUrl} ;\n\n${sanitizedTruncated}`;
                    const encodedPrompt = encodeURIComponent(urlPrompt);

                    // Always use GPT
                    const url = `https://chat.openai.com/?q=${encodedPrompt}`;

                    // Update button text to give feedback
                    this.innerHTML = 'Copied! Opening...';

                    // Update the opened window location
                    if (newWindow) {
                        newWindow.location.href = url;
                    }

                    // Generic notice
                    const notice = document.querySelector('.aisummarizer-notice small');
                    if (notice) notice.innerText = "Text copied to clipboard! Paste (Ctrl+V) if text is missing.";

                } catch (err) {
                    console.error(err);
                    if (newWindow) newWindow.close();
                    alert('Error processing content.');
                } finally {
                    // Reset button after a short delay to let user see "Copied!"
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        closeModal();
                    }, 1500);
                }
            });
        });
    });

})();
