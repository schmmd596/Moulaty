<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main', 'other']);
$form = new Form($db);

// ============================================================================
// Paramètres
// ============================================================================
$id_bon = GETPOST('id', 'int');
if ($id_bon <= 0) {
    setEventMessages($langs->trans("Identifiant du bon invalide."), null, 'errors');
    header("Location: detail_mis.php?id='.$id_bon.'");
    exit;
}

// ============================================================================
// Récupération du bon
// ============================================================================
$sql_bon = "SELECT b.rowid, b.ref, b.fk_receptiondet, b.fk_entrepot, b.commentaire, b.statut,
                   e.ref as entrepot_ref, e.ref as entrepot_label
            FROM ".MAIN_DB_PREFIX."pech_bon_misenplat AS b
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
            WHERE b.rowid = ".((int)$id_bon);

$res_bon = $db->query($sql_bon);
if (!$res_bon || $db->num_rows($res_bon) == 0) {
    setEventMessages($langs->trans("Bon introuvable."), null, 'errors');
    header("Location:  detail_mis.php?id='.$id_bon.'");
    exit;
}
$bon = $db->fetch_object($res_bon);

// ============================================================================
// Récupération des lignes de misenplat existantes
// ============================================================================
$lignes_misenplat = [];
$sql_misenplat = "SELECT m.rowid as misenplat_id, m.fk_product, m.nombre_plat, m.poids_plat, 
                         m.nombre_plat_sortie, m.statut,
                         p.ref as product_ref, p.label as product_label
                  FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
                  LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
                  WHERE m.fk_bon_misenplat = ".((int)$id_bon)."
                  ORDER BY p.ref";

$res_misenplat = $db->query($sql_misenplat);
if ($res_misenplat && $db->num_rows($res_misenplat) > 0) {
    while ($obj = $db->fetch_object($res_misenplat)) {
        $lignes_misenplat[$obj->fk_product] = $obj;
    }
}

// ============================================================================
// Récupération des produits disponibles dans les réceptions
// ============================================================================
$produits_disponibles = [];

if (!empty($bon->fk_receptiondet)) {
    // Récupérer les IDs des lignes de réception
    $receptiondet_ids = array_filter(array_map('intval', explode(',', $bon->fk_receptiondet)));
    
    if (!empty($receptiondet_ids)) {
        // Récupérer les produits avec leur disponibilité totale
        $sql_produits = "SELECT d.fk_product, 
                                SUM(d.poids_net) as poids_total,
                                SUM(d.poids_plater) as poids_deja_plate,
                                p.ref as product_ref, 
                                p.label as product_label
                         FROM ".MAIN_DB_PREFIX."pech_receptiondet AS d
                         LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
                         WHERE d.rowid IN (".implode(',', $receptiondet_ids).")
                         GROUP BY d.fk_product
                         ORDER BY p.ref";

        $res_produits = $db->query($sql_produits);
        if ($res_produits && $db->num_rows($res_produits) > 0) {
            while ($obj = $db->fetch_object($res_produits)) {
                $produits_disponibles[$obj->fk_product] = [
                    'ref' => $obj->product_ref,
                    'label' => $obj->product_label,
                    'poids_total' => $obj->poids_total,
                    'poids_deja_plate' => $obj->poids_deja_plate,
                    'poids_disponible' => $obj->poids_total - $obj->poids_deja_plate
                ];
            }
        }
    }
}

// ============================================================================
// Combinaison des données pour l'affichage
// ============================================================================
$produits_a_afficher = [];

// D'abord, les produits déjà dans le bon
foreach ($lignes_misenplat as $fk_product => $ligne) {
    $disponible = isset($produits_disponibles[$fk_product]) ? 
                  $produits_disponibles[$fk_product]['poids_disponible'] : 0;
    
    $produits_a_afficher[$fk_product] = [
        'ref' => $ligne->product_ref,
        'label' => $ligne->product_label,
        'nombre_plat' => $ligne->nombre_plat,
        'poids_plat' => $ligne->poids_plat,
        'poids_total' => isset($produits_disponibles[$fk_product]) ? 
                         $produits_disponibles[$fk_product]['poids_total'] : 0,
        'poids_deja_plate' => isset($produits_disponibles[$fk_product]) ? 
                              $produits_disponibles[$fk_product]['poids_deja_plate'] : 0,
        'poids_disponible' => $disponible + $ligne->poids_plat, // On ajoute le poids déjà dans le bon
        'existe_dans_bon' => true,
        'misenplat_id' => $ligne->misenplat_id
    ];
}

