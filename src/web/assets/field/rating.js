/**
 * Formie Rating Field JavaScript
 * 
 * @author LindemannRock
 * @since 1.0.0
 */

console.log('[FormieRating] Script loaded!');
console.log('[FormieRating] Window.Formie exists?', typeof window.Formie !== 'undefined');

// Define the FormieRating class in the global scope
window.FormieRating = class FormieRating {
        constructor(settings = {}) {
            console.log('[FormieRating] Constructor called with settings:', settings);
            
            this.$form = settings.$form;
            this.form = this.$form ? this.$form.form : null;
            this.$field = settings.$field;
            this.settings = settings.settings || {};
            
            // Initialize the rating field
            this.initializeField();
            
            // Listen for dynamic field additions (repeater fields)
            if (this.form && this.$form) {
                this.form.addEventListener(this.$form, 'onAfterFormieSubmit', this.onAfterSubmit.bind(this));
            }
        }

        initializeField() {
            console.log('[FormieRating] initializeField called');
            console.log('[FormieRating] $field:', this.$field);
            
            const selectElement = this.$field.querySelector('select[data-rating]');
            console.log('[FormieRating] Found select element:', selectElement);
            
            if (!selectElement) {
                console.warn('[FormieRating] No select element found with data-rating attribute in field:', this.$field);
                return;
            }
            
            if (selectElement.dataset.ratingInitialized) {
                console.log('[FormieRating] Skipping - already initialized');
                return;
            }
            
            try {
                this.createRatingInterface(selectElement);
                selectElement.dataset.ratingInitialized = 'true';
            } catch (error) {
                console.error('[FormieRating] Error creating rating interface:', error);
                console.error('[FormieRating] this:', this);
                console.error('[FormieRating] this.createStarItem:', this.createStarItem);
            }
        }

        createRatingInterface(selectElement) {
            // Get configuration from data attributes and settings
            const ratingType = selectElement.dataset.rating || this.settings.ratingType || 'star';
            const ratingSize = selectElement.dataset.ratingSize || this.settings.ratingSize || 'medium';
            const showSelectedLabel = selectElement.dataset.ratingShowSelected === 'true' || this.settings.showSelectedLabel;
            const showEndpoints = selectElement.dataset.ratingShowEndpoints === 'true' || this.settings.showEndpointLabels;
            const startLabel = selectElement.dataset.ratingStartLabel || '';
            const endLabel = selectElement.dataset.ratingEndLabel || '';

            // Create the visual rating container
            const container = document.createElement('div');
            container.className = `fui-rating-field fui-rating-${ratingType} fui-rating-size-${ratingSize}`;
            
            // Add SVG gradient definition for half stars if this is a star rating
            if (ratingType === 'star') {
                const svgDefs = document.createElement('div');
                svgDefs.style.position = 'absolute';
                svgDefs.style.width = '0';
                svgDefs.style.height = '0';
                svgDefs.style.overflow = 'hidden';
                svgDefs.innerHTML = `
                    <svg width="0" height="0">
                        <defs>
                            <linearGradient id="half-star-gradient-${selectElement.id}">
                                <stop offset="50%" stop-color="#f59e0b"/>
                                <stop offset="50%" stop-color="#e5e7eb"/>
                            </linearGradient>
                        </defs>
                    </svg>
                `;
                container.appendChild(svgDefs);
            }
            
            // Create visual elements container
            const visualContainer = document.createElement('div');
            visualContainer.className = 'fui-rating-visual';
            
            // Get options from select
            const options = Array.from(selectElement.options).filter(opt => opt.value);
            
            // Handle star ratings separately
            if (ratingType === 'star') {
                // Check if we have half values
                const hasHalfValues = options.some(opt => opt.value.includes('.5'));
                
                if (hasHalfValues) {
                    // Get the max integer value
                    const maxValue = Math.max(...options.map(opt => Math.ceil(parseFloat(opt.value))));
                    const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                    
                    // Create one star for each integer value
                    const starIndices = [];
                    for (let i = 1; i <= maxValue; i++) {
                        starIndices.push(i);
                    }
                    
                    // Reverse order for RTL
                    if (isRTL) {
                        starIndices.reverse();
                    }
                    
                    starIndices.forEach((i) => {
                        const starItem = this.createStarItem(i);
                        
                        // Add click handler that detects half clicks
                        starItem.addEventListener('click', (e) => {
                            const rect = starItem.getBoundingClientRect();
                            const x = e.clientX - rect.left;
                            const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                            // In RTL, the half detection is reversed
                            const isLeftHalf = isRTL ? x > rect.width / 2 : x < rect.width / 2;
                            
                            // Determine the value based on click position
                            let value = i;
                            if (isLeftHalf && options.some(opt => opt.value === (i - 0.5).toString())) {
                                value = i - 0.5;
                            }
                            
                            this.selectRating(selectElement, value, container);
                        });
                        
                        // Add hover handler for visual feedback
                        starItem.addEventListener('mousemove', (e) => {
                            const rect = starItem.getBoundingClientRect();
                            const x = e.clientX - rect.left;
                            const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                            // In RTL, the half detection is reversed
                            const isLeftHalf = isRTL ? x > rect.width / 2 : x < rect.width / 2;
                            const hoverValue = isLeftHalf && options.some(opt => opt.value === (i - 0.5).toString()) ? i - 0.5 : i;
                            
                            this.updateVisualState(container, hoverValue);
                        });
                        
                        visualContainer.appendChild(starItem);
                    });
                } else {
                    // No half values - create one star per option
                    const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                    const orderedOptions = isRTL ? [...options].reverse() : options;
                    
                    orderedOptions.forEach((option) => {
                        const value = parseFloat(option.value);
                        const starItem = this.createStarItem(value);
                        
                        starItem.addEventListener('click', () => {
                            this.selectRating(selectElement, option.value, container);
                        });
                        
                        visualContainer.appendChild(starItem);
                    });
                }
            } else {
                // For emoji and NPS rating types, create one item per option
                const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                
                // For RTL, reverse the order of elements for NPS and emoji
                const orderedOptions = (isRTL && (ratingType === 'nps' || ratingType === 'emoji')) ? [...options].reverse() : options;
                
                orderedOptions.forEach((option, index) => {
                    // Use the original index for emoji selection
                    const originalIndex = options.indexOf(option);
                    const ratingItem = this.createRatingItem(ratingType, option.value, option.text, originalIndex);
                    ratingItem.addEventListener('click', () => {
                        this.selectRating(selectElement, option.value, container);
                    });
                    visualContainer.appendChild(ratingItem);
                });
            }
            
            container.appendChild(visualContainer);
            
            // Remove hover on mouse leave
            container.addEventListener('mouseleave', () => {
                const currentValue = selectElement.value ? parseFloat(selectElement.value) : null;
                this.updateVisualState(container, currentValue);
            });
            
            // Add endpoint labels if enabled
            if (showEndpoints && (startLabel || endLabel)) {
                const endpointContainer = document.createElement('div');
                endpointContainer.className = 'fui-rating-endpoints';
                
                const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                
                if (startLabel) {
                    const startSpan = document.createElement('span');
                    startSpan.className = 'fui-rating-start-label';
                    // Don't swap - keep original label
                    startSpan.textContent = startLabel;
                    endpointContainer.appendChild(startSpan);
                }
                
                if (endLabel) {
                    const endSpan = document.createElement('span');
                    endSpan.className = 'fui-rating-end-label';
                    // Don't swap - keep original label
                    endSpan.textContent = endLabel;
                    endpointContainer.appendChild(endSpan);
                }
                
                container.appendChild(endpointContainer);
            }
            
            // Add selected label if enabled
            if (showSelectedLabel) {
                const selectedLabel = document.createElement('div');
                selectedLabel.className = 'fui-rating-selected-label';
                container.appendChild(selectedLabel);
            }
            
            // Insert the visual rating before the select (select is already hidden by CSS)
            selectElement.parentNode.insertBefore(container, selectElement);
            
            // Set initial value
            if (selectElement.value) {
                this.selectRating(selectElement, selectElement.value, container);
            }
            
            // Add keyboard navigation
            container.setAttribute('tabindex', '0');
            container.setAttribute('role', 'radiogroup');
            container.setAttribute('aria-label', selectElement.getAttribute('aria-label') || 'Rating');
            
            container.addEventListener('keydown', (e) => {
                this.handleKeyboardNavigation(e, selectElement, options, container);
            });
        }

        createStarItem(value) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'fui-rating-item fui-rating-star-item';
            item.setAttribute('data-value', value);
            item.setAttribute('role', 'radio');
            item.setAttribute('aria-label', `${value} stars`);
            
            // Use two overlapping SVGs for half-star effect (like the working TypeScript version)
            item.innerHTML = `
                <div class="star-container">
                    <svg class="star-background" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"
                              stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div class="star-fill">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"
                                  stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            `;
            
            return item;
        }

        createRatingItem(type, value, label, index) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'fui-rating-item';
            item.setAttribute('data-value', value);
            item.setAttribute('role', 'radio');
            item.setAttribute('aria-label', label);
            
            switch (type) {
                case 'star':
                    item.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
                    break;
                    
                case 'emoji':
                    const emojis = ['😢', '😕', '😐', '😊', '😍'];
                    item.textContent = emojis[Math.min(index, emojis.length - 1)];
                    break;
                    
                case 'nps':
                    item.textContent = value;
                    item.classList.add('fui-rating-nps-item');
                    break;
            }
            
            return item;
        }

        selectRating(selectElement, value, container) {
            // Update select value
            selectElement.value = value;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            selectElement.dispatchEvent(event);
            
            // Update visual state
            this.updateVisualState(container, value);
            
            // Update selected label
            const selectedLabel = container.querySelector('.fui-rating-selected-label');
            if (selectedLabel) {
                const selectedOption = selectElement.querySelector(`option[value="${value}"]`);
                selectedLabel.textContent = selectedOption ? selectedOption.text : '';
            }
        }
        
        updateVisualState(container, value) {
            const ratingType = container.classList.contains('fui-rating-emoji') ? 'emoji' : 
                              container.classList.contains('fui-rating-nps') ? 'nps' : 'star';
            
            // If no value is selected, don't highlight anything
            if (value === null || value === undefined || value === '') {
                if (ratingType === 'star') {
                    container.querySelectorAll('.star-fill').forEach(fill => {
                        fill.style.opacity = '0';
                    });
                } else {
                    container.querySelectorAll('.fui-rating-item').forEach(item => {
                        item.classList.remove('fui-rating-selected');
                        item.setAttribute('aria-checked', 'false');
                    });
                }
                return;
            }
            
            if (ratingType === 'star') {
                // Use the same approach as the working TypeScript version
                container.querySelectorAll('.fui-rating-item').forEach((star) => {
                    const starValue = parseFloat(star.getAttribute('data-value'));
                    const fillElement = star.querySelector('.star-fill');
                    
                    if (!fillElement) return;
                    
                    if (starValue <= Math.floor(value)) {
                        // Full star
                        fillElement.style.clipPath = 'none';
                        fillElement.style.opacity = '1';
                    } else if (starValue - 0.5 === value) {
                        // Half star - clip from right in RTL
                        const isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                        fillElement.style.clipPath = isRTL ? 'inset(0 0 0 50%)' : 'inset(0 50% 0 0)';
                        fillElement.style.opacity = '1';
                    } else {
                        // Empty star
                        fillElement.style.opacity = '0';
                    }
                });
            } else {
                // Original logic for emoji and NPS
                const items = container.querySelectorAll('.fui-rating-item');
                items.forEach(item => {
                    const itemValue = parseFloat(item.getAttribute('data-value'));
                    
                    item.classList.remove('fui-rating-selected');
                    
                    if (itemValue <= value) {
                        item.classList.add('fui-rating-selected');
                    }
                    
                    item.setAttribute('aria-checked', itemValue === value ? 'true' : 'false');
                });
            }
        }

        handleKeyboardNavigation(e, selectElement, options, container) {
            const currentValue = parseFloat(selectElement.value);
            let newIndex = -1;
            
            const currentIndex = options.findIndex(opt => parseFloat(opt.value) === currentValue);
            
            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowDown':
                    e.preventDefault();
                    newIndex = Math.max(0, currentIndex - 1);
                    break;
                    
                case 'ArrowRight':
                case 'ArrowUp':
                    e.preventDefault();
                    newIndex = Math.min(options.length - 1, currentIndex + 1);
                    break;
                    
                case 'Home':
                    e.preventDefault();
                    newIndex = 0;
                    break;
                    
                case 'End':
                    e.preventDefault();
                    newIndex = options.length - 1;
                    break;
            }
            
            if (newIndex >= 0 && newIndex < options.length) {
                this.selectRating(selectElement, options[newIndex].value, container);
            }
        }

        onAfterSubmit() {
            // Clean up if needed after form submission
        }
    };

