<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main']);
$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DU LOT
// ============================================================================
$id_lot = GETPOST('id', 'int');
if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// 🔹 Requête pour récupérer la valeur totale du lot avec frais inclus
$sqlll = "
SELECT 
    SUM(
        (
            SELECT SUM(p.prix_moyen + IFNULL(bm.total_frais / total_plats.total_plats, 0))
            FROM ".MAIN_DB_PREFIX."pech_plat p
            JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p.fk_misenplat
            JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat bm ON bm.rowid = mp.fk_bon_misenplat
            JOIN (
                SELECT fk_bon_misenplat, SUM(nombre_plat) AS total_plats
                FROM ".MAIN_DB_PREFIX."pech_misenplat
                GROUP BY fk_bon_misenplat
            ) AS total_plats ON total_plats.fk_bon_misenplat = bm.rowid
            WHERE p.fk_carton = c.rowid
        )
    ) AS valeur_lot
FROM ".MAIN_DB_PREFIX."pech_lot l
JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.fk_lot = l.rowid
JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
WHERE l.rowid = ".((int)$id_lot)."
GROUP BY l.rowid, l.ref
";

$reslll = $db->query($sqlll);
$valeurLot = 0;
if ($reslll && $db->num_rows($reslll) > 0) {
    $obj = $db->fetch_object($reslll);
    $valeurLot = $obj->valeur_lot;
} else {
$valeurLot = 0;
}


// --- Étape 2 : Valeur de la facture fournisseur (si associée)
$sqlFacture = "
    SELECT SUM(total_ht) AS total_facture
    FROM ".MAIN_DB_PREFIX."facture_fourn_det
    WHERE fk_facture_fourn = (
        SELECT fk_facture FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$id_lot)."
    )
";
$resFacture = $db->query($sqlFacture);
$totalFacture = 0;
if ($resFacture && $db->num_rows($resFacture) > 0) {
    $obj = $db->fetch_object($resFacture);
    $totalFacture = $obj->total_facture ?: 0;
}

// --- Étape 3 : Valeur du bon d’entrée (produits + services)
$sqlBonEntree = "
    SELECT 
        COALESCE(SUM(ds.total), 0) AS valeur_services
    FROM ".MAIN_DB_PREFIX."pech_bonentree_detserv ds
    WHERE ds.fk_bonentree = (
        SELECT fk_bonentree 
        FROM ".MAIN_DB_PREFIX."pech_lot 
        WHERE rowid = ".((int)$id_lot)."
    )
";
$resBonEntree = $db->query($sqlBonEntree);
$totalBonEntree = 0;
if ($resBonEntree && $db->num_rows($resBonEntree) > 0) {
    $obj = $db->fetch_object($resBonEntree);
    $totalBonEntree =  $obj->valeur_services ?: 0;
}

// --- Étape 4 : Calcul final
$valeurLotTotale = $valeurLot + $totalFacture + $totalBonEntree;


// --- Étape 5 : Mise à jour du lot avec la nouvelle valeur
/*$sqlUpdate = "
    UPDATE ".MAIN_DB_PREFIX."pech_lot 
    SET total_frais = ".price2num($valeurLotTotale)."
    WHERE rowid = ".((int)$id_lot);
if (!$db->query($sqlUpdate)) {
    throw new Exception('Erreur lors de la mise à jour de la valeur totale du lot.');
}*/
// ============================================================================
// 🔹 INFORMATIONS DU LOT
// ============================================================================
$sqlLot = "SELECT l.*, u.firstname, u.lastname,e.rowid as fk_entrepot, e.ref AS ref_entrepot
           FROM ".MAIN_DB_PREFIX."pech_lot AS l
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = l.fk_user_create
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
           WHERE l.rowid = ".(int)$id_lot;

$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
$lot = $db->fetch_object($resLot);

// ============================================================================
// 🔹 LIGNES DE DÉTAIL DU LOT
// ============================================================================
$sqlDet = "SELECT 
                d.rowid AS lotdet_id, 
                d.nb_carton, 
                d.poids_carton AS poids_par_carton, 
                d.plat_carton AS plat_par_carton, 
                d.commentaire,
                p.label AS produit
           FROM ".MAIN_DB_PREFIX."pech_lotdet AS d
           LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
           WHERE d.fk_lot = ".(int)$id_lot."
           ORDER BY d.rowid ASC";

