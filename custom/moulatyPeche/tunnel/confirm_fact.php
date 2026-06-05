<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("bills");
$langs->load("products");
$langs->load("pech@pech");

$form = new Form($db);

// Récupération des mises en plat sélectionnées
$selected = $_POST['select_misenplat'] ?? [];
$decongele = $_POST['decongele'] ?? [];
$fk_entrepot = GETPOST('fk_entrepot', 'int');

if (empty($selected)) {
    setEventMessages("Aucune mise en plat sélectionnée", null, 'errors');
    header("Location: misenplat_list.php");
    exit;
}

// Récupérer la liste des services congélation depuis le système
$services = [];
$resql = $db->query("SELECT rowid, label, price FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type=1"); // type service
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $services[$obj->rowid] = ['label'=>$obj->label, 'price'=>$obj->price];
    }
}

// Facture fournisseur - Création automatique
if ($_POST['action'] == 'create') {
    $db->begin();

    // On prend le fournisseur du premier rowid (supposé même fournisseur)
    $rowid_first = (int)$selected[0];
    $sql = "SELECT fk_bon_entree, fk_fournisseur FROM ".MAIN_DB_PREFIX."pech_misenplat WHERE rowid = ".$rowid_first;
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);

    $socid = $obj->fk_fournisseur;
    $facturefourn = new FactureFournisseur($db);
    $facturefourn->socid = $socid;
    $facturefourn->ref = ''; // Dolibarr génère automatiquement
    $facturefourn->ref_supplier = 'MP-' . time(); 
    $facturefourn->date = dol_now();
    $facturefourn->libelle = "Facture pour services de congélation";
    $facturefourn->entity = $conf->entity;

    $result = $facturefourn->create($user);
    if ($result <= 0) {
        $db->rollback();
        setEventMessages($facturefourn->error, $facturefourn->errors, 'errors');
        exit;
    }

    $total_ht = 0;

    // Ajout ligne service congélation sélectionnée
    $selected_service_id = (int)($_POST['service_congelation'] ?? 0);
    $service_label = $services[$selected_service_id]['label'] ?? 'Service congélation';
    $service_price = $services[$selected_service_id]['price'] ?? 0;

    foreach ($selected as $rowid) {
        $nb_decongele = (float)($decongele[$rowid] ?? 0);
        if ($nb_decongele > 0) {
            $sql = "SELECT mp.poids_plat, mp.fk_entrepot, mp.fk_congelateur, p.ref AS ref_prod, p.label AS label_prod
                    FROM ".MAIN_DB_PREFIX."pech_misenplat mp
                    LEFT JOIN ".MAIN_DB_PREFIX."pech_receptiondet rd ON rd.rowid = mp.fk_reception_det
                    LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = rd.fk_product
                    WHERE mp.rowid = ".$rowid;
            $resql = $db->query($sql);
            $obj = $db->fetch_object($resql);

            $poids_total = $nb_decongele * (float)$obj->poids_plat;
            $desc = "$service_label - ".$obj->ref_prod." ".$obj->label_prod;

            $facturefourn->addline($desc, $service_price, 0, 0, 0, $poids_total);
            $total_ht += $poids_total * $service_price;
        }
    }

    // Lignes manuelles
    if (!empty($_POST['desc'])) {
        foreach ($_POST['desc'] as $k => $desc) {
            $desc = trim($desc);
            $qte = (float) $_POST['qte'][$k];
            $prix = (float) $_POST['prix'][$k];
            if ($desc && $qte > 0) {
                $facturefourn->addline($desc, $prix, 0, 0, 0, $qte);
                $total_ht += $qte * $prix;
            }
        }
    }

    $db->commit();
    header("Location: ".DOL_URL_ROOT."/fourn/facture/card.php?facid=".$facturefourn->id);
    exit;
}

// ========================
// Affichage avant validation
// ========================
llxHeader('', 'Préparation facture fournisseur');

print load_fiche_titre("Préparation facture fournisseurs");

print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create">';

// Sélection service congélation
print '<h3>Service congélation</h3>';
print '<select name="service_congelation">';
foreach ($services as $id => $s) {
    print '<option value="'.$id.'">'.$s['label'].' (PU: '.price($s['price']).')</option>';
}
print '</select><br><br>';

// Récupération et affichage lignes décongelées avec congélateur et entrepôt
print '<h3>Lignes à facturer (plats décongelés)</h3>';
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>Produit</th><th>Plats décongelés</th><th>Poids total (kg)</th><th>Congélateur</th><th>Entrepôt</th></tr>';

