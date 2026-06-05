<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("main");
$form = new Form($db);

llxHeader('', 'Gestion des rôles');

// --- Style simple ---
print '
<style>
.fichecenter { max-width: 1200px; margin: 0 auto; }
.card { background: #f8f9fa; padding: 20px; border-radius: 12px; box-shadow: 0 3px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
.card h3 { margin-top: 0; }
.card p { color: #555; }
.btn-manage { display: inline-block; padding: 8px 15px; background: #0073aa; color: white; border-radius: 6px; text-decoration: none; font-weight: bold; transition: background 0.3s; }
.btn-manage:hover { background: #005f80; }
.cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px; }
</style>
';

print '<div class="fichecenter">';
print load_fiche_titre("Gestion des rôles", '', 'title_generic');

print '<div class="cards-container">';

// --- Card 1 : Utilisateurs par banque ---
print '<div class="card">';
print '<h3><i class="fa fa-university"></i> Utilisateurs par banque</h3>';
print '<p>Gérez les utilisateurs associés aux banques et leurs permissions.</p>';
print '<a href="user_bank.php" class="btn-manage">Gérer →</a>';
print '</div>';

// --- Card 2 : Utilisateurs par entrepôt ---
print '<div class="card">';
print '<h3><i class="fa fa-warehouse"></i> Utilisateurs par entrepôt</h3>';
print '<p>Gérez les utilisateurs associés aux entrepôts avec leurs rôles spécifiques.</p>';
print '<a href="users_entrepot.php" class="btn-manage">Gérer →</a>';
print '</div>';

print '</div>'; // cards-container
print '</div>'; // fichecenter

llxFooter();
$db->close();
?>
