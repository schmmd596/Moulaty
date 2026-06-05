<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$langs->load("pech@pech");

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
$search_fourn = GETPOST('search_fourn', 'int');       // id fournisseur
$search_congelateur = GETPOST('search_congelateur', 'int'); // id congélateur
$search_date = GETPOST('search_date', 'alpha');
$search_etat = GETPOST('search_etat', 'alpha');

$limit = GETPOST('limit', 'int') ?: 10;
$page = GETPOST('page', 'int') ?: 0;
$offset = $page * $limit;

// ---------------------------
// Requête principale avec filtres
// ---------------------------
$sql = "SELECT r.rowid, r.ref,r.poids, r.date_creation, r.montant, r.etat, r.fk_facture, r.fk_fournisseur, r.fk_congelateur, r.fk_entrepot,
        s.nom as fournisseur_name, s2.nom as congelateur_name, e.ref as entrepot_ref
        FROM ".MAIN_DB_PREFIX."pech_reception as r
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = r.fk_fournisseur
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s2 ON s2.rowid = r.fk_congelateur
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = r.fk_entrepot
        WHERE r.entity = ".$conf->entity;

// Filtrer uniquement les entrepôts accessibles

// Filtrage
// Filtrage
if ($search_ref !== '') {
    $sql .= " AND r.ref LIKE '%".$db->escape($search_ref)."%'";
}

// Fournisseur : uniquement si ID >= 0
if ($search_fourn !== '' && (int)$search_fourn >= 0) {
    $sql .= " AND r.fk_fournisseur = ".((int)$search_fourn);
}

// Congélateur : uniquement si ID >= 0
if ($search_congelateur !== '' && (int)$search_congelateur >= 0) {
    $sql .= " AND r.fk_congelateur = ".((int)$search_congelateur);
}
/*if (!empty($entrepots_accessibles)) {
    $sql .= " AND r.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}*/
// Date : uniquement si non vide
if ($search_date !== '') {
    $sql .= " AND DATE(r.date_creation) = '".$db->escape($search_date)."'";
}

// État : uniquement si valeur valide (0,1,2)
if ($search_etat !== '' && in_array($search_etat, array('0','1','2'))) {
    $sql .= " AND r.etat = ".((int)$search_etat);
}
$sql .= " ORDER BY r.rowid DESC LIMIT ".$limit." OFFSET ".$offset;
$resql = $db->query($sql);

// ---------------------------
// Affichage page
// ---------------------------
llxHeader('', $langs->trans("Liste des réceptions"));

print '<div class="fichecenter">';
print load_fiche_titre($langs->trans("Liste des réceptions"), '', 'object_list');

// ---------------------------
// Formulaire recherche
// ---------------------------
print '<form method="GET" class="divsearch">';
print '<table class="noborder" style="width:100%;">';

// Ligne des labels
print '<tr>';
print '<th>Référence</th>';
print '<th>Fournisseur</th>';
print '<th>Congélateur</th>';
print '<th>Date</th>';
print '<th>État</th>';
print '<th>Lignes/page</th>';
print '<th></th>';
print '</tr>';

// Ligne des champs
print '<tr>';
print '<td><input type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td>'.$form->select_company($search_fourn, 'search_fourn', '', 1, '', 0, 1).'</td>';
print '<td>'.$form->select_company($search_congelateur, 'search_congelateur', '', 1, '', 0, 1).'</td>';
print '<td><input type="date" name="search_date" value="'.dol_escape_htmltag($search_date).'"></td>';

$etat_array = array('' => 'Tous', 0 => 'En attente', 1 => 'Validé', 2 => 'Annulé');
print '<td>'.$form->selectarray('search_etat', $etat_array, $search_etat).'</td>';

$limit_array = array(10=>10, 20=>20, 50=>50, 100=>100);
print '<td>'.$form->selectarray('limit', $limit_array, $limit).'</td>';

print '<td><input type="submit" class="button" value="Rechercher"></td>';
print '</tr>';
print '</table>';
print '</form><br>';

// ---------------------------
// Tableau des résultats
// ---------------------------
if ($resql) {
    $num = $db->num_rows($resql);

    print '<style>
    .liste_titre {
        background: linear-gradient(90deg, #0073e6, #00bcd4);
        color: white;
        font-weight: bold;

        text-align: center;
    }
    .liste_titre th {
        padding: 10px;
        font-size: 14px;
    }
    .liste_titre th i {
        margin-right: 6px;
        color: #131212ff;
    }
    .liste_titre th:hover {
        background-color: rgba(255,255,255,0.15);
        transition: 0.3s;
    }
</style>';

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th><i class="fa fa-barcode"></i> '.$langs->trans("Référence").'</th>';
print '<th><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</th>';
print '<th><i class="fa fa-snowflake"></i> '.$langs->trans("Frigo").'</th>';
print '<th><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepôt").'</th>';
print '<th><i class="fa fa-calendar"></i> '.$langs->trans("Date création").'</th>';
print '<th class="right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("Poids").'</th>';
print '<th class="right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("Montant").'</th>';
print '<th class="center"><i class="fa fa-info-circle"></i> '.$langs->trans("État").'</th>';
print '<th class="center"><i class="fa fa-eye"></i> '.$langs->trans("Détails").'</th>';
print '</tr>';

    while ($obj = $db->fetch_object($resql)) {
        $etat_label = '';
        if ($obj->etat == 0) $etat_label = '<span class="badge badge-warning">En attente</span>';
        elseif ($obj->etat == 1) $etat_label = '<span class="badge badge-success">Validé</span>';
        else $etat_label = '<span class="badge badge-danger">Annulé</span>';

        print '<tr class="oddeven">';
        print '<td><a href="detail_rec.php?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
        print '<td>'.dol_escape_htmltag($obj->fournisseur_name).'</td>';
        print '<td>'.dol_escape_htmltag($obj->congelateur_name).'</td>';
        print '<td>'.dol_escape_htmltag($obj->entrepot_ref).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
        print '<td class="right">'.price($obj->poids).'</td>';
        print '<td class="right">'.price($obj->montant).'</td>';
        print '<td class="center">'.$etat_label.'</td>';

        print '<td class="center">';
        print '<a class="butAction" href="detail_rec.php?id='.$obj->rowid.'">👁️ '.$langs->trans("details").'</a>';
        
        /*if (empty($obj->fk_facture)) {
            print '<a class="butAction" href="create_facture.php?id='.$obj->rowid.'">'.$langs->trans("Facturer").'</a>';
        } else {
            print '<a class="butAction" href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$obj->fk_facture.'">'.$langs->trans("Voir facture").'</a>';
        }*/
        print '</td>';
        print '</tr>';
    }
    print '</table>';

    // ---------------------------
    // Pagination
    // ---------------------------
    $total_sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."pech_reception as r WHERE r.entity = ".$conf->entity;
    $total_resql = $db->query($total_sql);
    $total_obj = $db->fetch_object($total_resql);
    $total_rows = $total_obj->total;
    $total_pages = ceil($total_rows / $limit);

    print '<div class="center">';
    for ($p = 0; $p < $total_pages; $p++) {
        $active = ($p == $page) ? ' style="font-weight:bold;"' : '';
        print '<a href="?page='.$p.'&limit='.$limit.'&search_ref='.urlencode($search_ref).'&search_fourn='.urlencode($search_fourn).'&search_congelateur='.urlencode($search_congelateur).'&search_date='.urlencode($search_date).'&search_etat='.urlencode($search_etat).'"'.$active.'>'.($p+1).'</a> ';
    }
    print '</div>';

} else {
    dol_print_error($db);
}

llxFooter();
$db->close();
?>
