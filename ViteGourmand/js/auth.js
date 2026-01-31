class AuthValidator {
    constructor() {
        this.init();
    }

    init() {
        this.setupLoginForm();
        this.setupRegisterForm();
    }

    // Validation du mot de passe selon les exigences
    validatePassword(password) {
        const requirements = {
            length: password.length >= 10,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };

        return requirements;
    }

    // Validation de l'email
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Validation du téléphone
    validatePhone(phone) {
        const phoneRegex = /^(?:0|\(?\+33\)?\s?|0033\s?)[1-79](?:[\.\-\s]?\d{2}){4}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }

    // Mise à jour des indicateurs de mot de passe
    updatePasswordRequirements(password) {
        const requirements = this.validatePassword(password);
        
        Object.keys(requirements).forEach(req => {
            const element = document.querySelector(`[data-requirement="${req}"]`);
            if (element) {
                const icon = element.querySelector('i');
                if (requirements[req]) {
                    icon.className = 'bi bi-check-circle text-success';
                } else {
                    icon.className = 'bi bi-x-circle text-danger';
                }
            }
        });

        return Object.values(requirements).every(req => req === true);
    }

    // Configuration du formulaire de connexion
    setupLoginForm() {
        const loginForm = document.getElementById('loginForm');
        if (!loginForm) return;

        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (this.validateLoginForm()) {
                // Simulation de connexion - à remplacer par appel API
                console.log('Tentative de connexion...');
                this.showSuccess('Connexion réussie ! Redirection...');
                setTimeout(() => {
                    // Redirection vers l'espace utilisateur ou admin selon le rôle
                    window.location.href = '/';
                }, 2000);
            }
        });

        // Validation en temps réel
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        emailInput?.addEventListener('blur', () => {
            this.validateField(emailInput, this.validateEmail(emailInput.value));
        });

        passwordInput?.addEventListener('blur', () => {
            this.validateField(passwordInput, passwordInput.value.length > 0);
        });
    }

    // Configuration du formulaire d'inscription
    setupRegisterForm() {
        const registerForm = document.getElementById('registerForm');
        if (!registerForm) return;

        // Validation en temps réel du mot de passe
        const passwordInput = document.getElementById('password');
        passwordInput?.addEventListener('input', () => {
            this.updatePasswordRequirements(passwordInput.value);
        });

        registerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (this.validateRegisterForm()) {
                // Simulation d'inscription - à remplacer par appel API
                console.log('Tentative d\'inscription...');
                this.showSuccess('Inscription réussie ! Redirection...');
                setTimeout(() => {
                    // Redirection vers la page de connexion
                    window.location.href = '/Login';
                }, 2000);
            }
        });

        // Validation en temps réel des autres champs
        this.setupFieldValidation('lastName', (value) => value.trim().length >= 2);
        this.setupFieldValidation('firstName', (value) => value.trim().length >= 2);
        this.setupFieldValidation('phone', this.validatePhone.bind(this));
        this.setupFieldValidation('email', this.validateEmail.bind(this));
        this.setupFieldValidation('address', (value) => value.trim().length >= 10);
        this.setupFieldValidation('confirmPassword', (value) => {
            const password = document.getElementById('password')?.value;
            return value === password && value.length > 0;
        });
    }

    // Configuration de la validation d'un champ
    setupFieldValidation(fieldId, validator) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.addEventListener('blur', () => {
            this.validateField(field, validator(field.value));
        });
    }

    // Validation d'un champ spécifique
    validateField(field, isValid) {
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }
        return isValid;
    }

    // Validation complète du formulaire de connexion
    validateLoginForm() {
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        
        let isValid = true;

        if (!this.validateField(email, this.validateEmail(email.value))) {
            isValid = false;
        }

        if (!this.validateField(password, password.value.length > 0)) {
            isValid = false;
        }

        return isValid;
    }

    // Validation complète du formulaire d'inscription
    validateRegisterForm() {
        const fields = [
            { id: 'lastName', validator: (value) => value.trim().length >= 2 },
            { id: 'firstName', validator: (value) => value.trim().length >= 2 },
            { id: 'phone', validator: this.validatePhone.bind(this) },
            { id: 'email', validator: this.validateEmail.bind(this) },
            { id: 'address', validator: (value) => value.trim().length >= 10 },
            { id: 'password', validator: (value) => this.updatePasswordRequirements(value) },
            { id: 'confirmPassword', validator: (value) => {
                const password = document.getElementById('password')?.value;
                return value === password && value.length > 0;
            }}
        ];

        let isValid = true;

        fields.forEach(({ id, validator }) => {
            const field = document.getElementById(id);
            if (field && !this.validateField(field, validator(field.value))) {
                isValid = false;
            }
        });

        // Validation des CGU
        const termsCheckbox = document.getElementById('terms');
        if (termsCheckbox && !termsCheckbox.checked) {
            termsCheckbox.classList.add('is-invalid');
            isValid = false;
        } else if (termsCheckbox) {
            termsCheckbox.classList.remove('is-invalid');
        }

        return isValid;
    }

    // Affichage d'un message de succès
    showSuccess(message) {
        // Création d'une alerte Bootstrap
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    // Affichage d'un message d'erreur
    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

// Initialisation lorsque le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    new AuthValidator();
});
