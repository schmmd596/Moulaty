<?php
/**
 * Simulateur de sortie - Calcul du devis avec frais de stockage et services
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user, $conf;
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche', 'abricot@abricot']);

$form = new Form($db);

// ============================================================================
// 🔹 VÉRIFICATION DES DONNÉES
// ============================================================================
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$fk_client_dest = GETPOST('fk_client_dest', 'int');
$mode_sortie = GETPOST('mode_sortie', 'alpha');

// Récupération des produits sélectionnés
$products = GETPOST('products', 'array');
$nb_carton = GETPOST('nb_carton', 'array');
$poids = GETPOST('poids', 'array');
$cartons_rowid = GETPOST('cartons_rowid', 'array');

// Vérifier que nous avons des données
if (empty($fk_entrepot_source) || empty($fk_client_dest) || empty($products)) {
    setEventMessages($langs->trans("DonneesManquantes"), null, 'errors');
    header("Location: simulateur_sortie.php");
    exit;
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES INFORMATIONS
// ============================================================================
$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($fk_entrepot_source);

$client = new Societe($db);
$client->fetch($fk_client_dest);

// ============================================================================
// 🔹 RÉCUPÉRATION DES SERVICES DISPONIBLES (type service)
// ============================================================================
$services_disponibles = [];
$sql_services = "SELECT rowid, ref, label, price 
                 FROM ".MAIN_DB_PREFIX."product 
                 WHERE fk_product_type = 1 
                 AND entity = ".$conf->entity." 
                 ORDER BY label ASC";
$res_services = $db->query($sql_services);
if ($res_services) {
    while ($obj = $db->fetch_object($res_services)) {
        $services_disponibles[$obj->rowid] = $obj->label . ' (' . price($obj->price) . ' ' . $conf->currency . ')';
    }
}

// ============================================================================
// 🔹 CALCUL DE LA VALEUR DES PRODUITS (incluant frais de stockage)
// ============================================================================
$produits_details = [];
$total_valeur_produits = 0;
$total_cartons = 0;
$total_poids = 0;
$total_frais_stockage = 0;

foreach ($products as $i => $product_id) {
    if ($product_id <= 0) continue;
    
    $product = new Product($db);
    $product->fetch($product_id);
    
    $nb_carton_i = isset($nb_carton[$i]) ? (int)$nb_carton[$i] : 0;
    $poids_i = isset($poids[$i]) ? (float)$poids[$i] : 0;
    $cartons_rowid_i = isset($cartons_rowid[$i]) ? $cartons_rowid[$i] : '';
    
    if ($nb_carton_i <= 0) continue;
    
    // Calculer la valeur des cartons sélectionnés (prix moyen + frais stockage)
    $valeur_cartons = 0;
    $frais_stockage_ligne = 0;
    
    if (!empty($cartons_rowid_i)) {
        $ids = explode(',', $cartons_rowid_i);
        $ids = array_filter($ids);
        
        if (!empty($ids)) {
            $sql = "SELECT SUM(prix_moyen) as total_prix, SUM(frais) as total_frais 
                    FROM ".MAIN_DB_PREFIX."pech_carton 
                    WHERE rowid IN (" . implode(',', $ids) . ")";
            $res = $db->query($sql);
            if ($res && $obj = $db->fetch_object($res)) {
                $valeur_cartons = (float)$obj->total_prix + (float)$obj->total_frais;
                $frais_stockage_ligne = (float)$obj->total_frais;
            }
        }
    }
    
    $produits_details[] = [
        'id' => $product_id,
        'ref' => $product->ref,
        'label' => $product->label,
        'nb_carton' => $nb_carton_i,
        'poids' => $poids_i,
        'valeur' => $valeur_cartons,
        'valeur_moyenne' => ($nb_carton_i > 0) ? $valeur_cartons / $nb_carton_i : 0,
        'frais_stockage' => $frais_stockage_ligne,
        'prix_moyen' => ($nb_carton_i > 0) ? ($valeur_cartons - $frais_stockage_ligne) / $nb_carton_i : 0,
        'cartons_rowid' => $cartons_rowid_i
    ];
    
    $total_valeur_produits += $valeur_cartons;
    $total_frais_stockage += $frais_stockage_ligne;
    $total_cartons += $nb_carton_i;
    $total_poids += $poids_i;
}

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('CalculDevisSimulation'));
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-calculator"></i> '.$langs->trans("CalculDevisSimulation").'</h1>';
print '<p class="simulateur-subtitle">'.$langs->trans("AjoutezServicesMarge").'</p>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE DE CALCUL
// ============================================================================
print '<form method="POST" action="simulateur_pdf.php" id="formCalcul">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot_source" value="'.$fk_entrepot_source.'">';
print '<input type="hidden" name="fk_client_dest" value="'.$fk_client_dest.'">';
print '<input type="hidden" name="mode_sortie" value="'.$mode_sortie.'">';

// Données produits
foreach ($produits_details as $i => $prod) {
    print '<input type="hidden" name="products['.$i.']" value="'.$prod['id'].'">';
    print '<input type="hidden" name="nb_carton['.$i.']" value="'.$prod['nb_carton'].'">';
    print '<input type="hidden" name="poids['.$i.']" value="'.$prod['poids'].'">';
    print '<input type="hidden" name="valeur['.$i.']" value="'.$prod['valeur'].'">';
    print '<input type="hidden" name="frais_stockage['.$i.']" value="'.$prod['frais_stockage'].'">';
    print '<input type="hidden" name="cartons_rowid['.$i.']" value="'.$prod['cartons_rowid'].'">';
}

// ============================================================================
// 🔹 SECTION RÉSUMÉ DES PRODUITS (avec frais de stockage inclus)
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-boxes-stacked"></i> '.$langs->trans("ResumeProduitsSelectionnes");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="6">'.$langs->trans("DetailsProduitsFraisStockage").'</th></tr>';

print '<tr>';
print '<td width="30%"><strong>'.$langs->trans("Produit").'</strong></td>';
print '<td class="center"><strong>'.$langs->trans("NbCartons").'</strong></td>';
print '<td class="center"><strong>'.$langs->trans("PoidsKg").'</strong></td>';
print '<td class="center"><strong>'.$langs->trans("PrixMoyenCarton").'</strong></td>';
//print '<td class="center"><strong>'.$langs->trans("FraisStockage").'</strong></td>';
print '<td class="center"><strong>'.$langs->trans("ValeurTotale").'</strong></td>';
print '</tr>';

foreach ($produits_details as $prod) {
    print '<tr>';
    print '<td>';
    print '<strong>'.$prod['ref'].'</strong><br>';
    print '<small class="opacitymedium">'.$prod['label'].'</small>';
    print '</td>';
    print '<td class="center">'.$prod['nb_carton'].'</td>';
    print '<td class="center">'.price($prod['poids']).'</td>';
    print '<td class="center">'.price($prod['valeur_moyenne']).' '.$conf->currency.'</td>';
    //print '<td class="center">'.price($prod['frais_stockage']).' '.$conf->currency.'</td>';
    print '<td class="center"><strong>'.price($prod['valeur']).' '.$conf->currency.'</strong></td>';
    print '</tr>';
}

// Total produits
print '<tr class="liste_total" style="background: #f8f9fa; font-weight: bold;">';
print '<td><strong>'.$langs->trans("TotalProduits").'</strong></td>';
print '<td class="center"><strong>'.$total_cartons.'</strong></td>';
print '<td class="center"><strong>'.price($total_poids).' kg</strong></td>';
//print '<td class="center"></td>';
print '<td class="center"><strong>'.price($total_frais_stockage).' '.$conf->currency.'</strong></td>';
print '<td class="center"><strong>'.price($total_valeur_produits).' '.$conf->currency.'</strong></td>';
print '</tr>';

print '</table>';
print '</div>'; // .filter-section

// ============================================================================
// 🔹 SECTION SERVICES (sélection depuis les services du système)
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-concierge-bell"></i> '.$langs->trans("ServicesAdditionnels");
print '</div>';

if (!empty($services_disponibles)) {
    print '<div id="services_container">';
    // Les services seront ajoutés dynamiquement
    print '</div>';
    
    print '<div class="selection-actions">';
    print '<button type="button" class="selection-button selection-button-success" onclick="ajouterService()">';
    print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterService");
    print '</button>';
    print '</div>';
} else {
    print '<div class="info-panel">';
    print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucunServiceDisponible");
    print '</div>';
}
print '</div>'; // .selection-section

// ============================================================================
// 🔹 SECTION MARGE COMMERCIALE
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-chart-line"></i> '.$langs->trans("MargeCommerciale");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ConfigurationMarge").'</th></tr>';

// Marge en pourcentage
print '<tr>';
print '<td width="40%"><label class="selection-label"><i class="fa fa-percentage"></i> '.$langs->trans("MargePourcentage").' (%)</label></td>';
print '<td>';
print '<input type="number" name="marge_pourcentage" id="marge_pourcentage" value="15" step="0.1" min="0" max="100" class="filter-input" onchange="calculerTotal()">';
print '</td>';
print '</tr>';

// Option : Marge fixe
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-money-bill"></i> '.$langs->trans("MargeFixeOptionnelle").' ('.$conf->currency.')</label></td>';
print '<td>';
print '<input type="number" name="marge_fixe" id="marge_fixe" value="0" step="0.01" min="0" class="filter-input" onchange="calculerTotal()">';
print '</td>';
print '</tr>';

print '</table>';
print '</div>'; // .selection-section

// ============================================================================
// 🔹 SECTION RÉSUMÉ DES CALCULS
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-calculator"></i> '.$langs->trans("ResumeCalculs");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ResultatsSimulation").'</th></tr>';

// Valeur produits (prix + frais stockage)
print '<tr>';
print '<td width="40%">'.$langs->trans("ValeurProduits").'</td>';
print '<td class="right"><span id="affichage_valeur_produits">'.price($total_valeur_produits).' '.$conf->currency.'</span></td>';
print '</tr>';

// Détail : Prix produits
print '<tr style="display: none;" id="detail_prix_produits">';
print '<td class="indent">'.$langs->trans("PrixProduits").'</td>';
print '<td class="right"><span id="affichage_prix_produits">'.price($total_valeur_produits - $total_frais_stockage).' '.$conf->currency.'</span></td>';
print '</tr>';

// Détail : Frais stockage
print '<tr style="display: none;" id="detail_frais_stockage">';
print '<td class="indent">'.$langs->trans("FraisStockageInclus").'</td>';
print '<td class="right"><span id="affichage_frais_stockage">'.price($total_frais_stockage).' '.$conf->currency.'</span></td>';
print '</tr>';

// Total services
print '<tr>';
print '<td>'.$langs->trans("TotalServices").'</td>';
print '<td class="right"><span id="affichage_total_services">0.00 '.$conf->currency.'</span></td>';
print '</tr>';

// Sous-total
print '<tr>';
print '<td><strong>'.$langs->trans("SousTotal").'</strong></td>';
print '<td class="right"><strong><span id="affichage_sous_total">'.price($total_valeur_produits).' '.$conf->currency.'</span></strong></td>';
print '</tr>';

// Marge
print '<tr>';
print '<td>'.$langs->trans("MargeAjoutee").'</td>';
print '<td class="right"><span id="affichage_marge">0.00 '.$conf->currency.'</span></td>';
print '</tr>';

// Total général
print '<tr class="liste_total" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 1.1em;">';
print '<td><strong><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("ValeurTotaleEstimee").'</strong></td>';
print '<td class="right"><strong><span id="affichage_total_general">'.price($total_valeur_produits).' '.$conf->currency.'</span></strong></td>';
print '</tr>';

print '</table>';

// Bouton pour afficher/masquer les détails
print '<div class="center" style="margin-top: 10px;">';
print '<button type="button" class="butAction small" onclick="toggleDetails()">';
print '<i class="fa fa-eye"></i> '.$langs->trans("AfficherDetails");
print '</button>';
print '</div>';

print '</div>'; // .filter-section

// ============================================================================
// 🔹 BOUTONS D'ACTION
// ============================================================================
print '<div class="selection-actions">';
print '<a href="simulateur_sortie.php" class="selection-button selection-button-secondary">';
print '<i class="fa fa-arrow-left"></i> '.$langs->trans("RetourSelection");
print '</a>';
print '<button type="submit" class="selection-button selection-button-primary">';
print '<i class="fa fa-file-pdf"></i> '.$langs->trans("GenererDevisPDF");
print '</button>';
print '</div>';

print '</form>';
print '</div>'; // .selection-container

// ============================================================================
// 🔹 JAVASCRIPT POUR LES CALCULS ET SERVICES
// ============================================================================
?>
<style>
.frais-input {
    text-align: right;
    font-weight: 500;
}

.service-row {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.service-row select,
.service-row input {
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
}

.service-select {
    flex: 2;
    min-width: 250px;
}

.service-qty {
    flex: 1;
    min-width: 100px;
    text-align: right;
}

.service-pu {
    flex: 1;
    min-width: 120px;
    text-align: right;
}

.service-total {
    flex: 1;
    min-width: 100px;
    text-align: right;
    font-weight: bold;
    color: #1e40af;
}

.indent {
    padding-left: 30px !important;
    font-style: italic;
}

.info-panel {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    color: #0369a1;
}
</style>

<script>
// Variables globales
var totalValeurProduits = <?php echo json_encode($total_valeur_produits); ?>;
var totalFraisStockage = <?php echo json_encode($total_frais_stockage); ?>;
var services = [];
var serviceCounter = 0;
var servicesDisponibles = <?php echo json_encode($services_disponibles); ?>;
var detailsVisible = false;

// HTML pour le select des services
var selectServiceHTML = '<select class="service-select" name="service_id[INDEX]" onchange="calculerService(INDEX)">';
selectServiceHTML += '<option value=""><?php echo $langs->trans("SelectionnezService"); ?></option>';
<?php 
foreach ($services_disponibles as $id => $label) {
    echo "selectServiceHTML += '<option value=\"$id\">" . addslashes($label) . "</option>';";
}
?>
selectServiceHTML += '</select>';

/**
 * Ajoute un service additionnel
 */