// Ensuite, les produits disponibles mais pas encore dans le bon
foreach ($produits_disponibles as $fk_product => $produit) {
    if (!isset($produits_a_afficher[$fk_product])) {
        $produits_a_afficher[$fk_product] = [
            'ref' => $produit['ref'],
            'label' => $produit['label'],
            'nombre_plat' => 0,
            'poids_plat' => 0,
            'poids_total' => $produit['poids_total'],
            'poids_deja_plate' => $produit['poids_deja_plate'],
            'poids_disponible' => $produit['poids_disponible'],
            'existe_dans_bon' => false,
            'misenplat_id' => 0
        ];
    }
}

// ============================================================================
// Affichage
// ============================================================================
llxHeader('', $langs->trans("ModifyPlatingBon"));

print '
<style>
.misenplat-container {
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
}

.misenplat-header {
    background: #f0f7ff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.misenplat-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 24px;
    font-weight: 600;
}

.misenplat-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin: 15px 0;
}

.misenplat-table th {
    background: #e9ecef;
    color: #495057;
    padding: 12px 8px;
    font-weight: 600;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.misenplat-table th.right {
    text-align: right;
}

.misenplat-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e9ecef;
}

.misenplat-table td.right {
    text-align: right;
}

.misenplat-table tr:hover {
    background: #f8f9fa;
}

.quantity-input {
    padding: 6px 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    text-align: right;
    font-size: 14px;
    width: 100px;
}

.quantity-input:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.actions-section {
    text-align: center;
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #007bff;
    color: white;
}

.btn-secondary:hover {
    background: #0056b3;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
}

.warehouse-info {
    background: #e8f4fd;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.bon-info {
    background: #fff3cd;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #ffc107;
}

.new-product-row {
    background: #f8f9fa !important;
    font-style: italic;
}

.existing-product-row {
    background: #fff !important;
}

.delete-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .misenplat-container {
        padding: 10px;
    }
    
    .misenplat-table {
        font-size: 12px;
    }
    
    .misenplat-table th,
    .misenplat-table td {
        padding: 8px 6px;
    }
    
    .quantity-input {
        width: 70px;
    }
}
</style>
';

print '<div class="misenplat-container">';
print '<div class="misenplat-header">';
print '<h1><i class="fa fa-edit"></i> '.$langs->trans("ModifyPlatingBon").' : '.dol_escape_htmltag($bon->ref).'</h1>';
print '</div>';

// Informations du bon
print '<div class="bon-info">';
print '<strong><i class="fa fa-info-circle"></i> '.$langs->trans("BonInformation").' :</strong><br>';
print '<strong>'.$langs->trans("Ref").' :</strong> '.dol_escape_htmltag($bon->ref).'<br>';
//print '<strong>'.$langs->trans("DateCreation").' :</strong> '.dol_print_date($bon->date_creation, 'dayhour').'<br>';
print '<strong>'.$langs->trans("Warehouse").' :</strong> '.dol_escape_htmltag($bon->entrepot_ref);
print '</div>';

// Information sur les réceptions
if (!empty($bon->fk_receptiondet)) {
    print '<div class="warehouse-info">';
    print '<strong><i class="fa fa-list"></i> '.$langs->trans("ReceptionLines").' :</strong> ';
    $receptiondet_ids = array_filter(array_map('intval', explode(',', $bon->fk_receptiondet)));
    print count($receptiondet_ids).' '.$langs->trans("lines");
    print '</div>';
}

print '<form method="POST" action="./edit_trait.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id_bon" value="'.$id_bon.'">';

