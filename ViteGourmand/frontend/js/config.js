// frontend/js/config.js
// Configuration centralisée pour les URLs API

const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

const API_BASE_URL = isLocal ? '/api' : '/backend/api';

// Assigner à window pour accès global
window.API_BASE_URL = API_BASE_URL;