$resDet = $db->query($sqlDet);
$lines = [];
while ($obj = $db->fetch_object($resDet)) $lines[] = $obj;

// ============================================================================
// 🔹 AFFICHAGE
// ============================================================================
llxHeader('', "Détails du lot ".$lot->ref);
// --- Informations générales du lot ---
// Version améliorée : style moderne noir/blanc avec icônes et effet carte
print '<div style="
    display:flex;
    flex-wrap:wrap;
    gap:20px;
    margin-bottom:25px;
">';

// Carte principale
print '<div style="
    flex:1;
    min-width:320px;
    background:linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
    border:1px solid #ddd;
    border-radius:10px;
    padding:22px 25px;
    box-shadow:0 2px 8px rgba(0,0,0,0.07);
    transition:transform 0.2s ease, box-shadow 0.2s ease;
"
onmouseover="this.style.transform=\'translateY(-3px)\'; this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.12)\';"
onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.07)\';"
>';

print '<h3 style="
    margin-top:0;
    font-weight:700;
    font-size:18px;
    color:#0B5394;
    display:flex;
    align-items:center;
    border-bottom:2px solid #0B5394;
    padding-bottom:8px;
">
<i class="fa fa-box" style="margin-right:10px; color:#0B5394;"></i> Informations du Lot
</h3>';

