<?php
// card.php - Formulaire création lot direct (sans traitement save)
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

global $db, $user, $langs, $conf;
$langs->load("abricot@abricot");

$form = new Form($db);

$token = newToken();

// ======================
// Génération référence auto
// ======================
$res = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
$refPrev = ($res && $db->num_rows($res)) ? $db->fetch_object($res)->ref : '';

$nextNumber = (!empty($refPrev) && preg_match('/Lot-(\d+)/',$refPrev,$m)) ? ((int)$m[1]+1) : 1;
$ref = 'Lot-'.str_pad($nextNumber,6,'0',STR_PAD_LEFT);

// ======================
// Produits catégorie POISSON
// ======================
$products = [];
$sqlP = "
SELECT p.rowid, p.label
FROM ".MAIN_DB_PREFIX."product AS p
INNER JOIN ".MAIN_DB_PREFIX."categorie_product AS cp ON cp.fk_product = p.rowid
INNER JOIN ".MAIN_DB_PREFIX."categorie AS c ON c.rowid = cp.fk_categorie
WHERE c.label = 'POISSON'
ORDER BY p.label ASC";
$resP = $db->query($sqlP);
while ($obj = $db->fetch_object($resP)) $products[] = $obj;

// ======================
// Fournisseurs
// ======================
$fournisseurs = [];
$sqlF = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = 1 ORDER BY nom ASC";
$resF = $db->query($sqlF);
while ($o = $db->fetch_object($resF)) $fournisseurs[] = $o;

// ======================
// Entrepôts
// ======================
$TEntrepots = array();

$sqlW = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot WHERE statut = 1 ORDER BY ref ASC";
$resW = $db->query($sqlW);
if ($resW) {
    while ($obj = $db->fetch_object($resW)) {
        $TEntrepots[$obj->rowid] = $obj->ref;
    }
}

llxHeader('', $langs->trans("CreateDirectLot"));

print '
<style>
.facturation-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1400px;
    margin: 0 auto;
    background: #fff;
}

.facturation-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.facturation-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Sections */
.facturation-info-section,
.facturation-services-section,
.facturation-total-section {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.facturation-title {
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

/* Grille d\'informations */
.facturation-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-item label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
}

.info-value {
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    font-size: 15px;
}

.info-value.highlight {
    background: #e8f4fd;
    border-color: #3498db;
    color: #2c3e50;
    font-weight: 600;
}

/* Tableau des services */
.facturation-table-container {
    margin: 20px 0;
    overflow-x: auto;
}

.facturation-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    min-width: 1300px;
}

.facturation-table th {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    padding: 16px 8px;
    font-weight: 600;
    text-align: center;
    border: none;
    font-size: 14px;
    white-space: nowrap;
}

.facturation-table th i {
    margin-right: 6px;
}

.facturation-table th.center {
    text-align: center;
}

.facturation-table td {
    padding: 14px 8px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
    text-align: center;
}

.facturation-table td.left {
    text-align: left;
}

.facturation-table tr:last-child td {
    border-bottom: none;
}

.facturation-table tr:hover {
    background: #f8f9fa;
}

/* Champs de saisie */
.facturation-input {
    padding: 8px 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    text-align: center;
}

.facturation-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

.nb-input {
    width: 80px;
}

.poids-input, .pu-carton-input {
    width: 90px;
}

.total-line-input {
    width: 100px;
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
    text-align: right;
}

.comment-input {
    width: 200px;
    text-align: left;
}

.grand-total-input {
    width: 150px;
    padding: 12px 15px;
    font-size: 18px;
    font-weight: 700;
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
}

/* Boutons */
.facturation-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.facturation-btn.primary {
    background: #3498db;
    color: white;
}

.facturation-btn.primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.facturation-btn.success {
    background: #27ae60;
    color: white;
    padding: 12px 30px;
    font-size: 16px;
}

.facturation-btn.success:hover {
    background: #219a52;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
}

.facturation-btn.danger {
    background: #e74c3c;
    color: white;
    padding: 6px 12px;
    font-size: 12px;
}

.facturation-btn.danger:hover {
    background: #c0392b;
}

/* Sections d\'actions */
.facturation-actions {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.facturation-submit-section {
    text-align: center;
    margin: 30px 0;
}

.submit-btn {
    padding: 15px 40px;
    font-size: 16px;
    border-radius: 8px;
}

/* Section total */
.facturation-total-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #3498db;
}

