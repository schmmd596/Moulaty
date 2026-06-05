<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $user, $langs, $conf;

$langs->load("bills");
$langs->load("main");
$langs->load("abricot@abricot");

$form = new Form($db);
//if (empty($user->rights->facture->creer)) accessforbidden();

// Récupérer la sortie
$id_sortie = GETPOST('id', 'int');
if (!$id_sortie) exit($langs->trans("MissingOutputId"));

// Sortie
$sql_sortie = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid=".$id_sortie;
$res_sortie = $db->query($sql_sortie);
$sortie = $db->fetch_object($res_sortie);
if (!$sortie) exit($langs->trans("OutputNotFound"));

// Récupérer toutes les lignes du bon
$sql_prods = "SELECT b.rowid,p.rowid as ids, b.fk_product, b.nb_carton, b.total_poids, b.valeur, p.label
              FROM ".MAIN_DB_PREFIX."pech_bonsortie_detprod b
              LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = b.fk_product
              WHERE b.fk_bonentree=".$sortie->fk_bonsortie;
$res = $db->query($sql_prods);

$produits = [];
$total_frais = 0;

// Récupérer les frais du bon
$sql_frais = "SELECT total_frais FROM ".MAIN_DB_PREFIX."pech_sortie WHERE fk_bonsortie=".$sortie->fk_bonsortie;
$res_frais = $db->query($sql_frais);
if($res_frais && $db->num_rows($res_frais) > 0) {
    $total_frais = (float)$db->fetch_object($res_frais)->total_frais;
}

$nb_lignes = $db->num_rows($res);
$supplement = ($nb_lignes > 0) ? $total_frais / $nb_lignes : 0;

while($obj = $db->fetch_object($res)) {
    $valeur_totale = $obj->valeur + $supplement;
    $pu_moyen_carton = ($obj->nb_carton > 0) ? $valeur_totale / $obj->nb_carton : 0;

    $produits[] = [
        'rowid' => $obj->rowid,
        'ids' => $obj->ids,
        'label' => $obj->label,
        'nb_carton' => $obj->nb_carton,
        'poids_total' => $obj->total_poids,
        'valeur' => $obj->valeur,
        'pu_moyen_carton' => $pu_moyen_carton
    ];
}

// Client
$soc = new Societe($db);
$soc->fetch($sortie->fk_client);

// Devises
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
// Ajouter MRO comme devise par défaut avec rate = 1
$currencies['MRO'] = ['label'=>'Ouguiya mauritanien (MRO)', 'rate'=>1.0];
if ($rescur) {
    while ($c = $db->fetch_object($rescur)) {
        $currencies[$c->code] = ['label'=>$c->name,'rate'=>(float)$c->rate];
    }
}

// Header
llxHeader('', $langs->trans("CreateOutputInvoice"));
print '
<style>
.invoice-container {
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
}

.invoice-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.invoice-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-section {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.info-title {
    color: #2c3e50;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 3px solid #3498db;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.info-label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value {
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    font-size: 14px;
    color: #2c3e50;
}

.currency-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    border: 2px solid #3498db;
}

.currency-toggle {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.currency-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.currency-select {
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    min-width: 300px;
    transition: all 0.3s ease;
}

.currency-select:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin: 20px 0;
}

.products-table th {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    padding: 16px 12px;
    font-weight: 600;
    text-align: center;
    border: none;
    font-size: 14px;
}

.products-table th i {
    margin-right: 8px;
}

.products-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #e9ecef;
    text-align: center;
    vertical-align: middle;
}

.products-table tr:nth-child(even) {
    background: #fafafa;
}

.products-table tr:hover {
    background: #f0f7ff;
}

.products-table tr:last-child {
    background: linear-gradient(135deg, #34495e, #2c3e50);
    color: white;
    font-weight: 600;
}

.price-input {
    padding: 8px 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    text-align: right;
    width: 100px;
    transition: all 0.3s ease;
}

.price-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

.recommended-price {
    color: #e74c3c;
    font-weight: 600;
    font-size: 13px;
}

.total-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #3498db;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
}

.submit-btn {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
}

.currency-info {
    background: #e8f4fc;
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #3498db;
    font-size: 14px;
    display: none;
}

