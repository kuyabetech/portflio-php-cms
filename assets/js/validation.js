// assets/js/validation.js
// Form Validation

class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = [];
    }
    
    // Validate required fields
    required(value, fieldName) {
        if (!value || value.trim() === '') {
            this.errors.push(`${fieldName} is required`);
            return false;
        }
        return true;
    }
    
    // Validate email
    email(value) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(value)) {
            this.errors.push('Please enter a valid email address');
            return false;
        }
        return true;
    }
    
    // Validate phone
    phone(value) {
        const re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
        if (value && !re.test(value)) {
            this.errors.push('Please enter a valid phone number');
            return false;
        }
        return true;
    }
    
    // Validate min length
    minLength(value, length, fieldName) {
        if (value && value.length < length) {
            this.errors.push(`${fieldName} must be at least ${length} characters`);
                        return false;
        }
        return true;
    }
    
    // Validate max length
    maxLength(value, length, fieldName) {
        if (value && value.length > length) {
            this.errors.push(`${fieldName} must be less than ${length} characters`);
            return false;
        }
        return true;
    }
    
    // Validate URL
    url(value) {
        if (value) {
            const re = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/;
            if (!re.test(value)) {
                this.errors.push('Please enter a valid URL');
                return false;
            }
        }
        return true;
    }
    
    // Get errors
    getErrors() {
        return this.errors;
    }
    
    // Clear errors
    clearErrors() {
        this.errors = [];
    }
    
    // Validate form
    validate(rules) {
        this.clearErrors();
        
        for (let field in rules) {
            const input = this.form.querySelector(`[name="${field}"]`);
            if (!input) continue;
            
            const value = input.value.trim();
            const fieldRules = rules[field];
            
            for (let rule of fieldRules) {
                if (rule === 'required') {
                    this.required(value, field);
                } else if (rule === 'email') {
                    this.email(value);
                } else if (rule === 'phone') {
                    this.phone(value);
                } else if (rule === 'url') {
                    this.url(value);
                } else if (rule.startsWith('min:')) {
                    const length = parseInt(rule.split(':')[1]);
                    this.minLength(value, length, field);
                } else if (rule.startsWith('max:')) {
                    const length = parseInt(rule.split(':')[1]);
                    this.maxLength(value, length, field);
                }
            }
        }
        
        return this.errors.length === 0;
    }
}

// Initialize form validation
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.querySelector('.contact-form');
    
    if (contactForm) {
        const validator = new FormValidator(contactForm);
        
        contactForm.addEventListener('submit', function(e) {
            const rules = {
                name: ['required', 'min:2', 'max:100'],
                email: ['required', 'email'],
                phone: ['phone'],
                message: ['required', 'min:10', 'max:1000']
            };
            
            if (!validator.validate(rules)) {
                e.preventDefault();
                
                // Clear previous errors
                document.querySelectorAll('.error-message').forEach(el => el.remove());
                document.querySelectorAll('.error-input').forEach(el => {
                    el.classList.remove('error-input');
                });
                
                // Show errors
                const errors = validator.getErrors();
                const errorContainer = document.createElement('div');
                errorContainer.className = 'alert alert-error';
                errorContainer.innerHTML = '<strong>Please fix the following errors:</strong><ul>';
                
                errors.forEach(error => {
                    errorContainer.innerHTML += `<li>${error}</li>`;
                });
                
                errorContainer.innerHTML += '</ul>';
                
                contactForm.insertBefore(errorContainer, contactForm.firstChild);
                
                // Scroll to errors
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        
        // Real-time validation
        const inputs = contactForm.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                const fieldName = this.name;
                const value = this.value.trim();
                
                // Remove existing error for this field
                const existingError = this.closest('.form-group')?.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                this.classList.remove('error-input');
                
                // Validate based on field type
                if (fieldName === 'name' && !value) {
                    showFieldError(this, 'Name is required');
                } else if (fieldName === 'email' && !value) {
                    showFieldError(this, 'Email is required');
                } else if (fieldName === 'email' && value && !isValidEmail(value)) {
                    showFieldError(this, 'Please enter a valid email');
                } else if (fieldName === 'phone' && value && !isValidPhone(value)) {
                    showFieldError(this, 'Please enter a valid phone number');
                }
            });
        });
    }
});

// Helper functions
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidPhone(phone) {
    const re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
    return re.test(phone);
}

function showFieldError(input, message) {
    const formGroup = input.closest('.form-group');
    if (!formGroup) return;
    
    input.classList.add('error-input');
    
    const error = document.createElement('span');
    error.className = 'error-message';
    error.textContent = message;
    error.style.color = 'var(--error)';
    error.style.fontSize = '0.875rem';
    error.style.marginTop = '0.25rem';
    error.style.display = 'block';
    
    formGroup.appendChild(error);
}