// Données à afficher
$infos = [
    ['icon'=>'fa-hashtag','label'=>'Référence','value'=>'<b style="color:#0B5394;">'.dol_escape_htmltag($lot->ref).'</b>'],
    ['icon'=>'fa-dollar-sign','label'=>'Frais','value'=>'<b>'.price($lot->total_frais).' '.$conf->currency.'</b>'],
    ['icon'=>'fa-user','label'=>'Créé par','value'=>dol_escape_htmltag($lot->firstname.' '.$lot->lastname)],
    ['icon'=>'fa-calendar-alt','label'=>'Date création','value'=>dol_print_date($db->jdate($lot->date_creation), 'dayhour')],
    ['icon'=>'fa-warehouse','label'=>'Entrepôt','value'=>dol_escape_htmltag($lot->ref_entrepot ?? '<span style="color:#888;">—</span>')],
    ['icon'=>'fa-comment-alt','label'=>'Commentaire','value'=>nl2br(dol_escape_htmltag($lot->commentaire))],
    ['icon'=>'fa-info-circle','label'=>'Statut','value'=>'<span style="
        font-weight:bold;
        color:'.($lot->statut ? '#2E8B57' : '#B22222').';
        background-color:'.($lot->statut ? 'rgba(46,139,87,0.1)' : 'rgba(178,34,34,0.1)').';
        padding:3px 8px;
        border-radius:6px;
    ">'.($lot->statut ? '✅ Validé' : '🕓 Brouillon').'</span>'],
];

// Tableau stylé
print '<table style="
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
">';

foreach($infos as $info){
    print '<tr style="border-bottom:1px solid #eee;">';
    print '<td style="
        padding:10px 6px;
        width:35%;
        color:#444;
        font-weight:600;
        vertical-align:top;
    ">
        <i class="fa '.$info['icon'].'" style="margin-right:8px; color:#0B5394;"></i>'.$info['label'].'
    </td>';
    print '<td style="
        padding:10px 6px;
        color:#111;
        line-height:1.5;
    ">'.$info['value'].'</td>';
    print '</tr>';
}

print '</table>';
print '</div>'; // fin carte

//print '</div><br>';
print '</div><br>';

// --- Styles supplémentaires ---
print '<style>
h3 { font-size:1.3em; }
i.fa { color:#111; }
table td { vertical-align:top; }
</style>';

// --- Détails des lignes ---
// --- Détails des lignes ---
print '<br><h3 style="
    color:#0B5394;
    font-weight:bold;
    border-left:5px solid #0B5394;
    padding-left:10px;
    margin-bottom:10px;
    font-size:18px;
">📄 Détail du lot</h3>';

print '<table class="noborder centpercent" style="
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    background-color: #fff;
">';

print '<tr style="
    background-color:#0B5394;
    color: #000000ff;
    text-align:center;
    font-weight:bold;
    height:40px;
">
    <th>🐟 Produit</th>
    <th>Nb Cartons</th>
    <th>Poids / Carton (kg)</th>
    <th>Plat / Carton</th>
    <th>Poids total</th>
</tr>';

$total_cartons = 0;
$total_poids = 0;
$alternate = false;

foreach ($lines as $line) {
    $total_cartons += $line->nb_carton;
    $total_poids += $line->nb_carton * $line->poids_par_carton;

    $bg = $alternate ? '#f9f9f9' : '#ffffff';
    $alternate = !$alternate;

    print '<tr style="background-color:'.$bg.'; text-align:center; height:35px;">';
    print '<td style="text-align:left; padding-left:10px;">'.dol_escape_htmltag($line->produit).'</td>';
    print '<td>'.$line->nb_carton.'</td>';
    print '<td>'.price($line->poids_par_carton).'</td>';
    print '<td>'.$line->plat_par_carton.'</td>';
    print '<td style="font-weight:bold;">'.price($line->nb_carton * $line->poids_par_carton).'</td>';
    print '</tr>';
}

// --- Ligne Total ---
print '<tr style="
    font-weight:bold;
    background:linear-gradient(to right, #dfe9f3, #ffffff);
    border-top:2px solid #0B5394;
    height:40px;
">';
print '<td style="text-align:left; padding-left:10px;">🧮 Total :</td>';
print '<td>'.$total_cartons.' cartons</td>';
print '<td colspan="2"></td>';
print '<td style="color:#0B5394;">'.price($total_poids).' kg</td>';
print '</tr>';

print '</table><br>';

// ============================================================================
// 🔹 BOUTONS D’ACTION
// ============================================================================
print '<div class="center">';
$token = newToken();

if ($lot->statut == 0) {
    // Lot en brouillon : modification / suppression / validation autorisées
    print '<a class="button" href="../tunnel/sortie_tunnel_create.php?action=modify&id_lot='.$id_lot.'&fk_entrepot='.$lot->fk_entrepot.'">Modifier</a> ';
    print '<a class="butActionDelete" href="delete_lot.php?action=delete&id='.$id_lot.'&token='.$token.'" onclick="return confirm(\'Confirmer la suppression du lot ?\')">Supprimer</a> ';
    print '<a class="button" href="validation.php?action=validate&id='.$id_lot.'" onclick="return confirm(\'Confirmer la validation ? Le stock des cartons sera mis à jour.\')">Valider</a>';
} else {
    // Lot validé : possibilité de le rouvrir
    if ($lot->fk_bonentree>0){
        print '<a class="button" href="doc_bon.php?id='.$lot->fk_bonentree.'" >VOIR LE BON ACTUEL</a>';

    }else{
        print '<a class="button" href="bon_lot.php?id_lot='.$id_lot.'&fk_entrepot='.$lot->fk_entrepot.'" >CREER UN BON D\'ENTREE</a>';
    
    }
    if ($lot->fk_facture>0){
        print '<a class="button" href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture.'" >VOIR La FACTURE</a>';

    }else{
        print '<a class="button" href="facturer.php?id_lot='.$id_lot.'" > Facturer </a>';

    }
    
    //print '<a class="button" href="lot_actions.php?action=reopen&id='.$id_lot.'">Rouvrir le lot</a>';
}
// --- Affichage des factures liées à ce lot ---



print '</div><br>';

print '<br><h3 style="color:#0B5394; font-weight:bold;">📄 Factures liées à ce lot</h3>';

$sqlFact = "SELECT rowid, ref,fk_soc, ref_supplier, total_ht, datef 
            FROM ".MAIN_DB_PREFIX."facture_fourn 
            WHERE ref_supplier LIKE '".$db->escape($lot->ref)."%' 
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

// ============================================================================
// 🔹 CARTONS ASSOCIÉS AU LOT
// ============================================================================
/*$sql_cartons = "SELECT 
                    ld.rowid AS lotdet_id,
                    p.label AS product_label,
                    c.rowid AS carton_id,
                    c.ref_carton,
                    c.poids,
                    c.nb_plat,
                    c.statut,
                    c.date_creation
                FROM ".MAIN_DB_PREFIX."pech_lotdet ld
                LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
                LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = c.fk_product
                WHERE ld.fk_lot = ".(int)$id_lot."
                ORDER BY ld.rowid ASC, c.date_creation ASC";

$res_cartons = $db->query($sql_cartons);

if ($res_cartons && $db->num_rows($res_cartons) > 0) {
    print load_fiche_titre("Cartons générés");
    print '<table class="liste centpercent">';
    print '<tr class="liste_titre">
            <th>Réf. Carton</th>
            <th>Produit</th>
            <th>Poids (kg)</th>
            <th>Nb Plats</th>
            <th>Statut</th>
            <th>Date création</th>
          </tr>';

    while ($carton = $db->fetch_object($res_cartons)) {
        $statut_label = ($carton->statut == 0) ? '🟢 En stock' : '🔴 Sorti';
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($carton->ref_carton ?? '-').'</td>';
        print '<td>'.dol_escape_htmltag($carton->product_label).'</td>';
        print '<td >'.price($carton->poids).'</td>';
        print '<td>'.$carton->nb_plat.'</td>';
        print '<td >'.$statut_label.'</td>';
        print '<td>'.dol_print_date($db->jdate($carton->date_creation), 'dayhour').'</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<p class="opacitymedium center">Aucun carton généré pour ce lot.</p>';
}
*/

// ============================================================================
// 🔹 CARTONS ASSOCIÉS AU LOT (Groupés par produit)
// ============================================================================
$sql_cartons = "SELECT 
                    p.rowid AS product_id,
                    p.label AS product_label,
                    c.rowid AS carton_id,
                    c.ref_carton,
                    c.poids,
                    c.nb_plat,
                    c.statut,
                    c.date_creation
                FROM ".MAIN_DB_PREFIX."pech_lotdet ld
                LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
                LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = c.fk_product
                WHERE ld.fk_lot = ".(int)$id_lot."
                ORDER BY p.label ASC, c.date_creation ASC";

$res_cartons = $db->query($sql_cartons);

if ($res_cartons && $db->num_rows($res_cartons) > 0) {
    print load_fiche_titre("Cartons générés par produit");

    $current_product = null;
    print '<table class="liste centpercent">';
    $i=1;

    while ($carton = $db->fetch_object($res_cartons)) {
        // 🐟 Nouveau produit → afficher un en-tête
        if ($current_product !== $carton->product_id) {
            // Si ce n’est pas le premier produit, fermer le tableau précédent
            

            $current_product = $carton->product_id;
            print '<tr class="liste_titre">';
            print '<th colspan="6" style="background:#eef;font-weight:bold;">🐟 Produit : '.dol_escape_htmltag($carton->product_label ?? 'Inconnu').'</th>';
            print '</tr>';
            print '<tr class="liste_titre">
                    <th>Réf. Carton</th>
                    <th>Poids (kg)</th>
                    <th>Nb Plats</th>
                    <th>Statut</th>
                    <th>Date création</th>
                  </tr>';
        }

        // Ligne du carton
        $statut_label = ($carton->statut == 0) ? '🟢 En stock' : '🔴 Sorti';
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($carton->ref_carton ?? $i).'</td>';
        print '<td>'.price($carton->poids).'</td>';
        print '<td>'.$carton->nb_plat.'</td>';
        print '<td>'.$statut_label.'</td>';
        print '<td>'.dol_print_date($db->jdate($carton->date_creation), 'dayhour').'</td>';
        print '</tr>';
        $i++;
    }

    print '</table>';
} else {
    print '<p class="opacitymedium center">Aucun carton généré pour ce lot.</p>';
}

llxFooter();
$db->close();
?>