function ajouterService() {
    serviceCounter++;
    
    var html = '';
    html += '<div class="service-row" id="service_' + serviceCounter + '">';
    html += selectServiceHTML.replace(/INDEX/g, serviceCounter);
    html += '<input type="number" class="service-qty" name="service_qty[' + serviceCounter + ']" value="1" step="0.01" min="0" onchange="calculerService(' + serviceCounter + ')">';
    html += '<input type="number" class="service-pu" name="service_pu[' + serviceCounter + ']" value="0" step="0.01" min="0" onchange="calculerService(' + serviceCounter + ')">';
    html += '<span class="service-total" id="service_total_' + serviceCounter + '">0.00</span> <?php echo $conf->currency; ?>';
    html += '<button type="button" class="butActionDelete small" onclick="supprimerService(' + serviceCounter + ')">';
    html += '<i class="fa fa-trash"></i>';
    html += '</button>';
    html += '</div>';
    
    document.getElementById('services_container').insertAdjacentHTML('beforeend', html);
    
    services.push({
        id: serviceCounter,
        service_id: 0,
        qty: 1,
        pu: 0,
        total: 0
    });
}

/**
 * Calcule le total d'un service
 */
function calculerService(serviceIndex) {
    var select = document.querySelector('#service_' + serviceIndex + ' .service-select');
    var serviceId = select.value;
    var qty = parseFloat(document.querySelector('#service_' + serviceIndex + ' .service-qty').value) || 0;
    var puInput = document.querySelector('#service_' + serviceIndex + ' .service-pu');
    
    // Si un service est sélectionné et le prix n'est pas défini, mettre le prix par défaut
    if (serviceId > 0 && parseFloat(puInput.value) === 0) {
        // Récupérer le prix depuis le label (format: "Nom Service (10.00 €)")
        var optionText = select.options[select.selectedIndex].text;
        var match = optionText.match(/\(([\d.,]+)\s*<?php echo $conf->currency; ?>\)/);
        if (match) {
            var prix = parseFloat(match[1].replace(',', '.'));
            puInput.value = prix.toFixed(2);
        }
    }
    
    var pu = parseFloat(puInput.value) || 0;
    var total = qty * pu;
    
    document.getElementById('service_total_' + serviceIndex).textContent = total.toFixed(2);
    
    // Mettre à jour l'objet service
    var service = services.find(s => s.id == serviceIndex);
    if (service) {
        service.service_id = serviceId;
        service.qty = qty;
        service.pu = pu;
        service.total = total;
    }
    
    calculerTotal();
}

