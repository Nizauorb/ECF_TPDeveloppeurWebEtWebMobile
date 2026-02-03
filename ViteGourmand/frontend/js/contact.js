class ContactForm {
    constructor() {
        this._formBound = false;
        setTimeout(() => this.init(), 0);
    }

    init() {
        this.setupContactForm();
    }

    setupContactForm() {
        const contactForm = document.querySelector('.contact-form');
        
        if (!contactForm) {
            setTimeout(() => this.setupContactForm(), 50);
            return;
        }

        if (this._formBound) return;
        this._formBound = true;

        console.log('Formulaire de contact trouvé, configuration...');

        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (this.validateContactForm()) {
                await this.sendContactMessage();
            }
        });

        // Validation en temps réel des champs
        this.setupFieldValidation('name', (value) => value.trim().length >= 2);
        this.setupFieldValidation('email', (value) => this.validateEmail(value));
        this.setupFieldValidation('message', (value) => value.trim().length >= 10);
    }

    // Validation de l'email
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Configuration de la validation d'un champ
    setupFieldValidation(fieldId, validator) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.addEventListener('blur', () => {
            this.validateField(field, validator(field.value));
        });

        // Validation en temps réel pour une meilleure expérience
        field.addEventListener('input', () => {
            if (field.classList.contains('is-invalid')) {
                this.validateField(field, validator(field.value));
            }
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

    // Validation complète du formulaire de contact
    validateContactForm() {
        const name = document.getElementById('name');
        const email = document.getElementById('email');
        const message = document.getElementById('message');
        
        let isValid = true;
        let errorMessage = '';

        // Validation nom
        if (!name.value.trim()) {
            this.validateField(name, false);
            errorMessage = 'Le nom est requis';
            isValid = false;
        } else if (name.value.trim().length < 2) {
            this.validateField(name, false);
            errorMessage = 'Le nom doit contenir au moins 2 caractères';
            isValid = false;
        } else {
            this.validateField(name, true);
        }

        // Validation email
        if (!email.value.trim()) {
            this.validateField(email, false);
            if (!errorMessage) {
                errorMessage = 'L\'adresse email est requise';
            }
            isValid = false;
        } else if (!this.validateEmail(email.value)) {
            this.validateField(email, false);
            if (!errorMessage) {
                errorMessage = 'Veuillez entrer une adresse email valide';
            }
            isValid = false;
        } else {
            this.validateField(email, true);
        }

        // Validation message
        if (!message.value.trim()) {
            this.validateField(message, false);
            if (!errorMessage) {
                errorMessage = 'Le message est requis';
            }
            isValid = false;
        } else if (message.value.trim().length < 10) {
            this.validateField(message, false);
            if (!errorMessage) {
                errorMessage = 'Le message doit contenir au moins 10 caractères';
            }
            isValid = false;
        } else if (message.value.trim().length > 2000) {
            this.validateField(message, false);
            if (!errorMessage) {
                errorMessage = 'Le message ne peut pas dépasser 2000 caractères';
            }
            isValid = false;
        } else {
            this.validateField(message, true);
        }

        // Afficher le message d'erreur approprié
        if (!isValid && errorMessage) {
            this.showError(errorMessage);
        }

        return isValid;
    }

    // Envoyer le message de contact
    async sendContactMessage() {
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const message = document.getElementById('message').value;
        
        try {
            // Afficher l'état de chargement
            this.showLoadingState();
            
            const response = await fetch('/api/contact/send.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
                    email: email,
                    message: message
                })
            });

            if (!response.ok) {
                if (response.status === 400) {
                    const data = await response.json();
                    throw new Error(data.message || 'Données invalides');
                } else if (response.status >= 500) {
                    throw new Error('Erreur serveur. Veuillez réessayer ultérieurement.');
                } else {
                    throw new Error('Erreur lors de l\'envoi du message');
                }
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Succès : vider le formulaire et afficher confirmation
                this.handleSuccessfulSubmission();
            } else {
                // Erreur retournée par le backend
                this.showError(data.message || 'Erreur lors de l\'envoi du message');
            }
        } catch (error) {
            // Erreur réseau ou serveur
            console.error('Erreur fetch:', error);
            this.showError(error.message || 'Impossible de contacter le serveur. Vérifiez votre connexion.');
        } finally {
            this.hideLoadingState();
        }
    }

    // Gérer le succès de l'envoi
    handleSuccessfulSubmission() {
        // Vider le formulaire
        document.getElementById('name').value = '';
        document.getElementById('email').value = '';
        document.getElementById('message').value = '';
        
        // Retirer les classes de validation
        document.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });
        
        // Afficher le message de succès
        this.showSuccess('Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');
    }

    // Afficher l'état de chargement
    showLoadingState() {
        const submitBtn = document.querySelector('.contact-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Envoi en cours...
            `;
        }
    }

    // Masquer l'état de chargement
    hideLoadingState() {
        const submitBtn = document.querySelector('.contact-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Envoyer';
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
        // Création d'une alerte Bootstrap
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

// Le routeur charge ce script dynamiquement après injection du HTML.
// DOMContentLoaded est souvent déjà passé, donc on initialise immédiatement.
new ContactForm();