console.log('[FormieRating] Class defined on window:', typeof window.FormieRating);
console.log('[FormieRating] Waiting for Formie to instantiate...');

// Check if we can see what Formie has registered
if (window.Formie) {
    console.log('[FormieRating] Formie object:', window.Formie);
    
    // Initialize rating fields
    const initializeRatingFields = () => {
        console.log('[FormieRating] Initializing rating fields...');
        
        // Get all Formie forms
        const forms = document.querySelectorAll('[data-fui-form]');
        console.log('[FormieRating] Found forms:', forms.length);
        
        forms.forEach(form => {
            // Find rating select elements within this form
            const ratingSelects = form.querySelectorAll('select[data-rating]');
            console.log('[FormieRating] Found rating select elements in form:', ratingSelects.length);
            
            ratingSelects.forEach(select => {
                // Skip if already initialized
                if (select.dataset.ratingInitialized) {
                    return;
                }
                
                // Get the Formie form instance
                const formieForm = window.Formie ? window.Formie.forms.find(f => f.$form === form) : null;
                
                const settings = {
                    $form: form,
                    form: formieForm,
                    $field: select.parentElement, // Use the select's parent as the field container
                    settings: {}
                };
                
                console.log('[FormieRating] Manually initializing select:', select);
                new window.FormieRating(settings);
            });
        });
    };
    
    // Check if DOM is already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeRatingFields);
    } else {
        // DOM is already loaded, initialize immediately
        setTimeout(initializeRatingFields, 100);
    }
}