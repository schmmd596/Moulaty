<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("bills");
$langs->load("products");

$form = new Form($db);

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

// ========================
// 1️⃣ Vérification réception
// ========================

$sql = "SELECT fk_facture 
        FROM ".MAIN_DB_PREFIX."pech_reception 
        WHERE rowid = ".(int) $id;

$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    if ($obj && !empty($obj->fk_facture) && $obj->fk_facture != 0) {
        accessforbidden($langs->trans("Cette réception est déjà liée à une facture. Accès refusé."));
        exit;
    }
}

$sql = "SELECT r.*, s.nom as fournisseur_nom
        FROM ".MAIN_DB_PREFIX."pech_reception as r
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = r.fk_fournisseur
        WHERE r.rowid = ".((int) $id);
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) accessforbidden();

$rec = $db->fetch_object($resql);

// ========================
// 2️⃣ Traitement des actions
// ========================
if ($action == 'create') {
    $db->begin();

    $facturefourn = new FactureFournisseur($db);
    $facturefourn->socid = $rec->fk_fournisseur;
    $facturefourn->ref = 'FAC-'.time();
    $facturefourn->ref_supplier = 'REC-' . time(); 
    $facturefourn->ref = ''; // NE PAS FORCER
    $facturefourn->libelle = "Facture de la réception ".$rec->ref;
    $facturefourn->note_public = "Mode de réception : " . (
        $rec->reception_mode == 1 ? "Voiture" :
        ($rec->reception_mode == 2 ? "Poids Brut" : "Poids Net")
    );
    $facturefourn->date = dol_now();
    $facturefourn->entity = $conf->entity;

    $result = $facturefourn->create($user);
    if ($result <= 0) {
        $db->rollback();
        setEventMessages($facturefourn->error, $facturefourn->errors, 'errors');
        header("Location: ./detail_rec.php?id=".$id);
        exit;
    }

    // Ajout des lignes selon le mode
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".((int)$id);
    $resql = $db->query($sql);
    while ($obj = $db->fetch_object($resql)) {
        $product = new Product($db);
        $product->fetch($obj->fk_product);

        if ($rec->reception_mode == 1) {
            // Mode voiture
            $qty = $obj->nb_voiture;
            $pu = $obj->prix_voiture;
            $desc = $product->label . " (Mode voiture)";
            $facturefourn->addline($desc, $pu,  0, 0, 0,$qty);
        }
        elseif ($rec->reception_mode == 2) {
            // Mode poids brut
            $qty = $obj->poids_brut;
            $pu = $obj->pu_brut;
            $desc = $product->label . " (Poids brut)";
            //$facturefourn->addline($desc, $pu, $qty, 0, 0, 0, $product->id);
            $facturefourn->addline(
                $desc,                  // Description
                $pu,                    // PU
                0,                      // txtva (taux TVA)
                0,                      // txlocaltax1
                0,                      // txlocaltax2
                $qty                  // Quantité
                //$product->id            // fk_product
            );
        }
        else {
            // Mode poids net
            if ($obj->poids_accepte > 0) {
                $desc1 = $product->label . " (Poids accepté)";
                $facturefourn->addline($desc1, $obj->pu_poids_accepte,  0, 0, 0,$obj->poids_accepte);
            }
            if ($obj->poids_rejete > 0) {
                $desc2 = $product->label . " (Poids rejeté)";
                $facturefourn->addline($desc2, $obj->pu_poids_rejete,  0, 0, 0,$obj->poids_rejete);
            }
        }
    }
    if (!empty($_POST['desc'])) {
        foreach ($_POST['desc'] as $k => $desc) {
            $desc = trim($desc);
            $qte = (float) $_POST['qte'][$k];
            $prix = (float) $_POST['prix'][$k];

            if ($desc && $qte > 0) {
                $result_line = $facturefourn->addline($desc, $prix, 0, 0, 0, $qte);
                if ($result_line <= 0) {
                    setEventMessages($facturefourn->error, $facturefourn->errors, 'errors');
                }
            }
        }
    }

    // Lier facture à la réception
    $db->query("UPDATE ".MAIN_DB_PREFIX."pech_reception SET fk_facture = ".$facturefourn->id." WHERE rowid = ".(int)$id);

    $db->commit();

    header("Location: ".DOL_URL_ROOT."/fourn/facture/card.php?facid=".$facturefourn->id);
    exit;
}

// ========================
// 3️⃣ Affichage des lignes avant validation
// ========================
llxHeader('', 'Préparation facture réception', '', '', 0, 0, '', array('/fourn/js/lib.js'));

print load_fiche_titre("Préparation de la facture - Réception ".$rec->ref);

print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td><b>Référence</b></td><td>'.$rec->ref.'</td></tr>';
print '<tr><td><b>Fournisseur</b></td><td>'.$rec->fournisseur_nom.'</td></tr>';
print '<tr><td><b>Mode</b></td><td>'.($rec->reception_mode == 1 ? "Voiture" : ($rec->reception_mode == 2 ? "Poids Brut" : "Poids Net")).'</td></tr>';
print '<tr><td><b>Date</b></td><td>'.dol_print_date($db->jdate($rec->date_creation), 'dayhour').'</td></tr>';
print '</table><br>';

// Tableau des lignes à facturer
print '<h3>Lignes à facturer</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Produit</td><td>Description</td><td align="right">Quantité</td><td align="right">PU</td><td align="right">Total</td></tr>';

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".((int)$id);
$resql = $db->query($sql);

$total_ht = 0;

