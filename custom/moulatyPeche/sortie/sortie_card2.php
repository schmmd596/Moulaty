<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $user, $langs, $conf;
$langs->load("stocks");

$form = new Form($db);

// --- Récupération des entrepôts ---
$sql = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".$conf->entity;
$resql = $db->query($sql);
$entrepots = array();
if ($resql) {
    while ($obj = $db->fetch_object($resql)) $entrepots[$obj->rowid] = $obj->ref;
}

// --- Récupération des clients ---
$clients = array();
$sql2 = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE client = 1 AND entity = ".$conf->entity." ORDER BY nom";
$resql2 = $db->query($sql2);
if ($resql2) {
    while ($obj = $db->fetch_object($resql2)) $clients[$obj->rowid] = $obj->nom;
}

// --- Récupération des produits ---
$produits = array();
$resql3 = $db->query("SELECT rowid, label FROM ".MAIN_DB_PREFIX."product WHERE entity = ".$conf->entity." ORDER BY label");
if ($resql3) {
    while ($obj = $db->fetch_object($resql3)) $produits[$obj->rowid] = $obj->label;
}

// --- Valeurs POST ---
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$mode_sortie        = GETPOST('mode_sortie', 'alpha'); // 0 = transfert, 1 = vente
$fk_entrepot_dest   = GETPOST('fk_entrepot_dest', 'int');
$fk_client_dest     = GETPOST('fk_client_dest', 'int');

// --- Header Dolibarr ---
llxHeader('', 'Nouvelle sortie');
print_fiche_titre("Nouvelle sortie");

// --- Formulaire principal ---
print '<form method="post" action="" id="formSortie">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="type" value="1">';

// --- Étape 1 : Sélection entrepôt source et mode ---
print '<table class="border centpercent">';

// Entrepôt source
print '<tr><td width="25%">Entrepôt source</td><td>';
print $form->selectarray('fk_entrepot_source', $entrepots, $fk_entrepot_source, 1);
print '</td></tr>';

// Mode de sortie
$options = [
    ''  => '-- Sélectionner --',
    '0' => 'Transfert interne',
    '1' => 'Vente'
];

print '<tr><td>Mode de sortie</td><td>';
print $form->selectarray('mode_sortie', $options, $mode_sortie, 1); // le 1 ajoute onchange="this.form.submit()"
print '</td></tr>';

// Selon le mode choisi, afficher le champ correspondant
if ($mode_sortie === '0') { // Transfert interne
    print '<tr><td>Entrepôt destination</td><td>';
    print $form->selectarray('fk_entrepot_dest', $entrepots, $fk_entrepot_dest, 1);
    print '</td></tr>';
} elseif ($mode_sortie === '1') { // Vente
    print '<tr><td>Client</td><td>';
    print $form->selectarray('fk_client_dest', $clients, $fk_client_dest, 1);
    print '</td></tr>';
}

print '</table>';
//print '</form>';
// Selon le mode choisi, afficher le champ correspondant
/*if ($mode_sortie === '0') { // Transfert interne
    print '<tr><td>Entrepôt destination</td><td>';
    print $form->selectarray('fk_entrepot_dest', $entrepots, $fk_entrepot_dest, 1);
    print '</td></tr>';
} elseif ($mode_sortie === '1') { // Vente
    print '<tr><td>Client</td><td>';
    print $form->selectarray('fk_client_dest', $clients, $fk_client_dest, 1);
    print '</td></tr>';
}

print '</table><br>';*/

// --- Étape 2 : Ajouter des produits ---
if (!empty($fk_entrepot_source) && isset($mode_sortie) && 
    (($mode_sortie==='0' && !empty($fk_entrepot_dest)) || ($mode_sortie==='1' && !empty($fk_client_dest)))) {
    // Afficher la section produits
    print '<h3>Produits à transférer</h3>';
    print '<table class="noborder centpercent" id="tableProduits">';
    print '<tr class="liste_titre">
            <th>Produit</th>
            <th>Nombre de cartons</th>
            <th>Poids (kg)</th>
            <th>Stock disponible</th>
            <th>Action</th>
           </tr>';
    print '</table>';
    print '<button type="button" class="button" onclick="addRow()">+ Ajouter un produit</button><br><br>';
    print '<input type="submit" formaction="confirm.php" class="button" value="Valider la sortie">';
} else {
    print '<input type="submit" class="button" value="Confirmer la sélection">';
}

print '</form>';

?>

<style>
#tableProduits {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}

#tableProduits tr {
    background: #f9fafb;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

#tableProduits tr:hover {
    background: #eef2ff;
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

#tableProduits td {
    padding: 10px;
    vertical-align: middle;
}

#tableProduits select,
#tableProduits input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
}

#tableProduits select:focus,
#tableProduits input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99,102,241,0.2);
}

#cartons-section {
    margin-top: 40px;
    background: #fff;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

#cartons-table {
    width: 100%;
    border-collapse: collapse;
}

#cartons-table th, #cartons-table td {
    border: 1px solid #ddd;
    padding: 8px;
}

#cartons-table th {
    background-color: #f3f3f3;
}

#cartons-table button {
    border: none;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
}
</style>

<script>
const produitsHTML = <?php echo json_encode(str_replace("\n", '', $form->selectarray('products[]', $produits, '', 0, 0))); ?>;
let cartonsDisponibles = [];
let cartonsSelectionnes = new Set();
let currentRow = null;

