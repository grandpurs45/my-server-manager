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

## 🐞 BUG-002 – Formulaire non réinitialisé après modification

📝 Description :
Après avoir modifié un serveur, si l’utilisateur cliquait sur “➕ Ajouter un serveur”, le formulaire de la modale était pré-rempli avec les anciennes données de modification.

🔍 Cause :
Le paramètre ?edit=ID restait dans l’URL, et la modale se rouvrait avec les anciennes valeurs via $editData.

✅ Fix appliqué :
Ajout d’un resetForm() au clic sur “Ajouter un serveur” + nettoyage de l’URL pour supprimer edit.

📦 Date du correctif : 2025-06-23
🔖 Version concernée : Hotfix v0.5.1

