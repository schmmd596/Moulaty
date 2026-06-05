<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $user, $langs, $conf;

$langs->load("bills");
$langs->load("main");

$form = new Form($db);
if (empty($user->rights->facture->creer)) accessforbidden();

// 🔹 Récupérer la sortie
$id_sortie = GETPOST('id', 'int');
if (!$id_sortie) exit("⚠ Identifiant de sortie manquant.");

// Sortie
$sql_sortie = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid=".$id_sortie;
$res_sortie = $db->query($sql_sortie);
$sortie = $db->fetch_object($res_sortie);
if (!$sortie) exit("Sortie introuvable");

// Client
$soc = new Societe($db);
$soc->fetch($sortie->fk_client);

// Produits
$sql_prods = "SELECT p.rowid, p.fk_product, p.nb_carton, p.poids_total, pr.label 
              FROM ".MAIN_DB_PREFIX."pech_sortiedetprod p
              LEFT JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid = p.fk_product
              WHERE fk_sortie=".$id_sortie;
$res_prods = $db->query($sql_prods);
$produits = [];
while($obj = $db->fetch_object($res_prods)) {
    $sql_cartons = "SELECT SUM(poids) as total_poids FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton 
                    WHERE fk_sortiedetprod=".$obj->rowid;
    $res_cartons = $db->query($sql_cartons);
    $poids_total = $db->fetch_object($res_cartons)->total_poids;

    $produits[] = [
        'rowid' => $obj->fk_product,
        'label' => $obj->label,
        'poids_total' => $poids_total,
        'nb_carton' => $obj->nb_carton
    ];
}

// 🔹 Devises
$sqlcur = "SELECT c.rowid, c.code, c.name, r.rate
           FROM ".MAIN_DB_PREFIX."multicurrency AS c
           LEFT JOIN ".MAIN_DB_PREFIX."multicurrency_rate AS r 
             ON r.fk_multicurrency = c.rowid
           WHERE r.date_sync = (
                 SELECT MAX(r2.date_sync)
                 FROM ".MAIN_DB_PREFIX."multicurrency_rate AS r2
                 WHERE r2.fk_multicurrency = c.rowid
             )
           ORDER BY c.name";
$rescur = $db->query($sqlcur);
$currencies = [];
if ($rescur) {
    while ($c = $db->fetch_object($rescur)) {
        $currencies[$c->code] = ['label'=>$c->name,'rate'=>(float)$c->rate];
    }
}

llxHeader('', 'Créer facture sortie');
print load_fiche_titre("Créer facture pour sortie : ".$sortie->ref);

// 🔹 Infos sortie
print '<table class="border centpercent" style="margin-bottom:15px;">';
print '<tr><td><i class="fa fa-user"></i> Client</td><td>'.$soc->nom.'</td></tr>';
print '<tr><td><i class="fa fa-calendar"></i> Date création</td><td>'.$sortie->date_creation.'</td></tr>';
//print '<tr><td><i class="fa fa-warehouse"></i> Entrepôt source</td><td>'.$sortie->fk_entrepot_source.'</td></tr>';

$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($sortie->fk_entrepot_source);



print '<tr><td><i class="fa fa-warehouse"></i> Entrepôt source</td><td>'.$entrepot_src->ref.'</td></tr>';


if($sortie->fk_entrepot_dest) print '<tr><td><i class="fa fa-truck"></i> Entrepôt destination</td><td>'.$sortie->fk_entrepot_dest.'</td></tr>';
if($sortie->commentaire) print '<tr><td><i class="fa fa-comment"></i> Commentaire</td><td>'.$sortie->commentaire.'</td></tr>';
print '</table>';

// 🔹 Formulaire facture
print '<form method="POST" action="facture_vente_process.php" id="form_facture">';
print '<input type="hidden" name="id_sortie" value="'.$id_sortie.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

// Multi-devise
print '<button type="button" id="btn_multidevise" class="button">💱 Multi-devise</button><br><br>';
print '<div id="divise_section" style="display:none;">';
//print '<label>Devise : </label>';
print '<div style="margin:15px 0;">';
print '<label for="devise" style="font-weight:bold; color:#333;">';
print '<i class="fa fa-money-bill-wave" style="color:#28a745; margin-right:6px;"></i> Sélectionner la devise';
print '</label><br>';

print '<select name="devise" id="devise" 
        style="
            padding:8px 12px;
            border:1px solid #ccc;
            border-radius:6px;
            background-color:#f9f9f9;
            font-size:14px;
            min-width:260px;
            transition:all 0.2s ease;
        "
        title="Choisissez la devise de facturation (la monnaie principale sera utilisée si rien n’est sélectionné)">
        <option value="" selected disabled>-- Devise (Monnaie principale par défaut) --</option>';

foreach($currencies as $code => $cur) {
    print '<option value="'.$code.'">'.$cur['label'].' ('.$code.') | valeur:('.$cur['rate'].')</option>';
}

print '</select>';
print '</div>';
print '</div><br>';