print '<table class="misenplat-table">';
print '<thead>';
print '<tr>';
print '<th>'.$langs->trans("Product").'</th>';
print '<th class="right">'.$langs->trans("TotalWeight").' (kg)</th>';
print '<th class="right">'.$langs->trans("AlreadyPlated").' (kg)</th>';
print '<th class="right">'.$langs->trans("AvailableWeight").' (kg)</th>';
print '<th class="right">'.$langs->trans("QuantityToPlate").' (kg)</th>';
print '<th class="right">'.$langs->trans("NumberOfPlates").'</th>';
//rint '<th class="center">'.$langs->trans("Delete").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

foreach ($produits_a_afficher as $fk_product => $prod) {
    $row_class = $prod['existe_dans_bon'] ? 'existing-product-row' : 'new-product-row';
    
    print '<tr class="'.$row_class.'">';
    
    // Produit
    print '<td>';
    if (!$prod['existe_dans_bon']) {
        print '<input type="checkbox" name="add_product['.$fk_product.']" value="1" class="delete-checkbox"> ';
    }
    print '<strong>'.dol_escape_htmltag($prod['ref'].' - '.$prod['label']).'</strong>';
    print '</td>';
    
    // Poids total
    print '<td class="right">'.price($prod['poids_total'], 2, '', 1).' kg</td>';
    
    // Déjà platé
    print '<td class="right">'.price($prod['poids_deja_plate'], 2, '', 1).' kg</td>';
    
    // Disponible (en tenant compte de ce qui est déjà dans le bon)
    $disponible_max =$prod['poids_total'] - $prod['poids_deja_plate'] ;// $prod['poids_disponible'];
    print '<td class="right"><strong>'.price($disponible_max, 2, '', 1).' kg</strong></td>';
    
    // Quantité à plater
    print '<td class="right">';
    if ($prod['existe_dans_bon']) {
        print '<input type="hidden" name="misenplat_id['.$fk_product.']" value="'.$prod['misenplat_id'].'">';
    }
    print '<input type="number" step="0.01" name="qte_plater['.$fk_product.']" 
           value="'.price2num($prod['poids_plat'] * $prod['nombre_plat']).'" 
           min="0" max="'.$disponible_max.'" 
           class="quantity-input">';
    print '</td>';
    
    // Nombre de plats
    print '<td class="right">';
    print '<input type="number" name="nb_plat['.$fk_product.']" 
           value="'.(int)$prod['nombre_plat'].'" 
           min="0" class="quantity-input">';
    print '</td>';
    
    // Case à cocher pour suppression (seulement pour les produits existants)
   /* print '<td class="center">';
    if ($prod['existe_dans_bon']) {
        print '<input type="checkbox" name="delete_product['.$fk_product.']" value="1" class="delete-checkbox">';
    } else {
        print '&nbsp;';
    }
    print '</td>';*/
    
    print '</tr>';
}

if (empty($produits_a_afficher)) {
    print '<tr>';
    print '<td colspan="7" class="center">';
    print $langs->trans("NoProductsAvailableForThisBon");
    print '</td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';

// Commentaire
print '<div class="warehouse-info">';
print '<strong><i class="fa fa-comment"></i> '.$langs->trans("Comments").' :</strong><br>';
print '<textarea name="commentaire" class="commentaire-textarea" style="width:100%; min-height:80px; margin-top:10px;">';
print dol_escape_htmltag($bon->commentaire);
print '</textarea>';
print '</div>';

// Actions
print '<div class="actions-section">';
print '<input type="submit" class="btn btn-primary" name="action_update" value="'.$langs->trans("UpdateBon").'">';
print '&nbsp;&nbsp;';
print '<a class="btn btn-secondary"  href="./detail_mis.php?id='.$id_bon.'">'.$langs->trans("ViewBon").'</a>';
print '&nbsp;&nbsp;';
print '<a class="btn btn-cancel" href="./detail_mis.php?id='.$id_bon.'">'.$langs->trans("Cancel").'</a>';
print '</div>';

print '</form>';
print '</div>';

llxFooter();
$db->close();