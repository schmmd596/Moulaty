<?php
/* ============================================================================
 * Module : womapeche
 * Auteur : Abdou Mahfoudh <superadmin@womapeche.com>
 * Description : Liste des bons de mise en plat avec recherche et pagination.
 * ============================================================================
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->loadLangs(['main', 'other', 'womapeche@womapeche']);
$form = new Form($db);

// Récupérer les entrepôts accessibles pour l'utilisateur connecté
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// ============================================================================
// 🔍 Paramètres de recherche et pagination
// ============================================================================
$search_ref = GETPOST('search_ref', 'alpha');
$search_entrepot = GETPOST('search_entrepot', 'int');
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$limit = GETPOST('limit', 'int') ?: 20;
$page = GETPOST('page', 'int') ?: 1;
$offset = ($page - 1) * $limit;

// ============================================================================
// 📊 Construction de la requête SQL
// ============================================================================
$where = "1=1";
if ($search_ref) $where .= " AND b.ref LIKE '%" . $db->escape($search_ref) . "%'";
if ($search_entrepot && $search_entrepot != -1) $where .= " AND b.fk_entrepot = " . (int)$search_entrepot;
if ($search_date_start) $where .= " AND b.date_creation >= '" . $db->escape($search_date_start) . " 00:00:00'";
if ($search_date_end) $where .= " AND b.date_creation <= '" . $db->escape($search_date_end) . " 23:59:59'";

$sql_count = "SELECT COUNT(*) as nb FROM " . MAIN_DB_PREFIX . "pech_bon_misenplat AS b WHERE $where";
$res_count = $db->query($sql_count);
$nb_total = $res_count ? $db->fetch_object($res_count)->nb : 0;

$sql = "SELECT b.rowid, b.ref, b.date_creation, b.statut, b.commentaire,
               e.ref as entrepot_ref,
               u.login as user_login
        FROM " . MAIN_DB_PREFIX . "pech_bon_misenplat AS b
        LEFT JOIN " . MAIN_DB_PREFIX . "entrepot AS e ON e.rowid = b.fk_entrepot
        LEFT JOIN " . MAIN_DB_PREFIX . "user AS u ON u.rowid = b.fk_user
        WHERE $where";

if (!empty($entrepots_accessibles)) {
    $sql .= " AND b.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}
$sql .= " ORDER BY b.rowid DESC
        LIMIT $limit OFFSET $offset";
$resql = $db->query($sql);
if (!$resql) {
    setEventMessages($langs->trans("Erreur lors de la récupération des bons."), null, 'errors');
    exit;
}

// ============================================================================
// 🎨 En-tête + style CSS global
// ============================================================================
llxHeader('', $langs->trans("ListeBonsMiseEnPlat"));

// Inclusion du CSS global pour les listes
print '<link rel="stylesheet" href="../reception_list_style.css">';

print '<div class="reception-list-container">';
print '<div class="reception-list-header">';
print load_fiche_titre('<i class="fa fa-utensils"></i> '.$langs->trans("BonsMiseEnPlat"), '', 'object_list');
print '</div>';

// ============================================================================
// 🔎 Formulaire de recherche
// ============================================================================
print '<form method="GET" class="search-form">';
print '<table class="search-table">';
print '<tr>';
print '<th>'.$langs->trans("Reference").'</th>';
print '<th>'.$langs->trans("Entrepot").'</th>';
print '<th>'.$langs->trans("DateDebut").'</th>';
print '<th>'.$langs->trans("DateFin").'</th>';
print '<th>'.$langs->trans("LignesPage").'</th>';
print '<th></th>';
print '</tr><tr>';

// Référence
print '<td><input type="text" name="search_ref" class="search-input" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("RechercherReference").'"></td>';

// Entrepôt
$entrepot_array = ['' => $langs->trans("Tous")];
$resql2 = $db->query("SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref");
if ($resql2) {
    while ($obj2 = $db->fetch_object($resql2)) {
        $entrepot_array[$obj2->rowid] = dol_escape_htmltag($obj2->ref);
    }
}
print '<td>'.$form->selectarray('search_entrepot', $entrepot_array, $search_entrepot, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

// Dates
print '<td><input type="date" name="search_date_start" class="search-input" value="'.dol_escape_htmltag($search_date_start).'"></td>';
print '<td><input type="date" name="search_date_end" class="search-input" value="'.dol_escape_htmltag($search_date_end).'"></td>';

// Limite
$limit_array = array(10=>10, 20=>20, 50=>50, 100=>100);
print '<td>'.$form->selectarray('limit', $limit_array, $limit, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

print '<td><input type="submit" class="search-button" value="'.$langs->trans("Rechercher").'"></td>';
print '</tr></table></form>';

// ============================================================================
// 📋 Tableau principal
// ============================================================================
if ($db->num_rows($resql) == 0) {
    print '<div class="no-results">';
    print '<i class="fa fa-search"></i>';
    print '<h3>'.$langs->trans("AucunResultat").'</h3>';
    print '<p>'.$langs->trans("AucunBonEnregistre").'</p>';
    print '</div>';
} else {
    print '<div class="results-table-container">';
    print '<table class="results-table">';
    print '<thead><tr>';
    print '<th class="col-ref"><i class="fa fa-barcode"></i> '.$langs->trans("Reference").'</th>';
    print '<th class="col-fournisseur"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</th>';
    print '<th class="col-congelateur"><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</th>';
    print '<th class="col-date"><i class="fa fa-calendar"></i> '.$langs->trans("DateCreation").'</th>';
    print '<th class="col-etat"><i class="fa fa-info-circle"></i> '.$langs->trans("Etat").'</th>';
    print '<th class="col-actions"><i class="fa fa-eye"></i> '.$langs->trans("Details").'</th>';
    print '</tr></thead>';
    print '<tbody>';

    while ($bon = $db->fetch_object($resql)) {
        switch ((int)$bon->statut) {
            case 0: 
                $statut_html = '<span class="badge badge-warning">'.$langs->trans("Brouillon").'</span>'; 
                break;
            case 1: 
                $statut_html = '<span class="badge badge-success">'.$langs->trans("Valide").'</span>'; 
                break;
            case 2: 
                $statut_html = '<span class="badge badge-danger">'.$langs->trans("Termine").'</span>'; 
                break;
            default:
                $statut_html = '<span class="badge">'.$langs->trans("Inconnu").'</span>';
        }

        print '<tr>';
        print '<td class="col-ref"><a href="detail_mis.php?id='.$bon->rowid.'" style="color:#3498db; font-weight:500;">'.dol_escape_htmltag($bon->ref).'</a></td>';
        print '<td class="col-fournisseur">'.dol_escape_htmltag($bon->entrepot_ref).'</td>';
        print '<td class="col-congelateur">'.dol_escape_htmltag($bon->user_login).'</td>';
        print '<td class="col-date">'.dol_print_date($db->jdate($bon->date_creation), 'dayhour').'</td>';
        print '<td class="col-etat">'.$statut_html.'</td>';
        print '<td class="col-actions">';
        print '<a class="action-button" href="detail_mis.php?id='.$bon->rowid.'"><i class="fa fa-eye"></i> '.$langs->trans("VoirDetails").'</a>';
        print '</td>';
        print '</tr>';
    }

    print '</tbody></table>';
    print '</div>';

    // ============================================================================
    // 📄 Pagination
    // ============================================================================
    $nb_pages = ceil($nb_total / $limit);
    if ($nb_pages > 1) {
        print '<div class="pagination">';
        for ($p = 1; $p <= $nb_pages; $p++) {
            $url = '?page='.$p.'&limit='.$limit;
            if ($search_ref) $url .= '&search_ref='.urlencode($search_ref);
            if ($search_entrepot) $url .= '&search_entrepot='.$search_entrepot;
            if ($search_date_start) $url .= '&search_date_start='.urlencode($search_date_start);
            if ($search_date_end) $url .= '&search_date_end='.urlencode($search_date_end);
            
            if ($p == $page) {
                print '<a href="'.$url.'" style="font-weight:bold;">'.$p.'</a>';
            } else {
                print '<a href="'.$url.'">'.$p.'</a>';
            }
        }
        print '</div>';
    }
}

print '</div>'; // .reception-list-container

llxFooter();
$db->close();
?>