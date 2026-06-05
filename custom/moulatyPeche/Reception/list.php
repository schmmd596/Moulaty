<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$langs->load("moulatyPeche");

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

// ---------------------------
// Gestion pagination & recherche
// ---------------------------
$search_ref = GETPOST('search_ref', 'alpha');
$search_fourn = GETPOST('search_fourn', 'int');
$search_congelateur = GETPOST('search_congelateur', 'int');
$search_date = GETPOST('search_date', 'alpha');
$search_etat = GETPOST('search_etat', 'alpha');

$limit = GETPOST('limit', 'int') ?: 10;
$page = GETPOST('page', 'int') ?: 0;
$offset = $page * $limit;

// ---------------------------
// Requête principale avec filtres
// ---------------------------
$sql = "SELECT r.rowid, r.ref, r.poids, r.date_creation, r.montant, r.etat, r.fk_facture, r.fk_fournisseur, r.fk_congelateur, r.fk_entrepot,
        s.nom as fournisseur_name, s2.nom as congelateur_name, e.ref as entrepot_ref
        FROM ".MAIN_DB_PREFIX."pech_reception as r
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = r.fk_fournisseur
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s2 ON s2.rowid = r.fk_congelateur
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = r.fk_entrepot
        WHERE r.entity = ".$conf->entity;

// Filtrage
if ($search_ref !== '') {
    $sql .= " AND r.ref LIKE '%".$db->escape($search_ref)."%'";
}

if ($search_fourn !== '' && (int)$search_fourn >= 0) {
    $sql .= " AND r.fk_fournisseur = ".((int)$search_fourn);
}

if ($search_congelateur !== '' && (int)$search_congelateur >= 0) {
    $sql .= " AND r.fk_congelateur = ".((int)$search_congelateur);
}

if ($search_date !== '') {
    $sql .= " AND DATE(r.date_creation) = '".$db->escape($search_date)."'";
}

if ($search_etat !== '' && in_array($search_etat, array('0','1','2'))) {
    $sql .= " AND r.etat = ".((int)$search_etat);
}

