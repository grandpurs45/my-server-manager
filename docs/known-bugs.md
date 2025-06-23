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

[#0004] – Formulaire non réinitialisé après modification

📝 Description :
Après avoir modifié un serveur, si l’utilisateur cliquait sur “➕ Ajouter un serveur”, le formulaire de la modale était pré-rempli avec les anciennes données de modification.

🔍 Cause :
Le paramètre ?edit=ID restait dans l’URL, et la modale se rouvrait avec les anciennes valeurs via $editData.

✅ Fix appliqué :
Ajout d’un resetForm() au clic sur “Ajouter un serveur” + nettoyage de l’URL pour supprimer edit.

📦 Date du correctif : 2025-06-23
🔖 Version concernée : Hotfix v0.5.1

---

[#0003] Nom du serveur non mis à jour dans le tableau
Symptôme : Après modification d’un serveur, le nom affiché dans le tableau restait l’ancien, bien que le champ de formulaire et la base de données montraient la nouvelle valeur.

Cause : Le tableau affichait encore les anciennes données car la requête SQL de récupération (SELECT * FROM servers) était exécutée avant le traitement POST d'édition, donc avec un cache mémoire encore chaud.

Correction : Le traitement d’édition a été déplacé avant la récupération des serveurs, pour refléter les données modifiées dès la redirection vers serveurs.php.

Statut : ✅ Corrigé dans la version v0.5.2.

