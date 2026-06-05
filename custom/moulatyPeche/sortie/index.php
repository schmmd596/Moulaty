<?php
// ======================================================================
//  Fichier : sortie_par_entrepot.php
//  Description : Statistiques des sorties groupées par entrepôt source
// ======================================================================

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

global $db, $langs, $user;

$langs->load("main");
$langs->load("products");

$form = new Form($db);

// ======================================================================
//  RÉCUPÉRATION DU FILTRE
// ======================================================================
$entrepot_id = GETPOST('entrepot', 'int');

// ======================================================================
//  HEADER
// ======================================================================
llxHeader('', 'Sorties par entrepôt source');

print load_fiche_titre("Sorties par entrepôt source", '', 'warehouse');

// ======================================================================
//  FORMULAIRE DE FILTRE
// ======================================================================
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="tabBar">';
print '<table class="noborder">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Filtrer par entrepôt source").'</td>';

// Récupérer la liste des entrepôts
$entrepots = array(0 => '🏭 Tous les entrepôts');
$sql_entrepot = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC";
$resql_entrepot = $db->query($sql_entrepot);
if ($resql_entrepot) {
    while ($obj = $db->fetch_object($resql_entrepot)) {
        $entrepots[$obj->rowid] = $obj->ref;
    }
}

print '<td>';
print $form->selectarray('entrepot', $entrepots, $entrepot_id, 0);
print '</td>';
print '<td><input type="submit" class="button" value="'.$langs->trans("Filtrer").'"></td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form><br>';

// ======================================================================
//  REQUÊTE SQL PRINCIPALE
// ======================================================================
$sql = "
SELECT 
    e.rowid AS entrepot_id,
    e.ref AS entrepot_ref,
    COUNT(DISTINCT s.rowid) AS nb_sorties,
    SUM(d.nb_carton) AS total_cartons,
    SUM(d.poids_total) AS total_poids,
    SUM(f.total_ttc) AS total_factures
FROM 
    ".MAIN_DB_PREFIX."pech_sortie s
INNER JOIN 
    ".MAIN_DB_PREFIX."entrepot e ON e.rowid = s.fk_entrepot_source
LEFT JOIN 
    ".MAIN_DB_PREFIX."pech_sortiedetprod d ON s.rowid = d.fk_sortie
LEFT JOIN 
    ".MAIN_DB_PREFIX."facture f ON f.rowid = s.fk_facture_client
WHERE 
    s.statut = 2
";

if ($entrepot_id > 0) {
    $sql .= " AND s.fk_entrepot_source = ".((int) $entrepot_id);
}

$sql .= "
GROUP BY e.rowid, e.ref
ORDER BY e.ref ASC
";

// ======================================================================
//  EXÉCUTION ET AFFICHAGE
// ======================================================================
$resql = $db->query($sql);

if (!$resql) {
    dol_print_error($db);
} else {
    $nb = $db->num_rows($resql);

    if ($nb == 0) {
        print '<div class="info">Aucune sortie trouvée pour les critères sélectionnés.</div>';
    } else {
        print '<table class="liste" width="100%" border="1" cellpadding="4" cellspacing="0">';
        print '<tr class="liste_titre">';
        print '<th>🏭 Entrepôt</th>';
        print '<th>Nombre de sorties</th>';
        print '<th>Nombre total de cartons</th>';
        print '<th>Poids total (kg)</th>';
        print '<th>Montant total des factures (FCFA)</th>';
        print '</tr>';

        $total_sorties_global = 0;
        $total_cartons_global = 0;
        $total_poids_global = 0;
        $total_factures_global = 0;

        while ($obj = $db->fetch_object($resql)) {
            print '<tr>';
            print '<td><strong>'.$obj->entrepot_ref.'</strong></td>';
            print '<td align="right">'.$obj->nb_sorties.'</td>';
            print '<td align="right">'.price($obj->total_cartons).'</td>';
            print '<td align="right">'.price($obj->total_poids).'</td>';
            print '<td align="right">'.price($obj->total_factures).'</td>';
            print '</tr>';

            $total_sorties_global += $obj->nb_sorties;
            $total_cartons_global += $obj->total_cartons;
            $total_poids_global += $obj->total_poids;
            $total_factures_global += $obj->total_factures;
        }

        // Totaux globaux
        print '<tr class="liste_total">';
        print '<td align="right"><b>Total général</b></td>';
        print '<td align="right"><b>'.$total_sorties_global.'</b></td>';
        print '<td align="right"><b>'.$total_cartons_global.'</b></td>';
        print '<td align="right"><b>'.$total_poids_global.'</b></td>';
        print '<td align="right"><b>'.price($total_factures_global).'</b></td>';
        print '</tr>';
        print '</table>';
    }
}

// ======================================================================
//  FOOTER
// ======================================================================
llxFooter();
$db->close();
?>
