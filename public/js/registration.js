/**
 * Registration JavaScript
 * Frontend enhancements for the registration form
 *
 * File: public/js/registration.js
 * Directory: /public/js/
 */

'use strict';

/**
 * Initialize registration form enhancements
 */
function initializeRegistrationForm() {
    const form = document.getElementById('registrationForm');
    if (!form) return;

    // Initialize components
    initializePasswordStrength();
    initializeFormValidation();
    initializePasswordConfirmation();
    initializeSubmitHandler();
    initializeFieldEnhancements();
}

/**
 * Password strength indicator
 */
function initializePasswordStrength() {
    const passwordField = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');

    if (!passwordField || !strengthIndicator) return;

    passwordField.addEventListener('input', function () {
        const password = this.value;
        const strength = calculatePasswordStrength(password);

        // Update visual indicator
        strengthIndicator.className = 'password-strength ' + strength.level;

        // Update help text
        updatePasswordHelpText(strength);
    });
}

/**
 * Calculate password strength
 */
function calculatePasswordStrength(password) {
    let score = 0;
    const feedback = [];

    // Length check
    if (password.length >= 8) {
        score += 20;
    } else {
        feedback.push('At least 8 characters');
    }

    if (password.length >= 12) {
        score += 10;
    }

    // Character variety checks
    if (/[a-z]/.test(password)) {
        score += 15;
    } else {
        feedback.push('Lowercase letter');
    }

    if (/[A-Z]/.test(password)) {
        score += 15;
    } else {
        feedback.push('Uppercase letter');
    }

    if (/[0-9]/.test(password)) {
        score += 15;
    } else {
        feedback.push('Number');
    }

    if (/[^a-zA-Z0-9]/.test(password)) {
        score += 15;
    } else {
        feedback.push('Special character');
    }

    // Bonus for good length
    if (password.length >= 16) {
        score += 10;
    }

    const level = score >= 80 ? 'strong' : score >= 60 ? 'medium' : score >= 40 ? 'weak' : '';

    return {
        score: score,
        level: level,
        feedback: feedback
    };
}

/**
 * Update password help text
 */
function updatePasswordHelpText(strength) {
    const helpElement = document.getElementById('password_help');
    if (!helpElement) return;

    if (strength.feedback.length > 0) {
        helpElement.textContent = 'Missing: ' + strength.feedback.join(', ');
        helpElement.style.color = '#F44336';
    } else {
        helpElement.textContent = 'Strong password!';
        helpElement.style.color = '#4CAF50';
    }
}

/**
 * Real-time form validation
 */
function initializeFormValidation() {
    const fields = [
        'trainer_name',
        'email',
        'password',
        'password_confirmation',
        'team_name'
    ];

    fields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field) return;

        field.addEventListener('blur', function () {
            validateField(this);
        });

        field.addEventListener('input', function () {
            clearFieldError(this);
        });
    });
}

/**
 * Validate individual field
 */
function validateField(field) {
    const fieldName = field.name;
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';

    switch (fieldName) {
        case 'trainer_name':
            if (!value) {
                errorMessage = 'Trainer name is required';
                isValid = false;
            } else if (value.length < 3) {
                errorMessage = 'Trainer name must be at least 3 characters';
                isValid = false;
            } else if (!/^[a-zA-Z0-9_\-\s]+$/.test(value)) {
                errorMessage = 'Only letters, numbers, spaces, hyphens and underscores allowed';
                isValid = false;
            }
            break;

        case 'email':
            if (!value) {
                errorMessage = 'Email is required';
                isValid = false;
            } else if (!isValidEmail(value)) {
                errorMessage = 'Please enter a valid email address';
                isValid = false;
            }
            break;

        case 'password':
            if (!value) {
                errorMessage = 'Password is required';
                isValid = false;
            } else {
                const strength = calculatePasswordStrength(value);
                if (strength.score < 60) {
                    errorMessage = 'Password is too weak';
                    isValid = false;
                }
            }
            break;

        case 'password_confirmation':
            const passwordField = document.getElementById('password');
            if (!value) {
                errorMessage = 'Password confirmation is required';
                isValid = false;
            } else if (value !== passwordField.value) {
                errorMessage = 'Passwords do not match';
                isValid = false;
            }
            break;

        case 'team_name':
            if (!value) {
                errorMessage = 'Team name is required';
                isValid = false;
            } else if (value.length < 3) {
                errorMessage = 'Team name must be at least 3 characters';
                isValid = false;
            } else if (!/^[a-zA-Z0-9_\-\s]+$/.test(value)) {
                errorMessage = 'Only letters, numbers, spaces, hyphens and underscores allowed';
                isValid = false;
            }
            break;
    }

    if (!isValid) {
        showFieldError(field, errorMessage);
    } else {
        clearFieldError(field);
    }

    return isValid;
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    field.classList.add('error');

    let errorElement = field.parentNode.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        field.parentNode.appendChild(errorElement);
    }

    errorElement.innerHTML = '<div>' + escapeHtml(message) + '</div>';
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    field.classList.remove('error');

    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