.total-grid {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
}

.total-value {
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Styles spécifiques pour ce formulaire */
.lot-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
}

.form-control {
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

.form-control[disabled] {
    background: #e9ecef;
    color: #6c757d;
}

/* Style pour les totaux de ligne */
.line-total-cell {
    background: #e8f4fd;
    border-left: 3px solid #3498db;
}

/* Responsive */
@media (max-width: 768px) {
    .facturation-container {
        padding: 10px;
    }
    
    .facturation-info-grid {
        grid-template-columns: 1fr;
    }
    
    .facturation-table {
        font-size: 12px;
        min-width: 1100px;
    }
    
    .facturation-table th,
    .facturation-table td {
        padding: 10px 6px;
    }
    
    .total-grid {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .nb-input, .poids-input, .pu-carton-input {
        width: 70px;
    }
    
    .total-line-input {
        width: 90px;
    }
    
    .comment-input {
        width: 150px;
    }
    
    .lot-form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .facturation-header {
        padding: 20px;
    }
    
    .facturation-header h1 {
        font-size: 22px;
    }
    
    .facturation-info-section,
    .facturation-services-section,
    .facturation-total-section {
        padding: 15px;
    }
    
    .facturation-title {
        font-size: 18px;
    }
}
</style>
';

print '<div class="facturation-container">';
print '<div class="facturation-header">';
print '<h1><i class="fa fa-cube"></i> '.$langs->trans("CreateDirectLot").'</h1>';
print '</div>';

// ======================
// FORM
// ======================
print '<form method="POST" action="save_lot.php">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="source_type" value="1">';  // Direct

// ---- Infos principales
print '<div class="facturation-info-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("BasicInformation");
print '</div>';

print '<div class="lot-form-grid">';
print '<div class="form-group">';
print '<label>'.$langs->trans("LotReference").'</label>';
print '<input type="text" class="form-control" value="'.$ref.'" disabled>';
print '<input type="hidden" name="ref" value="'.$ref.'">';
print '</div>';

print '<div class="form-group">';
print '<label>'.$langs->trans("Supplier").'</label>';
print $form->select_company('', 'fk_fournisseur', 0, null, 0, '', 1);
print '</div>';

print '<div class="form-group">';
print '<label>'.$langs->trans("Warehouse").'</label>';
print $form->selectarray('fk_entrepot', $TEntrepots, '', 1);
print '</div>';

print '<div class="form-group full-width">';
print '<label>'.$langs->trans("Comment").'</label>';
print '<textarea name="commentaire" class="form-control" rows="3"></textarea>';
print '</div>';
print '</div>';
print '</div>';
$date_creation_val = dol_print_date(time(), '%Y-%m-%dT%H:%M'); // valeur par défaut = maintenant
if (!empty($bon->date_creation)) {
    $date_creation_val = date('Y-m-d\TH:i', strtotime($bon->date_creation));
}

print '<div class="form-group" style="margin-bottom:20px;">';
print '<label for="date_creation" style="display:block; margin-bottom:6px; font-weight:600; color:#2c3e50;">';
print '<i class="fa fa-calendar-alt"></i> '.$langs->trans("CreationDate").' :';
print '</label>';
print '<div style="position:relative; display:inline-block;">';
print '<input type="datetime-local" id="date_creation" name="date_creation" value="'.$date_creation_val.'" ';
print 'style="padding:10px 12px 10px 40px; border:2px solid #e0e0e0; border-radius:6px; ';
print 'font-size:14px; width:220px; transition:all 0.3s ease; background:#fff; ';
print 'color:#333; box-shadow:0 2px 5px rgba(0,0,0,0.05);" ';
print 'onfocus="this.style.borderColor=\'#4a90e2\'; this.style.boxShadow=\'0 2px 8px rgba(74,144,226,0.2)\'" ';
print 'onblur="this.style.borderColor=\'#e0e0e0\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.05)\'">';
print '<i class="fa fa-clock" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7f8c8d;"></i>';
print '</div>';
print '</div>';
// ---- Lignes du lot
print '<div class="facturation-services-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-list-alt"></i> '.$langs->trans("LotDetails");
print '</div>';

print '<div class="facturation-table-container">';
print '<table class="facturation-table" id="tableLines">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Product").'</th>';
print '<th class="center"><i class="fa fa-box"></i> '.$langs->trans("NbCartons").'</th>';
print '<th class="center"><i class="fa fa-weight"></i> '.$langs->trans("WeightPerCarton").' (kg)</th>';
print '<th class="center"><i class="fa fa-money-bill"></i> '.$langs->trans("PricePerCarton").' ('.$conf->currency.')</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("LineTotal").' ('.$conf->currency.')</th>';
print '<th><i class="fa fa-comment"></i> '.$langs->trans("Comment").'</th>';
print '<th class="center"><i class="fa fa-cog"></i> '.$langs->trans("Actions").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

print '<tr class="lineRow">';
print '<td class="left">';
print '<select name="product_id[]" class="form-control" style="min-width: 200px;">';
print '<option value="">-- '.$langs->trans("SelectProduct").' --</option>';
foreach ($products as $p) {
    print '<option value="'.$p->rowid.'">'.htmlspecialchars($p->label).'</option>';
}
print '</select>';
print '</td>';

print '<td class="center">';
print '<input type="number" name="nb_carton[]" value="1" min="0" step="1" class="facturation-input nb-input nb-carton" onchange="calculateLineTotal(this)">';
print '</td>';

print '<td class="center">';
print '<input type="number" name="poids_carton[]" value="20" min="0" step="0.01" class="facturation-input poids-input poids-carton" onchange="calculateLineTotal(this)">';
print '</td>';

print '<td class="center">';
print '<input type="number" name="pu_carton[]" value="0" min="0" step="0.01" class="facturation-input pu-carton-input prix-carton" onchange="calculateLineTotal(this)">';
print '</td>';

print '<td class="center line-total-cell">';
print '<input type="text" name="total_line[]" value="0" readonly class="facturation-input total-line-input total-line">';
print '</td>';

print '<td>';
print '<input type="text" name="line_comment[]" class="facturation-input comment-input">';
print '</td>';

print '<td class="center">';
print '<button type="button" class="facturation-btn danger removeLine">';
print '<i class="fa fa-trash"></i> '.$langs->trans("Remove");
print '</button>';
print '</td>';
print '</tr>';

print '</tbody>';
print '</table>';
print '</div>';

print '<div class="facturation-actions">';
print '<button type="button" id="addLine" class="facturation-btn primary">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AddLine");
print '</button>';
print '</div>';
print '</div>';

// Total lot
print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">';
print '<i class="fa fa-calculator"></i> '.$langs->trans("TotalLot").' : ';
print '</div>';
print '<div class="total-value">';
print '<span class="grand-total-input" id="total_lot">0 '.$conf->currency.'</span>';
print '</div>';
print '</div>';
print '</div>';

// Bouton de soumission
print '<div class="facturation-submit-section">';
print '<input type="submit" class="facturation-btn success submit-btn" value="'.$langs->trans("CreateLot").'">';
print '</div>';

print '</form>';
print '</div>';

llxFooter();
?>

<script>
// REMPLACER TOUT LE SCRIPT JAVASCRIPT PAR CECI :

// Stocker la devise PHP dans une variable JS
const currency = '<?php echo $conf->currency; ?>';

// Fonction pour calculer le total d'une ligne
function calculateLineTotal(inputElement) {
    const row = inputElement.closest('tr');
    const nbCartons = parseFloat(row.querySelector('.nb-carton').value) || 0;
    const prixCarton = parseFloat(row.querySelector('.prix-carton').value) || 0;
    
    // Calcul du total de la ligne
    const totalLine = nbCartons * prixCarton;
    
    // Mettre à jour le champ total de la ligne
    const totalInput = row.querySelector('.total-line');
    totalInput.value = totalLine.toFixed(2);
    
    // Calculer le total général
    calculateGrandTotal();
}

// Fonction pour calculer le total général
function calculateGrandTotal() {
    let totalLot = 0;
    
    // Parcourir toutes les lignes
    document.querySelectorAll('#tableLines .lineRow').forEach(row => {
        const totalLine = parseFloat(row.querySelector('.total-line').value) || 0;
        totalLot += totalLine;
    });
    
    // Mettre à jour l'affichage du total général
    const totalElement = document.getElementById('total_lot');
    totalElement.textContent = totalLot.toFixed(2) + ' ' + currency;
    
    // Mettre à jour un champ caché si nécessaire
    const hiddenTotalInput = document.getElementById('hidden_total_lot');
    if (hiddenTotalInput) {
        hiddenTotalInput.value = totalLot.toFixed(2);
    }
}

// Fonction pour créer une nouvelle ligne
function createNewLine() {
    // Cloner la première ligne
    const firstRow = document.querySelector('.lineRow');
    const newRow = firstRow.cloneNode(true);
    
    // Réinitialiser les valeurs
    newRow.querySelectorAll('input').forEach(input => {
        if (input.type !== 'hidden') {
            if (input.classList.contains('nb-carton')) input.value = '1';
            else if (input.classList.contains('poids-carton')) input.value = '20';
            else if (input.classList.contains('prix-carton')) input.value = '0';
            else if (input.classList.contains('total-line')) input.value = '0';
            else if (input.classList.contains('comment-input')) input.value = '';
        }
    });
    
    // Réinitialiser le select
    const select = newRow.querySelector('select');
    if (select) select.selectedIndex = 0;
    
    // Ajouter la ligne au tableau
    document.querySelector('#tableLines tbody').appendChild(newRow);
    
    // Recalculer les totaux
    calculateGrandTotal();
}

// Fonction pour supprimer une ligne
function removeLine(button) {
    // Vérifier qu'il reste au moins une ligne
    if (document.querySelectorAll('.lineRow').length > 1) {
        button.closest('tr').remove();
        calculateGrandTotal();
    } else {
        alert('<?php echo $langs->trans("CantRemoveLastLine"); ?>');
    }
}

// Initialiser les événements avec EVENT DELEGATION
document.addEventListener('DOMContentLoaded', function() {
    // Événement pour le bouton "Ajouter ligne"
    document.getElementById('addLine').addEventListener('click', createNewLine);
    
    // ÉVÉNEMENT DELEGATION pour les champs de saisie
    document.getElementById('tableLines').addEventListener('input', function(e) {
        // Vérifier si l'élément cliqué est un champ de calcul
        if (e.target.classList.contains('nb-carton') || 
            e.target.classList.contains('poids-carton') || 
            e.target.classList.contains('prix-carton')) {
            calculateLineTotal(e.target);
        }
    });
    
    // ÉVÉNEMENT DELEGATION pour les boutons supprimer
    document.getElementById('tableLines').addEventListener('click', function(e) {
        if (e.target.classList.contains('removeLine') || 
            e.target.closest('.removeLine')) {
            const button = e.target.classList.contains('removeLine') ? e.target : e.target.closest('.removeLine');
            removeLine(button);
        }
    });
    
    // Validation du formulaire
    document.querySelector('form').addEventListener('submit', function(e) {
        let isValid = true;
        const errors = [];
        
        // Vérifier qu'au moins un produit est sélectionné
        let hasProduct = false;
        document.querySelectorAll('select[name="product_id[]"]').forEach(select => {
            if (select.value) hasProduct = true;
        });
        
        if (!hasProduct) {
            errors.push('<?php echo $langs->trans("SelectAtLeastOneProduct"); ?>');
            isValid = false;
        }
        
        // Vérifier que tous les produits sont sélectionnés
        document.querySelectorAll('select[name="product_id[]"]').forEach((select, index) => {
            if (!select.value) {
                errors.push('<?php echo $langs->trans("ProductRequiredForLine"); ?> ' + (index + 1));
                isValid = false;
            }
        });
        
        // Vérifier les quantités
        document.querySelectorAll('.nb-carton').forEach((input, index) => {
            if (parseFloat(input.value) <= 0) {
                errors.push('<?php echo $langs->trans("QuantityMustBeGreaterThanZero"); ?> ' + (index + 1));
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('<?php echo $langs->trans("PleaseCorrectErrors"); ?>:\n\n' + errors.join('\n'));
        }
    });
    
    // Calcul initial
    calculateLineTotal(document.querySelector('.nb-carton'));
});

// Ajouter un champ caché pour le total du lot
document.write('<input type="hidden" id="hidden_total_lot" name="total_lot" value="0">');

// Option : ajouter un événement keyup pour un calcul plus réactif
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('tableLines').addEventListener('keyup', function(e) {
        if (e.target.classList.contains('nb-carton') || 
            e.target.classList.contains('poids-carton') || 
            e.target.classList.contains('prix-carton')) {
            calculateLineTotal(e.target);
        }
    });
});
</script>