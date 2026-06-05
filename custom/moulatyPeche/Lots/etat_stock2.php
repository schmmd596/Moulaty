<?php
/**
 * État de stock des cartons - Valeurs claires et précises
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;

$langs->load("abricot@abricot");
$langs->load("stocks");
$form = new Form($db);

// Traductions
$langs->loadLangs(array("products", "stocks", "companies"));

// Récupération des filtres
$entrepots_selected = GETPOST('entrepots', 'array');
$produits_selected = GETPOST('produits', 'array');
$statut = GETPOST('statut', 'int');
$date_debut = GETPOST('date_debut', 'alpha');
$date_fin = GETPOST('date_fin', 'alpha');

// Valeur par défaut pour statut
if ($statut === '') $statut = 0;

// Entrepôts accessibles par l'utilisateur
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// Si l'utilisateur n'a pas de restriction d'entrepôt, on prend tous les entrepôts
if (empty($entrepots_accessibles)) {
    $sql_all_ent = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE entity IN (".getEntity('stock').")";
    $res_all_ent = $db->query($sql_all_ent);
    if ($res_all_ent) {
        while ($obj_all = $db->fetch_object($res_all_ent)) {
            $entrepots_accessibles[] = (int)$obj_all->rowid;
        }
    }
}

// Récupération des entrepôts avec restriction d'accès
$entrepots = [];
if (!empty($entrepots_accessibles)) {
    $entrepots_list = implode(',', $entrepots_accessibles);
    $sql_entrepots = "SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot 
                      WHERE rowid IN (".$entrepots_list.")
                      ORDER BY ref ASC";
    $resql = $db->query($sql_entrepots);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $entrepots[$obj->rowid] = $obj->ref.(($obj->lieu)?' - '.$obj->lieu:'');
        }
    }
}

// Récupération des produits (poissons uniquement)
$produits = [];
$sqlp = "SELECT p.rowid, p.ref, p.label 
         FROM ".MAIN_DB_PREFIX."product p
         INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
         INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
         WHERE c.label = 'POISSON'
         AND p.entity IN (".getEntity('product').")
         ORDER BY p.ref ASC";

$resqlp = $db->query($sqlp);
if ($resqlp) {
    while ($obj = $db->fetch_object($resqlp)) {
        $produits[$obj->rowid] = $obj->ref . ' - ' . $obj->label;
    }
}

// Construction de la requête (regroupement par PRODUIT uniquement)
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

// Filtre par entrepôt(s) sélectionné(s) - Multiple
if (!empty($entrepots_selected) && !in_array('all', $entrepots_selected)) {
    $entrepots_list = implode(',', array_map('intval', $entrepots_selected));
    $sql .= " AND l.fk_entrepot IN (".$entrepots_list.")";
}
// Filtre par produit(s) sélectionné(s) - Multiple
if (!empty($produits_selected) && !in_array('all', $produits_selected)) {
    $produits_list = implode(',', array_map('intval', $produits_selected));
    $sql .= " AND c.fk_product IN (".$produits_list.")";
}

$sql .= " GROUP BY c.fk_product
          ORDER BY p.ref";

// Exécution de la requête
$resql = $db->query($sql);
$total_poids = 0;
$total_valeur = 0;
$total_cartons = 0;

// CSS personnalisé
print '<style>
    .filter-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-bottom: 30px;
    }
    
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        padding: 5px !important;
        min-height: 42px !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #3b82f6 !important;
        color: white !important;
        border: none !important;
        border-radius: 6px !important;
        padding: 3px 8px !important;
        margin-right: 5px !important;
        margin-top: 3px !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white !important;
        margin-right: 5px !important;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6 !important;
        color: white !important;
    }
    
    .select2-container--default .select2-dropdown {
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    }
    
    .date-input {
        padding: 10px 12px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        width: 100% !important;
        font-size: 14px !important;
        transition: border-color 0.2s !important;
    }
    
    .date-input:focus {
        outline: none !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }
    
    .filter-label {
        font-weight: 600 !important;
        color: #374151 !important;
        margin-bottom: 8px !important;
        display: block !important;
    }
    
    .filter-row {
        margin-bottom: 20px !important;
    }
    
    .filter-buttons {
        display: flex !important;
        gap: 10px !important;
        justify-content: center !important;
        margin-top: 25px !important;
        padding-top: 20px !important;
        border-top: 1px solid #e5e7eb !important;
    }
    
    .button-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white !important;
        border: none !important;
        padding: 12px 24px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
    }
    
    .button-primary:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
    }
    
    .button-secondary {
        background: #f3f4f6 !important;
        color: #374151 !important;
        border: 1px solid #d1d5db !important;
        padding: 12px 24px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
    }
    
    .button-secondary:hover {
        background: #e5e7eb !important;
        transform: translateY(-1px) !important;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .table-hover tbody tr:hover {
        background-color: #f8fafc;
        transition: background-color 0.2s;
    }
</style>';

// Affichage de l'entête
llxHeader('', $langs->trans("StockCartonsReport"));

// Charger Select2 si disponible dans Dolibarr, sinon inclure localement
print '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
print '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
print '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/fr.js"></script>';

print '<div class="stat-card">';
print '<div class="text-center">';
print '<h3 class="stat-title">'.$langs->trans("StockCartonsReport").'</h3>';
print '<p class="opacity-80">'.$langs->trans("FilterAndViewStock").'</p>';
print '</div>';
print '</div>';

// Formulaire de filtres
print '<div class="fichecenter">';
print '<div class="fichethirdlef">';
print '<div class="filter-card">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="form-horizontal" id="filterForm">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Filters").'</td></tr>';

// Filtre par entrepôt (avec Select2)
print '<tr><td><label class="filter-label"><i class="fas fa-warehouse"></i> '.$langs->trans("Warehouses").'</label></td><td>';
print '<div class="filter-row">';
print '<select name="entrepots[]" multiple class="select2-multiselect" id="entrepots" data-placeholder="'.$langs->trans("SelectWarehouses").'">';
foreach ($entrepots as $id => $label) {
    $selected = (!empty($entrepots_selected) && in_array($id, $entrepots_selected)) ? ' selected' : '';
    print '<option value="'.$id.'"'.$selected.'>'.$label.'</option>';
}
print '</select>';
print '<div class="small opacitymedium" style="margin-top: 5px;">';
print '<a href="#" onclick="$(\'#entrepots\').val(null).trigger(\'change\'); return false;" class="link-action">'.$langs->trans("ClearSelection").'</a>';
print ' | <a href="#" onclick="selectAllOptions(\'entrepots\'); return false;" class="link-action">'.$langs->trans("SelectAll").'</a>';
print '</div>';
print '</div>';
print '</td></tr>';

// Filtre par produit (avec Select2)
print '<tr><td><label class="filter-label"><i class="fas fa-box"></i> '.$langs->trans("Products").'</label></td><td>';
print '<div class="filter-row">';
print '<select name="produits[]" multiple class="select2-multiselect" id="produits" data-placeholder="'.$langs->trans("SelectProducts").'">';
foreach ($produits as $id => $label) {
    $selected = (!empty($produits_selected) && in_array($id, $produits_selected)) ? ' selected' : '';
    print '<option value="'.$id.'"'.$selected.'>'.$label.'</option>';
}
print '</select>';
print '<div class="small opacitymedium" style="margin-top: 5px;">';
print '<a href="#" onclick="$(\'#produits\').val(null).trigger(\'change\'); return false;" class="link-action">'.$langs->trans("ClearSelection").'</a>';
print ' | <a href="#" onclick="selectAllOptions(\'produits\'); return false;" class="link-action">'.$langs->trans("SelectAll").'</a>';
print '</div>';
print '</div>';
print '</td></tr>';

// Filtre par statut
print '<tr><td><label class="filter-label"><i class="fas fa-tag"></i> '.$langs->trans("Status").'</label></td><td>';
print '<div class="filter-row">';
print '<select name="statut" class="select2-single" style="width: 100%;" id="statut">';
print '<option value="0"'.($statut == 0 ? ' selected' : '').'>'.$langs->trans("InStock").'</option>';
print '<option value="1"'.($statut == 1 ? ' selected' : '').'>'.$langs->trans("OutStock").'</option>';
print '<option value=""'.($statut === '' ? ' selected' : '').'>'.$langs->trans("AllStatus").'</option>';
print '</select>';
print '</div>';
print '</td></tr>';

// Filtre par date (HTML5)
print '<tr><td><label class="filter-label"><i class="fas fa-calendar-alt"></i> '.$langs->trans("DateFrom").'</label></td><td>';
print '<div class="filter-row">';
print '<input type="date" name="date_debut" class="date-input" value="'.$date_debut.'" id="date_debut">';
print '</div>';
print '</td></tr>';

print '<tr><td><label class="filter-label"><i class="fas fa-calendar-alt"></i> '.$langs->trans("DateTo").'</label></td><td>';
print '<div class="filter-row">';
print '<input type="date" name="date_fin" class="date-input" value="'.$date_fin.'" id="date_fin">';
print '</div>';
print '</td></tr>';

print '<tr><td colspan="2">';
print '<div class="filter-buttons">';
print '<button type="submit" class="button-primary"><i class="fas fa-filter"></i> '.$langs->trans("ApplyFilters").'</button>';
print '<a href="'.$_SERVER["PHP_SELF"].'" class="button-secondary"><i class="fas fa-redo"></i> '.$langs->trans("Reset").'</a>';
print '</div>';
print '</td></tr>';

print '</table>';
print '</div>';

print '</form>';
print '</div>'; // .filter-card
print '</div>';
print '</div>';

// JavaScript pour Select2 et fonctionnalités améliorées
print '<script>
$(document).ready(function() {
    // Initialisation de Select2 pour les sélections multiples
    $(".select2-multiselect").select2({
        width: "100%",
        placeholder: function() {
            return $(this).data("placeholder");
        },
        allowClear: true,
        language: "fr",
        dropdownParent: $(".filter-card"),
        templateResult: formatOption,
        templateSelection: formatSelection
    });
    
    // Initialisation de Select2 pour les sélections simples
    $(".select2-single").select2({
        width: "100%",
        minimumResultsForSearch: -1,
        dropdownParent: $(".filter-card")
    });
    
    // Formater l\'affichage des options
    function formatOption(option) {
        if (!option.id) {
            return option.text;
        }
        var $option = $(
            \'<span><i class="fas fa-check-circle text-success" style="margin-right: 8px; opacity: 0.6;"></i>\' + option.text + \'</span>\'
        );
        return $option;
    }
    
    // Formater l\'affichage de la sélection
    function formatSelection(option) {
        return option.text;
    }
    
    // Définir la date d\'aujourd\'hui comme date maximale pour la date de fin
    var today = new Date().toISOString().split("T")[0];
    //$("#date_fin").attr("max", today);
    
    // Synchroniser les dates pour éviter les incohérences
    $("#date_debut").on("change", function() {
        var startDate = $(this).val();
        if (startDate) {
            $("#date_fin").attr("min", startDate);
            if ($("#date_fin").val() && $("#date_fin").val() < startDate) {
                $("#date_fin").val(startDate);
            }
        }
    });
    
    // Pré-remplir les dates par défaut (dernier mois)
    if (!$("#date_debut").val()) {
        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 1);
        $("#date_debut").val(lastMonth.toISOString().split("T")[0]);
    }
    if (!$("#date_fin").val()) {
        $("#date_fin").val(today);
    }
});

// Fonction pour sélectionner toutes les options
function selectAllOptions(selectId) {
    $("#" + selectId + " option").prop("selected", true);
    $("#" + selectId).trigger("change");
}

// Fonction pour désélectionner toutes les options
function deselectAllOptions(selectId) {
    $("#" + selectId + " option").prop("selected", false);
    $("#" + selectId).trigger("change");
}

// Validation du formulaire
document.getElementById("filterForm").addEventListener("submit", function(e) {
    var dateDebut = document.getElementById("date_debut").value;
    var dateFin = document.getElementById("date_fin").value;
    
    if (dateDebut && dateFin && dateDebut > dateFin) {
        alert("'.$langs->trans("DateStartMustBeBeforeDateEnd").'");
        e.preventDefault();
        return false;
    }
    
    // Afficher un indicateur de chargement
    var submitBtn = this.querySelector("button[type=\'submit\']");
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = \'<i class="fas fa-spinner fa-spin"></i> '.$langs->trans("Loading").'...\';
    submitBtn.disabled = true;
    
    setTimeout(function() {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 2000);
});
</script>';

// CSS supplémentaire pour les badges
print '<style>
    .price-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 13px;
        display: inline-block;
        min-width: 80px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .filter-summary {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }
    .filter-tag {
        background: #3b82f6;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .filter-tag i {
        font-size: 11px;
    }
    .table-hover tbody tr:hover {
        background-color: #f8fafc;
        transition: background-color 0.2s;
    }
</style>';

// Affichage du résumé des filtres (si des entrepôts spécifiques sont sélectionnés)
if (!empty($entrepots_selected) && !in_array('all', $entrepots_selected) && !empty($entrepots)) {
    $selected_entrepots_labels = [];
    foreach ($entrepots_selected as $entrepot_id) {
        if (isset($entrepots[$entrepot_id])) {
            $selected_entrepots_labels[] = $entrepots[$entrepot_id];
        }
    }
    
    if (!empty($selected_entrepots_labels)) {
        print '<div class="filter-summary">';
        print '<div><strong>'.$langs->trans("SelectedWarehouses").':</strong></div>';
        print '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
        foreach ($selected_entrepots_labels as $label) {
            print '<span class="filter-tag"><i class="fas fa-warehouse"></i> '.$label.'</span>';
        }
        print '</div>';
        
        // Afficher aussi le statut sélectionné
        $status_label = '';
        if ($statut === 0) {
            $status_label = $langs->trans("InStock");
        } elseif ($statut === 1) {
            $status_label = $langs->trans("OutStock");
        } else {
            $status_label = $langs->trans("AllStatus");
        }
        print '<div class="ml-auto">';
        print '<span class="filter-tag"><i class="fas fa-tag"></i> '.$status_label.'</span>';
        print '</div>';
        print '</div>';
    }
}

// Affichage des résultats
print '<div class="div-table-responsive-no-min">';
print '<table class="liste table-hover" width="100%">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("ProductReference").'</th>';
print '<th class="text-right">'.$langs->trans("NumberOfCartons").'</th>';
print '<th class="text-right">'.$langs->trans("TotalWeight").' (kg)</th>';
print '<th class="text-right">'.$langs->trans("TotalValue").'</th>';
print '<th class="text-right">'.$langs->trans("AverageUnitPrice").'</th>';
print '</tr>';

if ($resql) {
    $i = 0;
    while ($obj = $db->fetch_object($resql)) {
        $i++;
        // Calcul avec arrondi à 2 décimales
        $pu_carton = ($obj->nb_carton > 0) ? round($obj->valeur_total / $obj->nb_carton, 2) : 0;
        
        $class = ($i % 2 == 0) ? 'impair' : 'pair';
        print '<tr class="oddeven '.$class.'">';
        print '<td><strong>'.$obj->produit_ref.'</strong></td>';
        
        // Nombre de cartons avec badge
        print '<td class="text-right">';
        print '<span class="price-badge">'.number_format($obj->nb_carton, 0, '.', ' ').'</span>';
        print '</td>';
        
        // Poids avec 2 décimales
        $poids_formatted = number_format($obj->poids_total, 2, '.', ' ');
        print '<td class="text-right"><strong>'.$poids_formatted.' kg</strong></td>';
        
        // Valeur totale avec 2 décimales
        $valeur_formatted = number_format($obj->valeur_total, 2, '.', ' ');
        print '<td class="text-right"><strong class="text-success">'.$valeur_formatted.'</strong></td>';
        
        // PU moyen avec badge
        print '<td class="text-right">';
        print '<span class="price-badge">'.number_format($pu_carton, 2, '.', ' ').'</span>';
        print '</td>';
        
        print '</tr>';
        
        $total_cartons += $obj->nb_carton;
        $total_poids += $obj->poids_total;
        $total_valeur += $obj->valeur_total;
    }
    
    // Totaux
    if ($i > 0) {
        $pu_moyen_total = ($total_cartons > 0) ? round($total_valeur / $total_cartons, 2) : 0;
        
        print '<tr class="liste_total" style="background: #dddcdcff">';
        print '<td class="text-right"><strong>'.$langs->trans("Totals").':</strong></td>';
        
        // Total cartons avec badge
        print '<td class="text-right">';
        print '<span class="price-badge" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">';
        print number_format($total_cartons, 0, '.', ' ');
        print '</span>';
        print '</td>';
        
        // Total poids avec 2 décimales
        $total_poids_formatted = number_format($total_poids, 2, '.', ' ');
        print '<td class="text-right"><strong>'.$total_poids_formatted.' kg</strong></td>';
        
        // Total valeur avec 2 décimales
        $total_valeur_formatted = number_format($total_valeur, 2, '.', ' ');
        print '<td class="text-right"><strong class="text-success">'.$total_valeur_formatted.'</strong></td>';
        
        // PU moyen total avec badge
        print '<td class="text-right">';
        /*print '<span class="price-badge" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">';
        print number_format($pu_moyen_total, 2, '.', ' ');
        print '</span>';*/
        print '</td>';
        
        print '</tr>';
        
        // Ligne supplémentaire pour les moyennes
        print '<tr class="liste_subtotal" style="background: #dddcdcff">';
        print '<td colspan="5" class="text-center opacitymedium" style="padding: 10px; font-size: 13px;">';
        print '<i class="fas fa-calculator"></i> ';
        print $langs->trans("AveragePerCarton").': ';
        
        // Calcul des moyennes par carton
        $moyenne_poids = ($total_cartons > 0) ? round($total_poids / $total_cartons, 2) : 0;
        $moyenne_valeur = ($total_cartons > 0) ? round($total_valeur / $total_cartons, 2) : 0;
        
        print '<span class="badge badge-info" style="margin: 0 5px;">'.number_format($moyenne_poids, 2, '.', ' ').' kg/carton</span>';
        print '<span class="badge badge-info" style="margin: 0 5px;">'.number_format($moyenne_valeur, 2, '.', ' ').' €/carton</span>';
        
        print '</td>';
        print '</tr>';
    }
    
    if ($i == 0) {
        print '<tr><td colspan="5" class="text-center opacitymedium" style="padding: 40px;">';
        print '<i class="fas fa-inbox fa-2x opacity-50" style="margin-bottom: 15px;"></i><br>';
        print $langs->trans("NoCartonsFoundWithCriteria");
        print '</td></tr>';
    }
} else {
    print '<tr><td colspan="5" class="text-center error" style="padding: 40px;">';
    print '<i class="fas fa-exclamation-triangle fa-2x"></i><br>';
    print $langs->trans("Error").': '.$db->lasterror();
    print '</td></tr>';
}