$total_ht_display = 0;
foreach ($selected as $rowid) {
    $nb_decongele = (float)($decongele[$rowid] ?? 0);
    if ($nb_decongele > 0) {
        $sql = "SELECT 
            mp.poids_plat, 
            mp.fk_entrepot, 
            mp.fk_congelateur, 
            p.ref AS ref_prod, 
            p.label AS label_prod,
            c.nom AS cong_nom, 
            e.label AS ent_label
        FROM ".MAIN_DB_PREFIX."pech_misenplat mp
        LEFT JOIN ".MAIN_DB_PREFIX."pech_receptiondet rd 
            ON rd.rowid = mp.fk_reception_det
        LEFT JOIN ".MAIN_DB_PREFIX."product p 
            ON p.rowid = rd.fk_product
        LEFT JOIN ".MAIN_DB_PREFIX."societe c 
            ON c.rowid = mp.fk_congelateur
        
        WHERE mp.rowid = ".$rowid;

$resql = $db->query($sql);

// Vérification et affichage de l'erreur si elle existe
if (!$resql) {
    print '<div style="color:red;font-weight:bold;">Erreur SQL : '.$db->lasterror().'</div>';
    exit;
}

$obj = $db->fetch_object($resql);

        $poids_total = $nb_decongele * (float)$obj->poids_plat;
        $total_ht_display += $poids_total * $service_price;

        print '<tr>';
        print '<td>'.$obj->ref_prod.' - '.$obj->label_prod.'</td>';
        print '<td align="right">'.$nb_decongele.'</td>';
        print '<td align="right">'.price($poids_total).'</td>';
        print '<td>'.$obj->cong_nom.'</td>';
        print '<td>'.$obj->ent_label.'</td>';
        print '</tr>';

        print '<input type="hidden" name="select_misenplat[]" value="'.$rowid.'">';
        print '<input type="hidden" name="decongele['.$rowid.']" value="'.$nb_decongele.'">';
    }
}
print '<tr class="liste_total"><td colspan="2" align="right"><b>Total HT service</b></td><td align="right" colspan="3"><b>'.price($total_ht_display).'</b></td></tr>';
print '</table><br>';

// Formulaire lignes manuelles
print '<h3>Ajouter des lignes manuelles</h3>';
print '<table id="ligneTable" class="noborder centpercent">';
print '<tr class="liste_titre"><th>Description</th><th>Quantité</th><th>PU</th><th>Total</th><th>Action</th></tr>';
print '</table>';
print '<div class="center" style="margin-top:10px;"><button type="button" class="button" onclick="addLine()">+ Ajouter une ligne</button></div>';

// Total affiché en clair
print '<div class="center" style="margin-top:20px;">';
print '<b>Montant total HT : </b><span id="totalGlobal">'.$total_ht_display.'</span> '.$conf->currency.'<br><br>';
print '<input type="submit" class="button button-action" value="Créer la facture fournisseur">';
print '</div>';

print '</form>';

// JS pour lignes manuelles
print '<script>
function addLine() {
    const table = document.getElementById("ligneTable");
    const row = document.createElement("tr");
    row.innerHTML = `
        <td><input type="text" name="desc[]" class="flat" placeholder="Description"></td>
        <td><input type="number" name="qte[]" class="flat" step="0.01" value="1" oninput="updateTotal(this)"></td>
        <td><input type="number" name="prix[]" class="flat" step="0.01" value="0" oninput="updateTotal(this)"></td>
        <td class="ligne-total" align="right">0</td>
        <td align="center"><button type="button" onclick="removeLine(this)">🗑️</button></td>
    `;
    table.appendChild(row);
}
function removeLine(btn) {
    const row = btn.closest("tr");
    row.remove();
    updateGlobalTotal();
}
function updateTotal(el) {
    const row = el.closest("tr");
    const qte = parseFloat(row.querySelector("input[name=\'qte[]\']").value) || 0;
    const prix = parseFloat(row.querySelector("input[name=\'prix[]\']").value) || 0;
    row.querySelector(".ligne-total").innerText = (qte*prix).toFixed(2);
    updateGlobalTotal();
}
function updateGlobalTotal() {
    let total = '.json_encode((float)$total_ht_display).';
    document.querySelectorAll(".ligne-total").forEach(td => {
        total += parseFloat(td.innerText) || 0;
    });
    document.getElementById("totalGlobal").innerText = total.toFixed(2);
}
</script>';

llxFooter();
$db->close();
?>