/**
 * Password confirmation validation
 */
function initializePasswordConfirmation() {
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('password_confirmation');

    if (!passwordField || !confirmField) return;

    function checkPasswordMatch() {
        if (confirmField.value && passwordField.value !== confirmField.value) {
            showFieldError(confirmField, 'Passwords do not match');
        } else {
            clearFieldError(confirmField);
        }
    }

    passwordField.addEventListener('input', checkPasswordMatch);
    confirmField.addEventListener('input', checkPasswordMatch);
}

/**
 * Form submit handler
 */
function initializeSubmitHandler() {
    const form = document.getElementById('registrationForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnSpinner = submitBtn.querySelector('.btn-spinner');

    if (!form || !submitBtn) return;

    form.addEventListener('submit', function (e) {
        // Validate all fields
        const fields = form.querySelectorAll('input[required]');
        let isFormValid = true;

        fields.forEach(field => {
            if (!validateField(field)) {
                isFormValid = false;
            }
        });

        // Check terms acceptance
        const termsCheckbox = document.getElementById('terms_accepted');
        if (termsCheckbox && !termsCheckbox.checked) {
            showFieldError(termsCheckbox, 'You must accept the terms and conditions');
            isFormValid = false;
        }

        if (!isFormValid) {
            e.preventDefault();
            scrollToFirstError();
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnSpinner.style.display = 'inline';

        // Note: Form will submit normally, loading state will be reset on page reload
    });
}

/**
 * Field enhancements
 */
function initializeFieldEnhancements() {
    // Auto-format team name and trainer name
    const nameFields = ['trainer_name', 'team_name'];
    nameFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field) return;

        field.addEventListener('input', function () {
            // Remove invalid characters as user types
            this.value = this.value.replace(/[^a-zA-Z0-9_\-\s]/g, '');
        });
    });

    // Email field enhancement
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function () {
            this.value = this.value.trim().toLowerCase();
        });
    }
}

/**
 * Scroll to first error
 */
function scrollToFirstError() {
    const firstError = document.querySelector('.field-error, input.error');
    if (firstError) {
        firstError.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        // Focus the field if it's an input
        if (firstError.tagName === 'INPUT') {
            firstError.focus();
        }
    }
}

/**
 * Email validation
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Debounce function for performance
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add CSS for error states if not already defined
if (!document.querySelector('#registration-js-styles')) {
    const style = document.createElement('style');
    style.id = 'registration-js-styles';
    style.textContent = `
        input.error {
            border-color: #F44336 !important;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1) !important;
        }
        
        .field-error {
            background: #FFEBEE;
            border: 1px solid #F44336;
            border-radius: 4px;
            padding: 8px 12px;
            margin-top: 5px;
            color: #C62828;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .password-strength {
            height: 4px;
            background: #E0E0E0;
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        
        .password-strength.weak {
            background: linear-gradient(90deg, #F44336 33%, #E0E0E0 33%);
        }
        
        .password-strength.medium {
            background: linear-gradient(90deg, #FF9800 66%, #E0E0E0 66%);
        }
        
        .password-strength.strong {
            background: #4CAF50;
        }
    `;
    document.head.appendChild(style);
}