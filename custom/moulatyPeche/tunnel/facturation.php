<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;

$langs->load("pech@pech");
$langs->load("stocks");

// Vérification des droits
//if (empty($user->rights->pech->read)) accessforbidden();

// Initialisation
$action = GETPOST('action', 'alpha');
$form = new Form($db);

// Valeurs filtres
$date_start = GETPOST('date_start', 'alphanohtml');
$date_end   = GETPOST('date_end', 'alphanohtml');

if (!empty($date_start)) $date_start_sql = $db->idate($date_start);
if (!empty($date_end))   $date_end_sql   = $db->idate($date_end);
$fk_entrepot = GETPOST('fk_entrepot', 'int');

// --- Récupération liste entrepôts ---
$entrepots = array();
$sql_ent = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot WHERE entity IN (".getEntity('stock').")";
$res_ent = $db->query($sql_ent);
if ($res_ent) {
    while ($obj = $db->fetch_object($res_ent)) {
        $entrepots[$obj->rowid] = $obj->ref;
    }
}

// Header
llxHeader('', $langs->trans('TunnelManagement'));
print load_fiche_titre($langs->trans("ListeDesMisesEnPlat"), '', 'title_generic.png');

// --- FILTRES ---
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="6">'.$langs->trans("Filtres").'</th></tr>';

// Date début
print '<td>'.$langs->trans("Date début").'</td><td>';
print '<input type="datetime-local" name="date_start" value="'.(GETPOST('date_start') ? dol_escape_htmltag(GETPOST('date_start')) : '').'" class="flat">';
print '</td>';

// Date fin
print '<td>'.$langs->trans("Date fin").'</td><td>';
print '<input type="datetime-local" name="date_end" value="'.(GETPOST('date_end') ? dol_escape_htmltag(GETPOST('date_end')) : '').'" class="flat">';
print '</td>';

// Entrepôt
print '<td>'.$langs->trans("Entrepôt").'</td><td>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 1, 0, 0, '', 0, 0, 0, '', '', 1);
print '</td>';
print '</tr>';

// Bouton Filtrer
print '<tr><td colspan="6" class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Filtrer").'">';
print '&nbsp;<a class="button" href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("Réinitialiser").'</a>';
print '</td></tr>';
print '</table>';
print '</form>';

// --- REQUÊTE PRINCIPALE ---
// --- REQUÊTE PRINCIPALE MODIFIÉE ---
$sql = "SELECT mp.rowid,
               mp.date_creation,
               mp.fk_bon_entree,
               mp.fk_reception,
               mp.nombre_plat AS nb_plats,
               mp.poids_plat AS qte_total,
               mp.pointeur,
               mp.statut,
               r.ref AS ref_reception,
               e.ref AS ref_entrepot,
               p.ref as ref_prod,
               p.label as label_prod,
               (SELECT COUNT(*) 
                FROM ".MAIN_DB_PREFIX."pech_plat pl 
                WHERE pl.fk_misenplat = mp.rowid) AS total_plats
        FROM ".MAIN_DB_PREFIX."pech_misenplat AS mp
        LEFT JOIN ".MAIN_DB_PREFIX."pech_reception AS r ON r.rowid = mp.fk_reception
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = r.fk_entrepot
        LEFT JOIN ".MAIN_DB_PREFIX."pech_receptiondet rd ON rd.rowid = mp.fk_reception_det
        LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = rd.fk_product
        WHERE 1=1";

if ($fk_entrepot > 0) $sql .= " AND r.fk_entrepot = ".((int) $fk_entrepot);
if (!empty($date_start)) $sql .= " AND mp.date_creation >= '".$db->escape($date_start)."'";
if (!empty($date_end))   $sql .= " AND mp.date_creation <= '".$db->escape($date_end)."'";

$sql .= " ORDER BY mp.date_creation DESC";
$resql = $db->query($sql);

