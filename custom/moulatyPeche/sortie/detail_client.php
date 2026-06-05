<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

$langs->load("products");
$form = new Form($db);

$id = GETPOST('id', 'int');
if (empty($id)) accessforbidden("ID manquant");

$sql = "SELECT s.*, u.firstname, u.lastname
        FROM ".MAIN_DB_PREFIX."pech_sortie AS s
        LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = s.fk_user
        WHERE s.rowid = ".(int)$id;
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) accessforbidden("Bon de sortie introuvable");
$sortie = $db->fetch_object($resql);

$sql_prod = "SELECT p.rowid, pr.ref, pr.label, p.nb_carton, p.poids_total, p.pu, p.total_line
             FROM ".MAIN_DB_PREFIX."pech_sortiedetprod AS p
             LEFT JOIN ".MAIN_DB_PREFIX."product AS pr ON pr.rowid = p.fk_product
             WHERE p.fk_sortie = ".(int)$id;
$resql_prod = $db->query($sql_prod);

llxHeader('', 'Bon de sortie - Détail');

$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($sortie->fk_entrepot_source);

$entrepot_dest_name = '';
if ($sortie->type == 0 && !empty($sortie->fk_entrepot_dest)) {
    $entrepot_dest = new Entrepot($db);
    $entrepot_dest->fetch($sortie->fk_entrepot_dest);
    $entrepot_dest_name = $entrepot_dest->ref.' - '.$entrepot_dest->label;
}

$client_name = '';
if ($sortie->type == 1 && !empty($sortie->fk_client)) {
    $soc = new Societe($db);
    $soc->fetch($sortie->fk_client);
    $client_name = $soc->name;
}

