class AuthValidator {
    constructor() {
        this._loginBound = false;
        this._registerBound = false;
        this.csrfToken = null;
        setTimeout(() => this.init(), 0);
    }
 
    init() {
        this.setupLoginForm();
        this.setupRegisterForm();
        this.setupForgotPasswordForm();
        this.setupResetPasswordForm();
        this.loadCSRFToken();
    }

    async loadCSRFToken() {
        try {
            const response = await fetch(`${API_BASE_URL}/csrf/token.php`, {
                method: 'GET',
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.csrfToken = data.csrf_token;
                // DEBUG: console.log('CSRF Token chargé dans AuthValidator:', this.csrfToken.substring(0, 8) + '...');
            } else {
                console.error('Erreur chargement token CSRF dans AuthValidator');
            }
        } catch (error) {
            console.error('Erreur réseau CSRF AuthValidator:', error);
        }
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
        
        // Mise à jour de l'interface utilisateur (si vous avez des indicateurs visuels)
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

// Nouvelle méthode pour la connexion
async loginUser() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
        // Afficher l'état de chargement
        this.showLoadingState();
        
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.csrfToken) headers['X-CSRF-Token'] = this.csrfToken;
        
        const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
            method: 'POST',
            headers: headers,
            credentials: 'include',
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

         // Gérer les réponses HTTP
        if (!response.ok) {
            if (response.status === 401) {
                throw new Error('Email ou mot de passe incorrect');
            } else if (response.status === 429) {
                throw new Error('Trop de tentatives de connexion. Veuillez réessayer plus tard.');
            } else if (response.status >= 500) {
                throw new Error('Erreur serveur. Veuillez réessayer ultérieurement.');
            } else {
                throw new Error('Erreur de connexion');
            }
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Succès : stocker les infos et rediriger
           this.handleSuccessfulLogin(data);   
        } else {
            // Erreur retournée par le backend
            this.showError(data.message || 'Erreur de connexion');
        }
    } catch (error) {
        // Erreur réseau ou serveur
        console.error('Erreur fetch:', error);
        this.showError('Impossible de contacter le serveur. Vérifiez votre connexion.');
    } finally {
        this.hideLoadingState();
    }
}

    setupLoginForm() {
            const loginForm = document.getElementById('loginForm');
            if (!loginForm) {
                // Si pas trouvé, réessayer dans 50ms
                setTimeout(() => this.setupLoginForm(), 50);
                return;
            }

            if (this._loginBound) return;
            this._loginBound = true;

            console.log('Formulaire trouvé, configuration...');
            
            loginForm.addEventListener('submit', async (e) => {
                console.log('Événement intercepté !');
                e.preventDefault();
                
                if (this.validateLoginForm()) {
                    await this.loginUser();
                }
            });
        }
        
    // Gérer le succès de connexion
    handleSuccessfulLogin(data) {
        // Stocker les informations
        localStorage.setItem('token', data.data.token);
        localStorage.setItem('user', JSON.stringify(data.data.user));
        
        // Afficher le message de succès
        this.showSuccess('Connexion réussie ! Redirection...');
        
        // Redirection selon le rôle
        setTimeout(() => {
            const user = data.data.user;
            let redirectUrl = '/';
            
            switch(user.role) {
                case 'administrateur':
                    redirectUrl = '/admin';
                    break;
                case 'employe':
                    redirectUrl = '/employe';
                    break;
                case 'utilisateur':
                    redirectUrl = '/UserDashboard';
                    break;
                default:
                    redirectUrl = '/';
            }
            
            window.location.href = redirectUrl;
        }, 1500);
    }    

    setupRegisterForm() {
        const registerForm = document.getElementById('registerForm');
        if (!registerForm) {
            setTimeout(() => this.setupRegisterForm(), 50);
            return;
        }

        if (this._registerBound) return;
        this._registerBound = true;

        // Validation en temps réel du mot de passe
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                this.updatePasswordRequirements(passwordInput.value);
            });
        }

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (this.validateRegisterForm()) {
                try {
                    this.showLoadingState();
                    
                    // Récupération des données du formulaire
                    const formData = {
                        lastName: document.getElementById('lastName').value,
                        firstName: document.getElementById('firstName').value,
                        email: document.getElementById('email').value,
                        phone: document.getElementById('phone').value,
                        adresse: document.getElementById('adresse').value,
                        code_postal: document.getElementById('code_postal').value,
                        ville: document.getElementById('ville').value,
                        password: document.getElementById('password').value,
                        confirmPassword: document.getElementById('confirmPassword').value
                    };

                    // Envoi des données au serveur
                    const regHeaders = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                    if (this.csrfToken) regHeaders['X-CSRF-Token'] = this.csrfToken;
                    
                    const response = await fetch(`${API_BASE_URL}/auth/register.php`, {
                        method: 'POST',
                        headers: regHeaders,
                        credentials: 'include',
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Erreur lors de l\'inscription');
                    }

                    // Inscription réussie
                    this.showSuccess('Inscription réussie ! Redirection vers la page de connexion...');
                    
                    // Redirection vers la page de connexion après 2 secondes
                    setTimeout(() => {
                        window.location.href = '/Login';
                    }, 2000);

                } catch (error) {
                    console.error('Erreur lors de l\'inscription:', error);
                    this.showError(error.message || 'Une erreur est survenue lors de l\'inscription');
                } finally {
                    this.hideLoadingState();
                }
            }
        });

        // Configuration de la validation en temps réel
        this.setupFieldValidation('lastName', (value) => value.trim().length >= 2);
        this.setupFieldValidation('firstName', (value) => value.trim().length >= 2);
        this.setupFieldValidation('phone', this.validatePhone.bind(this));
        this.setupFieldValidation('email', this.validateEmail.bind(this));
        this.setupFieldValidation('adresse', (value) => value.trim().length >= 5);
        this.setupFieldValidation('code_postal', (value) => /^[0-9]{5}$/.test(value.trim()));
        this.setupFieldValidation('ville', (value) => value.trim().length >= 2);
        this.setupFieldValidation('password', (value) => this.updatePasswordRequirements(value));
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
        let errorMessage = '';

        // Validation email
        if (!email.value.trim()) {
            this.validateField(email, false);
            errorMessage = 'L\'adresse email est requise';
            isValid = false;
        } else if (!this.validateEmail(email.value)) {
            this.validateField(email, false);
            errorMessage = 'Veuillez entrer une adresse email valide';
            isValid = false;
        } else {
            this.validateField(email, true);
        }

        // Validation mot de passe
        if (!password.value.trim()) {
            this.validateField(password, false);
            if (!errorMessage) {
                errorMessage = 'Le mot de passe est requis';
            }
            isValid = false;
        } else {
            this.validateField(password, true);
        }

        // Afficher le message d'erreur approprié
        if (!isValid && errorMessage) {
            this.showError(errorMessage);
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
            { id: 'adresse', validator: (value) => value.trim().length >= 5 },
            { id: 'code_postal', validator: (value) => /^[0-9]{5}$/.test(value.trim()) },
            { id: 'ville', validator: (value) => value.trim().length >= 2 },
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

    setupForgotPasswordForm() {
        const form = document.getElementById('forgotPasswordForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            
            if (!this.validateEmail(email)) {
                this.showError('emailError', 'Veuillez entrer un email valide');
                return;
            }

            try {
                // Vérifier si le token CSRF est disponible
                if (!this.csrfToken) {
                    throw new Error('Token CSRF non disponible. Veuillez réessayer.');
                }

                const response = await fetch(`${API_BASE_URL}/auth/forgot-password.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    credentials: 'include',
                    body: JSON.stringify({ 
                        csrf_token: this.csrfToken,
                        email 
                    })
                });
            
                // Lire la réponse une seule fois
                const responseText = await response.text();
                console.log('Réponse brute:', responseText);
                
                // Essayer de parser en JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    throw new Error('Réponse serveur invalide: ' + responseText);
                }
                
                if (data.success) {
                    this.showSuccess(data.message);
                    setTimeout(() => {
                        window.location.href = '/Login';
                    }, 3000);
                } else {
                    this.showError('generalError', data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.showError('generalError', 'Erreur de communication avec le serveur: ' + error.message);
            }
        });
    }

    setupResetPasswordForm() {
        const form = document.getElementById('resetPasswordForm');
        if (!form) return;

        // Gestion du token de réinitialisation
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        
        // Si le token est présent dans l'URL, le stocker dans le champ caché
        if (token) {
            const tokenInput = document.getElementById('token');
            if (tokenInput) {
                tokenInput.value = token;
            }
        } else {
            // Si aucun token n'est trouvé, vérifier dans le localStorage
            const storedToken = localStorage.getItem('resetToken');
            if (storedToken) {
                const tokenInput = document.getElementById('token');
                if (tokenInput) {
                    tokenInput.value = storedToken;
                    // Nettoyer le localStorage après utilisation
                    localStorage.removeItem('resetToken');
                }
            } else {
                // Rediriger vers la page de demande de réinitialisation si aucun token n'est trouvé
                window.location.href = '/ForgotPassword';
                return; // Ne pas continuer l'exécution si redirection
            }
        }

        // Ajouter l'écouteur d'événement pour la validation en temps réel du mot de passe
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                this.updatePasswordRequirements(passwordInput.value);
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const token = document.getElementById('token').value;

            // Validation des exigences du mot de passe selon le cahier des charges
            const passwordRequirements = this.validatePassword(password);
            const isPasswordValid = Object.values(passwordRequirements).every(req => req === true);
            
            if (!isPasswordValid) {
                this.showError('passwordError', 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial');
                return;
            }

            if (password !== confirmPassword) {
                this.showError('confirmPasswordError', 'Les mots de passe ne correspondent pas');
                return;
            }

            try {
                // Afficher l'état de chargement
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('loading');
                }

                const resetHeaders = { 'Content-Type': 'application/json' };
                if (this.csrfToken) resetHeaders['X-CSRF-Token'] = this.csrfToken;
                
                const response = await fetch(`${API_BASE_URL}/auth/reset-password.php`, {
                    method: 'POST',
                    headers: resetHeaders,
                    credentials: 'include',
                    body: JSON.stringify({ 
                        token, 
                        password 
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showSuccess('Votre mot de passe a été réinitialisé avec succès');
                    setTimeout(() => {
                        window.location.href = '/Login';
                    }, 3000);
                } else {
                    this.showError('generalError', data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.showError('generalError', 'Une erreur est survenue lors de la communication avec le serveur');
            } finally {
                // Masquer l'état de chargement
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                }
            }
        });
    }   
    
    // Afficher l'état de chargement
    showLoadingState() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Connexion en cours...
            `;
        }
    }

    // Masquer l'état de chargement
    hideLoadingState() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Se connecter';
        }
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

    // Vérifier si l'utilisateur est connecté
    isLoggedIn() {
        const token = localStorage.getItem('token');
        const user = localStorage.getItem('user');
        
        return token && user;
    }

    // Obtenir l'utilisateur connecté
    getCurrentUser() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    }

    // Déconnexion
    logout() {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/Login';
    }
}



// Le routeur charge ce script dynamiquement après injection du HTML.
// DOMContentLoaded est souvent déjà passé, donc on initialise immédiatement.
new AuthValidator();