// Produits
/*print '<table class="border centpercent" id="table_produits">';
print '<tr>
    <th><i class="fa fa-box"></i> Produit</th>
    <th><i class="fa fa-cubes"></i> Nb Cartons</th>
    <th><i class="fa fa-weight-hanging"></i> Poids total (Kg)</th>
    <th><i class="fa fa-money-bill-wave"></i> Prix unitaire ('.$conf->currency.')</th>
    <th><i class="fa fa-calculator"></i> Total ligne</th>
</tr>';
foreach($produits as $prod) {
    print '<tr>';
    print '<td>'.$prod['label'].'</td>';
    print '<td>'.$prod['nb_carton'].'</td>';
    print '<td class="poids" data-poids="'.$prod['poids_total'].'">'.$prod['poids_total'].'</td>';
    print '<td><input type="number" step="0.01" name="pu_'.$prod['rowid'].'" class="pu_input" value=""></td>';
    print '<td class="total_ligne">0</td>';
    print '</tr>';
}
print '<tr><td colspan="4" align="right"><strong>Total général</strong></td><td id="total_general">0</td></tr>';
print '</table><br>';
*/

// --- Table Produits stylée ---
print '
<style>
    /* Table principale */
    #table_produits {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 10px;
        font-family: "Segoe UI", Tahoma, sans-serif;
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }

    /* En-têtes */
    #table_produits th {
        background: linear-gradient(135deg, #222, #444);
        color: #fff;
        text-align: center;
        font-weight: 600;
        padding: 10px;
        font-size: 14px;
        border-bottom: 2px solid #666;
    }

    #table_produits th i {
        color: #f1c40f;
        margin-right: 6px;
    }

    /* Lignes */
    #table_produits td {
        padding: 10px;
        text-align: center;
        border-bottom: 1px solid #eee;
        font-size: 13.5px;
    }

    #table_produits tr:nth-child(even) td {
        background: #fafafa;
    }

    #table_produits tr:hover td {
        background: #f3f3f3;
    }

    /* Input prix unitaire */
    #table_produits input.pu_input {
        width: 90px;
        text-align: right;
        padding: 5px 8px;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #fff;
        transition: all 0.2s ease;
    }

    #table_produits input.pu_input:focus {
        border-color: #007bff;
        box-shadow: 0 0 4px rgba(0,123,255,0.4);
        outline: none;
    }

    /* Ligne total */
    #table_produits tr:last-child td {
        background: #f8f8f8;
        font-weight: bold;
        font-size: 14px;
    }

    #total_general {
        color: #007bff;
    }
</style>
';

print '<table id="table_produits">';
print '<tr>
    <th><i class="fa fa-box"></i> Produit</th>
    <th><i class="fa fa-cubes"></i> Nb Cartons</th>
    <th><i class="fa fa-weight-hanging"></i> Poids total (Kg)</th>
    <th><i class="fa fa-money-bill-wave"></i> Prix unitaire ('.$conf->currency.')</th>
    <th><i class="fa fa-calculator"></i> Total ligne</th>
</tr>';

foreach($produits as $prod) {
    print '<tr>';
    print '<td>'.$prod['label'].'</td>';
    print '<td class="nbcarton" data-poids="'.$prod['nb_carton'].'">'.$prod['nb_carton'].'</td>';
    print '<td class="poids" data-poids="'.$prod['poids_total'].'">'.$prod['poids_total'].'</td>';
    print '<td><input type="number" step="0.01" name="pu_'.$prod['rowid'].'" class="pu_input" value=""></td>';
    print '<td class="total_ligne">0</td>';
    print '</tr>';
}

print '<tr><td colspan="4" align="right"><strong><i class="fa fa-sigma"></i> Total général</strong></td><td id="total_general">0</td></tr>';
print '</table><br>';
print '<input type="submit" class="button" value="Créer facture">';
print '</form>';

// 🔹 JS
?>
<script>
document.querySelectorAll('.pu_input').forEach(function(input){
    input.addEventListener('input', function(){
        let row = input.closest('tr');
        //let poids = parseFloat(row.querySelector('.poids').dataset.poids);
        let poids = parseFloat(row.querySelector('.nbcarton').dataset.poids);
        let total = poids * parseFloat(input.value || 0);
        row.querySelector('.total_ligne').textContent = total.toFixed(2);

        let totalGen = 0;
        document.querySelectorAll('.total_ligne').forEach(function(tl){
            totalGen += parseFloat(tl.textContent || 0);
        });
        document.getElementById('total_general').textContent = totalGen.toFixed(2);
    });
});

// Bouton Multi-devise
document.getElementById('btn_multidevise').addEventListener('click', function(){
    let section = document.getElementById('divise_section');
    section.style.display = (section.style.display === 'none') ? 'block' : 'none';
});

// Confirmation submit
document.getElementById('form_facture').addEventListener('submit', function(e){
    if(!confirm('⚠ Confirmer la création de la facture ?')) e.preventDefault();
});
</script>
<?php
llxFooter();
$db->close();
?>
