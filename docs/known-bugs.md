# 📋 Registre des bugs connus – My Server Manager

Ce document recense les bugs identifiés dans le projet afin de servir de base pour des tests de non-régression.

---

## 🐞 BUG-001 – URL de modification non nettoyée

**Description**  
Quand on clique sur “Modifier” un serveur, la modale s’ouvre avec l’URL contenant `?edit=ID`.  
Si on clique sur ❌ ou “Annuler”, l’URL reste inchangée → à la prochaine actualisation, la modale se rouvre automatiquement.

**Statut**  
✅ Corrigé le 2025-06-22

**Étapes pour reproduire**  
1. Aller sur `/pages/serveurs.php`
2. Cliquer sur “Modifier” sur un serveur
3. Cliquer sur “Annuler” ou ❌
4. Actualiser la page → la modale se rouvre

**Fix implémenté**  
Ajout d’un `window.history.replaceState(...)` dans la fonction `toggleModal(false)` pour nettoyer l’URL.

---
