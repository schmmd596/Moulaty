<?php
/**
 * Sortie Tunnel - Interface de gestion des mises en plat
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(['womapeche@womapeche', 'stocks', 'main']);

$form = new Form($db);

// ============================================================================
// 🔹 Récupération des filtres
// ============================================================================
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$date_start = GETPOST('date_start', 'alphanohtml');
$date_end   = GETPOST('date_end', 'alphanohtml');

include_once '../user_entrepot_access.php';
if (!empty($fk_entrepot)) {
    check_user_entrepot_access($fk_entrepot);
}

// ============================================================================
// 🔹 Liste des entrepôts
// ============================================================================
$user_warehouses = get_user_entrepots();
$entrepots = ['' => $langs->trans("Tous")];
$sql_ent = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot";
if (!empty($user_warehouses)) {
    $sql_ent .= " WHERE rowid IN (".implode(',', array_map('intval', $user_warehouses)).")";
}
$sql_ent .= " ORDER BY ref ASC";
$res_ent = $db->query($sql_ent);
if ($res_ent) {
    while ($obj = $db->fetch_object($res_ent)) {
        $entrepots[$obj->rowid] = $obj->ref;
    }
}

// ============================================================================
// 🔹 En-tête de page
// ============================================================================
llxHeader('', $langs->trans('SortieTunnel'));
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-fish"></i> '.$langs->trans("MisesEnPlatAttenteSortie").'</h1>';
print '</div>';

// ============================================================================
// 🔹 Formulaire de filtre
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-filter"></i> '.$langs->trans("Filtres");
print '</div>';

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="6">'.$langs->trans("CritèresFiltrage").'</th></tr>';

print '<tr>';
print '<td><label class="selection-label">'.$langs->trans("Entrepot").'</label></td>';
print '<td>'.$form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 1).'</td>';

print '<td><label class="selection-label">'.$langs->trans("DateDebut").'</label></td>';
print '<td><input type="date" name="date_start" class="filter-input" value="'.dol_escape_htmltag($date_start).'"></td>';

print '<td><label class="selection-label">'.$langs->trans("DateFin").'</label></td>';
print '<td><input type="date" name="date_end" class="filter-input" value="'.dol_escape_htmltag($date_end).'"></td>';
print '</tr>';

print '<tr><td colspan="6" class="center">';
print '<div class="selection-actions">';
print '<button type="submit" class="selection-button selection-button-primary">';
print '<i class="fa fa-search"></i> '.$langs->trans("Filtrer");
print '</button>';
print '<a class="selection-button selection-button-secondary" href="'.$_SERVER["PHP_SELF"].'">';
print '<i class="fa fa-refresh"></i> '.$langs->trans("Reinitialiser");
print '</a>';
print '</div>';
print '</td></tr>';
print '</table>';
print '</form>';
print '</div>'; // .filter-section

// ============================================================================
// 🧾 REQUÊTE PRINCIPALE : afficher les mises en plat disponibles
// ============================================================================
$user_warehouses = get_user_entrepots();

$sql = "SELECT mp.rowid, mp.date_creation, mp.nombre_plat, mp.nombre_plat_sortie, mp.poids_plat, mp.statut,
               mp.fk_bon_misenplat, mp.pointeur,
               p.ref AS ref_product, p.label AS label_product,
               b.ref AS ref_bon, e.ref AS ref_entrepot
        FROM ".MAIN_DB_PREFIX."pech_misenplat AS mp
        LEFT JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat AS b ON b.rowid = mp.fk_bon_misenplat
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
        LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = mp.fk_product
        WHERE b.statut > 0 and mp.nombre_plat > nombre_plat_sortie ";

if ($fk_entrepot > 0) {
    $sql .= " AND b.fk_entrepot = ".((int)$fk_entrepot);
} elseif (!empty($user_warehouses)) {
    $sql .= " AND b.fk_entrepot IN (".implode(',', array_map('intval', $user_warehouses)).")";
}

if (!empty($date_start)) $sql .= " AND mp.date_creation >= '".$db->escape($date_start)." 00:00:00'";
if (!empty($date_end))   $sql .= " AND mp.date_creation <= '".$db->escape($date_end)." 23:59:59'";

$sql .= " ORDER BY mp.date_creation DESC";

$resql = $db->query($sql);

// ============================================================================
// 📋 Tableau des résultats
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-list"></i> '.$langs->trans("MisesEnPlatDisponibles");
print '</div>';

print '<form method="POST" action="sortie_tunnel_create.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot" value="'.((int)$fk_entrepot).'">';

print '<div class="selection-actions">';
print '<button type="submit" formaction="sortie_tunnel_create.php" class="selection-button selection-button-success">';
print '<i class="fa fa-arrow-right"></i> '.$langs->trans("CreerSortieTunnel");
print '</button>';
print '</div>';

print '<div class="div-table-responsive selection-table-container">';
print '<table class="selection-table">';
print '<thead>';
print '<tr>';
print '<th class="center"><input type="checkbox" id="selectAll" class="selection-checkbox"></th>';
print '<th><i class="fa fa-file-text"></i> '.$langs->trans("BonMiseEnPlat").'</th>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Produit").'</th>';
print '<th><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</th>';
print '<th class="right"><i class="fa fa-hashtag"></i> '.$langs->trans("NbPlats").'</th>';
print '<th class="right"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsNet").' Kg</th>';
print '<th><i class="fa fa-calendar"></i> '.$langs->trans("Date").'</th>';
print '<th><i class="fa fa-status"></i> '.$langs->trans("Statut").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

if ($resql && $db->num_rows($resql) > 0) {
    while ($obj = $db->fetch_object($resql)) {
        $remaining_plats = $obj->nombre_plat - $obj->nombre_plat_sortie;
        $total_weight = $remaining_plats * $obj->poids_plat;
        
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" name="select_misenplat[]" value="'.$obj->rowid.'" class="selection-checkbox"></td>';
        print '<td><strong>'.dol_escape_htmltag($obj->ref_bon).'</strong></td>';
        print '<td>'.dol_escape_htmltag($obj->ref_product.' - '.$obj->label_product).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_entrepot).'</td>';
        print '<td class="right"><strong>'.price($remaining_plats).'</strong></td>';
        print '<td class="right"><strong>'.price($total_weight).'</strong></td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';

        // Statut avec badges colorés
        $statuts = [
            0 => ['label' => $langs->trans("Brouillon"), 'class' => 'badge-warning'],
            1 => ['label' => $langs->trans("Valide"), 'class' => 'badge-info'], 
            2 => ['label' => $langs->trans("Sorti"), 'class' => 'badge-success']
        ];
        
        $statut_info = $statuts[$obj->statut] ?? ['label' => $langs->trans("Inconnu"), 'class' => 'badge-warning'];
        print '<td class="center"><span class="selection-badge '.$statut_info['class'].'">'.$statut_info['label'].'</span></td>';

        print '</tr>';
    }
} else {
    print '<tr><td colspan="8" class="selection-empty">';
    print '<i class="fa fa-inbox"></i>';
    print '<p>'.$langs->trans("AucuneMiseEnPlatTrouvee").'</p>';
    print '</td></tr>';
}

print '</tbody>';
print '</table>';
print '</div>'; // .div-table-responsive
print '</form>';
print '</div>'; // .selection-section
print '</div>'; // .selection-container

?>
<script>
// ✅ Sélectionner / désélectionner tout
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="select_misenplat[]"]');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        // Ajouter/supprimer la classe selected sur la ligne parente
        const row = cb.closest('tr');
        if (row) {
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
    });
});

// ✅ Gérer la sélection individuelle
document.querySelectorAll('input[name="select_misenplat[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const row = this.closest('tr');
        if (row) {
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
        
        // Mettre à jour la case "Tout sélectionner"
        const allCheckboxes = document.querySelectorAll('input[name="select_misenplat[]"]');
        const selectAll = document.getElementById('selectAll');
        const checkedCount = document.querySelectorAll('input[name="select_misenplat[]"]:checked').length;
        
        selectAll.checked = checkedCount === allCheckboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    });
});

// ✅ Empêcher envoi sans sélection
document.querySelectorAll('button[type="submit"]').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const checked = document.querySelectorAll('input[name="select_misenplat[]"]:checked');
        if (checked.length === 0) {
            alert("<?php echo $langs->trans('VeuillezSelectionnerMiseEnPlat'); ?>");
            e.preventDefault();
        }
    });
});
</script>

<?php
llxFooter();
$db->close();