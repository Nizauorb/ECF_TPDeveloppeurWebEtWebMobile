// frontend/js/avis-accueil.js
// Charge et affiche les avis validés sur la page d'accueil

// Observer le DOM pour détecter quand le conteneur est injecté par le Router
const avisObserver = new MutationObserver(() => {
    const container = document.getElementById('avis-accueil-container');
    if (container && !container.dataset.loaded) {
        container.dataset.loaded = 'true';
        loadAvisAccueil(container);
    }
});
avisObserver.observe(document.getElementById('main-content') || document.body, { childList: true, subtree: true });

async function loadAvisAccueil(container) {
    try {
        const response = await fetch(`${API_BASE_URL}/avis/list.php?limit=6`);
        const result = await response.json();

        if (!result.success || !result.data || result.data.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Aucun avis pour le moment.</p>';
            return;
        }

        const avis = result.data;

        // Note moyenne
        const moyenne = (avis.reduce((sum, a) => sum + a.note, 0) / avis.length).toFixed(1);

        let html = `
            <div class="text-center mb-4">
                <span class="fs-3 fw-bold text-warning">${moyenne}</span>
                <span class="fs-5 text-muted">/5</span>
                <div class="mt-1">${generateAccueilStars(Math.round(parseFloat(moyenne)))}</div>
                <small class="text-muted">${result.count} avis client${result.count > 1 ? 's' : ''}</small>
            </div>
            <div class="row g-4 justify-content-center">
        `;

        avis.forEach(a => {
            const stars = generateAccueilStars(a.note);
            html += `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="text-primary">${a.client_prenom}</strong>
                                <small class="text-muted">${a.created_at}</small>
                            </div>
                            <div class="mb-2">${stars}</div>
                            <p class="card-text flex-grow-1 fst-italic text-secondary">"${a.commentaire}"</p>
                            <small class="text-muted mt-auto"><i class="bi bi-egg-fried me-1"></i>${a.menu_nom || ''}</small>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

    } catch (error) {
        console.error('Erreur chargement avis accueil:', error);
        container.innerHTML = '<p class="text-center text-muted">Impossible de charger les avis.</p>';
    }
}

function generateAccueilStars(note) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += i <= note
            ? '<i class="bi bi-star-fill text-warning"></i>'
            : '<i class="bi bi-star text-muted"></i>';
    }
    return html;
}
