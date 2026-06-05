<?php
/**
 * Export Excel de l'état de stock des cartons
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;

$langs->load("abricot@abricot");
$langs->load("stocks");

// Récupération des filtres
$entrepots_selected = GETPOST('entrepots', 'array');
$produits_selected = GETPOST('produits', 'array');
$statut = GETPOST('statut', 'int');
$date_debut = GETPOST('date_debut', 'alpha');
$date_fin = GETPOST('date_fin', 'alpha');

// Valeur par défaut
//if ($statut == '') $statut = 0;

// Vérifier les droits d'accès aux entrepôts
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// Si pas de restriction, prendre tous les entrepôts
if (empty($entrepots_accessibles)) {
    $sql_all_ent = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE entity IN (".getEntity('stock').")";
    $res_all_ent = $db->query($sql_all_ent);
    if ($res_all_ent) {
        while ($obj_all = $db->fetch_object($res_all_ent)) {
            $entrepots_accessibles[] = (int)$obj_all->rowid;
        }
    }
}

// Construction de la requête (même que dans le fichier principal)
$sql = "SELECT 
            p.rowid as product_id,
            p.ref as produit_ref,
            p.label as produit_label,
            COUNT(c.rowid) as nb_carton,
            SUM(c.poids) as poids_total,
            SUM(c.prix_moyen + c.frais) as valeur_total
        FROM ".MAIN_DB_PREFIX."pech_carton c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON c.fk_lotdet = ld.rowid
        INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON ld.fk_lot = l.rowid
        INNER JOIN ".MAIN_DB_PREFIX."product p ON c.fk_product = p.rowid
        WHERE c.entity = ".$conf->entity;

// Filtre par entrepôt(s) accessibles
if (!empty($entrepots_accessibles)) {
    $entrepots_accessibles_list = implode(',', $entrepots_accessibles);
    $sql .= " AND l.fk_entrepot IN (".$entrepots_accessibles_list.")";
}

// Filtres supplémentaires
if ($statut != '') {
    $sql .= " AND c.statut = ".$db->escape($statut);
}

if (!empty($date_debut)) {
    $sql .= " AND DATE(c.date_creation) >= '".$db->escape($date_debut)."'";
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(c.date_creation) <= '".$db->escape($date_fin)."'";
}

// Filtre par entrepôt(s) sélectionné(s)
if (!empty($entrepots_selected) && !in_array('all', $entrepots_selected)) {
    $entrepots_list = implode(',', array_map('intval', $entrepots_selected));
    $sql .= " AND l.fk_entrepot IN (".$entrepots_list.")";
}

// Filtre par produit(s) sélectionné(s)
if (!empty($produits_selected) && !in_array('all', $produits_selected)) {
    $produits_list = implode(',', array_map('intval', $produits_selected));
    $sql .= " AND c.fk_product IN (".$produits_list.")";
}

$sql .= " GROUP BY c.fk_product
          ORDER BY p.ref";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    // Nom du fichier
    $filename = "etat_stock_cartons_" . date("Y-m-d_His") . ".xls";
    
    // Headers pour téléchargement Excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Début du fichier Excel avec style CSS professionnel
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "/* Style général */";
    echo "body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; color: #000000; margin: 20px; line-height: 1.4; }";
    echo "h1, h2, h3, h4 { font-family: 'Calibri Light', 'Arial Narrow', sans-serif; color: #2c3e50; }";
    echo "h1 { font-size: 18pt; font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid #2c3e50; }";
    echo "h2 { font-size: 14pt; font-weight: bold; margin: 25px 0 15px 0; color: #34495e; }";
    echo "h3 { font-size: 12pt; font-weight: bold; margin: 20px 0 10px 0; color: #2c3e50; }";
    echo "";
    echo "/* Tableaux */";
    echo "table { border-collapse: collapse; width: 100%; margin-top: 15px; font-size: 10pt; }";
    echo "th { background-color: #f8f9fa; color: #2c3e50; font-weight: bold; padding: 8px 10px; text-align: left; border: 1px solid #dee2e6; font-size: 10pt; }";
    echo "td { padding: 7px 10px; border: 1px solid #dee2e6; vertical-align: middle; }";
    echo ".total-row { background-color: #f1f8ff; font-weight: bold; }";
    echo ".alt-row { background-color: #f8f9fa; }";
    echo ".summary-table { width: 60%; margin-top: 20px; }";
    echo ".summary-table td { border: none; padding: 5px 10px; }";
    echo ".summary-table tr:first-child td { padding-top: 10px; }";
    echo ".summary-table tr:last-child td { padding-bottom: 10px; }";
    echo "";
    echo "/* Alignements */";
    echo ".text-right { text-align: right; }";
    echo ".text-center { text-align: center; }";
    echo ".text-left { text-align: left; }";
    echo ".number { font-family: 'Consolas', 'Courier New', monospace; text-align: right; }";
    echo ".currency { font-family: 'Consolas', 'Courier New', monospace; text-align: right; }";
    echo "";
    echo "/* Badges et indicateurs */";
    echo ".indicator { background-color: #e9ecef; padding: 4px 10px; border-radius: 3px; font-weight: 600; display: inline-block; min-width: 70px; text-align: center; border: 1px solid #ced4da; }";
    echo ".filter-indicator { background-color: #e9ecef; color: #495057; padding: 4px 8px; border-radius: 3px; font-size: 9pt; display: inline-block; margin: 1px 3px; border: 1px solid #adb5bd; }";
    echo "";
    echo "/* Boîtes d'information */";
    echo ".info-section { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin-bottom: 20px; }";
    echo ".info-section p { margin: 5px 0; }";
    echo "";
    echo "/* Notes et footer */";
    echo ".notes { font-size: 9pt; color: #6c757d; margin-top: 30px; padding-top: 15px; border-top: 1px solid #dee2e6; }";
    echo ".footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 8pt; color: #868e96; text-align: center; }";
    echo ".separator { height: 1px; background-color: #dee2e6; margin: 20px 0; }";
    echo "";
    echo "/* Largeurs de colonnes */";
    echo ".col-ref { width: 20%; }";
    echo ".col-qty { width: 15%; }";
    echo ".col-weight { width: 15%; }";
    echo ".col-value { width: 25%; }";
    echo ".col-pu { width: 15%; }";
    echo ".col-empty { width: 15%; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // En-tête du document
    echo "<div style=\"text-align: center; margin-bottom: 25px;\">";
    echo "<h1>État de Stock des Cartons</h1>";
    echo "<div style=\"color: #6c757d; font-size: 10pt;\">";
    echo "Export généré le " . dol_print_date(time(), 'dayhour');
    echo "</div>";
    echo "</div>";
    
    // Section Informations sur les filtres
    echo "<div class=\"info-section\">";
    echo "<h3 style=\"margin-top: 0;\">Filtres Appliqués</h3>";
    
    // Filtre entrepôts
    if (!empty($entrepots_selected) && !in_array('all', $entrepots_selected)) {
        $selected_entrepots_labels = [];
        $entrepot_ids = implode(',', array_map('intval', $entrepots_selected));
        $sql_ent_labels = "SELECT ref, lieu FROM ".MAIN_DB_PREFIX."entrepot WHERE rowid IN ($entrepot_ids)";
        $res_ent_labels = $db->query($sql_ent_labels);
        if ($res_ent_labels) {
            while ($obj_ent = $db->fetch_object($res_ent_labels)) {
                $selected_entrepots_labels[] = $obj_ent->ref . ($obj_ent->lieu ? ' - ' . $obj_ent->lieu : '');
            }
        }
        echo "<p><strong>Entrepôt(s) :</strong><br>";
        foreach ($selected_entrepots_labels as $label) {
            echo "<span class=\"filter-indicator\">" . $label . "</span> ";
        }
        echo "</p>";
    } else {
        echo "<p><strong>Entrepôt(s) :</strong> Tous les entrepôts</p>";
    }
    
    // Filtre statut
    $status_label = '';
    if ($statut == 0) {
        $status_label = 'En stock';
    } elseif ($statut == 1) {
        $status_label = 'Sorti';
    } else {
        $status_label = 'Tous les statuts';
    }
    echo "<p><strong>Statut :</strong> " . $status_label . "</p>";
    
    // Filtre dates
    if ($date_debut || $date_fin) {
        echo "<p><strong>Période :</strong> ";
        if ($date_debut) echo "Du " . $date_debut . " ";
        if ($date_fin) echo "au " . $date_fin;
        echo "</p>";
    }
    
    // Utilisateur
    echo "<p><strong>Utilisateur :</strong> " . $user->firstname . " " . $user->lastname . "</p>";
    
    echo "</div>";
    
    // Tableau principal des données
    echo "<h2>Détail du Stock par Produit</h2>";
    echo "<table>";
    
    // En-têtes du tableau
    echo "<thead>";
    echo "<tr>";
    echo "<th class=\"col-ref\">Référence Produit</th>";
    echo "<th class=\"col-qty text-center\">Nombre de Cartons</th>";
    echo "<th class=\"col-weight text-right\">Poids Total (kg)</th>";
    echo "<th class=\"col-value text-right\">Valeur Totale (" . $conf->currency . ")</th>";
    echo "<th class=\"col-pu text-right\">Prix Unitaire Moyen (" . $conf->currency . "/carton)</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    // Variables pour les totaux
    $total_cartons = 0;
    $total_poids = 0;
    $total_valeur = 0;
    $i = 0;
    
    while ($obj = $db->fetch_object($resql)) {
        $i++;
        // Calcul avec arrondi à 2 décimales
        $pu_carton = ($obj->nb_carton > 0) ? round($obj->valeur_total / $obj->nb_carton, 2) : 0;
        
        // Alternance des lignes pour meilleure lisibilité
        $row_class = ($i % 2 == 0) ? 'alt-row' : '';
        
        echo "<tr class=\"" . $row_class . "\">";
        echo "<td><strong>" . $obj->produit_ref . "</strong></td>";
        echo "<td class=\"text-center\"><span class=\"indicator\">" . number_format($obj->nb_carton, 0, '', ' ') . "</span></td>";
        echo "<td class=\"number\">" . number_format($obj->poids_total, 2, ',', ' ') . "</td>";
        echo "<td class=\"currency\"><strong>" . number_format($obj->valeur_total, 2, ',', ' ') . "</strong></td>";
        echo "<td class=\"currency\"><span class=\"indicator\">" . number_format($pu_carton, 2, ',', ' ') . "</span></td>";
        echo "</tr>";
        
        $total_cartons += $obj->nb_carton;
        $total_poids += $obj->poids_total;
        $total_valeur += $obj->valeur_total;
    }
    
    // Ligne des totaux
    if ($i > 0) {
        $pu_moyen_total = ($total_cartons > 0) ? round($total_valeur / $total_cartons, 2) : 0;
        $moyenne_poids = ($total_cartons > 0) ? round($total_poids / $total_cartons, 2) : 0;
        $moyenne_valeur = ($total_cartons > 0) ? round($total_valeur / $total_cartons, 2) : 0;
        
        echo "<tr class=\"total-row\">";
        echo "<td><strong>TOTAUX</strong></td>";
        echo "<td class=\"text-center\"><strong><span class=\"indicator\" style=\"background-color: #e3f2fd; border-color: #90caf9;\">" . number_format($total_cartons, 0, '', ' ') . "</span></strong></td>";
        echo "<td class=\"number\"><strong>" . number_format($total_poids, 2, ',', ' ') . " kg</strong></td>";
        echo "<td class=\"currency\"><strong>" . number_format($total_valeur, 2, ',', ' ') . "</strong></td>";
        echo "<td class=\"currency\"><strong><span class=\"indicator\" style=\"background-color: #e3f2fd; border-color: #90caf9;\">" . number_format($pu_moyen_total, 2, ',', ' ') . "</span></strong></td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    
    // Séparateur
    echo "<div class=\"separator\"></div>";
    
    // Tableau des statistiques récapitulatives
    if ($i > 0) {
        echo "<h2>Statistiques Récapitulatives</h2>";
        echo "<table class=\"summary-table\">";
        
        echo "<tr>";
        echo "<td width=\"70%\"><strong>Nombre total de cartons :</strong></td>";
        echo "<td width=\"30%\" class=\"text-right\"><strong>" . number_format($total_cartons, 0, '', ' ') . "</strong></td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td><strong>Poids total :</strong></td>";
        echo "<td class=\"text-right\"><strong>" . number_format($total_poids, 2, ',', ' ') . " kg</strong></td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td><strong>Valeur totale :</strong></td>";
        echo "<td class=\"text-right\"><strong>" . number_format($total_valeur, 2, ',', ' ') . " " . $conf->currency . "</strong></td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td><strong>Prix unitaire moyen :</strong></td>";
        echo "<td class=\"text-right\"><strong>" . number_format($pu_moyen_total, 2, ',', ' ') . " " . $conf->currency . "/carton</strong></td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td><strong>Poids moyen par carton :</strong></td>";
        echo "<td class=\"text-right\"><strong>" . number_format($moyenne_poids, 2, ',', ' ') . " kg/carton</strong></td>";
        echo "</tr>";

        
        echo "<tr>";
        echo "<td><strong>Nombre de produits différents :</strong></td>";
        echo "<td class=\"text-right\"><strong>" . $i . "</strong></td>";
        echo "</tr>";
        
        
        echo "</table>";
    }
 
    
    // Pied de page
    echo "<div class=\"footer\">";
    if (!empty($conf->global->MAIN_INFO_SOCIETE_NOM)) {
        echo $conf->global->MAIN_INFO_SOCIETE_NOM . " • ";
    }
    echo "Page 1/1 • Document confidentiel • " . date('d/m/Y H:i:s');
    echo "</div>";
    
    echo "</body>";
    echo "</html>";
    
} else {
    // Si aucune donnée
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "body { font-family: 'Calibri', 'Arial', sans-serif; padding: 50px; text-align: center; }";
    echo ".message-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 40px; display: inline-block; max-width: 500px; }";
    echo ".message-icon { color: #6c757d; font-size: 36px; margin-bottom: 15px; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    echo "<div class=\"message-box\">";
    echo "<div class=\"message-icon\">📭</div>";
    echo "<h3 style=\"color: #495057;\">Aucune donnée à exporter</h3>";
    echo "<p>Aucun carton trouvé avec les critères de filtrage sélectionnés.</p>";
    echo "<p style=\"margin-top: 20px;\">";
    echo "<a href=\"javascript:history.back()\" style=\"color: #007bff; text-decoration: none;\">";
    echo "← Retour à la page précédente";
    echo "</a>";
    echo "</p>";
    echo "</div>";
    echo "</body>";
    echo "</html>";
}

$db->close();
?>