// ───────────────────────────────
// 🔹 STYLE GÉNÉRAL
// ───────────────────────────────
print '<style>
    .fiche-container {
        max-width: 1100px;
        margin: 40px auto;
        background: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 3px 15px rgba(0,0,0,0.15);
        font-family: "Segoe UI", sans-serif;
    }
    h3 {
        color: #333;
        margin-top: 25px;
        border-bottom: 2px solid #eee;
        padding-bottom: 5px;
    }
    table.border {
        width: 100%;
        border-collapse: collapse;
        background: #fafafa;
    }
    table.border td {
        padding: 10px 8px;
        border-bottom: 1px solid #ddd;
    }
    table.border td:first-child {
        background: #f6f6f6;
        font-weight: bold;
        width: 30%;
        color: #444;
    }
    .liste_titre {
        background: #202124;
        color: #fff;
        font-weight: bold;
        text-transform: uppercase;
    }
    table.noborder td {
        padding: 8px;
        border-bottom: 1px solid #e5e5e5;
    }
    tr.oddeven:nth-child(odd) {
        background: #f9f9f9;
    }
    tr.oddeven:nth-child(even) {
        background: #ffffff;
    }
    .tabsAction {
        text-align: center;
        margin-top: 30px;
    }
    
    .status {
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: bold;
        display: inline-block;
    }
    .status.red { background:#fdecea; color:#d93025; }
    .status.green { background:#e6f4ea; color:#188038; }
</style>';

// ───────────────────────────────
// 🔹 FICHE PRINCIPALE
// ───────────────────────────────
print '<div class="fiche-container">';
print load_fiche_titre('📦 Détail du Bon de sortie : <span style="color:#000;">'.$sortie->ref.'</span>', '', 'object_out');

print '<table class="border centpercent">';
print '<tr><td>🆔 Référence</td><td>'.$sortie->ref.'</td></tr>';
print '<tr><td>📋 Type</td><td>'.($sortie->type == 0 ? 'Transfert interne' : 'Vente client').'</td></tr>';
print '<tr><td>🏭 Entrepôt source</td><td>'.$entrepot_src->ref.' - '.$entrepot_src->label.'</td></tr>';
if ($sortie->type == 0) print '<tr><td>🏬 Entrepôt destination</td><td>'.$entrepot_dest_name.'</td></tr>';
else print '<tr><td>👤 Client</td><td>'.$client_name.'</td></tr>';
print '<tr><td>📅 Date création</td><td>'.dol_print_date($db->jdate($sortie->date_creation), 'dayhour').'</td></tr>';
print '<tr><td>👷 Utilisateur</td><td>'.$sortie->firstname.' '.$sortie->lastname.'</td></tr>';

/*$color_class = ($sortie->statut == 0) ? 'red' : 'green';
$label_statut = ($sortie->statut == 0) ? 'Brouillon' : 'Validé';
print '<tr><td>🔖 Statut</td><td><span class="status '.$color_class.'">'.$label_statut.'</span></td></tr>';
*/
switch ($sortie->statut) {
    case 0:
        $statutLabel = '<span class="badge badge-status0" style="background:#e2fa0cff !important;color:#000;">Brouillon</span>';
        break;
    case 1:
        $statutLabel = '<span class="badge badge-status1" style="background:#05f739ff !important;color:#fff;">Validé</span>';
        break;
    case 2:
        $statutLabel = '<span class="badge badge-status2" style="background:#fa1c0cff !important;color:#fff;">Transféré</span>';
        break;
    default:
        $statutLabel = '<span class="badge small-muted" style="background:#ccc !important;color:#000;">Inconnu</span>';
        break;
}

print '<tr><td>🔖 Statut</td><td>'.$statutLabel.'</td></tr>';

print '<tr><td>💬 Commentaire</td><td>'.nl2br(dol_escape_htmltag($sortie->commentaire)).'</td></tr>';
print '<tr><td>⚖️ Frais </td><td>'.price($sortie->total_frais).' </td></tr>';
print '<tr><td>⚖️ Poids total</td><td>'.price($sortie->poids_total).' kg</td></tr>';
print '<tr><td>📦 Nombre de cartons</td><td>'.$sortie->nb_carton_total.'</td></tr>';
print '</table>';

// ───────────────────────────────
// 🔹 PRODUITS SORTIS
// ───────────────────────────────
print '<h3>📋 Produits sortis</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Produit</td><td align="center">Nb Cartons</td><td align="center">Poids (kg)</td><td align="center">Valeur totale</td></tr>';

if ($resql_prod && $db->num_rows($resql_prod) > 0) {
    $total_valeur = 0;
    while ($obj = $db->fetch_object($resql_prod)) {
        $sql_cartons = "SELECT c.rowid, c.poids, (c.prix_moyen + c.frais) AS valeur
                        FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton AS sc
                        LEFT JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.rowid = sc.fk_carton
                        WHERE sc.fk_sortiedetprod = ".$obj->rowid;
        $res_cartons = $db->query($sql_cartons);
        $valeur = 0;
        if ($res_cartons && $db->num_rows($res_cartons) > 0) {
            while ($c = $db->fetch_object($res_cartons)) {
                $valeur += $c->valeur;
            }
        }
        $total_valeur += $valeur;
        print '<tr class="oddeven">';
        print '<td>'.$obj->ref.' - '.$obj->label.'</td>';
        print '<td align="center">'.$obj->nb_carton.'</td>';
        print '<td align="center">'.price($obj->poids_total).'</td>';
        print '<td align="center"><strong>'.price($valeur).' '.$conf->currency.'</strong></td>';
        print '</tr>';
    }
    print '<tr style="background:#f6f6f6;font-weight:bold;"><td colspan="3" align="right">TOTAL :</td><td align="center">'.price($total_valeur).' '.$conf->currency.'</td></tr>';
} else {
    print '<tr><td colspan="4" align="center"><i>Aucun produit</i></td></tr>';
}
print '</table>';

// ───────────────────────────────
// 🔹 ACTIONS
// ───────────────────────────────
print '<div class="tabsAction">';

print '<a class="butAction" href="list.php">⬅️ Retour à la liste</a>';

if ($sortie->statut == 0) {
    //print '<a class="butAction" href="confirm.php?id='.$id.'">✏️ Modifier</a>';
    print '<a class="butAction" href="delete_sortie.php?id='.$id.'" onclick="return confirm(\'Confirmer la suppression ?\')">🗑️ Supprimer</a>';
    print '<a class="butAction" style="background:#188038;" href="validate.php?id='.$id.'">✅ Valider Sortie Interne</a>';
  
}else{
    if (!empty($sortie->fk_facture)) {
        // Facture déjà créée → bouton "Voir facture"
        print '<a class="butAction" href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$sortie->fk_facture.'"> <i class="fa fa-eye"></i> VOIR FACTURE</a>';
    } else {
        // Facture non créée → bouton "Facturer"
        print '<a class="butAction" href="facturer.php?id_sortie='.$sortie->rowid.'">FACTURER</a>';
    }

    if ($sortie->fk_bonsortie > 0) {
        // 🔹 Le bon existe déjà → bouton "Voir le bon"
        $url_bon = 'bonsortie_document.php?id='.$sortie->fk_bonsortie;
        print '<a class="butAction" href="'.$url_bon.'">
                <i class="fa fa-eye"></i> Voir le bon de sortie
            </a>';
    } else {
        // 🔹 Aucun bon → bouton "Créer le bon"
        //$url_creer = dol_buildpath('/custom/peche/bonsortie/create_bon.php?id_sortie='.$id, 1);
        $url_creer = 'bon_sortie.php?id_sortie='.$id;
        print '<a class="butAction" href="'.$url_creer.'">
                <i class="fa fa-plus-circle"></i> Créer le bon de sortie
            </a>';
    }
}

// 🔹 Vérification si le transfert est possible
$can_transfer = ($sortie->statut == 1 && !empty($sortie->fk_bonsortie) && !empty($sortie->fk_facture));

if ($can_transfer) {
    // Bouton actif
    print '<a class="butAction" style="background:#007bff;" href="validate_transfere.php?id='.$id.'" 
       onclick="return confirm(\'⚠️ Êtes-vous sûr de vouloir transférer cette sortie vers l\'entrepôt ?\');">
       🚚 Facturer Pour le client
       </a>';
} else {
    // Bouton désactivé avec notification
    $message = "Impossible de transférer : la sortie doit être validée, le bon et la facture créés.";
    print '<a class="butAction" style="background:#cccccc; cursor:not-allowed;" href="javascript:void(0);" onclick="alert(\''.$message.'\')">
            🚚 Facturer Pour le client
           </a>';
}

print '</div><br>';

print '<br><h3 style="color:#0B5394; font-weight:bold;">📄 Factures liées à cette sortie </h3>';

$sqlFact = "SELECT rowid, ref,fk_soc, ref_supplier, total_ht, datef 
            FROM ".MAIN_DB_PREFIX."facture_fourn 
            WHERE ref_supplier LIKE '".$db->escape($sortie->ref)."%' 
            ORDER BY datef DESC";

$resFact = $db->query($sqlFact);

if ($resFact && $db->num_rows($resFact) > 0) {
    print '<table style="width:100%; border-collapse:collapse; margin-top:10px;">';
    print '<tr style="background-color:#D9E1F2; text-align:left; font-weight:bold;">';
    print '<th style="padding:8px; border:1px solid #ccc;">Réf. Dolibarr</th>';
    print '<th style="padding:8px; border:1px solid #ccc;">Réf. Fournisseur</th>';
    print '<th style="padding:8px; border:1px solid #ccc;">Fournisseur</th>';
    print '<th style="padding:8px; border:1px solid #ccc;">Date</th>';
    print '<th style="padding:8px; border:1px solid #ccc; text-align:right;">Total HT</th>';
    print '</tr>';

    $rowColor = false;
    while ($obj = $db->fetch_object($resFact)) {
        $bg = $rowColor ? '#F2F2F2' : '#FFFFFF';
        $rowColor = !$rowColor;

        // Récupérer le nom du fournisseur
        $sqlFourn = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".((int)$obj->fk_soc);
        $resFourn = $db->query($sqlFourn);
        $fournNom = ($resFourn && $db->num_rows($resFourn) > 0) ? $db->fetch_object($resFourn)->nom : '-';

        $url = DOL_URL_ROOT.'/fourn/facture/card.php?id='.$obj->rowid;
        print '<tr style="background-color:'.$bg.';">';
        print '<td style="padding:6px; border:1px solid #ccc;"><a href="'.$url.'" style="text-decoration:none; color:#1155CC;">'.$obj->ref.'</a></td>';
        print '<td style="padding:6px; border:1px solid #ccc;">'.$obj->ref_supplier.'</td>';
        print '<td style="padding:6px; border:1px solid #ccc;">'.$fournNom.'</td>';
        print '<td style="padding:6px; border:1px solid #ccc;">'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
        print '<td style="padding:6px; border:1px solid #ccc; text-align:right;">'.price($obj->total_ht).'</td>';
        print '</tr>';
    }

    print '</table>';
} else {
    print '<p style="color:#999; font-style:italic;">Aucune facture liée à ce lot.</p>';
}

print '</div>';
print '</div>';

llxFooter();
$db->close();
?>