@media (max-width: 768px) {
    .invoice-container {
        padding: 10px;
    }
    
    .products-table {
        font-size: 12px;
    }
    
    .products-table th,
    .products-table td {
        padding: 10px 8px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
';

print '<div class="invoice-container">';
print '<div class="invoice-header">';
print '<h1><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("CreateOutputInvoice").'</h1>';
print '</div>';

print load_fiche_titre($langs->trans("CreateInvoiceForOutput")." : ".$sortie->ref);

// Infos sortie
print '<div class="info-section">';
print '<div class="info-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("OutputInformation");
print '</div>';

print '<div class="info-grid">';
print '<div class="info-item">';
print '<div class="info-label"><i class="fa fa-user"></i> '.$langs->trans("Customer").'</div>';
print '<div class="info-value">'.$soc->nom.'</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label"><i class="fa fa-calendar"></i> '.$langs->trans("CreationDate").'</div>';
print '<div class="info-value">'.$sortie->date_creation.'</div>';
print '</div>';

$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($sortie->fk_entrepot_source);
print '<div class="info-item">';
print '<div class="info-label"><i class="fa fa-warehouse"></i> '.$langs->trans("SourceWarehouse").'</div>';
print '<div class="info-value">'.$entrepot_src->ref.'</div>';
print '</div>';

if($sortie->fk_entrepot_dest) {
    print '<div class="info-item">';
    print '<div class="info-label"><i class="fa fa-truck"></i> '.$langs->trans("DestinationWarehouse").'</div>';
    print '<div class="info-value">'.$sortie->fk_entrepot_dest.'</div>';
    print '</div>';
}

if($sortie->commentaire) {
    print '<div class="info-item" style="grid-column: 1 / -1;">';
    print '<div class="info-label"><i class="fa fa-comment"></i> '.$langs->trans("Comment").'</div>';
    print '<div class="info-value">'.$sortie->commentaire.'</div>';
    print '</div>';
}
print '</div>';
print '</div>';

// Formulaire facture
print '<form method="POST" action="facture_vente_process_m.php" id="form_facture">';
print '<input type="hidden" name="id_sortie" value="'.$id_sortie.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

// Multi-devise
print '<div class="currency-section">';
print '<button type="button" id="btn_multidevise" class="currency-toggle">';
print '<i class="fa fa-money-bill-wave"></i> '.$langs->trans("MultiCurrency");
print '</button>';
print '<div id="divise_section" style="display:none; margin-top:15px;">';
print '<label style="font-weight:600; color:#2c3e50; margin-bottom:8px; display:block;">';
print '<i class="fa fa-coins" style="color:#f39c12; margin-right:6px;"></i> '.$langs->trans("SelectCurrency");
print '</label>';
print '<select name="devise" id="devise" class="currency-select">';
print '<option value="'.$conf->currency.'" selected>-- '.$langs->trans("DefaultCurrency").' (MRU) --</option>';
foreach($currencies as $code => $cur) {
    if ($code != 'MRU') {
        print '<option value="'.$code.'" data-rate="'.$cur['rate'].'">'.$cur['label'].' ('.$code.') | '.$langs->trans("Rate").': '.$cur['rate'].'</option>';
    }
}
print '</select>';
print '<div class="currency-info" id="currency_info"></div>';
print '</div>';
print '</div>';

// Table Produits
print '<div class="info-section">';
print '<div class="info-title">';
print '<i class="fa fa-cubes"></i> '.$langs->trans("Products");
print '</div>';

print '<table class="products-table">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Product").'</th>';
print '<th><i class="fa fa-boxes"></i> '.$langs->trans("NbCartons").'</th>';
print '<th><i class="fa fa-weight"></i> '.$langs->trans("TotalWeight").' (Kg)</th>';
print '<th><i class="fa fa-lightbulb"></i> <span id="header_recommended">'.$langs->trans("RecommendedPU").' ('.$conf->currency.')</span></th>';
print '<th><i class="fa fa-money-bill"></i> <span id="header_unitprice">'.$langs->trans("UnitPrice").' ('.$conf->currency.')</span></th>';
print '<th><i class="fa fa-calculator"></i> <span id="header_linetotal">'.$langs->trans("LineTotal").' ('.$conf->currency.')</span></th>';
print '</tr>';
print '</thead>';
print '<tbody>';

foreach($produits as $prod) {
    print '<tr>';
    print '<td>'.$prod['label'].'</td>';
    print '<td class="nbcarton" data-poids="'.$prod['nb_carton'].'">'.$prod['nb_carton'].'</td>';
    print '<td class="poids" data-poids="'.$prod['poids_total'].'">'.$prod['poids_total'].'</td>';
    
    // PU recommandé avec data-base
    print '<td class="recommended-price" data-base="'.$prod['pu_moyen_carton'].'">'.number_format($prod['pu_moyen_carton'], 2).'</td>';
    
    // Input PU avec data-base
    print '<td>';
    print '<input type="number" step="0.01" name="pu_'.$prod['ids'].'" class="price-input" ';
    print 'min="'.number_format($prod['pu_moyen_carton'], 2).'" value="'.number_format($prod['pu_moyen_carton'], 2).'" ';
    print 'data-base="'.$prod['pu_moyen_carton'].'">';
    print '</td>';
    
    // Total ligne avec data-base
    print '<td class="total_ligne" data-base="0">0</td>';
    print '</tr>';
}

print '<tr>';
print '<td colspan="5" style="text-align: right; padding-right: 20px;">';
print '<strong><i class="fa fa-sigma"></i> '.$langs->trans("GrandTotal").'</strong>';
print '</td>';
print '<td id="total_general" style="font-weight: bold; color: #fff;">0</td>';
print '</tr>';
print '</tbody>';
print '</table>';
print '</div>';

// Champ caché pour la devise sélectionnée
print '<input type="hidden" name="selected_currency" id="hidden_selected_currency" value="MRO">';
print '<input type="hidden" name="selected_rate" id="hidden_selected_rate" value="1">';

print '<div class="total-section">';
print '<input type="submit" class="submit-btn" value="'.$langs->trans("CreateInvoice").'">';
print '</div>';
print '</form>';
print '</div>';

// JS avec les taux de change
?>
<script>
// Stocker les taux de change
const currencyRates = <?php echo json_encode($currencies); ?>;
const baseCurrency = "<?php echo $conf->currency; ?>";

// Fonction pour recalculer tous les totaux
function recalcAll(rate = 1) {
    let totalGeneral = 0;

    document.querySelectorAll('.price-input').forEach(input => {
        let row = input.closest('tr');
        let nb = parseFloat(row.querySelector('.nbcarton').dataset.poids);
        
        let pu = parseFloat(input.value || 0);
        let total = pu * nb;
        
        let totalCell = row.querySelector('.total_ligne');
        totalCell.textContent = total.toFixed(2);
        totalCell.dataset.base = (total * rate).toFixed(2);
        
        totalGeneral += total;
        
        // Vérifier si le prix est inférieur au prix recommandé
        let recommendedCell = row.querySelector('.recommended-price');
        let recommendedPrice = parseFloat(recommendedCell.textContent || 0);
        
        if (pu < recommendedPrice) {
            input.style.borderColor = '#e74c3c';
            input.style.backgroundColor = '#ffeaea';
        } else {
            input.style.borderColor = '#ced4da';
            input.style.backgroundColor = 'white';
        }
    });

    document.getElementById('total_general').textContent = totalGeneral.toFixed(2);
}

// Changement de devise
document.getElementById('devise').addEventListener('change', function () {
    let code = this.value;
    let selectedOption = this.options[this.selectedIndex];
    let rate = parseFloat(selectedOption.getAttribute('data-rate') || 1);
    
    // Mettre à jour les champs cachés
    document.getElementById('hidden_selected_currency').value = code;
    document.getElementById('hidden_selected_rate').value = rate;
    
    // Mettre à jour les en-têtes
    if (code === 'MRU') {
        document.getElementById('header_recommended').textContent = '<?php echo $langs->transnoentities("RecommendedPU"); ?> (' + baseCurrency + ')';
        document.getElementById('header_unitprice').textContent = '<?php echo $langs->transnoentities("UnitPrice"); ?> (' + baseCurrency + ')';
        document.getElementById('header_linetotal').textContent = '<?php echo $langs->transnoentities("LineTotal"); ?> (' + baseCurrency + ')';
        document.getElementById('currency_info').style.display = 'none';
    } else {
        document.getElementById('header_recommended').textContent = '<?php echo $langs->transnoentities("RecommendedPU"); ?> (' + code + ')';
        document.getElementById('header_unitprice').textContent = '<?php echo $langs->transnoentities("UnitPrice"); ?> (' + code + ')';
        document.getElementById('header_linetotal').textContent = '<?php echo $langs->transnoentities("LineTotal"); ?> (' + code + ')';
        
        // Afficher l'info devise
        let currencyInfo = document.getElementById('currency_info');
        currencyInfo.innerHTML = '<i class="fa fa-info-circle"></i> ' +
            '<?php echo $langs->transnoentities("CurrencySelected"); ?>: <strong>' + selectedOption.text + '</strong><br>' +
            '<?php echo $langs->transnoentities("ExchangeRate"); ?>: 1 ' + baseCurrency + ' = ' + rate.toFixed(4) + ' ' + code;
        currencyInfo.style.display = 'block';
    }
    
    // PU recommandé
    document.querySelectorAll('.recommended-price').forEach(el => {
        let base = parseFloat(el.dataset.base || 0);
        el.textContent = (base * rate).toFixed(2);
    });

    // PU saisie
    

});

// Calcul standard (sans devise)
document.querySelectorAll('.price-input').forEach(input => {
    input.addEventListener('input', function () {
        // Mettre à jour data-base pour l'input
        let row = this.closest('tr');
        let nb = parseFloat(row.querySelector('.nbcarton').dataset.poids);
        let pu = parseFloat(this.value || 0);
        let selectedRate = parseFloat(document.getElementById('hidden_selected_rate').value || 1);
        this.dataset.base = (pu * selectedRate).toFixed(2);
        
        recalcAll(selectedRate);
    });
});

// Initialiser les calculs
document.addEventListener('DOMContentLoaded', function() {
    recalcAll(1);
});

// Bouton Multi-devise
document.getElementById('btn_multidevise').addEventListener('click', function(){
    let section = document.getElementById('divise_section');
    section.style.display = (section.style.display === 'none') ? 'block' : 'none';
});

// Confirmation submit
document.getElementById('form_facture').addEventListener('submit', function(e){
    if(!confirm("ConfirmCreateInvoice")) e.preventDefault();
});
</script>
<?php
llxFooter();
$db->close();
?>