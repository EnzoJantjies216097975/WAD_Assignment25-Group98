/**
 * University Timetable Manager - Main JavaScript
 * Core functionality and utilities
 */

// ===== UTILITY FUNCTIONS =====

const UTM = {
    /**
     * Generate unique ID
     */
    generateId: () => {
        return 'id_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    },

    /**
     * Show toast notification
     */
    showAlert: (message, type = 'info', duration = 3000) => {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        `;
        alertDiv.textContent = message;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => alertDiv.remove(), 300);
        }, duration);
    },

    /**
     * Format date
     */
    formatDate: (date, format = 'MMM DD, YYYY') => {
        const d = new Date(date);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        return format
            .replace('MMM', months[d.getMonth()])
            .replace('DD', String(d.getDate()).padStart(2, '0'))
            .replace('YYYY', d.getFullYear());
    },

    /**
     * Validate email
     */
    validateEmail: (email) => {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Debounce function
     */
    debounce: (func, wait) => {
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
};

// ===== MODAL MANAGER =====

const ModalManager = {
    open: (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    close: (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    init: () => {
        // Close modal when clicking backdrop
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    ModalManager.close(modal.id);
                }
            });
        });

        // Close modal when clicking close button
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = btn.closest('.modal');
                if (modal) {
                    ModalManager.close(modal.id);
                }
            });
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    ModalManager.close(modal.id);
                });
            }
        });
    }
};

// ===== NAVIGATION =====

const Navigation = {
    init: () => {
        const toggle = document.querySelector('.nav-toggle');
        const menu = document.querySelector('.nav-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', () => {
                menu.classList.toggle('active');
                toggle.classList.toggle('active');
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('active');
                    toggle.classList.remove('active');
                }
            });
        }
    }
};

// ===== FORM VALIDATION =====

const FormValidator = {
    validate: (formId) => {
        const form = document.getElementById(formId);
        if (!form) return false;

        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }

            // Email validation
            if (input.type === 'email' && input.value) {
                if (!UTM.validateEmail(input.value)) {
                    isValid = false;
                    input.classList.add('error');
                }
            }
        });

        return isValid;
    },

    init: () => {
        // Clear error state on input
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error');
            });
        });
    }
};

// ===== INITIALIZATION =====

document.addEventListener('DOMContentLoaded', () => {
    // Initialize modules
    ModalManager.init();
    Navigation.init();
    FormValidator.init();

    // Add loading class removal
    document.body.classList.remove('loading');

    console.log('✅ University Timetable Manager initialized');
});

// Make utilities globally available
window.UTM = UTM;
window.ModalManager = ModalManager;