if (!empty($entrepots_accessibles)) {
    $sql .= " AND r.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

$sql .= " ORDER BY r.rowid DESC LIMIT ".$limit." OFFSET ".$offset;
$resql = $db->query($sql);

// ---------------------------
// Affichage page
// ---------------------------
llxHeader('', $langs->trans("ListeReceptions"));

// Inclusion du CSS
print '<link rel="stylesheet" href="../reception_list_style.css">';

print '<div class="reception-list-container">';
print '<div class="reception-list-header">';
print load_fiche_titre($langs->trans("ListeReceptions"), '', 'object_list');
print '</div>';

// ---------------------------
// Formulaire recherche
// ---------------------------
print '<form method="GET" class="search-form">';
print '<table class="search-table">';

// Ligne des labels
print '<tr>';
print '<th>'.$langs->trans("Reference").'</th>';
print '<th>'.$langs->trans("Fournisseur").'</th>';
print '<th>'.$langs->trans("Congelateur").'</th>';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Etat").'</th>';
print '<th>'.$langs->trans("LignesPage").'</th>';
print '<th></th>';
print '</tr>';

// Ligne des champs
print '<tr>';
print '<td><input type="text" name="search_ref" class="search-input" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("RechercherReference").'"></td>';
//print '<td>'.$form->select_company($search_fourn, 'search_fourn', '', 1, '', 0, 1, 0).'</td>';
//print '<td>'.$form->select_company($search_congelateur, 'search_congelateur', '', 1, '', 0, 1, 0).'</td>';

print '<td>'.$form->select_company($search_fourn, 'search_fourn', '', 1, '', 0, 1).'</td>';
print '<td>'.$form->select_company($search_congelateur, 'search_congelateur', '', 1, '', 0, 1).'</td>';
print '<td><input type="date" name="search_date" class="search-input" value="'.dol_escape_htmltag($search_date).'"></td>';

$etat_array = array('' => $langs->trans("Tous"), 0 => $langs->trans("EnAttente"), 1 => $langs->trans("Valide"), 2 => $langs->trans("Annule"));
print '<td>'.$form->selectarray('search_etat', $etat_array, $search_etat, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

$limit_array = array(10=>10, 20=>20, 50=>50, 100=>100);
print '<td>'.$form->selectarray('limit', $limit_array, $limit, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

print '<td><input type="submit" class="search-button" value="'.$langs->trans("Rechercher").'"></td>';
print '</tr>';
print '</table>';
print '</form>';

// ---------------------------
// Tableau des résultats
// ---------------------------
if ($resql) {
    $num = $db->num_rows($resql);

    if ($num > 0) {
        print '<div class="results-table-container">';
        print '<table class="results-table">';
        print '<thead><tr>';
        print '<th class="col-ref"><i class="fa fa-barcode"></i> '.$langs->trans("Reference").'</th>';
        print '<th class="col-fournisseur"><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</th>';
        print '<th class="col-congelateur"><i class="fa fa-snowflake"></i> '.$langs->trans("Frigo").'</th>';
        print '<th class="col-entrepot"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</th>';
        print '<th class="col-date"><i class="fa fa-calendar"></i> '.$langs->trans("DateCreation").'</th>';
        print '<th class="col-poids"><i class="fa fa-weight-hanging"></i> '.$langs->trans("PoidsNet").'</th>';
        print '<th class="col-montant"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("Montant").'</th>';
        print '<th class="col-etat"><i class="fa fa-info-circle"></i> '.$langs->trans("Etat").'</th>';
        print '<th class="col-actions"><i class="fa fa-eye"></i> '.$langs->trans("Details").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        while ($obj = $db->fetch_object($resql)) {
            $etat_label = '';
            if ($obj->etat == 0) $etat_label = '<span class="badge badge-warning">'.$langs->trans("EnAttente").'</span>';
            elseif ($obj->etat == 1) $etat_label = '<span class="badge badge-success">'.$langs->trans("Valide").'</span>';
            else $etat_label = '<span class="badge badge-danger">'.$langs->trans("Annule").'</span>';

            print '<tr>';
            print '<td class="col-ref"><a href="detail_rec.php?id='.$obj->rowid.'" style="color:#3498db; font-weight:500;">'.dol_escape_htmltag($obj->ref).'</a></td>';
            print '<td class="col-fournisseur">'.dol_escape_htmltag($obj->fournisseur_name).'</td>';
            print '<td class="col-congelateur">'.dol_escape_htmltag($obj->congelateur_name).'</td>';
            print '<td class="col-entrepot">'.dol_escape_htmltag($obj->entrepot_ref).'</td>';
            print '<td class="col-date">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
            print '<td class="col-poids">'.price($obj->poids).'</td>';
            print '<td class="col-montant">'.price($obj->montant).'</td>';
            print '<td class="col-etat">'.$etat_label.'</td>';
            print '<td class="col-actions">';
            print '<a class="action-button" href="detail_rec.php?id='.$obj->rowid.'"><i class="fa fa-eye"></i> '.$langs->trans("VoirDetails").'</a>';
            print '</td>';
            print '</tr>';
        }

        print '</tbody>';
        print '</table>';
        print '</div>';

        // ---------------------------
        // Pagination
        // ---------------------------
        $total_sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."pech_reception as r WHERE r.entity = ".$conf->entity;
        if (!empty($entrepots_accessibles)) {
            $total_sql .= " AND r.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
        }
        
        $total_resql = $db->query($total_sql);
        $total_obj = $db->fetch_object($total_resql);
        $total_rows = $total_obj->total;
        $total_pages = ceil($total_rows / $limit);

        if ($total_pages > 1) {
            print '<div class="pagination">';
            for ($p = 0; $p < $total_pages; $p++) {
                $active = ($p == $page) ? ' style="font-weight:bold;"' : '';
                print '<a href="?page='.$p.'&limit='.$limit.'&search_ref='.urlencode($search_ref).'&search_fourn='.urlencode($search_fourn).'&search_congelateur='.urlencode($search_congelateur).'&search_date='.urlencode($search_date).'&search_etat='.urlencode($search_etat).'"'.$active.'>'.($p+1).'</a> ';
            }
            print '</div>';
        }

    } else {
        print '<div class="no-results">';
        print '<i class="fa fa-search"></i>';
        print '<h3>'.$langs->trans("AucunResultat").'</h3>';
        print '<p>'.$langs->trans("AucuneReceptionTrouvee").'</p>';
        print '</div>';
    }

} else {
    dol_print_error($db);
}

print '</div>'; // .reception-list-container

llxFooter();
$db->close();
?>