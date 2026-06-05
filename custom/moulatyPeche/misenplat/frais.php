<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $langs, $user, $conf;

$langs->load("main");
$langs->load("womapeche@womapeche");

$id = GETPOST('id','int');
if (empty($id)) {
    setEventMessages($langs->trans("BonNotSpecified"), null, 'errors');
    header("Location: misenplat_select.php");
    exit;
}

// 🔹 Récupération du bon
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".((int)$id);
$res = $db->query($sql);
$bon = $db->fetch_object($res);
if (!$bon) exit($langs->trans("BonNotFound"));

llxHeader("", $langs->trans("FeesAndServices")." - ".$langs->trans("Bon")." ".$bon->ref);

print '
<style>
/* ============================================================================
   VARIABLES & BASE STYLES
   ============================================================================ */
:root {
    --primary-color: #28a745;
    --primary-hover: #218838;
    --secondary-color: #17a2b8;
    --secondary-hover: #138496;
    --danger-color: #dc3545;
    --danger-hover: #c82333;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --border-color: #dee2e6;
    --shadow: 0 2px 8px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

.frais-container {
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

/* ============================================================================
   HEADER
   ============================================================================ */
.frais-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    padding: 25px 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.frais-header::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.frais-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 15px;
}

.frais-header h1 i {
    font-size: 32px;
    background: rgba(255,255,255,0.2);
    padding: 12px;
    border-radius: 10px;
    backdrop-filter: blur(5px);
}

/* ============================================================================
   CONTENT AREA
   ============================================================================ */
.content-wrapper {
    padding: 30px;
}

.info-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    border-left: 4px solid var(--primary-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.info-card h3 {
    color: var(--dark-color);
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-card h3 i {
    color: var(--primary-color);
}

/* ============================================================================
   TABLES
   ============================================================================ */
.info-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 10px 0;
}

.info-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eef2f7;
}

.info-table td:first-child {
    font-weight: 600;
    color: #495057;
    width: 200px;
}

.info-table tr:last-child td {
    border-bottom: none;
}

.frais-table-container {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin: 25px 0;
    border: 1px solid var(--border-color);
}

.frais-table {
    width: 100%;
    border-collapse: collapse;
}

.frais-table thead {
    background: linear-gradient(to right, #f8f9fa, #e9ecef);
}

.frais-table th {
    padding: 16px 12px;
    color: var(--dark-color);
    font-weight: 600;
    text-align: left;
    border-bottom: 2px solid var(--border-color);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.frais-table tbody tr {
    transition: var(--transition);
    border-bottom: 1px solid #f1f3f5;
}

.frais-table tbody tr:hover {
    background-color: #f8fafc;
}

.frais-table td {
    padding: 14px 12px;
    vertical-align: middle;
}

.frais-table tfoot {
    background: #f8fafc;
    border-top: 2px solid var(--border-color);
}

/* ============================================================================
   FORM ELEMENTS
   ============================================================================ */
.form-input {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    transition: var(--transition);
    background: #fff;
    width: 100%;
    box-sizing: border-box;
}

.form-input:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    transform: translateY(-1px);
}

.form-input.total-input {
    background: #e9ecef;
    font-weight: 700;
    color: var(--dark-color);
    text-align: center;
    font-size: 16px;
    border: 2px solid var(--primary-color);
}

select.form-input {
    appearance: none;
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
    padding-right: 35px;
}

/* ============================================================================
   BUTTONS
   ============================================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    line-height: 1;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: white;
    padding: 12px 30px;
    font-size: 16px;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-hover) 0%, #1e7e34 100%);
}

.btn-add {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-hover) 100%);
    color: white;
    padding: 12px 24px;
}

.btn-add:hover {
    background: linear-gradient(135deg, var(--secondary-hover) 0%, #0f6674 100%);
}

.btn-remove {
    background: var(--danger-color);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.btn-remove:hover {
    background: var(--danger-hover);
    transform: scale(1.1);
}

/* ============================================================================
   ACTIONS & TOTALS
   ============================================================================ */
.actions-section {
    text-align: center;
    margin: 35px 0 20px;
    padding-top: 25px;
    border-top: 1px solid var(--border-color);
}

.total-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    border: 1px solid var(--border-color);
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.total-row:last-child {
    margin-bottom: 0;
}

.total-label {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 16px;
}

.total-value {
    font-weight: 700;
    font-size: 18px;
    color: var(--primary-color);
}

/* ============================================================================
   RESPONSIVE DESIGN
   ============================================================================ */
@media (max-width: 768px) {
    .frais-container {
        margin: 15px;
        border-radius: 8px;
    }
    
    .content-wrapper {
        padding: 20px;
    }
    
    .frais-header {
        padding: 20px;
    }
    
    .frais-header h1 {
        font-size: 22px;
    }
    
    .frais-table-container {
        overflow-x: auto;
    }
    
    .frais-table {
        min-width: 700px;
    }
    
    .info-table td {
        display: block;
        width: 100%;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .info-table td:first-child {
        width: 100%;
        font-weight: 700;
        color: var(--primary-color);
        padding-top: 15px;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .actions-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
}

/* ============================================================================
   ANIMATIONS
   ============================================================================ */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.frais-table tbody tr {
    animation: fadeIn 0.3s ease-out;
}

/* ============================================================================
   UTILITY CLASSES
   ============================================================================ */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-primary { color: var(--primary-color); }
.mb-3 { margin-bottom: 1rem; }
.mt-3 { margin-top: 1rem; }
.p-3 { padding: 1rem; }

/* ============================================================================
   CUSTOM SCROLLBAR
   ============================================================================ */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-hover);
}
</style>
';

print '<div class="frais-container">';
print '<div class="frais-header">';
print '<h1><i class="fa fa-calculator"></i> '.$langs->trans("FeesAndServices").'</h1>';
print '</div>';

print '<div class="content-wrapper">';

// 🔹 Infos du bon
print '<div class="info-card">';
print '<h3><i class="fa fa-info-circle"></i> '.$langs->trans("BonInformation").'</h3>';
print '<table class="info-table">';
print '<tr><td>'.$langs->trans("Reference").'</td><td class="text-primary"><strong>'.$bon->ref.'</strong></td></tr>';
print '<tr><td>'.$langs->trans("CreationDate").'</td><td>'.dol_print_date($bon->date_creation, 'dayhour').'</td></tr>';
print '<tr><td>'.$langs->trans("Status").'</td><td><span class="badge badge-'.($bon->statut==1 ? 'success' : 'secondary').'">'.($bon->statut==1 ? $langs->trans("Validated") : $langs->trans("Draft")).'</span></td></tr>';
if (!empty($bon->commentaire)) {
    print '<tr><td>'.$langs->trans("Comment").'</td><td><em>'.$bon->commentaire.'</em></td></tr>';
}
print '</table>';
print '</div>';

// 🔹 Récupérer la liste des services depuis les produits type=1
$sql_service = "SELECT rowid, label, price AS pu FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 1 ORDER BY label ASC";
$res_service = $db->query($sql_service);
$services = [];
while($s = $db->fetch_object($res_service)) {
    $services[] = $s;
}

// 🔹 Générer JSON pour JS
$services_json = json_encode($services);

// 🔹 Formulaire
print '<form id="form_frais" method="POST" action="frais_trait.php">';
print '<input type="hidden" name="fk_bon" value="'.$id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

// 🔹 Tableau frais/services
print '<div class="frais-table-container">';
print '<table class="frais-table" id="table_frais">';
print '<thead><tr>';
print '<th style="width: 35%;">'.$langs->trans("Description").'</th>';
print '<th style="width: 15%;">'.$langs->trans("Quantity").'</th>';
print '<th style="width: 20%;">'.$langs->trans("UnitPrice").'</th>';
print '<th style="width: 20%;">'.$langs->trans("Total").'</th>';
print '<th style="width: 10%;" class="text-center">'.$langs->trans("Action").'</th>';
print '</tr></thead>';
print '<tbody id="table_frais_body">';
// Lignes seront ajoutées dynamiquement par JavaScript
print '</tbody>';

print '<tfoot>';
print '<tr><td colspan="5" class="text-center" style="padding: 20px;">';
print '<button type="button" id="addLineBtn" class="btn btn-add">';
print '<i class="fa fa-plus-circle"></i> '.$langs->trans("AddNewLine");
print '</button>';
print '</td></tr>';
print '<tr><td colspan="3" class="text-right" style="padding: 20px;"><strong>'.$langs->trans("TotalGeneral").'</strong></td>';
print '<td colspan="2" style="padding: 20px;"><input type="text" id="total_general" class="form-input total-input" readonly value="0.00" style="font-size: 18px;"></td></tr>';
print '</tfoot>';
print '</table>';
print '</div>';

// Submit
print '<div class="actions-section">';
print '<button type="submit" class="btn btn-primary">';
print '<i class="fa fa-save"></i> '.$langs->trans("Save");
print '</button>';
print '</div>';
print '</form>';

// 🔹 Template de ligne (hors tableau)
print '<div style="display: none;">';
print '<table>';
print '<tr id="ligneTemplate">';
print '<td>';
print '<select name="desc[]" class="desc form-input" style="width: 100%;">';
print '<option value="">-- '.$langs->trans("ChooseService").' --</option>';
foreach($services as $s){
    print '<option value="'.dol_escape_htmltag($s->rowid).'" data-pu="'.price2num($s->pu).'">'.dol_escape_htmltag($s->label).'</option>';
}
print '</select>';
print '</td>';
print '<td><input type="number" class="qty form-input" value="1" min="0.01" step="0.01" name="qte[]"></td>';
print '<td><input type="number" step="0.01" class="pu form-input" value="0" name="pu[]"></td>';
print '<td class="total_ligne text-right" style="font-weight: 600; color: var(--primary-color);">0.00</td>';
print '<td class="text-center"><button type="button" class="remove_line btn-remove" title="'.$langs->trans("RemoveLine").'"><i class="fa fa-trash"></i></button></td>';
print '</tr>';
print '</table>';
print '</div>';

print '</div>'; // .content-wrapper
print '</div>'; // .frais-container

llxFooter();
?>

<script>
const services = <?php echo $services_json; ?>;

// 🔹 Calcul total ligne
function updateTotalRow(row){
    const qte = parseFloat(row.querySelector(".qty")?.value||0);
    const pu = parseFloat(row.querySelector(".pu")?.value||0);
    const total = qte * pu;
    row.querySelector(".total_ligne").textContent = total.toFixed(2);
    updateTotalGeneral();
}

// 🔹 Calcul total général
function updateTotalGeneral(){
    let total = 0;
    document.querySelectorAll("#table_frais_body tr").forEach(tr=>{
        const val = parseFloat(tr.querySelector(".total_ligne")?.textContent||0);
        total += val;
    });
    const totalInput = document.getElementById("total_general");
    totalInput.value = total.toFixed(2);
    
    // Animation du changement
    if (total > 0) {
        totalInput.style.backgroundColor = '#d4edda';
        totalInput.style.color = '#155724';
        setTimeout(() => {
            totalInput.style.backgroundColor = '';
            totalInput.style.color = '';
        }, 300);
    }
}

// 🔹 Ajouter ligne dynamique
function addNewLine() {
    const tbody = document.querySelector("#table_frais_body");
    const template = document.getElementById("ligneTemplate");
    const newRow = template.cloneNode(true);
    newRow.style.display = '';
    newRow.removeAttribute("id");
    
    // Ajouter une classe pour l'animation
    newRow.classList.add('new-row');

    // 🔹 Quand on change le service, remplir le PU
    const select = newRow.querySelector(".desc");
    const puInput = newRow.querySelector(".pu");
    select.addEventListener("change", function(){
        const selectedOption = this.options[this.selectedIndex];
        const serviceId = this.value;
        const service = services.find(s=>s.rowid == serviceId);
        
        if (service) {
            puInput.value = parseFloat(service.pu).toFixed(2);
            // Animation de mise à jour
            puInput.style.borderColor = '#28a745';
            setTimeout(() => {
                puInput.style.borderColor = '';
            }, 500);
        } else {
            puInput.value = '0.00';
        }
        updateTotalRow(newRow);
    });

    // 🔹 Recalcul quand on change Qte ou PU
    const inputs = newRow.querySelectorAll(".qty, .pu");
    inputs.forEach(input=>{
        input.addEventListener("input", function(){
            // Validation
            if (this.value < 0) this.value = 0;
            updateTotalRow(newRow);
        });
    });

    // 🔹 Supprimer ligne
    const removeBtn = newRow.querySelector(".remove_line");
    removeBtn.addEventListener("click", function(){
        // Animation de suppression
        newRow.style.transform = 'translateX(-100px)';
        newRow.style.opacity = '0';
        setTimeout(() => {
            newRow.remove();
            updateTotalGeneral();
        }, 300);
    });

    tbody.appendChild(newRow);
    
    // Focus sur le select
    setTimeout(() => {
        select.focus();
    }, 10);
    
    updateTotalGeneral();
}

// 🔹 Initialiser l'événement du bouton d'ajout
document.getElementById("addLineBtn").addEventListener("click", addNewLine);

// 🔹 Ajouter une ligne par défaut au chargement
document.addEventListener("DOMContentLoaded", function() {
    addNewLine();
});

// 🔹 Recalcul dynamique sur tout le tableau
document.addEventListener("input", function(e){
    if(e.target.classList.contains("qty") || e.target.classList.contains("pu")){
        const row = e.target.closest("tr");
        if (row) updateTotalRow(row);
    }
});

// 🔹 Empêcher la soumission du formulaire si des lignes sont invalides
document.getElementById("form_frais").addEventListener("submit", function(e){
    let valid = true;
    const rows = document.querySelectorAll("#table_frais_body tr");
    
    rows.forEach(row => {
        const desc = row.querySelector(".desc").value;
        const qty = row.querySelector(".qty").value;
        const pu = row.querySelector(".pu").value;
        
        if (!desc || qty <= 0 || pu <= 0) {
            valid = false;
            row.style.backgroundColor = '#f8d7da';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert("<?php echo $langs->trans('PleaseFillAllLinesCorrectly'); ?>");
    }
});

// 🔹 Touche Entrée pour ajouter une nouvelle ligne
document.addEventListener("keydown", function(e){
    if (e.key === 'Enter' && e.target.classList.contains('form-input')) {
        e.preventDefault();
        addNewLine();
    }
});
</script>