while ($obj = $db->fetch_object($resql)) {
    $product = new Product($db);
    $product->fetch($obj->fk_product);

    if ($rec->reception_mode == 1) {
        $qty = $obj->nb_voiture;
        $pu = $obj->prix_voiture;
        $total = $qty * $pu;
        print '<tr><td>'.$product->ref.'</td><td>'.$product->label.' (Mode voiture)</td><td align="right">'.$qty.'</td><td align="right">'.price($pu).'</td><td align="right">'.price($total).'</td></tr>';
        $total_ht += $total;
    }
    elseif ($rec->reception_mode == 2) {
        $qty = $obj->poids_brut;
        $pu = $obj->pu_brut;
        $total = $qty * $pu;
        print '<tr><td>'.$product->ref.'</td><td>'.$product->label.' (Poids brut)</td><td align="right">'.$qty.'</td><td align="right">'.price($pu).'</td><td align="right">'.price($total).'</td></tr>';
        $total_ht += $total;
    }
    else {
        if ($obj->poids_accepte > 0) {
            $total = $obj->poids_accepte * $obj->pu_poids_accepte;
            print '<tr><td>'.$product->ref.'</td><td>'.$product->label.' (Poids accepté)</td><td align="right">'.$obj->poids_accepte.'</td><td align="right">'.price($obj->pu_poids_accepte).'</td><td align="right">'.price($total).'</td></tr>';
            $total_ht += $total;
        }
        if ($obj->poids_rejete > 0) {
            $total = $obj->poids_rejete * $obj->pu_poids_rejete;
            print '<tr><td>'.$product->ref.'</td><td>'.$product->label.' (Poids rejeté)</td><td align="right">'.$obj->poids_rejete.'</td><td align="right">'.price($obj->pu_poids_rejete).'</td><td align="right">'.price($total).'</td></tr>';
            $total_ht += $total;
        }
    }
}

print '<tr class="liste_total"><td colspan="4" align="right"><b>Total HT</b></td><td align="right"><b>'.price($total_ht).'</b></td></tr>';
print '</table><br>';

print '<h3>Ajouter des lignes manuelles</h3>';

print '<form method="POST" id="factureForm">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<table id="ligneTable" class="noborder centpercent">';
print '<tr class="liste_titre">
        <th>Description</th>
        <th>Quantité</th>
        <th>Prix unitaire</th>
        <th>Total</th>
        <th>Action</th>
      </tr>';

/*print '<tr>
        <td><input type="text" name="desc[]" class="flat" placeholder="Description"></td>
        <td><input type="number" name="qte[]" class="flat" step="0.01" value="1" oninput="updateTotal(this)"></td>
        <td><input type="number" name="prix[]" class="flat" step="0.01" value="0" oninput="updateTotal(this)"></td>
        <td class="ligne-total" align="right">0</td>
        <td align="center"><button type="button" class="butAction" onclick="removeLine(this)">🗑️</button></td>
      </tr>';
*/
print '</table>';

print '<div class="center" style="margin-top: 10px;">';
print '<button type="button" class="button" onclick="addLine()">+ Ajouter une ligne</button>';
print '</div>';

print '<hr>';
print '<div class="center">';
print '<b>Total Facture : </b> <span id="totalGlobal">'.$total_ht.'</span> '.$conf->currency.'<br><br>';
print '<input type="submit" class="button" value="Créer la facture fournisseur">';
print ' &nbsp; <a class="button" href="./detail_rec.php?id='.$id.'">Annuler</a>';
print '</div>';

print '</form>';

/*print '<div class="center">';
print '<form method="POST">';

print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<input type="submit" class="button" value="Créer la facture fournisseur">';
print ' &nbsp; <a class="button button-cancel" href="./detail_rec.php?id='.$id.'">Annuler</a>';
print '</form>';
print '</div>';*/

print '</div>';

print '<script>
function updateTotal(el) {
    const row = el.closest("tr");
    const qte = parseFloat(row.querySelector("input[name=\'qte[]\']").value) || 0;
    const prix = parseFloat(row.querySelector("input[name=\'prix[]\']").value) || 0;
    const total = qte * prix;
    row.querySelector(".ligne-total").innerText = total.toFixed(2);
    updateGlobalTotal();
}

function updateGlobalTotal() {
    // 🔹 Montant de base PHP (lignes automatiques)
    let totalInitial = '.json_encode((float)$total_ht).';

    // 🔹 Somme des lignes manuelles ajoutées
    let totalManuel = 0;
    document.querySelectorAll(".ligne-total").forEach(td => {
        totalManuel += parseFloat(td.innerText) || 0;
    });

    // 🔹 Total général = automatique + manuel
    let totalGlobal = totalInitial + totalManuel;

    // 🔹 Mise à jour affichage
    document.getElementById("totalGlobal").innerText = totalGlobal.toFixed(2);
}
function addLine() {
    const table = document.getElementById("ligneTable");
    const row = document.createElement("tr");
    row.innerHTML = `
        <td><input type="text" name="desc[]" class="flat" placeholder="Description"></td>
        <td><input type="number" min="1" name="qte[]" class="flat"  value="1" oninput="updateTotal(this)"></td>
        <td><input type="number" min="0" name="prix[]" class="flat" step="0.01" value="0" oninput="updateTotal(this)"></td>
        <td class="ligne-total" align="right">0</td>
        <td align="center"><button type="" class="" onclick="removeLine(this)">🗑️</button></td>
    `;
    table.appendChild(row);
}

function removeLine(btn) {
    const row = btn.closest("tr");
    row.remove();
    updateGlobalTotal();
}
</script>';
llxFooter();
$db->close();
?>