/**
 * Supprime un service
 */
function supprimerService(serviceIndex) {
    var element = document.getElementById('service_' + serviceIndex);
    if (element) {
        element.remove();
        services = services.filter(s => s.id != serviceIndex);
        calculerTotal();
    }
}

/**
 * Calcule le total général
 */
function calculerTotal() {
    // Total services
    var totalServices = 0;
    services.forEach(function(service) {
        totalServices += service.total;
    });
    
    // Sous-total (produits + services)
    var sousTotal = totalValeurProduits + totalServices;
    
    // Marge
    var margePourcentage = parseFloat(document.getElementById('marge_pourcentage').value) || 0;
    var margeFixe = parseFloat(document.getElementById('marge_fixe').value) || 0;
    
    var margeMontant = 0;
    if (margeFixe > 0) {
        margeMontant = margeFixe;
    } else {
        margeMontant = sousTotal * (margePourcentage / 100);
    }
    
    // Total général
    var totalGeneral = sousTotal + margeMontant;
    
    // Mettre à jour l'affichage
    document.getElementById('affichage_valeur_produits').textContent = totalValeurProduits.toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_prix_produits').textContent = (totalValeurProduits - totalFraisStockage).toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_frais_stockage').textContent = totalFraisStockage.toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_total_services').textContent = totalServices.toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_sous_total').textContent = sousTotal.toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_marge').textContent = margeMontant.toFixed(2) + ' <?php echo $conf->currency; ?>';
    document.getElementById('affichage_total_general').textContent = totalGeneral.toFixed(2) + ' <?php echo $conf->currency; ?>';
    
    // Stocker les totaux dans des champs cachés pour le PDF
    var existingHidden = document.querySelectorAll('input[name^="total_"], input[name="valeur_totale"]');
    existingHidden.forEach(function(el) { el.remove(); });
    
    document.getElementById('formCalcul').insertAdjacentHTML('beforeend', 
        '<input type="hidden" name="total_services" value="' + totalServices + '">' +
        '<input type="hidden" name="marge_montant" value="' + margeMontant + '">' +
        '<input type="hidden" name="valeur_totale" value="' + totalGeneral + '">' +
        '<input type="hidden" name="total_frais_stockage" value="' + totalFraisStockage + '">'
    );
    
    // Stocker les services
    services.forEach(function(service, index) {
        if (service.service_id > 0) {
            document.getElementById('formCalcul').insertAdjacentHTML('beforeend',
                '<input type="hidden" name="services[' + index + '][id]" value="' + service.service_id + '">' +
                '<input type="hidden" name="services[' + index + '][qty]" value="' + service.qty + '">' +
                '<input type="hidden" name="services[' + index + '][pu]" value="' + service.pu + '">' +
                '<input type="hidden" name="services[' + index + '][total]" value="' + service.total + '">'
            );
        }
    });
}

