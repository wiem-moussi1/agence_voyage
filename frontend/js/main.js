// Fonction utilitaire pour fetcher l'API
async function fetchAPI(endpoint, options = {}) { //endpoint → le chemin de l’API que tu veux appeler
    // options = {} → objet facultatif pour passer des headers, méthode POST, body, etc.
    // Si tu ne fournis rien, par défaut c’est un objet vide {} (GET par défaut).
    try {
        const res = await fetch(`http://localhost/agence-voyage/backend/index.php?uri=${endpoint}`, options); // fetch() → fonction native JS pour faire une requête HTTP vers un serveur.
        return await res.json(); // await → JS attend la réponse avant de continuer.
    } catch (err) {
      console.error("Erreur API :", err);return null;
    }
}