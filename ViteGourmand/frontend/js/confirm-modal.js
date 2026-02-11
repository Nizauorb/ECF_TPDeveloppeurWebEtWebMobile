// frontend/js/confirm-modal.js
// Modale de confirmation réutilisable (remplace les confirm() natifs)

function confirmAction({ title, message, btnText, btnClass }) {
    return new Promise((resolve) => {
        const modalEl = document.getElementById('confirmActionModal');
        if (!modalEl) { resolve(false); return; }

        document.getElementById('confirmActionTitle').textContent = title || 'Confirmation';
        document.getElementById('confirmActionMessage').textContent = message || 'Êtes-vous sûr ?';

        const btn = document.getElementById('confirmActionBtn');
        btn.textContent = btnText || 'Confirmer';
        btn.className = 'btn btn-sm ' + (btnClass || 'btn-primary');

        // Nettoyer les anciens listeners
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        const modal = new bootstrap.Modal(modalEl);

        newBtn.addEventListener('click', () => {
            modal.hide();
            resolve(true);
        });

        modalEl.addEventListener('hidden.bs.modal', function onHidden() {
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            resolve(false);
        });

        modal.show();
    });
}
