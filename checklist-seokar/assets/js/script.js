/**
 * Checklist Seokar - Real-time Admin Script v2.0.0
 *
 * Handles real-time checklist updates, SERP preview, AJAX checks,
 * and interactions within the WordPress editor (Classic & Gutenberg),
 * based on user-configurable settings.
 *
 * @package Checklist_Seokar
 */
jQuery(document).ready(function($) {
    'use strict';

    // --- Early Exit if Checklist Wrapper isn't present ---
    const $wrapper = $('.csk-checklist-wrapper');
    if (!$wrapper.length) return;

    // --- Configuration & Data from PHP (wp_localize_script - csk_vars) ---
    const config = {
        // Settings passed from PHP
        settings: window.csk_vars?.settings || {}, // Default to empty object if not set
        ajaxUrl: window.csk_vars?.ajax_url || '',
        nonce: window.csk_vars?.nonce || '',
        featuredImageNonce: window.csk_vars?.featuredImageNonce || '',
        textDomain: window.csk_vars?.text_domain || 'checklist-seokar',
        objectId: window.csk_vars?.object_id || 0,
        isTerm: window.csk_vars?.is_term || false,
        isGutenberg: window.csk_vars?.is_gutenberg || false,
        // i18n: window.csk_vars?.i18n || {}, // For translated strings if needed

        // JS internal config
        debounceDelay: 550,      // General input debounce
        editorDebounceDelay: 750, // Editor content debounce
        ajaxTimeout: 15000,     // AJAX request timeout (15 seconds)

        // Default thresholds (used if settings not available)
        defaultMinLength: 300,
        defaultDensityMin: 0.5,
        defaultDensityMax: 2.5,

        // --- Retrieve specific settings with defaults ---
        get minLength() { return this.settings?.minLength ?? this.defaultMinLength; },
        get densityMin() { return this.settings?.densityMin ?? this.defaultDensityMin; },
        get densityMax() { return this.settings?.densityMax ?? this.defaultDensityMax; },
        get enabledChecks() { return this.settings?.enabledChecks || {}; },

        // --- Persian Stop Words (Consider passing from PHP if extensive/filterable) ---
        stopWords: [
            'از', 'به', 'در', 'با', 'و', 'که', 'یا', 'را', 'این', 'آن', 'اینها', 'آنها',
            'برای', 'بر', 'تا', 'هم', 'نیز', 'کنید', 'کنم', 'کند', 'کنند', 'کرد', 'کردم', 'کردی',
            'کردند', 'شده', 'شدم', 'شدی', 'شد', 'شدند', 'بود', 'بودم', 'بودی', 'بودند', 'است',
            'هست', 'هستم', 'هستی', 'هستند', 'نیست', 'نیستم', 'نیستی', 'نیستند', 'باشد', 'باشم',
            'باشی', 'باشند', 'می', 'های', 'تر', 'ترین', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش',
            'هفت', 'هشت', 'نه', 'ده', 'هر', 'همه', 'من', 'تو', 'او', 'ما', 'شما', 'ایشان',
            'خود', 'اگر', 'اما', 'ولی', 'پس', 'زیرا', 'چون', 'چه', 'چگونه', 'کجا', 'کی', 'وقتی',
            'چیزی', 'کسی', 'دیگر', 'درباره', 'مورد', 'روی', 'زیر', 'بالای', 'بین', 'قبل', 'بعد',
            'فقط', 'حدود', 'مثل', 'مانند', 'طبق', 'بر اساس', 'اول', 'دوم', 'سوم', // Add more?
        ],
        stopWordsPattern: null, // Will be generated on init

        // --- DOM Selectors ---
        selectors: {
            mainKeyword: '#csk_main_keyword',
            relatedKeywords: '#csk_related_keywords',
            googleTitle: '#csk_google_title',
            googleDescription: '#csk_google_description',
            classicEditor: '#content',
            visualEditorIframe: '#content_ifr',
            termDescription: '#description',
            postTitle: '#title',
            termName: '#name',
            termSlug: '#slug',
            postSlugClassic: '#post_name', // Classic editor slug input
            checklistItems: '.csk-checklist li[data-check]',
            serpTitle: '#csk-serp-preview .csk-serp-title',
            serpUrl: '#csk-serp-preview .csk-serp-url',
            serpDesc: '#csk-serp-preview .csk-serp-desc',
            overallStatusDiv: '#csk-overall-status',
            overallMessageSpan: '#csk-overall-status .csk-overall-message',
            checklistLoader: '.csk-checklist-loader', // Overall loader near h4
            itemLoader: '.csk-item-loader', // Per-item loader
        }
    };

    // Cache jQuery selectors
    const $selectors = {};
    for (const key in config.selectors) {
        $selectors[key] = $(config.selectors[key]);
    }
    $selectors.wrapper = $wrapper; // Add wrapper itself

    // --- State Variables ---
    let isUpdating = false; // Flag to prevent concurrent updates
    let ajaxRequests = {}; // Store ongoing AJAX requests (e.g., for featured image)

    // --- Initialization ---
    function initializeChecklist() {
        // console.log('CSK: Initializing Checklist - Config:', config);

        // Prepare stop words regex pattern
        if (config.stopWords.length > 0) {
            const pattern = config.stopWords.map(word => '\\b' + word.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '\\b').join('|');
            config.stopWordsPattern = new RegExp(pattern, 'giu'); // Global, Case-insensitive, Unicode
        }

        // Hide checklist items that are disabled in settings
        $selectors.checklistItems.each(function() {
            const $item = $(this);
            const checkKey = $item.data('check');
            if (!config.enabledChecks[checkKey]) {
                $item.hide().addClass('csk-check-disabled');
            }
            // Special case: hide featured image for terms regardless of setting
             if (checkKey === 'featured_image' && config.isTerm) {
                  $item.hide().addClass('csk-check-disabled');
             }
        });

        // Initial checklist run after a delay
        setTimeout(runChecklistUpdate, 700);
        // Trigger initial featured image check if applicable and enabled
        if (!config.isTerm && config.enabledChecks.featured_image) {
             setTimeout(checkFeaturedImageStatus, 800); // Slight delay after main update
        }

        attachEventListeners();
    }

    // --- Helper Functions ---

    /**
     * Gets the current content from the active editor.
     * @returns {string} The editor content.
     */
    function getCurrentContent() {
        let content = '';
        if (config.isGutenberg && typeof wp !== 'undefined' && wp.data?.select) {
            content = wp.data.select('core/editor')?.getEditedPostAttribute('content');
            if (typeof content === 'string') return content;
        }
        if (typeof tinymce !== 'undefined') {
             const editorInstance = tinymce.get('content');
             if (editorInstance && !editorInstance.isHidden()) {
                  content = editorInstance.getContent({ format: 'raw' }); // Get raw content
                  if (content) return content;
             }
        }
        if ($selectors.termDescription.length) content = $selectors.termDescription.val();
        else if ($selectors.classicEditor.length) content = $selectors.classicEditor.val();

        return content || ''; // Return empty string if nothing found
    }

    /** Gets the current title. */
    function getCurrentTitle() {
        let title = '';
        if (config.isGutenberg && typeof wp !== 'undefined' && wp.data?.select) {
             title = wp.data.select('core/editor')?.getEditedPostAttribute('title');
             if (typeof title === 'string') return title.trim();
        }
        if ($selectors.termName.length) title = $selectors.termName.val();
        else if ($selectors.postTitle.length) title = $selectors.postTitle.val();

        return title?.trim() || $selectors.wrapper.data('default-title') || '';
    }

     /** Gets the current slug. */
    function getCurrentSlug() {
        let slug = '';
         if (config.isGutenberg && typeof wp !== 'undefined' && wp.data?.select) {
            slug = wp.data.select('core/editor')?.getEditedPostAttribute('slug');
             if (typeof slug === 'string') return slug.trim();
         }
        if ($selectors.termSlug.length) slug = $selectors.termSlug.val();
        else if ($selectors.postSlugClassic.length) slug = $selectors.postSlugClassic.val();

         return slug?.trim() || $selectors.wrapper.data('default-slug') || '';
    }

     /** Generates SERP Preview URL. */
    function getSerpPreviewUrl() {
        let displayUrl = $selectors.wrapper.data('default-url') || window.location.origin;
        const currentSlug = getCurrentSlug();
        displayUrl = displayUrl.replace(/^(?:https?:\/\/)?(?:www\.)?/i, "").replace(/\/$/, "");
        const parts = displayUrl.split('/');
        let path = '';

        if (currentSlug) {
            path = currentSlug;
        } else if (parts.length > 1) {
            path = parts[parts.length - 1]; // Use last part of default URL if no slug
        }

        let domain = parts[0] || window.location.hostname;

        // Basic truncation/simplification logic
        if (path) {
            displayUrl = domain + ' › ' + path.replace(/-/g, ' '); // Use › and replace hyphens
            if (displayUrl.length > 55) {
                 displayUrl = domain + ' › ... › ' + path.substring(path.length - 15).replace(/-/g, ' ');
            }
        } else {
            displayUrl = domain;
        }

        return displayUrl.substring(0, 60); // Final length limit
    }

    /** Strips HTML tags. */
    function stripHtml(htmlString) {
        if (!htmlString || typeof htmlString !== 'string') return '';
        try {
            let tmp = document.createElement("DIV");
            tmp.innerHTML = htmlString;
            return tmp.textContent || tmp.innerText || "";
        } catch (e) { return htmlString.replace(/<[^>]*>?/gm, ''); } // Basic fallback
    }

    /** Counts words, excluding punctuation and optionally stopwords. */
    function countWords(text, removeStopwords = false) {
        if (!text) return 0;
        let processedText = text.toLowerCase();
        processedText = processedText.replace(/[\p{P}\p{S}]+/gu, ' '); // Remove punctuation and symbols
        if (removeStopwords && config.stopWordsPattern) {
            processedText = processedText.replace(config.stopWordsPattern, '');
        }
        processedText = processedText.replace(/\s+/g, ' ').trim(); // Normalize whitespace
        return processedText ? processedText.split(' ').length : 0;
    }

    /** Counts sentences (basic). */
    function countSentences(text) {
        if (!text) return 0;
        // Split by sentence terminators followed by space or end
        const sentences = text.match(/[^.!?؟۔]+[.!?؟۔]+/gu);
        return sentences ? sentences.length : 1; // Assume 1 if text exists but no terminator found
    }

    /** Approximates syllables (very basic). */
    function countSyllablesApprox(text) {
         if (!text) return 0;
         text = text.toLowerCase();
         const words = text.match(/[\p{L}\p{N}]+/gu) || [];
         let totalSyllables = 0;
         const persianVowels = /[اآاوی]/u; // Basic check for vowels
         words.forEach(word => {
             const vowelMatches = word.match(persianVowels);
             totalSyllables += Math.max(1, vowelMatches ? vowelMatches.length : 0); // At least 1 syllable per word
         });
         return totalSyllables || 1; // At least 1 syllable overall
    }

    /** Truncates text. */
    function truncateText(text, maxLength) {
        if (!text) return '';
        text = String(text);
        if (text.length <= maxLength) return text;
        let truncated = text.substr(0, maxLength);
        let lastSpace = truncated.lastIndexOf(' ');
        if (lastSpace > maxLength / 2) truncated = truncated.substr(0, lastSpace);
        return truncated + '...';
    }

    /** Debounce function. */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const context = this; // Capture 'this' context
            const later = () => {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /** Shows/hides the main checklist loader. */
    function toggleChecklistLoader(show) {
        if (show) $selectors.checklistLoader.css('display', 'inline-block');
        else $selectors.checklistLoader.hide();
        $selectors.overallStatusDiv.toggleClass('csk-loading', show);
    }

    /** Shows/hides a specific item's loader. */
     function toggleItemLoader($item, show) {
         const $loader = $item.find(config.selectors.itemLoader);
         if (show) $loader.css('display', 'inline-block');
         else $loader.hide();
     }

    /** Updates a checklist item's UI. */
    function updateChecklistItemUI(checkKey, status, message) {
        const $item = $selectors.checklistItems.filter(`[data-check="${checkKey}"]`);
        if ($item.length && !$item.hasClass('csk-check-disabled')) {
            const $icon = $item.find('.csk-status-icon');
            const $message = $item.find('.csk-check-message');
            const currentStatusClass = Array.from($icon[0].classList).find(cls => cls.startsWith('csk-status-'));

            $icon.removeClass(currentStatusClass || '').addClass(`csk-status-${status || 'pending'}`);
            $message.text(message || '');
            toggleItemLoader($item, false); // Hide loader after update
        }
    }

    // --- AJAX Function for Featured Image ---
    function checkFeaturedImageStatus() {
        const checkKey = 'featured_image';
        if (!config.enabledChecks[checkKey] || config.isTerm) return; // Skip if disabled or term

        const $item = $selectors.checklistItems.filter(`[data-check="${checkKey}"]`);
        if (!$item.length || $item.hasClass('csk-check-disabled')) return;

        // Abort previous request if any
        if (ajaxRequests[checkKey]) {
             ajaxRequests[checkKey].abort();
        }

        toggleItemLoader($item, true); // Show loader

        ajaxRequests[checkKey] = $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csk_check_featured_image', // Matches PHP wp_ajax_ action
                _ajax_nonce: config.featuredImageNonce, // Specific nonce for this action
                post_id: config.objectId // Pass the current post ID
            },
            dataType: 'json',
            timeout: config.ajaxTimeout
        })
        .done(function(response) {
            if (response && response.success && response.data) {
                updateChecklistItemUI(checkKey, response.data.status, response.data.message);
            } else {
                 // Handle failure or invalid response
                 const errorMsg = response?.data?.message || 'Error checking featured image.';
                 updateChecklistItemUI(checkKey, 'pending', errorMsg);
                 console.error("CSK AJAX Error (Featured Image):", response?.data?.debug || 'Unknown error');
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            if (textStatus !== 'abort') { // Don't show error if manually aborted
                console.error("CSK AJAX Fail (Featured Image):", textStatus, errorThrown);
                 updateChecklistItemUI(checkKey, 'pending', 'Could not check featured image (request failed).');
            }
        })
        .always(function() {
            toggleItemLoader($item, false); // Hide loader always
            delete ajaxRequests[checkKey]; // Clear request state
        });
    }

    // --- Sanitizes slug (basic JS version) ---
    function sanitizeSlug(text) {
        if (!text) return '';
        return text.toString().toLowerCase()
                   .replace(/\s+/g, '-')           // Replace spaces with -
                   .replace(/[^\u0600-\u06FF\uFB8A\u067E\u0686\u06AF\u0750-\u077F\w-]+/g, '') // Remove invalid chars (allow Persian, basic Latin, numbers, hyphen)
                   .replace(/--+/g, '-')         // Replace multiple - with single -
                   .replace(/^-+/, '')             // Trim - from start of text
                   .replace(/-+$/, '');            // Trim - from end of text
    }

    // --- Core Update Function ---
    const runChecklistUpdate = () => {
        if (isUpdating) return; // Prevent concurrent runs
        isUpdating = true;
        toggleChecklistLoader(true);
        // console.log('CSK: Running checklist update...');

        // 1. Get current data
        const content = getCurrentContent();
        const plainContent = stripHtml(content);
        const wordCount = countWords(plainContent);
        const wordCountNoStopwords = countWords(plainContent, true); // For density
        const charCount = plainContent.length;
        const mainKeyword = $selectors.mainKeyword.val()?.trim() || '';
        const mainKeywordLower = mainKeyword.toLowerCase();
        const relatedKeywords = $selectors.relatedKeywords.val()?.trim() || '';
        const googleTitle = $selectors.googleTitle.val()?.trim() || '';
        const googleDescription = $selectors.googleDescription.val()?.trim() || '';
        const currentTitle = getCurrentTitle();
        const currentSlug = getCurrentSlug();
        const effectiveTitle = googleTitle || currentTitle;
        const effectiveDescription = googleDescription || $selectors.wrapper.data('default-description') || stripHtml(content).substr(0, 180); // Fallback for SERP

        let checks = {}; // Store results { status: '...', message: '...' }
        let counts = { success: 0, warning: 0, error: 0, pending: 0 };

        const recordStatus = (key, status, message) => {
             // Only record if the check is enabled
             if (config.enabledChecks[key]) {
                 checks[key] = { status, message };
                 if (status !== 'pending') counts[status]++; // Don't count pending towards total success/warn/error counts initially
                 else counts.pending++;
             }
        };

        // --- 2. Perform Enabled Checks (Client-Side) ---

        // Helper to check if a specific check is enabled
        const isEnabled = (key) => !!config.enabledChecks[key];

        if (isEnabled('content_length')) {
            if (charCount >= config.minLength) recordStatus('content_length', 'success', `طول محتوا: ${charCount} کاراکتر.`);
            else if (charCount > 0) recordStatus('content_length', 'warning', `طول محتوا: ${charCount} (کمتر از ${config.minLength} پیشنهاد شده).`);
            else recordStatus('content_length', 'error', `محتوا خالی است (حداقل ${config.minLength} کاراکتر).`);
        }

        if (isEnabled('headings')) {
            const headingMatches = [...content.matchAll(/<h([2-6])[^>]*>(.*?)<\/h\1>/gis)];
            const headingCount = headingMatches.length;
            if (headingCount >= 1) recordStatus('headings', 'success', `تعداد ${headingCount} هدینگ (H2-H6) یافت شد.`);
            else recordStatus('headings', 'warning', 'هیچ هدینگ (H2-H6) استفاده نشده.');
        }

        // Featured Image - Status updated via AJAX, record pending initially if enabled
        if (isEnabled('featured_image') && !config.isTerm) {
            recordStatus('featured_image', 'pending', 'در حال بررسی تصویر شاخص...'); // Let AJAX update this
        }

        if (isEnabled('internal_links') || isEnabled('external_links')) {
            const linkMatches = content.match(/<a\s[^>]*href=["']([^"']+)["']/gi) || [];
            let internalLinkCount = 0;
            let externalLinkCount = 0;
            const siteHost = window.location.hostname.replace(/^www\./, '');
            linkMatches.forEach(linkTag => {
                const hrefMatch = /href=["']([^"']+)["']/i.exec(linkTag);
                if (hrefMatch?.[1]) {
                    const href = hrefMatch[1].trim();
                    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
                    try {
                        const url = new URL(href, window.location.origin); // Resolve relative URLs
                        const linkHost = url.hostname.replace(/^www\./, '');
                        if (linkHost === siteHost) internalLinkCount++;
                        else externalLinkCount++;
                    } catch (e) { /* Ignore invalid URLs */ }
                }
            });
            if (isEnabled('internal_links')) {
                if (internalLinkCount >= 1) recordStatus('internal_links', 'success', `تعداد ${internalLinkCount} لینک داخلی یافت شد.`);
                else recordStatus('internal_links', 'warning', 'هیچ لینک داخلی یافت نشد.');
            }
             if (isEnabled('external_links')) {
                if (externalLinkCount >= 1) recordStatus('external_links', 'success', `تعداد ${externalLinkCount} لینک خارجی یافت شد.`);
                else recordStatus('external_links', 'warning', 'هیچ لینک خارجی یافت نشد.');
            }
        }

        if (isEnabled('main_keyword_presence')) {
            if (mainKeyword) recordStatus('main_keyword_presence', 'success', 'کلمه کلیدی اصلی وارد شده.');
            else recordStatus('main_keyword_presence', 'error', 'کلمه کلیدی اصلی وارد نشده.');
        }

        if (isEnabled('keyword_title')) {
            if (!mainKeyword) recordStatus('keyword_title', 'pending', 'کلمه کلیدی وارد نشده.');
            else if (!effectiveTitle) recordStatus('keyword_title', 'pending', 'عنوان یافت نشد.');
            else if (effectiveTitle.toLowerCase().includes(mainKeywordLower)) recordStatus('keyword_title', 'success', 'کلمه کلیدی در عنوان سئو یافت شد.');
            else recordStatus('keyword_title', 'error', 'کلمه کلیدی در عنوان سئو یافت نشد.');
        }

        if (isEnabled('keyword_description')) {
             if (!mainKeyword) recordStatus('keyword_description', 'pending', 'کلمه کلیدی وارد نشده.');
             else if (!googleDescription) recordStatus('keyword_description', 'pending', 'توضیحات متا تنظیم نشده.');
             else if (googleDescription.toLowerCase().includes(mainKeywordLower)) recordStatus('keyword_description', 'success', 'کلمه کلیدی در توضیحات متا یافت شد.');
             else recordStatus('keyword_description', 'warning', 'کلمه کلیدی در توضیحات متا یافت نشد.');
        }

         if (isEnabled('keyword_headings')) {
             let keywordInHeadingFound = false;
             const headingMatches = [...content.matchAll(/<h([2-6])[^>]*>(.*?)<\/h\1>/gis)];
             if (!mainKeyword) recordStatus('keyword_headings', 'pending', 'کلمه کلیدی وارد نشده.');
             else if (headingMatches.length === 0) recordStatus('keyword_headings', 'pending', 'هدینگی یافت نشد.');
             else {
                 for (const match of headingMatches) {
                     if (stripHtml(match[2] || '').toLowerCase().includes(mainKeywordLower)) {
                         keywordInHeadingFound = true; break;
                     }
                 }
                 if (keywordInHeadingFound) recordStatus('keyword_headings', 'success', 'کلمه کلیدی در هدینگ‌ها یافت شد.');
                 else recordStatus('keyword_headings', 'warning', 'کلمه کلیدی در هدینگ‌ها یافت نشد.');
             }
        }

        if (isEnabled('keyword_density')) {
             if (!mainKeyword) recordStatus('keyword_density', 'pending', 'کلمه کلیدی وارد نشده.');
             else if (wordCountNoStopwords === 0) recordStatus('keyword_density', 'pending', 'محتوای قابل تحلیل یافت نشد.');
             else {
                const keywordRegex = new RegExp('\\b' + mainKeywordLower.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '\\b', 'giu');
                 const keywordCount = (plainContent.toLowerCase().match(keywordRegex) || []).length;
                 const density = (keywordCount / wordCountNoStopwords) * 100;
                 const densityFormatted = density.toFixed(1);
                 let msg = `تراکم کلمه کلیدی: ${densityFormatted}%.`;
                 if (density >= config.densityMin && density <= config.densityMax) recordStatus('keyword_density', 'success', `${msg} (مناسب)`);
                 else if (density > config.densityMax) recordStatus('keyword_density', 'warning', `${msg} (بالا)`);
                 else recordStatus('keyword_density', 'warning', `${msg} (پایین)`);
             }
        }

        if (isEnabled('keyword_slug')) {
             if (!mainKeyword) recordStatus('keyword_slug', 'pending', 'کلمه کلیدی وارد نشده.');
             else if (!currentSlug) recordStatus('keyword_slug', 'pending', 'URL (اسلاگ) یافت نشد.');
             else {
                 const keywordSlugVersion = sanitizeSlug(mainKeyword);
                 if (currentSlug.includes(keywordSlugVersion)) recordStatus('keyword_slug', 'success', 'کلمه کلیدی در URL (اسلاگ) یافت شد.');
                 else recordStatus('keyword_slug', 'warning', 'کلمه کلیدی در URL (اسلاگ) یافت نشد.');
             }
        }

         if (isEnabled('image_alt_texts')) {
             const imgTags = content.match(/<img[^>]+>/gi) || [];
             if (imgTags.length === 0) {
                 recordStatus('image_alt_texts', 'success', 'تصویری در محتوا یافت نشد.');
             } else {
                 let missingAltCount = 0;
                 let keywordInAltFound = false;
                 imgTags.forEach(imgTag => {
                     const altMatch = /alt=(["'])(.*?)\1/i.exec(imgTag);
                     if (!altMatch || altMatch[2].trim() === '') missingAltCount++;
                     else if (mainKeyword && altMatch[2].toLowerCase().includes(mainKeywordLower)) keywordInAltFound = true;
                 });

                 let altStatus = 'warning';
                 let altMessages = [];
                 if (missingAltCount === 0) { altStatus = 'success'; altMessages.push(`تمام ${imgTags.length} تصاویر Alt دارند.`); }
                 else { altStatus = 'error'; altMessages.push(`${missingAltCount} تصویر فاقد Alt است.`); }

                 if (mainKeyword) {
                     if (keywordInAltFound) { altMessages.push('کلمه کلیدی در Alt یافت شد.'); if(altStatus === 'error') altStatus = 'warning';} // Downgrade if KW found
                     else { altMessages.push('کلمه کلیدی در Alt یافت نشد.'); if(altStatus === 'success') altStatus = 'warning';} // Downgrade if KW missing
                 }
                 recordStatus('image_alt_texts', altStatus, altMessages.join(' '));
             }
         }

         if (isEnabled('text_structure')) {
             const sentences = plainContent.match(/[^.!?؟۔]+[.!?؟۔]+/gu) || [];
             const numSentences = sentences.length;
             let longSentences = 0; const sentenceThreshold = 20;
             if (numSentences > 0) {
                 sentences.forEach(s => { if (countWords(s) > sentenceThreshold) longSentences++; });
             }
             // Basic paragraph check (split by double newline approx)
             const paragraphs = plainContent.split(/\n\s*\n/).filter(p => p.trim());
             let longParagraphs = 0; const paragraphThreshold = 150;
             paragraphs.forEach(p => { if (countWords(p) > paragraphThreshold) longParagraphs++; });

             let structureStatus = 'success';
             let structureMessages = [];
             if (numSentences > 0) {
                 const longSentencePerc = Math.round((longSentences / numSentences) * 100);
                 if (longSentencePerc > 25) { structureStatus = 'warning'; structureMessages.push(`${longSentencePerc}% جملات طولانی.`);}
                 else structureMessages.push('طول جملات مناسب.');
             }
              if (paragraphs.length > 0) {
                 const longParaPerc = Math.round((longParagraphs / paragraphs.length) * 100);
                 if (longParaPerc > 30) { structureStatus = 'warning'; structureMessages.push(`${longParaPerc}% پاراگراف‌ها طولانی.`);}
                 else structureMessages.push('طول پاراگراف‌ها مناسب.');
             }
             recordStatus('text_structure', structureStatus, structureMessages.length > 0 ? structureMessages.join(' ') : 'ساختار متن بررسی شد.');
         }

         if (isEnabled('readability')) {
             const wordCountR = countWords(plainContent);
             const sentenceCountR = countSentences(plainContent);
             const syllableCountR = countSyllablesApprox(plainContent);
             if (wordCountR < 50 || sentenceCountR === 0) {
                 recordStatus('readability', 'pending', 'متن برای تحلیل خوانایی کافی نیست.');
             } else {
                 const score = Math.max(0, Math.min(100, Math.round(206.835 - (1.015 * (wordCountR / sentenceCountR)) - (84.6 * (syllableCountR / wordCountR)))));
                 let readStatus = 'warning'; let readLevel = 'نامشخص';
                 if (score >= 60) { readStatus = 'success'; readLevel = score >= 70 ? 'آسان' : 'متوسط'; }
                 else if (score < 30) { readStatus = 'error'; readLevel = 'بسیار دشوار'; }
                 else readLevel = 'دشوار';
                 recordStatus('readability', readStatus, `خوانایی Flesch (تقریبی): ${score} (${readLevel}).`);
             }
         }

        // --- 3. Update UI ---
        for (const key in checks) {
             if (checks.hasOwnProperty(key)) {
                 updateChecklistItemUI(key, checks[key].status, checks[key].message);
             }
        }

        // Update SERP Preview
        $selectors.serpTitle.text(truncateText(effectiveTitle, 65));
        $selectors.serpUrl.text(getSerpPreviewUrl());
        $selectors.serpDesc.text(truncateText(stripHtml(effectiveDescription), 160));

        // Update Overall Status
        let overallStatus = 'pending';
        let overallMessage = 'در حال محاسبه...';
        const totalRelevantChecks = counts.success + counts.warning + counts.error;
        const score = totalRelevantChecks > 0 ? Math.round((counts.success / totalRelevantChecks) * 100) : 0;

        if (counts.error > 0) { overallStatus = 'error'; overallMessage = `ضعیف (${score}%) - ${counts.error} خطا وجود دارد.`; }
        else if (counts.warning > 0) { overallStatus = 'warning'; overallMessage = `متوسط (${score}%) - ${counts.warning} پیشنهاد بهبود.`; }
        else if (counts.success > 0 && totalRelevantChecks > 0) { overallStatus = 'success'; overallMessage = `خوب (${score}%) - وضعیت مطلوب است.`; }
        else if (totalRelevantChecks === 0 && counts.pending > 0) { overallMessage = 'در انتظار اطلاعات بیشتر...'; }
        else { overallMessage = 'بررسی‌ها فعال نیستند یا اطلاعات کافی نیست.'; }

        const $overallDiv = $selectors.overallStatusDiv;
        const currentOverallClass = Array.from($overallDiv[0].classList).find(cls => cls.startsWith('csk-overall-status-'));
        $overallDiv.removeClass(currentOverallClass || '').addClass(`csk-overall-status-${overallStatus}`);
        $selectors.overallMessageSpan.text(overallMessage);

        toggleChecklistLoader(false);
        isUpdating = false;
    }; // End of runChecklistUpdate


    // --- Debounced Update Functions ---
    const debouncedMetaFieldUpdate = debounce(runChecklistUpdate, config.debounceDelay);
    const debouncedEditorUpdate = debounce(runChecklistUpdate, config.editorDebounceDelay);
    const debouncedFeaturedImageCheck = debounce(checkFeaturedImageStatus, 1500); // Debounce AJAX check


    // --- Event Listeners ---
    function attachEventListeners() {
        // Meta fields, Title, Slug
        const $inputsToWatch = $selectors.mainKeyword.add($selectors.relatedKeywords)
                                       .add($selectors.googleTitle)
                                       .add($selectors.googleDescription)
                                       .add($selectors.postTitle)
                                       .add($selectors.termName)
                                       .add($selectors.termSlug)
                                       .add($selectors.postSlugClassic);
        $inputsToWatch.on('input keyup change paste', debouncedMetaFieldUpdate);

        // Content Areas
        $selectors.classicEditor.add($selectors.termDescription).on('input keyup paste change', debouncedEditorUpdate);

        // TinyMCE
        if (typeof tinymce !== 'undefined') {
            let tinymceInitCheck = setInterval(function() {
                const editor = tinymce.get('content');
                if (editor) {
                    clearInterval(tinymceInitCheck);
                    editor.on('keyup input Paste SetContent change', debouncedEditorUpdate);
                     // Also trigger on tab switch
                     $('#content-tmce, #content-html').on('click', () => setTimeout(runChecklistUpdate, 300));
                }
            }, 500);
        }

        // Gutenberg
        if (config.isGutenberg && typeof wp !== 'undefined' && wp.data?.subscribe) {
            let isSubscribed = false;
            const subscribeToGutenberg = () => {
                if (isSubscribed) return;
                const editor = wp.data.select('core/editor');
                if (!editor?.getEditedPostAttribute) return; // Not ready
                isSubscribed = true;

                let prevContent = editor.getEditedPostAttribute('content');
                let prevTitle = editor.getEditedPostAttribute('title');
                let prevSlug = editor.getEditedPostAttribute('slug');
                let prevFeaturedMedia = editor.getEditedPostAttribute('featured_media');

                const unsubscribe = wp.data.subscribe(() => {
                    const currentEditor = wp.data.select('core/editor');
                    if (!currentEditor) return;
                    const newContent = currentEditor.getEditedPostAttribute('content');
                    const newTitle = currentEditor.getEditedPostAttribute('title');
                    const newSlug = currentEditor.getEditedPostAttribute('slug');
                    const newFeaturedMedia = currentEditor.getEditedPostAttribute('featured_media');
                    const isSaving = currentEditor.isSavingPost() || currentEditor.isAutosavingPost();

                    if (!isSaving) {
                        let changed = false;
                        if (newContent !== prevContent) { prevContent = newContent; changed = true; }
                        if (newTitle !== prevTitle) { prevTitle = newTitle; changed = true; }
                        if (newSlug !== prevSlug) { prevSlug = newSlug; changed = true; }

                        if (changed) debouncedEditorUpdate(); // Run full update if content/title/slug changed

                        // Check featured image specifically
                        if (newFeaturedMedia !== prevFeaturedMedia) {
                            prevFeaturedMedia = newFeaturedMedia;
                             // console.log('CSK: Featured media changed:', newFeaturedMedia);
                            if (isEnabled('featured_image')) {
                                debouncedFeaturedImageCheck(); // Check FI status via AJAX after a delay
                            }
                        }
                    }
                });
            };
            // Attempt subscription after delays
            setTimeout(subscribeToGutenberg, 1500);
            setTimeout(() => { if (!isSubscribed) subscribeToGutenberg(); }, 4000);
        }

         // Listen for Classic Editor Featured Image changes (more tricky)
         // We rely on the AJAX check triggered after save or Gutenberg change for now.
         // A possible improvement is to observe the featured image meta box DOM changes,
         // but this can be fragile.
          if (!config.isGutenberg && typeof wp !== 'undefined' && wp.media) {
               // Try to hook into the media frame when an image is selected/removed
               // This requires more advanced JS integration with the media library.
               // Example (conceptual):
               // wp.media.featuredImage.frame().on('select update', function() { ... });
               // $('#remove-post-thumbnail').on('click', function() { ... });
               // For now, we'll stick to AJAX checks after delay/save.
         }

    } // End of attachEventListeners

    // --- Run Initialization ---
    initializeChecklist();

}); // End of jQuery(document).ready
