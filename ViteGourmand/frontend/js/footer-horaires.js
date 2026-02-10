// frontend/js/footer-horaires.js
// Charge dynamiquement les horaires d'ouverture dans le footer

(async function loadFooterHoraires() {
    const container = document.getElementById('footer-horaires');
    if (!container) return;

    try {
        const response = await fetch(`${API_BASE_URL}/horaires/list.php`);
        const result = await response.json();

        if (!result.success || !result.data || result.data.length === 0) {
            container.innerHTML = '<p class="mb-1">Horaires non disponibles</p>';
            return;
        }

        const horaires = result.data;

        // Regrouper les jours ayant les mêmes horaires pour un affichage compact
        const groups = [];
        let currentGroup = null;

        horaires.forEach(h => {
            const key = h.ouvert
                ? `${h.matin_ouverture || ''}-${h.matin_fermeture || ''}|${h.soir_ouverture || ''}-${h.soir_fermeture || ''}`
                : 'ferme';

            if (currentGroup && currentGroup.key === key) {
                currentGroup.jours.push(h.jour);
            } else {
                currentGroup = { key, jours: [h.jour], data: h };
                groups.push(currentGroup);
            }
        });

        const jourLabels = {
            'lundi': 'Lundi', 'mardi': 'Mardi', 'mercredi': 'Mercredi',
            'jeudi': 'Jeudi', 'vendredi': 'Vendredi', 'samedi': 'Samedi', 'dimanche': 'Dimanche'
        };

        const jourShort = {
            'lundi': 'Lun', 'mardi': 'Mar', 'mercredi': 'Mer',
            'jeudi': 'Jeu', 'vendredi': 'Ven', 'samedi': 'Sam', 'dimanche': 'Dim'
        };

        let rows = '';

        groups.forEach(group => {
            const jours = group.jours;
            let jourText;

            if (jours.length === 7) {
                jourText = 'Tous les jours';
            } else if (jours.length >= 3) {
                jourText = `${jourShort[jours[0]]} — ${jourShort[jours[jours.length - 1]]}`;
            } else if (jours.length === 2) {
                jourText = `${jourShort[jours[0]]} & ${jourShort[jours[1]]}`;
            } else {
                jourText = jourShort[jours[0]];
            }

            let horaireText;
            if (!group.data.ouvert) {
                horaireText = '<span class="text-muted">Fermé</span>';
            } else {
                const slots = [];
                if (group.data.matin_ouverture && group.data.matin_fermeture) {
                    slots.push(`${group.data.matin_ouverture} - ${group.data.matin_fermeture}`);
                }
                if (group.data.soir_ouverture && group.data.soir_fermeture) {
                    slots.push(`${group.data.soir_ouverture} - ${group.data.soir_fermeture}`);
                }
                horaireText = slots.join(' | ');
            }

            rows += `<tr><td class="text-end pe-2"><strong>${jourText}</strong></td><td class="text-start ps-2">${horaireText}</td></tr>`;
        });

        container.innerHTML = `<table class="small mb-1 mx-auto"><tbody>${rows}</tbody></table>`;

    } catch (error) {
        console.error('Erreur chargement horaires footer:', error);
        container.innerHTML = '<p class="mb-1">Horaires non disponibles</p>';
    }
})();