// Ajouter une ligne produit
function addRow() {
    const table = document.getElementById('tableProduits');
    const row = table.insertRow(-1);
    //row.insertCell(0).innerHTML = produitsHTML.replace('name="products[]"', 'name="products[]" onchange="updateRow(this)"');
    
    row.insertCell(0).innerHTML = `
    <select name="products[]" onchange="updateRow(this)">
        <option value="">-- Sélectionner un produit --</option>
        ${<?php echo json_encode(
            implode('', array_map(function($id, $label) {
                return "<option value='$id'>".addslashes($label)."</option>";
            }, array_keys($produits), $produits))
        ); ?>}
    </select>
`;
    row.insertCell(1).innerHTML = '<input type="number" name="nb_carton[]" value="0" readonly>';
    row.insertCell(2).innerHTML = '<input type="number" name="poids[]" value="0" step="0.01" readonly>';
    row.insertCell(3).innerHTML = '<input type="text" name="stock_dispo[]" value="" readonly>';
    row.insertCell(4).innerHTML = '<button type="button" onclick="removeRow(this)" style="background:#ef4444;">Supprimer</button>';
    row.insertCell(5).innerHTML = '<input type="text" name="cartons_rowid[]" value="" readonly>';
    

}

// Supprimer une ligne
function removeRow(btn) {
    const row = btn.closest('tr');
    row.remove();
}

// Lorsqu’un produit est sélectionné
function updateRow(select) {
    currentRow = select.closest('tr');
    const productId = select.value;
    const fk_entrepot = <?php echo $fk_entrepot_source ?: 0; ?>;
    if (!productId || fk_entrepot === 0) return;

    // Champ caché pour stocker les rowid cartons
    let hiddenInput = currentRow.querySelector('input[name="cartons_rowid[]"]');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'cartons_rowid[]';
        currentRow.appendChild(hiddenInput);
    }
    // Stocker le nom du produit dans currentRow pour l'affichage
    // Récupérer le label du produit sélectionné
    const productLabel = select.options[select.selectedIndex].text;

    // Stocker le nom du produit dans currentRow pour l'affichage
    currentRow.dataset.productLabel = productLabel;

    // Charger les cartons disponibles
    fetch('ajax_cartons.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot)
    .then(res => res.json())
    .then(data => {
        cartonsDisponibles = data;
        cartonsSelectionnes = new Set();
        afficherCartons();
    });
     updateStockDisponibilite(currentRow);
}
function updateStockDisponibilite(row) {
    var productId = row.querySelector('select[name="products[]"]').value;
    var fk_entrepot = <?php echo $fk_entrepot_source ?: 0; ?>;
    if(productId > 0) {
        fetch('ajax.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot)
        .then(response => response.json())
        .then(data => {
            row.querySelector('input[name="stock_dispo[]"]').value = data.nb_carton_dispo + ' cartons / ' + data.poids_dispo + ' kg';
        });
    } else {
        row.querySelector('input[name="stock_dispo[]"]').value = '';
    }
}



// Afficher la table des cartons
function afficherCartons() {
    const section = document.getElementById('cartons-section');
    const tbody = document.getElementById('cartons-body');
    section.style.display = 'block';
    const productLabel = currentRow.dataset.productLabel || '';
    section.querySelector('h3').textContent = `📦 Cartons disponibles pour : ${productLabel}`;

    tbody.innerHTML = '';

    cartonsDisponibles.forEach(c => {
        const isAdded = cartonsSelectionnes.has(c.rowid);
        const btnColor = isAdded ? '#ff4d4d' : '#4CAF50';
        const btnText = isAdded ? 'Enlever' : 'Ajouter';

        tbody.innerHTML += `
            <tr>
                <td>${c.rowid}</td>
                <td>${c.ref_carton || '(aucune)'}</td>
                <td>${c.poids}</td>
                <td>
                    <button onclick="toggleCarton(${c.rowid}, ${c.poids}, this)" style="background:${btnColor}">${btnText}</button>
                </td>
            </tr>`;
    });
}

// Ajouter ou enlever un carton
function toggleCarton(id, poids, btn) {
    const nbCartonInput = currentRow.querySelector('input[name="nb_carton[]"]');
    const poidsInput = currentRow.querySelector('input[name="poids[]"]');
    const hiddenInput = currentRow.querySelector('input[name="cartons_rowid[]"]');

    let nb = parseInt(nbCartonInput.value) || 0;
    let totalPoids = parseFloat(poidsInput.value) || 0;

    if (cartonsSelectionnes.has(id)) {
        // Retirer
        cartonsSelectionnes.delete(id);
        nb--;
        totalPoids -= poids;

        btn.style.background = '#4CAF50';
        btn.textContent = 'Ajouter';
    } else {
        // Ajouter
        cartonsSelectionnes.add(id);
        nb++;
        totalPoids += poids;

        btn.style.background = '#ff4d4d';
        btn.textContent = 'Enlever';
    }

    // Mettre à jour les champs de la ligne
    nbCartonInput.value = nb;
    poidsInput.value = totalPoids.toFixed(2);
    hiddenInput.value = Array.from(cartonsSelectionnes).join(',');
}

</script>


<div id="cartons-section" style="display:none;">
    <h3>📦 Cartons disponibles </h3>
    <table id="cartons-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>date Entree</th>
                <th>Poids (kg)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="cartons-body"></tbody>
    </table>
</div>

<?php
llxFooter();
$db->close();
?>
