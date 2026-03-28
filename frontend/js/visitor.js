// Afficher les voyages
async function loadVoyages() {
    const voyages = await fetchAPI("voyages");
    const container = document.getElementById("voyages");

    if (!voyages) {
        container.innerHTML = "<p>Impossible de charger les voyages.</p>";
        return;
    }

    container.innerHTML = voyages.map(v => `
        <div class="voyage-card">
            <h2>${v.title}</h2>
            <p>${v.description}</p>
            <p>Prix: ${v.price} €</p>
            <button onclick="reserverVoyage(${v.id})">Réserver</button>
        </div>
    `).join('');
}

// Charger automatiquement les voyages au chargement
loadVoyages();