if (empty($fk_entrepot) || $fk_entrepot < 0) {
    print '<div class="warning" style="margin-top:10px;">';
    print '<strong>Veuillez sélectionner un entrepôt pour afficher les résultats.</strong>';
    print '</div>';
    
llxFooter();
exit;
}
// --- AFFICHAGE TABLEAU ---
/*print '<form method="POST" action="confirm_fact.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot" value="'.((int) $fk_entrepot).'">'; // ✅ ajout clé ici

print '<div class="tabsAction center">';
// Bouton pour créer un bon d'entrée
print '<button type="submit" formaction="confirm_fact.php" class="button button-action">';
print $langs->trans("Facturer la cogelation");
print '</button>';


print '<div class="div-table-responsive">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print '<th class="center"><input type="checkbox" id="selectAll"></th>';
//print '<th>'.$langs->trans("Réf Mise en Plat").'</th>';
print '<th>'.$langs->trans("Réception").'</th>';
print '<th>'.$langs->trans("Product").'</th>';
print '<th>'.$langs->trans("Entrepôt").'</th>';
//print '<th class="right">'.$langs->trans("Nb Plats").'</th>';
//print '<th class="right">'.$langs->trans("Poids Plater (kg)").'</th>';
//print '<th>'.$langs->trans("Pointeur").'</th>';
print '<th class="right">'.$langs->trans("Total Plats").'</th>';
print '<th class="right">'.$langs->trans("Nb Plats Congelés").'</th>';
print '<th class="right">'.$langs->trans("Plats Décongelés").'</th>';
print '<th>'.$langs->trans("Date Création").'</th>';
print '<th>'.$langs->trans("Statut").'</th>';
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" name="select_misenplat[]" value="'.$obj->rowid.'"></td>';
        //print '<td>'.dol_escape_htmltag($obj->ref).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_reception).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_prod.' - '.$obj->label_prod).'</td>';
        
        print '<td>'.dol_escape_htmltag($obj->ref_entrepot).'</td>';
        //print '<td class="right">'.price($obj->nb_plats).'</td>';
        //print '<td class="right">'.price($obj->qte_total * $obj->nb_plats).'</td>';
        //print '<td>'.dol_escape_htmltag($obj->pointeur).'</td>';
        $nb_congele = (int)$obj->nb_plats;
        $total_plats = (int)$obj->total_plats;
        $decongele = $total_plats - $nb_congele;
        print '<td class="right">'.price($total_plats).'</td>';
        print '<td class="right">'.price($nb_congele).'</td>';
        print '<td class="right">'.price($decongele).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
        
        // --- Statut mise en plat ---
        $statuts = [
            0 => 'Platted',
            1 => 'Semi Cartonné',
            2 => 'Cartonné'
        ];

        $colors = [
            0 => '#d1ecf1', // bleu clair pour Platted
            1 => '#fff3cd', // jaune pour Semi Cartonné
            2 => '#d4edda'  // vert clair pour Cartonné
        ];

        $statut_label = isset($statuts[$obj->statut]) ? $statuts[$obj->statut] : 'Inconnu';
        $color = isset($colors[$obj->statut]) ? $colors[$obj->statut] : '#f8d7da'; // rouge pour inconnu

        print '<td class="center" style="background-color:'.$color.'; font-weight:bold;">'.dol_escape_htmltag($statut_label).'</td>';
         
        print '</tr>';
    }
} else {
    print '<tr><td colspan="8" class="center opacitymedium">'.$langs->trans("Aucune mise en plat trouvée").'</td></tr>';
}

print '</table>';
print '</div>';



print '</form>';
*/
?>

<!--script>

document.querySelectorAll('button[type="submit"]').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const checked = document.querySelectorAll('input[name="select_misenplat[]"]:checked');
        if (checked.length === 0) {
            alert("Veuillez sélectionner au moins une mise en plat avant de continuer.");
            e.preventDefault();
        }
    });
});
</script-->


<?php
// --- AFFICHAGE TABLEAU ---
print '<form method="POST" action="confirm_fact.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot" value="'.((int) $fk_entrepot).'">';

print '<div class="tabsAction center">';
print '<button type="submit" class="button button-action">';
print $langs->trans("Facturer la cogelation");
print '</button>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print '<th class="center"><input type="checkbox" id="selectAll"></th>';
print '<th>'.$langs->trans("Réception").'</th>';
print '<th>'.$langs->trans("Product").'</th>';
print '<th>'.$langs->trans("Entrepôt").'</th>';
print '<th class="right">'.$langs->trans("Total Plats").'</th>';
print '<th class="right">'.$langs->trans("Nb Plats Congelés").'</th>';
print '<th class="right">'.$langs->trans("Plats Décongelés").'</th>';
print '<th>'.$langs->trans("Date Création").'</th>';
print '<th>'.$langs->trans("Statut").'</th>';
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
    while ($obj = $db->fetch_object($resql)) {
        $nb_congele = (int)$obj->nb_plats;
        $total_plats = (int)$obj->total_plats;
        $decongele = $total_plats - $nb_congele;

        print '<tr class="oddeven">';
        print '<td class="center">
                <input type="checkbox" name="select_misenplat[]" value="'.$obj->rowid.'">
                <input type="hidden" name="decongele['.$obj->rowid.']" value="'.$decongele.'">
               </td>';
        print '<td>'.dol_escape_htmltag($obj->ref_reception).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_prod.' - '.$obj->label_prod).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_entrepot).'</td>';
        print '<td class="right">'.price($total_plats).'</td>';
        print '<td class="right">'.price($nb_congele).'</td>';
        print '<td class="right">'.price($decongele).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';

        $statuts = [0 => 'Platted', 1 => 'Semi Cartonné', 2 => 'Cartonné'];
        $colors = [0 => '#d1ecf1', 1 => '#fff3cd', 2 => '#d4edda'];
        $statut_label = $statuts[$obj->statut] ?? 'Inconnu';
        $color = $colors[$obj->statut] ?? '#f8d7da';
        print '<td class="center" style="background-color:'.$color.'; font-weight:bold;">'.dol_escape_htmltag($statut_label).'</td>';

        print '</tr>';
    }
} else {
    print '<tr><td colspan="9" class="center opacitymedium">'.$langs->trans("Aucune mise en plat trouvée").'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';
?>

<script>
// Bouton Select All
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('input[name="select_misenplat[]"]').forEach(cb => cb.checked = this.checked);
});

// Vérifier sélection avant soumission
document.querySelectorAll('button[type="submit"]').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const checked = document.querySelectorAll('input[name="select_misenplat[]"]:checked');
        if (checked.length === 0) {
            alert("Veuillez sélectionner au moins une mise en plat avant de continuer.");
            e.preventDefault();
        }
    });
});
</script>


<?php
llxFooter();
$db->close();
