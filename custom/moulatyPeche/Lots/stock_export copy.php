<?php
/**
 * Export Excel de l'état de stock des cartons
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");

// Récupération des filtres
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');
$ref_lot     = GETPOST('ref_lot', 'alpha');

// Vérifier les droits d'accès
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// Requête SQL POUR LES CALCULS EXACTS
$sql = "
SELECT 
    -- Informations lot
    l.rowid AS lot_id,
    l.ref AS lot_ref,
    l.date_creation AS lot_date,
    l.total_frais AS lot_frais_total,
    
    -- Informations entrepôt
    e.rowid AS entrepot_id,
    e.ref AS entrepot_ref,
    e.lieu AS entrepot_lieu,
    
    -- Informations produit
    p.rowid AS product_id,
    p.label AS product_label,
    p.ref AS product_ref,
    p.price AS product_price_std,
    
    -- Détails lotdet
    ld.rowid AS lotdet_id,
    ld.prix AS prix_unit_lotdet,
    ld.taux_rendement AS taux_rendement,
    
    -- Statistiques cartons par lotdet
    COUNT(c.rowid) AS total_cartons_tout,
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS cartons_stock,
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS cartons_sortis,
    
    -- Poids
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortis,
    
    -- CALCUL 1: Valeur des cartons en STOCK
    SUM(CASE WHEN c.statut = 0 THEN c.prix_moyen ELSE 0 END) AS prix_moyen_stock,
    SUM(CASE WHEN c.statut = 0 THEN c.frais ELSE 0 END) AS frais_cartons_stock,
    
    -- CALCUL 2: Valeur de TOUS les cartons
    SUM(CASE WHEN c.statut IN (0,1) THEN c.prix_moyen ELSE 0 END) AS prix_moyen_tout,
    SUM(CASE WHEN c.statut IN (0,1) THEN c.frais ELSE 0 END) AS frais_cartons_tout
    
FROM ".MAIN_DB_PREFIX."pech_lot AS l
INNER JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.fk_lot = l.rowid
INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = ld.fk_product
INNER JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.fk_lotdet = ld.rowid
";

$where = [];
if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "p.rowid = ".(int)$fk_product;
if (!empty($ref_lot)) $where[] = "l.ref LIKE '%".$db->escape($ref_lot)."%'";

// Ajouter restriction sur les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $where[] = "l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

// Filtrer uniquement les lots qui ont des cartons en stock
$where[] = "EXISTS (
    SELECT 1 FROM ".MAIN_DB_PREFIX."pech_carton c2 
    WHERE c2.fk_lotdet = ld.rowid AND c2.statut = 0
)";

if (count($where) > 0) {
    $sql .= " WHERE ".implode(" AND ", $where);
}

$sql .= " 
GROUP BY l.rowid, l.ref, l.date_creation, l.total_frais, 
         e.rowid, e.ref, e.lieu,
         p.rowid, p.label, p.ref, p.price,
         ld.rowid, ld.prix, ld.taux_rendement
HAVING cartons_stock > 0
ORDER BY e.ref, l.ref, p.label
";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    // Créer le contenu Excel
    $filename = "stock_cartons_export_" . date("Y-m-d_His") . ".xls";
    
    // Headers pour téléchargement Excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Début du fichier Excel
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th { background-color: #4CAF50; color: white; font-weight: bold; padding: 8px; text-align: left; border: 1px solid #ddd; }";
    echo "td { padding: 6px; text-align: left; border: 1px solid #ddd; }";
    echo ".total-row { background-color: #f2f2f2; font-weight: bold; }";
    echo ".subtotal-row { background-color: #e8f4f8; }";
    echo ".lot-header { background-color: #d9edf7; font-weight: bold; }";
    echo ".money { text-align: right; }";
    echo ".number { text-align: right; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Titre et informations
    echo "<h1>État de stock des cartons - Valeur réelle du stock</h1>";
    echo "<p><strong>Date d'export :</strong> " . date("d/m/Y H:i:s") . "</p>";
    echo "<p><strong>Utilisateur :</strong> " . $user->firstname . " " . $user->lastname . "</p>";
    
    if ($fk_entrepot > 0) {
        $entrepot = new Entrepot($db);
        $entrepot->fetch($fk_entrepot);
        echo "<p><strong>Entrepôt :</strong> " . $entrepot->ref . ($entrepot->lieu ? " - " . $entrepot->lieu : "") . "</p>";
    }
    
    if ($fk_product > 0) {
        $product = new Product($db);
        $product->fetch($fk_product);
        echo "<p><strong>Produit :</strong> " . $product->label . "</p>";
    }
    
    if (!empty($ref_lot)) {
        echo "<p><strong>Filtre lot :</strong> " . dol_escape_htmltag($ref_lot) . "</p>";
    }
    
    echo "<br>";
    
    // Tableau principal
    echo "<table>";
    
    // En-têtes du tableau
    echo "<thead>";
    echo "<tr>";
    echo "<th>Entrepôt</th>";
    echo "<th>Lieu</th>";
    echo "<th>Réf. Lot</th>";
    echo "<th>Date Lot</th>";
    echo "<th>Produit</th>";
    echo "<th>Réf. Produit</th>";
    //echo "<th>Prix Unit. (€)</th>";
    //echo "<th>Taux Rendement (%)</th>";
    echo "<th>Cartons Stock</th>";
    echo "<th>Poids Stock (kg)</th>";
    echo "<th>Cartons Sortis</th>";
    echo "<th>Poids Sortis (kg)</th>";
    echo "<th>Valeur Stock ($conf->currency)</th>";
    echo "<th>Valeur Complète ($conf->currency)</th>";
    echo "<th>Frais Lot ($conf->currency)</th>";
    echo "<th>Valeur Moyenne/Carton ($conf->currency)</th>";
    //echo "<th>Prix Moyen Stock (€)</th>";
    //echo "<th>Frais Cartons Stock (€)</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    // Variables pour les totaux
    $total_cartons_stock = 0;
    $total_poids_stock = 0;
    $total_cartons_sortis = 0;
    $total_poids_sortis = 0;
    $total_valeur_stock = 0;
    $total_valeur_complete = 0;
    $total_frais_lots = 0;
    $current_lot = null;
    $lot_counter = 0;
    
    while ($obj = $db->fetch_object($resql)) {
        // Calculs
        $valeur_stock = $obj->prix_moyen_stock + $obj->frais_cartons_stock;
        $valeur_complete = $obj->prix_moyen_tout + $obj->frais_cartons_tout;
        $valeur_moyenne = ($obj->cartons_stock > 0) ? $valeur_stock / $obj->cartons_stock : 0;
        
        // Nouveau lot ?
        if ($current_lot != $obj->lot_ref) {
            $current_lot = $obj->lot_ref;
            $lot_counter++;
           $lot_frais_deja_compte = false; // AJOUTER CETTE VARIABLE
        
        $total_frais_lots += $obj->lot_frais_total;
            // Ligne de séparation pour nouveau lot
            if ($lot_counter > 1) {
                echo "<tr><td colspan=\"14\" style=\"height: 5px; background-color: #ccc;\"></td></tr>";
            }
        }
        
        echo "<tr>";
        echo "<td>" . $obj->entrepot_ref . "</td>";
        echo "<td>" . ($obj->entrepot_lieu ?: '') . "</td>";
        echo "<td>" . $obj->lot_ref . "</td>";
        echo "<td>" . dol_print_date($obj->lot_date, '%d/%m/%Y') . "</td>";
        echo "<td>" . $obj->product_label . "</td>";
        echo "<td>" . $obj->product_ref . "</td>";
        //echo "<td class=\"money\">" . price($obj->prix_unit_lotdet) . "</td>";
        //echo "<td class=\"number\">" . ($obj->taux_rendement ? price($obj->taux_rendement) : '') . "</td>";
        echo "<td class=\"number\">" . $obj->cartons_stock . "</td>";
        echo "<td class=\"number\">" . price($obj->poids_stock) . "</td>";
        echo "<td class=\"number\">" . $obj->cartons_sortis . "</td>";
        echo "<td class=\"number\">" . price($obj->poids_sortis) . "</td>";
        echo "<td class=\"money\">" . price($valeur_stock) . "</td>";
        echo "<td class=\"money\">" . price($valeur_complete) . "</td>";
        //echo "<td class=\"money\">" . price($obj->lot_frais_total) . "</td>";
        // CORRECTION ICI : Afficher les frais seulement sur la première ligne du lot
        if (!$lot_frais_deja_compte) {
            echo "<td class=\"money\">" . price($obj->lot_frais_total) . "</td>";
            $lot_frais_deja_compte = true;
        } else {
            echo "<td class=\"money\"></td>"; // Cellule vide pour les autres lignes
        }
        echo "<td class=\"money\">" . price($valeur_moyenne) . "</td>";
        //echo "<td class=\"money\">" . price($obj->prix_moyen_stock) . "</td>";
        //echo "<td class=\"money\">" . price($obj->frais_cartons_stock) . "</td>";
        echo "</tr>";
        
        // Ajouter aux totaux
        $total_cartons_stock += $obj->cartons_stock;
        $total_poids_stock += $obj->poids_stock;
        $total_cartons_sortis += $obj->cartons_sortis;
        $total_poids_sortis += $obj->poids_sortis;
        $total_valeur_stock += $valeur_stock;
        $total_valeur_complete += $valeur_complete;
    }
    
    // Ligne des totaux
    echo "<tr class=\"total-row\">";
    echo "<td colspan=\"6\"><strong>TOTAUX GÉNÉRAUX</strong></td>";
    echo "<td class=\"number\"><strong>" . $total_cartons_stock . "</strong></td>";
    echo "<td class=\"number\"><strong>" . price($total_poids_stock) . "</strong></td>";
    echo "<td class=\"number\"><strong>" . $total_cartons_sortis . "</strong></td>";
    echo "<td class=\"number\"><strong>" . price($total_poids_sortis) . "</strong></td>";
    echo "<td class=\"money\"><strong>" . price($total_valeur_stock) . "</strong></td>";
    echo "<td class=\"money\"><strong>" . price($total_valeur_complete) . "</strong></td>";
    echo "<td class=\"money\"><strong>" . price($total_frais_lots) . "</strong></td>";
    echo "<td class=\"money\"><strong>" . ($total_cartons_stock > 0 ? price($total_valeur_stock / $total_cartons_stock) : '0.00') . "</strong></td>";
    //echo "<td colspan=\"2\"></td>";
    echo "</tr>";
    
    echo "</tbody>";
    echo "</table>";
    
    // Tableau des statistiques récapitulatives
    echo "<br><br>";
    echo "<h2>Statistiques Récapitulatives</h2>";
    echo "<table style=\"width: 80%;\">";
    
    $total_cartons = $total_cartons_stock + $total_cartons_sortis;
    $total_poids = $total_poids_stock + $total_poids_sortis;
    $taux_sortie = ($total_cartons > 0) ? ($total_cartons_sortis / $total_cartons * 100) : 0;
    
    echo "<tr>";
    echo "<td>Total cartons (stock + sortis):</td>";
    echo "<td><strong>" . $total_cartons . "</strong></td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>Total poids (stock + sortis):</td>";
    echo "<td><strong>" . price($total_poids) . " kg</strong></td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>Cartons en stock:</td>";
    echo "<td><strong>" . $total_cartons_stock . " (" . round(100 - $taux_sortie, 1) . "%)</strong></td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>Cartons sortis:</td>";
    echo "<td><strong>" . $total_cartons_sortis . " (" . round($taux_sortie, 1) . "%)</strong></td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>Poids moyen par carton (stock):</td>";
    echo "<td><strong>" . ($total_cartons_stock > 0 ? price($total_poids_stock / $total_cartons_stock) : '0.00') . " kg</strong></td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>Valeur moyenne par carton (stock):</td>";
    echo "<td><strong>" . ($total_cartons_stock > 0 ? price($total_valeur_stock / $total_cartons_stock) : '0.00') . " ($conf->currency)</strong></td>";
    echo "</tr>";
   
    
    echo "</table>";
    
    // Notes et informations complémentaires
    echo "<br><br>";
    echo "<h3>Notes:</h3>";
    echo "<ul>";
    echo "<li><strong>Valeur Stock:</strong> Valeur des cartons actuellement en stock (statut = 0)</li>";
    echo "<li><strong>Valeur Complète:</strong> Valeur de tous les cartons du lot (stock + sortis)</li>";
    //echo "<li><strong>Prix Unit.:</strong> Prix unitaire du produit dans le lot dét</li>";
    echo "<li><strong>Frais Lot:</strong> Frais totaux affectés au lot</li>";
    //echo "<li><strong>Frais Cartons Stock:</strong> Part des frais affectée aux cartons en stock</li>";
    echo "<li>Export généré le " . date("d/m/Y à H:i:s") . "</li>";
    echo "</ul>";
    
    echo "</body>";
    echo "</html>";
    
} else {
    // Si aucune donnée
    dol_syslog("Aucune donnée à exporter pour les critères sélectionnés");
    header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=no_data");
}

$db->close();
?>