<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

// Chargement des langues
$langs->loadLangs(['womapeche@womapeche', 'main', 'other']);
$form = new Form($db);

// ============================================================================
// Paramètres
// ============================================================================
$fk_entrepot     = GETPOST('fk_entrepot', 'int');
$fk_fournisseur  = GETPOST('fk_fournisseur', 'int');
$action          = GETPOST('action', 'aZ09');

include_once '../user_entrepot_access.php';
if (!empty($fk_entrepot)) {
    check_user_entrepot_access($fk_entrepot);
}

// ============================================================================
// Chargement des entrepôts et fournisseurs
// ============================================================================
$entrepot = new Entrepot($db);
$warehouses = $entrepot->list_array();

$suppliers = [];
$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = 1 ORDER BY nom ASC";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $suppliers[$obj->rowid] = $obj->nom;
    }
}

// ============================================================================
// En-tête
// ============================================================================
llxHeader('', $langs->trans("MiseEnPlat"));

// Inclusion du CSS pour les formulaires de sélection
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';
    
    // ============================================================================
    // 🎯 En-tête
    // ============================================================================
    print '<div class="selection-header">';
    print '<h1>';
    print '<i class="fa fa-utensils"></i>';
    print $langs->trans("PreparationMiseEnPlat");
    print '</h1>';
    print '</div>';

    // ============================================================================
    // 🔍 Filtres
    // ============================================================================
    print '<div class="filter-section">';
    print '<h3 class="filter-title"><i class="fa fa-filter"></i> '.$langs->trans("Filtres").'</h3>';
    
    print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
    print '<table class="filter-table">';
    
    print '<tr>';
    print '<td width="30%"><label for="fk_entrepot"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</label></td>';
    print '<td width="70%">'.$form->selectarray('fk_entrepot', $warehouses, $fk_entrepot, 1, 0, 0, '', 0, 0, 0, 'class="filter-input"').'</td>';
    print '</tr>';
    
    print '<tr>';
    print '<td colspan="2" class="center">';
    print '<button type="submit" class="selection-button selection-button-primary"><i class="fa fa-search"></i> '.$langs->trans("Filtrer").'</button>';
    print '&nbsp;';
    print '<a href="'.$_SERVER['PHP_SELF'].'" class="selection-button selection-button-secondary"><i class="fa fa-refresh"></i> '.$langs->trans("Reset").'</a>';
    print '</td>';
    print '</tr>';
    
    print '</table>';
    print '</form>';
    print '</div>';

    print '<hr class="selection-separator">';

    // ============================================================================
    // 📋 Liste des produits issus des réceptions filtrées
    // ============================================================================
    if ($fk_entrepot || $fk_fournisseur) {
        $sql = "SELECT r.rowid as fk_reception, r.ref as reception_ref, r.fk_congelateur, s.nom as fournisseur,
                       d.rowid as receptiondet_id, d.fk_product, 
                       p.ref as product_ref, p.label as product_label,
                       d.poids_net as quantite_totale,
                       d.poids_plater as quantite_plater
                FROM ".MAIN_DB_PREFIX."pech_receptiondet as d
                INNER JOIN ".MAIN_DB_PREFIX."pech_reception as r ON r.rowid = d.fk_reception
                LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = r.fk_congelateur
                LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = d.fk_product
                WHERE r.etat <> 0 and r.fk_bon_recep > 0" ;

        if ($fk_entrepot > 0) {
            $sql .= " AND r.fk_entrepot = ".((int) $fk_entrepot);
        }
        if ($fk_fournisseur > 0) {
            $sql .= " AND r.fk_congelateur = ".((int) $fk_fournisseur);
        }

        $sql .= " ORDER BY r.rowid DESC";
        $resql = $db->query($sql);

        print '<form method="POST" action="./misenplat_create.php">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';

        print '<div class="selection-section">';
        print '<h3 class="selection-title"><i class="fa fa-list"></i> '.$langs->trans("ProduitsDisponibles").'</h3>';

        if ($resql && $db->num_rows($resql) > 0) {
            print '<div class="table-responsive">';
            print '<table class="selection-table">';
            print '<thead><tr>';
            print '<th width="5%"><i class="fa fa-check"></i> '.$langs->trans("Selection").'</th>';
            print '<th width="15%"><i class="fa fa-truck"></i> '.$langs->trans("Reception").'</th>';
            print '<th width="20%"><i class="fa fa-user"></i> '.$langs->trans("Congelateur").'</th>';
            print '<th width="20%"><i class="fa fa-box"></i> '.$langs->trans("Produit").'</th>';
            print '<th width="12%" class="text-right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("QuantiteTotale").' (kg)</th>';
            print '<th width="12%" class="text-right"><i class="fa fa-check-circle"></i> '.$langs->trans("DejaPlate").' (kg)</th>';
            print '<th width="12%" class="text-right"><i class="fa fa-hourglass-half"></i> '.$langs->trans("ResteAPlater").' (kg)</th>';
            print '<th width="14%" class="center"><i class="fa fa-info-circle"></i> '.$langs->trans("Statut").'</th>';
            print '</tr></thead>';
            print '<tbody>';

            $has_products = false;
            while ($obj = $db->fetch_object($resql)) {
                $deja_plate = (int) $obj->quantite_plater;
                $reste_a_plater = max(0, $obj->quantite_totale - $deja_plate);
                
                // Détermination du statut
                if ($deja_plate <= 0) {
                    $statut = '<span class="selection-badge badge-warning"><i class="fa fa-hourglass-start"></i> '.$langs->trans("EnReception").'</span>';
                } elseif ($deja_plate >= $obj->quantite_totale) {
                    $statut = '<span class="selection-badge badge-success"><i class="fa fa-check-circle"></i> '.$langs->trans("Termine").'</span>';
                } else {
                    $statut = '<span class="selection-badge badge-info"><i class="fa fa-spinner"></i> '.$langs->trans("EnTraitement").'</span>';
                }
                
                if ($reste_a_plater > 0) {
                    $has_products = true;
                    print '<tr>';
                    print '<td class="center"><input type="checkbox" name="selected[]" value="'.$obj->receptiondet_id.'" class="selection-checkbox"></td>';
                    print '<td><strong>'.dol_escape_htmltag($obj->reception_ref).'</strong></td>';
                    print '<td>'.dol_escape_htmltag($obj->fournisseur).'</td>';
                    print '<td>'.dol_escape_htmltag($obj->product_ref.' - '.$obj->product_label).'</td>';
                    print '<td class="text-right"><strong>'.price($obj->quantite_totale, 2, '', 1).' kg</strong></td>';
                    print '<td class="text-right">'.price($deja_plate, 2, '', 1).' kg</td>';
                    print '<td class="text-right"><strong style="color:#e74c3c;">'.price($reste_a_plater, 2, '', 1).' kg</strong></td>';
                    print '<td class="center">'.$statut.'</td>';
                    print '</tr>';
                }
            }

            print '</tbody>';
            print '</table>';
            print '</div>';

            if ($has_products) {
                print '<div class="selection-actions">';
                print '<button type="submit" class="selection-button selection-button-success" name="action_misenplat">';
                print '<i class="fa fa-play"></i> '.$langs->trans("LancerMiseEnPlat");
                print '</button>';
                print '</div>';
            } else {
                print '<div class="selection-empty">';
                print '<i class="fa fa-info-circle"></i>';
                print '<h3>'.$langs->trans("AucunProduitDisponible").'</h3>';
                print '<p>'.$langs->trans("TousLesProduitsTraites").'</p>';
                print '</div>';
            }

        } else {
            print '<div class="selection-empty">';
            print '<i class="fa fa-search"></i>';
            print '<h3>'.$langs->trans("AucuneReceptionTrouvee").'</h3>';
            print '<p>'.$langs->trans("AucuneReceptionFiltre").'</p>';
            print '</div>';
        }

        print '</div>'; // .selection-section
        print '</form>';
    }

print '</div>'; // .selection-container

llxFooter();
$db->close();
?>