/**
 * Afficher/masquer les détails
 */
function toggleDetails() {
    detailsVisible = !detailsVisible;
    var detailPrix = document.getElementById('detail_prix_produits');
    var detailFrais = document.getElementById('detail_frais_stockage');
    var btn = document.querySelector('button[onclick="toggleDetails()"]');
    
    if (detailPrix && detailFrais) {
        if (detailsVisible) {
            detailPrix.style.display = 'table-row';
            detailFrais.style.display = 'table-row';
            btn.innerHTML = '<i class="fa fa-eye-slash"></i> <?php echo $langs->trans("MasquerDetails"); ?>';
        } else {
            detailPrix.style.display = 'none';
            detailFrais.style.display = 'none';
            btn.innerHTML = '<i class="fa fa-eye"></i> <?php echo $langs->trans("AfficherDetails"); ?>';
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    calculerTotal();
    
    // Ajouter un service par défaut si des services disponibles
    <?php if (!empty($services_disponibles)): ?>
    ajouterService();
    <?php endif; ?>
});
</script>

<?php
// ============================================================================
// 🔹 TRADUCTIONS NÉCESSAIRES
// ============================================================================
?>
<script>
// Traductions pour JavaScript
var translations = {
    'AjouterService': '<?php echo $langs->trans("AjouterService"); ?>',
    'SelectionnezService': '<?php echo $langs->trans("SelectionnezService"); ?>',
    'AfficherDetails': '<?php echo $langs->trans("AfficherDetails"); ?>',
    'MasquerDetails': '<?php echo $langs->trans("MasquerDetails"); ?>'
};
</script>

<?php
llxFooter();
$db->close();
?>