<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $user, $langs;

$langs->load("abricot@abricot");
$langs->load("main");

$form = new Form($db);
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// Paramètres de recherche
$search_ref     = GETPOST('search_ref', 'alpha');
$search_date_de = GETPOST('search_date_de', 'alpha');
$search_date_a  = GETPOST('search_date_a', 'alpha');
$limit          = GETPOST('limit', 'int') ?: 10;
$page           = GETPOST('page', 'int') ?: 0;
$offset         = $page * $limit;

// Construction du filtre SQL
$where = [];
if (!empty($entrepots_accessibles)) {
    $where[] =  " l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}
if ($search_ref) $where[] = " l.ref LIKE '%".$db->escape($search_ref)."%'";
if ($search_date_de) $where[] = " DATE(l.date_creation) >= '".$db->escape($search_date_de)."'";
if ($search_date_a)  $where[] = " DATE(l.date_creation) <= '".$db->escape($search_date_a)."'";

$sql_where = !empty($where) ? " WHERE ".implode(" AND ", $where) : "";

// Comptage total
$sql_count = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."pech_lot $sql_where";
$res_count = $db->query($sql_count);
$total = $res_count ? $db->fetch_object($res_count)->nb : 0;

// Récupération des lots
$sql = "SELECT l.*, e.ref AS ref_entrepot
        FROM ".MAIN_DB_PREFIX."pech_lot as l
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
        $sql_where 
        ORDER BY date_creation DESC
        LIMIT $offset, $limit";
$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

// --- Header ---
llxHeader('', $langs->trans("ListeLots"));

// Inclusion du CSS global pour les listes
print '<link rel="stylesheet" href="../reception_list_style.css">';

print '<div class="reception-list-container">';
print '<div class="reception-list-header">';
print load_fiche_titre('<i class="fa fa-box"></i> '.$langs->trans("ListeLots"), '', 'object_list');
print '</div>';

// --- Formulaire de recherche ---
print '<form method="GET" class="search-form">';
print '<table class="search-table">';
print '<tr>';
print '<th>'.$langs->trans("Reference").'</th>';
print '<th>'.$langs->trans("DateDebut").'</th>';
print '<th>'.$langs->trans("DateFin").'</th>';
print '<th>'.$langs->trans("LignesPage").'</th>';
print '<th></th>';
print '</tr><tr>';

// Référence Lot
print '<td><input type="text" name="search_ref" class="search-input" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("RechercherReference").'"></td>';

// Date début
print '<td><input type="date" name="search_date_de" class="search-input" value="'.dol_escape_htmltag($search_date_de).'"></td>';

// Date fin
print '<td><input type="date" name="search_date_a" class="search-input" value="'.dol_escape_htmltag($search_date_a).'"></td>';

// Limit
$limit_array = array(10=>10, 20=>20, 50=>50, 100=>100);
print '<td>'.$form->selectarray('limit', $limit_array, $limit, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

// Bouton Rechercher
print '<td><input type="submit" class="search-button" value="'.$langs->trans("Rechercher").'"></td>';
print '</tr></table></form>';

// --- Tableau des lots ---
if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="results-table-container">';
    print '<table class="results-table">';
    print '<thead><tr>';
    print '<th class="col-ref"><i class="fa fa-hashtag"></i> '.$langs->trans("ReferenceLot").'</th>';
    print '<th class="col-fournisseur"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</th>';
    print '<th class="col-congelateur"><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</th>';
    print '<th class="col-date"><i class="fa fa-calendar-alt"></i> '.$langs->trans("DateCreation").'</th>';
    print '<th class="col-etat"><i class="fa fa-info-circle"></i> '.$langs->trans("Etat").'</th>';
    print '<th class="col-etat"><i class="fa fa-info-circle"></i> '.$langs->trans("EtatDesCartons").'</th>';
    print '<th class="col-actions"><i class="fa fa-eye"></i> '.$langs->trans("Details").'</th>';
    print '</tr></thead>';
    print '<tbody>';

    while ($obj = $db->fetch_object($resql)) {
        $user_name = '-';
        if ($obj->fk_user_create > 0) {
            $sqlUser = "SELECT firstname, lastname FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".((int)$obj->fk_user_create);
            $resUser = $db->query($sqlUser);
            if ($resUser && ($u = $db->fetch_object($resUser))) {
                $user_name = dol_escape_htmltag(trim($u->firstname.' '.$u->lastname));
            }
        }

        // Badges avec traductions
        if ($obj->statut == 0) {
            $badge = '<span class="badge badge-warning">'.$langs->trans("Brouillon").'</span>';
        } elseif ($obj->statut == 1) {
            $badge = '<span class="badge badge-success">'.$langs->trans("Valide").'</span>';
        } else {
            $badge = '<span class="badge badge-secondary">'.$langs->trans("Ferme").'</span>';
        }
        $sqljhjhjhksjkll = "SELECT ef.monnaie
                FROM ".MAIN_DB_PREFIX."entrepot_extrafields ef
                WHERE ef.fk_object = ".((int)$obj->fk_entrepot);;

        $resqlsqljhjhjhksjkll = $db->query($sqljhjhjhksjkll);

        $monnaie = '';
        if ($resqlsqljhjhjhksjkll && $db->num_rows($resqlsqljhjhjhksjkll)) {
            $objsqljhjhjhksjkll = $db->fetch_object($resqlsqljhjhjhksjkll);
            $monnaie = $objsqljhjhjhksjkll->monnaie;
        }

        $is_multidevise = empty($monnaie);

        
        if (!$is_multidevise){
            $url = 'detail_lot_m.php?id='.$obj->rowid;
        }else{
              $url = 'detail_lot.php?id='.$obj->rowid;
        }
        print '<tr>';
        print '<td class="col-ref"><a href="'.$url.'" style="color:#3498db; font-weight:500;"><i class="fa fa-box-open"></i> '.dol_escape_htmltag($obj->ref).'</a></td>';
        print '<td class="col-fournisseur">'.dol_escape_htmltag($obj->ref_entrepot).'</td>';
        print '<td class="col-congelateur">'.$user_name.'</td>';
        print '<td class="col-date">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
        print '<td class="col-etat">'.$badge.'</td>';
        $etat_lot = getLotEtatOptimise($obj->rowid);
    
    print '<td class="col-etat">'.displayLotEtatBadge($etat_lot).'</td>';
        print '<td class="col-actions">';
        print '<a class="action-button" href="detail_lot.php?id='.$obj->rowid.'"><i class="fa fa-eye"></i> '.$langs->trans("VoirDetails").'</a>';
        print '</td>';
        print '</tr>';
    }

    print '</tbody></table>';
    print '</div>';

    // --- Pagination ---
    $pages = ceil($total / $limit);
    if ($pages > 1) {
        print '<div class="pagination">';
        for ($i = 0; $i < $pages; $i++) {
            $url = '?page='.$i.'&limit='.$limit;
            if ($search_ref) $url .= '&search_ref='.urlencode($search_ref);
            if ($search_date_de) $url .= '&search_date_de='.urlencode($search_date_de);
            if ($search_date_a) $url .= '&search_date_a='.urlencode($search_date_a);
            
            if ($i == $page) {
                print '<a href="'.$url.'" style="font-weight:bold;">'.($i+1).'</a>';
            } else {
                print '<a href="'.$url.'">'.($i+1).'</a>';
            }
        }
        print '</div>';
    }

} else {
    print '<div class="no-results">';
    print '<i class="fa fa-search"></i>';
    print '<h3>'.$langs->trans("AucunResultat").'</h3>';
    print '<p>'.$langs->trans("AucunLotTrouve").'</p>';
    print '</div>';
}

print '</div>'; // .reception-list-container
function getLotEtatOptimise($lot_id) {
    global $db;
    
    $sql = "SELECT 
                SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS nb_stock,
                SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS nb_sortis,
                COUNT(c.rowid) AS total_cartons
            FROM " . MAIN_DB_PREFIX . "pech_carton c
            INNER JOIN " . MAIN_DB_PREFIX . "pech_lotdet ld ON ld.rowid = c.fk_lotdet
            WHERE ld.fk_lot = " . (int)$lot_id;
    
    $resql = $db->query($sql);
    
    if (!$resql || $db->num_rows($resql) === 0) {
        return 'vide';
    }
    
    $obj = $db->fetch_object($resql);
    
    if ($obj->total_cartons === 0) {
        return 'vide';
    }
    
    if ($obj->nb_stock === $obj->total_cartons) {
        return 'stock';
    }
    
    if ($obj->nb_sortis === $obj->total_cartons) {
        return 'sorti';
    }
    
    if ($obj->nb_stock > 0 && $obj->nb_sortis > 0) {
        return 'partiel';
    }
    
    return 'inconnu';
}

/**
 * Fonction pour afficher le badge d'état
 */
function displayLotEtatBadge($etat) {
    switch($etat) {
        case 'stock':
            return '<span class="badge badge-success"><i class="fa fa-box"></i> En stock</span>';
        case 'sorti':
            return '<span class="badge badge-info"><i class="fa fa-truck"></i> Sorti</span>';
        case 'partiel':
            return '<span class="badge badge-warning"><i class="fa fa-exchange-alt"></i> Partiel</span>';
        case 'vide':
            return '<span class="badge badge-secondary"><i class="fa fa-ban"></i> Vide</span>';
        default:
            return '<span class="badge badge-dark"><i class="fa fa-question"></i> Inconnu</span>';
    }
}

llxFooter();
$db->close();
?>