print '</table>';
print '</div>';




// Bouton d'export Excel
if ($resql && $i > 0) {
    print '<div class="tabsAction" style="margin-top: 20px;">';
    
    // Construire l'URL avec tous les filtres
    $export_params = array();
    
    // Ajouter les entrepôts sélectionnés
    if (!empty($entrepots_selected)) {
        foreach ($entrepots_selected as $entrepot) {
            $export_params[] = 'entrepots[]=' . urlencode($entrepot);
        }
    }
    
    // Ajouter les produits sélectionnés
    if (!empty($produits_selected)) {
        foreach ($produits_selected as $produit) {
            $export_params[] = 'produits[]=' . urlencode($produit);
        }
    }
    
    // Ajouter les autres filtres
    if ($statut !== '') {
        $export_params[] = 'statut=' . $statut;
    }
    if ($date_debut) {
        $export_params[] = 'date_debut=' . urlencode($date_debut);
    }
    if ($date_fin) {
        $export_params[] = 'date_fin=' . urlencode($date_fin);
    }
    
    // Ajouter le token de sécurité
    $export_params[] = 'token=' . newToken();
    
    // Construire l'URL complète
    $export_url = 'stock_export.php?' . implode('&', $export_params);
    
    // Bouton d'export
    print '<a class="butAction" href="' . $export_url . '" target="_blank">';
    print '<i class="fas fa-file-excel"></i> ' . $langs->trans("ExportToExcel");
    print '</a>';
    
    print '</div>';
}
llxFooter();
$db->close();
?>