/**
 * Kindlinks Auto Glossary - Client-Side Keyword Highlighting
 * 
 * Efficiently scans and highlights keywords in long-form content (50k+ words)
 * using TreeWalker for optimal performance.
 */

(function() {
    'use strict';

    // Exit if no glossary data is available
    if (typeof KindlinksData === 'undefined' || !KindlinksData.terms || KindlinksData.terms.length === 0) {
        return;
    }

    const config = {
        terms: KindlinksData.terms,
        maxLimit: KindlinksData.max_limit || 2,
        targetSelectors: KindlinksData.content_selectors ? KindlinksData.content_selectors.split(',').map(s => s.trim()) : ['.entry-content', '.breakdance-post-content'],
        readMoreText: KindlinksData.read_more_text || 'Xem thêm',
        excludeTags: ['A', 'SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE', 'BUTTON', 'INPUT'],
        trackClicks: KindlinksData.track_clicks !== undefined ? KindlinksData.track_clicks : true,
        ajaxUrl: KindlinksData.ajax_url || ''
    };

    // Track how many times each keyword has been highlighted
    const keywordCount = {};

    /**
     * Initialize the glossary functionality
     */
    function init() {
        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', processContent);
        } else {
            processContent();
        }
    }

    /**
     * Process the content and highlight keywords
     */
    function processContent() {
        // Find target content area
        const contentArea = findContentArea();
        if (!contentArea) {
            console.warn('Kindlinks Glossary: Target content area not found');
            return;
        }

        // Initialize keyword counter
        config.terms.forEach(term => {
            keywordCount[term.keyword.toLowerCase()] = 0;
        });

        // Process text nodes and highlight keywords
        highlightKeywords(contentArea);

        // Initialize Tippy.js tooltips
        initializeTooltips();
    }

    /**
     * Find the content area to process
     * @returns {Element|null} The content element or null
     */
    function findContentArea() {
        for (const selector of config.targetSelectors) {
            const element = document.querySelector(selector);
            if (element) {
                return element;
            }
        }
        return null;
    }

    /**
     * Highlight keywords in the content area
     * @param {Element} rootElement - The root element to scan
     */
    function highlightKeywords(rootElement) {
        // Create a TreeWalker to efficiently traverse text nodes
        const walker = document.createTreeWalker(
            rootElement,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip if parent is an excluded tag
                    if (config.excludeTags.includes(node.parentElement.tagName)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip if already processed
                    if (node.parentElement.classList.contains('kindlinks-term')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip if text is empty or whitespace only
                    if (!node.textContent.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        const nodesToProcess = [];
        let currentNode;

        // Collect all text nodes first (to avoid issues with DOM manipulation during traversal)
        while (currentNode = walker.nextNode()) {
            nodesToProcess.push(currentNode);
        }

        // Process each text node
        nodesToProcess.forEach(node => {
            processTextNode(node);
        });
    }

    /**
     * Process a single text node and highlight keywords
     * @param {Text} textNode - The text node to process
     */
    function processTextNode(textNode) {
        const originalText = textNode.textContent;
        
        // Collect all matches for all terms first
        const allMatches = [];

        // Process each term (already sorted by length, longest first)
        for (const term of config.terms) {
            const keywordLower = term.keyword.toLowerCase();

            // Check if we've reached the limit for this keyword
            if (keywordCount[keywordLower] >= config.maxLimit) {
                continue;
            }

            // Create regex for whole word matching (case insensitive)
            const regex = new RegExp(`\\b(${escapeRegex(term.keyword)})\\b`, 'gi');
            
            let match;
            let matchesForThisTerm = 0;

            // Find all matches for this term
            while ((match = regex.exec(originalText)) !== null) {
                const remainingSlots = config.maxLimit - keywordCount[keywordLower] - matchesForThisTerm;
                
                if (remainingSlots > 0) {
                    // Check if this position is already matched by a longer term
                    const isOverlapping = allMatches.some(existing => 
                        (match.index >= existing.start && match.index < existing.end) ||
                        (match.index + match[0].length > existing.start && match.index < existing.start)
                    );

                    if (!isOverlapping) {
                        allMatches.push({
                            start: match.index,
                            end: match.index + match[0].length,
                            text: match[0],
                            term: term,
                            keyword: keywordLower
                        });
                        matchesForThisTerm++;
                    }
                }
            }

            // Update the keyword count
            if (matchesForThisTerm > 0) {
                keywordCount[keywordLower] = (keywordCount[keywordLower] || 0) + matchesForThisTerm;
            }
        }

        // If no matches found, return early
        if (allMatches.length === 0) {
            return;
        }

        // Sort matches by position (start index)
        allMatches.sort((a, b) => a.start - b.start);

        // Build the new HTML by replacing matches
        let newHTML = '';
        let lastIndex = 0;

        allMatches.forEach(match => {
            // Add text before the match
            newHTML += escapeHtml(originalText.substring(lastIndex, match.start));
            // Add the highlighted term
            newHTML += createTooltipSpan(match.text, match.term);
            lastIndex = match.end;
        });

        // Add remaining text after last match
        newHTML += escapeHtml(originalText.substring(lastIndex));

        // Replace the text node with the new HTML
        const span = document.createElement('span');
        span.innerHTML = newHTML;
        textNode.parentNode.replaceChild(span, textNode);
        
        // Move children out of wrapper span
        while (span.firstChild) {
            span.parentNode.insertBefore(span.firstChild, span);
        }
        span.parentNode.removeChild(span);
    }

    /**
     * Create a tooltip span element
     * @param {string} matchedText - The matched text to wrap
     * @param {Object} term - The glossary term object
     * @returns {string} HTML string for the tooltip span
     */
    function createTooltipSpan(matchedText, term) {
        const tooltipContent = createTooltipContent(term);
        // Store the original keyword (from database) for accurate click tracking
        return `<span class="kindlinks-term" data-tippy-content='${tooltipContent}' data-keyword='${escapeHtml(term.keyword)}'>${escapeHtml(matchedText)}</span>`;
    }

    /**
     * Create the tooltip content HTML
     * @param {Object} term - The glossary term object
     * @returns {string} HTML string for tooltip content
     */
    function createTooltipContent(term) {
        let content = `<div class="kindlinks-tooltip-content">`;
        content += `<strong class="kindlinks-tooltip-keyword">${escapeHtml(term.keyword)}</strong>`;
        content += `<p class="kindlinks-tooltip-definition">${term.definition}</p>`;
        
        if (term.url && term.url.trim() !== '') {
            content += `<a href="${escapeHtml(term.url)}" class="kindlinks-tooltip-link" target="_blank" rel="noopener noreferrer">${escapeHtml(config.readMoreText)} →</a>`;
        }
        
        content += `</div>`;
        
        // Escape single quotes for use in HTML attribute
        return content.replace(/'/g, '&#39;');
    }

    /**
     * Initialize Tippy.js tooltips
     */
    function initializeTooltips() {
        // Check if Tippy.js is loaded
        if (typeof tippy === 'undefined') {
            console.error('Kindlinks Glossary: Tippy.js is not loaded. Tooltips will not work.');
            
            // Add basic title fallback for accessibility
            document.querySelectorAll('.kindlinks-term').forEach(el => {
                const content = el.getAttribute('data-tippy-content');
                if (content) {
                    // Strip HTML for title attribute
                    const temp = document.createElement('div');
                    temp.innerHTML = content;
                    el.setAttribute('title', temp.textContent || temp.innerText || '');
                }
            });
            return;
        }

        try {
            tippy('.kindlinks-term', {
                theme: 'light',
                allowHTML: true,
                interactive: true,
                maxWidth: 300,
                placement: 'top',
                animation: 'fade',
                duration: [200, 150],
                arrow: true,
                touch: true, // Enable tap on mobile (no hold required)
                // Accessibility improvements
                aria: {
                    content: 'describedby',
                    expanded: 'auto',
                },
                appendTo: () => document.body,
                onShow(instance) {
                    // Track click analytics
                    if (config.trackClicks && config.ajaxUrl) {
                        // Get the original keyword from the term data (case-preserved)
                        const clickedText = instance.reference.textContent;
                        const originalKeyword = instance.reference.getAttribute('data-keyword') || clickedText;
                        trackTermClick(originalKeyword);
                    }
                },
                // Keyboard navigation
                onTrigger(instance, event) {
                    // Show on Enter/Space key
                    if (event.type === 'keydown' && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        instance.show();
                    }
                }
            });

            // Add keyboard accessibility attributes
            document.querySelectorAll('.kindlinks-term').forEach(el => {
                el.setAttribute('role', 'button');
                el.setAttribute('tabindex', '0');
                el.setAttribute('aria-label', 'Show definition: ' + el.textContent);
                
                // Add keyboard event listeners
                el.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        const instance = el._tippy;
                        if (instance) {
                            instance.hide();
                        }
                    }
                });
            });
        } catch (error) {
            console.error('Kindlinks Glossary: Failed to initialize Tippy.js', error);
        }
    }

    /**
     * Track term click for analytics
     * @param {string} keyword - The clicked keyword
     */
    function trackTermClick(keyword) {
        if (!config.ajaxUrl) return;

        // Use sendBeacon for better performance (fire and forget)
        const data = new FormData();
        data.append('action', 'kindlinks_track_click');
        data.append('keyword', keyword);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.ajaxUrl, data);
        } else {
            // Fallback to fetch
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).catch(() => {}); // Silent fail
        }
    }

    /**
     * Escape special regex characters
     * @param {string} string - String to escape
     * @returns {string} Escaped string
     */
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Escape HTML special characters
     * @param {string} string - String to escape
     * @returns {string} Escaped string
     */
    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string;
        return div.innerHTML;
    }

    // Initialize the glossary
    init();

})();

