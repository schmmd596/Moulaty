<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

if (empty($user->rights->societe->client->voir)) accessforbidden();

// Chargement du thème Dolibarr
llxHeader('', 'Tableau de bord - Production & Stock');
print load_fiche_titre("📊 Tableau de bord de la production", '', 'chart-line');

// =====================
// STATISTIQUES SQL
// =====================

// 🧊 Nombre total de plats produits (en tunnel)
$sqlPlats = "SELECT count(*) AS total_plats, sum(poids) as poids 
             FROM ".MAIN_DB_PREFIX."pech_plat 
             WHERE fk_carton IS NOT NULL";

$resPlats = $db->query($sqlPlats);

$totalPlats = 0;
$poidPlats = 0;

if ($resPlats && ($obj = $db->fetch_object($resPlats))) {
    $totalPlats = (int)$obj->total_plats;
    $poidPlats = (float)$obj->poids;
}

// 📦 Nombre total de cartons
$sqlCartons = "SELECT COUNT(*) AS total_cartons, SUM(poids) AS total_poids 
               FROM ".MAIN_DB_PREFIX."pech_carton
               WHERE statut = 0"; // 1 = validé
$resCartons = $db->query($sqlCartons);
$totalCartons = $totalPoids = 0;
if ($resCartons && $db->num_rows($resCartons) > 0) {
    $obj = $db->fetch_object($resCartons);
    $totalCartons = (int)$obj->total_cartons;
    $totalPoids = (float)$obj->total_poids;
}

// 💰 Montant total des réceptions validées
$sqlReceptions = "SELECT SUM(montant) AS total_recep
                  FROM ".MAIN_DB_PREFIX."pech_reception
                  WHERE etat = 1";
$resRecep = $db->query($sqlReceptions);
$totalReceptions = ($resRecep && ($obj = $db->fetch_object($resRecep))) ? (float)$obj->total_recep : 0;

// 🚚 Nombre total de sorties validées
$sqlSorties = "SELECT COUNT(*) AS total_sorties, SUM(nb_carton_total) AS cartons_sortis
               FROM ".MAIN_DB_PREFIX."pech_sortie
               WHERE statut = 1";
$resSorties = $db->query($sqlSorties);
$totalSorties = $cartonsSortis = 0;
if ($resSorties && $db->num_rows($resSorties) > 0) {
    $obj = $db->fetch_object($resSorties);
    $totalSorties = (int)$obj->total_sorties;
    $cartonsSortis = (int)$obj->cartons_sortis;
}

// =====================
// AFFICHAGE STYLE DASHBOARD
// =====================

print '<div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">';

// Carte 1 : Plats produits
print '<div style="background:#f0f8ff;border-radius:15px;padding:20px;width:230px;text-align:center;box-shadow:0 0 8px rgba(0,0,0,0.1);">';
print '<h3>🍽️ Plats produits</h3>';
print '<p style="font-size:2em;color:#0078D7;">'.number_format($totalPlats, 0, ',', ' ').'</p>';
print '<small>Poids total : <strong>'.price($poidPlats).' kg</strong></small>';

print '</div>';

// Carte 2 : Cartons en stock
print '<div style="background:#fffaf0;border-radius:15px;padding:20px;width:230px;text-align:center;box-shadow:0 0 8px rgba(0,0,0,0.1);">';
print '<h3>📦 Cartons en stock</h3>';
print '<p style="font-size:2em;color:#ff7f00;">'.number_format($totalCartons, 0, ',', ' ').'</p>';
print '<small>Poids total : <strong>'.number_format($totalPoids, 2, ',', ' ').' kg</strong></small>';
print '</div>';

// Carte 3 : Montant des réceptions
print '<div style="background:#f5fff0;border-radius:15px;padding:20px;width:230px;text-align:center;box-shadow:0 0 8px rgba(0,0,0,0.1);">';
print '<h3>💰 Montant réceptions</h3>';
print '<p style="font-size:2em;color:#28a745;">'.number_format($totalReceptions, 0, ',', ' ').' MRU</p>';
print '</div>';

// Carte 4 : Sorties validées
print '<div style="background:#fff0f5;border-radius:15px;padding:20px;width:230px;text-align:center;box-shadow:0 0 8px rgba(0,0,0,0.1);">';
print '<h3>🚚 Sorties</h3>';
print '<p style="font-size:2em;color:#d63384;">'.number_format($totalSorties, 0, ',', ' ').'</p>';
print '<small>Cartons sortis : <strong>'.number_format($cartonsSortis, 0, ',', ' ').'</strong></small>';
print '</div>';

print '</div>';

// =====================
// IDÉES FUTURES DE STATISTIQUES
// =====================
// - Taux de rendement global
// - Poids moyen par carton
// - Top 5 produits les plus transformés
// - Historique mensuel sous forme de graphique

llxFooter();
$db->close();
