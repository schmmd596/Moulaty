<?php

/**
 * Fichier : conversion_xof.php
 * Gestion de la conversion XOF / Devise principale
 * Règle Dolibarr : 1 [devise de base] = rate × [devise étrangère]
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

global $db, $langs, $user, $conf;



$langs->load("multicurrency@multicurrency");
$langs->load("admin");

$action = GETPOST('action', 'alpha');
$currency_amount = GETPOST('currency_amount', 'alpha'); // Valeur en MRO pour 5000 XOF
$systemCurrency = $conf->currency;
$fixed_xof_amount = 5000;

// Pagination Dolibarr
$limit = GETPOST('limit', 'int') ?: 10;
$page  = GETPOST('page', 'int');
$offset = $limit * (int) $page;

// Filtres dates
$date_start = GETPOST('date_start', 'alpha');
$date_end   = GETPOST('date_end', 'alpha');
// Devise de base
$base_currency = !empty($conf->currency) ? $conf->currency : 'MRO';
// Devise étrangère (XOF)
$foreign_currency = 'XOF';

// Montant fixe XOF
$fixed_xof_amount = 5000;

// Initialisation des messages
$mesg = '';

// Actions
switch($action) {
    case 'update':
        // Mettre à jour le taux XOF
        if ($currency_amount > 0) {
            // Calculer le taux selon la règle Dolibarr :
            // 1 [devise de base] = rate × [devise étrangère]
            // Donc rate = [montant XOF] ÷ [montant devise de base]
            $rate = $fixed_xof_amount / $currency_amount;
            
            // Vérifier si XOF existe dans la table multicurrency
            $sql_check = "SELECT rowid FROM " . MAIN_DB_PREFIX . "multicurrency 
                         WHERE code = '" . $db->escape($foreign_currency) . "' 
                         AND entity IN (" . getEntity('multicurrency') . ")";
            $res_check = $db->query($sql_check);
            
            if ($db->num_rows($res_check) > 0) {
                $obj = $db->fetch_object($res_check);
                $xof_id = $obj->rowid;
                
                // Mettre à jour le taux dans Dolibarr
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."multicurrency_rate
                (fk_multicurrency, rate, date_sync, entity)
                VALUES (
                    ".(int)$xof_id.",
                    ".$db->escape($rate).",
                    NOW(),
                    ".$conf->entity."
                )";
                
                if ($db->query($sql)) {
                    $mesg = '<div class="ok">' . $langs->trans("XOFExchangeRateUpdated") . '</div>';
                    
                    // Mettre à jour les variables de configuration
                    dolibarr_set_const($db, "XOF_EXCHANGE_RATE", $rate, 'chaine', 0, '', $conf->entity);
                    dolibarr_set_const($db, "XOF_FIXED_AMOUNT", $fixed_xof_amount, 'chaine', 0, '', $conf->entity);
                    dolibarr_set_const($db, "XOF_CURRENCY_VALUE", $currency_amount, 'chaine', 0, '', $conf->entity);
                } else {
                    $mesg = '<div class="error">' . $langs->trans("ErrorUpdateXOF") . ': ' . $db->lasterror() . '</div>';
                }
            } else {
                $mesg = '<div class="error">' . $langs->trans("XOFNotConfigured") . '</div>';
            }
        } else {
            $mesg = '<div class="error">' . $langs->trans("InvalidAmount") . '</div>';
        }
        break;
        
    case 'init':
        // Initialiser la devise XOF si elle n'existe pas
        initXOFCurrency();
        $mesg = '<div class="ok">' . $langs->trans("XOFInitialized") . '</div>';
        break;
}

// Fonction pour initialiser la devise XOF
function initXOFCurrency() {
    global $db, $conf, $foreign_currency, $base_currency, $fixed_xof_amount;
    
    // Vérifier si XOF existe déjà
    $sql_check = "SELECT rowid FROM " . MAIN_DB_PREFIX . "multicurrency 
                  WHERE code = '" . $db->escape($foreign_currency) . "' 
                  AND entity IN (" . getEntity('multicurrency') . ")";
    $res_check = $db->query($sql_check);
    
    if ($db->num_rows($res_check) == 0) {
        // Ajouter la devise XOF
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "multicurrency (code, name, entity)";
        $sql .= " VALUES ('" . $db->escape($foreign_currency) . "', 'Franc CFA Ouest Africain', " . $conf->entity . ")";
        
        if ($db->query($sql)) {
            $xof_id = $db->last_insert_id(MAIN_DB_PREFIX . "multicurrency");
            
            // Valeur par défaut : 5000 XOF = 1 [devise de base]
            $default_currency_amount = 1;
            $rate = $fixed_xof_amount / $default_currency_amount; // 5000 / 1 = 5000
            
            // Ajouter le taux dans Dolibarr
            $sql_rate = "INSERT INTO " . MAIN_DB_PREFIX . "multicurrency_rate";
            $sql_rate .= " (fk_multicurrency, rate, date_sync, entity)";
            $sql_rate .= " VALUES (" . (int)$xof_id . ", " . $db->escape($rate);
            $sql_rate .= ", NOW(), " . $conf->entity . ")";
            
            $db->query($sql_rate);
            
            // Sauvegarder les variables
            dolibarr_set_const($db, "XOF_EXCHANGE_RATE", $rate, 'chaine', 0, '', $conf->entity);
            dolibarr_set_const($db, "XOF_FIXED_AMOUNT", $fixed_xof_amount, 'chaine', 0, '', $conf->entity);
            dolibarr_set_const($db, "XOF_CURRENCY_VALUE", $default_currency_amount, 'chaine', 0, '', $conf->entity);
        }
    }
    
    return true;
}

// Récupérer les informations actuelles
function getCurrentExchangeInfo() {
    global $db, $conf, $foreign_currency, $base_currency, $fixed_xof_amount;
    
    $info = array(
        'rate' => 0,          // 1 [base] = rate × XOF (stocké dans Dolibarr)
        'currency_amount' => 0, // 5000 XOF = ? [base]
        'xof_per_base' => 0    // 1 [base] = ? XOF (calculé à partir du rate)
    );
    
    // Récupérer le taux stocké dans Dolibarr
    $sql = "SELECT r.rate FROM " . MAIN_DB_PREFIX . "multicurrency m";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "multicurrency_rate r ON r.fk_multicurrency = m.rowid";
    $sql .= " WHERE m.code = '" . $db->escape($foreign_currency) . "'";
    $sql .= " AND m.entity IN (" . getEntity('multicurrency') . ")";
    $sql .= " ORDER BY r.rowid DESC LIMIT 1";
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $info['rate'] = (float)$obj->rate;
        
        // Calculer : 1 [base] = rate × XOF
        $info['xof_per_base'] = $info['rate'];
        
        // Calculer : 5000 XOF = ? [base]
        // Si 1 [base] = rate XOF, alors 1 XOF = 1/rate [base]
        // Donc 5000 XOF = 5000 × (1/rate) [base] = 5000/rate [base]
        $info['currency_amount'] = $fixed_xof_amount / $info['rate'];
    } else {
        // Utiliser les valeurs de configuration
        $info['rate'] = (!empty($conf->global->XOF_EXCHANGE_RATE)) ? $conf->global->XOF_EXCHANGE_RATE : 5000;
        $info['currency_amount'] = (!empty($conf->global->XOF_CURRENCY_VALUE)) ? $conf->global->XOF_CURRENCY_VALUE : 1;
        $info['xof_per_base'] = $info['rate'];
    }
    
    return $info;
}

// Vérifier si XOF est configuré
$is_xof_configured = false;
$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "multicurrency 
              WHERE code = '" . $db->escape($foreign_currency) . "' 
              AND entity IN (" . getEntity('multicurrency') . ")";
$res_check = $db->query($sql_check);
if ($res_check) {
    $obj = $db->fetch_object($res_check);
    $is_xof_configured = ($obj->count > 0);
}

// Récupérer les informations actuelles
$current_info = getCurrentExchangeInfo();

// Affichage de l'entête
llxHeader('', $langs->trans("XOFConversionManagement"));

// CSS et JavaScript
print '<style>
.conversion-card { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 25px; margin-bottom: 25px; }
.rate-display { font-size: 2em; font-weight: bold; text-align: center; margin: 20px 0; padding: 15px; background: #f7fafc; border-radius: 6px; }
.formula { background: #e8f4f8; padding: 15px; border-radius: 6px; margin: 15px 0; font-family: monospace; }
.input-group { display: flex; margin-bottom: 10px; }
.input-group-prepend { padding: 10px 15px; background: #e2e8f0; border: 1px solid #cbd5e0; border-right: none; border-radius: 6px 0 0 6px; min-width: 120px; }
.input-group-append { padding: 10px 15px; background: #e2e8f0; border: 1px solid #cbd5e0; border-left: none; border-radius: 0 6px 6px 0; min-width: 80px; }
.form-control { padding: 10px 12px; border: 1px solid #cbd5e0; width: 100%; text-align: right; }
.btn { padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #4CAF50; color: white; }
</style>';

// Titre
print '<div class="titre">' . $langs->trans("XOFConversionManagement") . '</div>';

// Affichage des messages
if (!empty($mesg)) {
    print $mesg;
}

// Carte principale
print '<div class="conversion-card">';

// Affichage du taux actuel
print '<div class="rate-display">';
print '1 ' . $base_currency . ' = ' . number_format($current_info['rate'], 2) . ' XOF';
print '</div>';

// Équivalence fixe
print '<div style="text-align: center; margin: 20px 0; padding: 20px; background: #f0fff4; border-radius: 6px;">';
print '<h3 style="margin-top: 0;">' . $fixed_xof_amount . ' XOF = ' . number_format($current_info['currency_amount'], 4) . ' ' . $base_currency . '</h3>';
print '</div>';

// Formulaire Dolibarr
print '<div class="formula">';
print '<strong>Règle systeme :</strong><br>';
print '1 ' . $base_currency . ' = rate × ' . $foreign_currency . '<br>';
print 'rate = ' . $fixed_xof_amount . ' ÷ ' . number_format($current_info['currency_amount'], 4) . ' = ' . number_format($current_info['rate'], 2);
print '</div>';

// Si XOF n'est pas configuré
if (!$is_xof_configured) {
    print '<div style="text-align: center; padding: 20px;">';
    print '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="init">';
    print '<button type="submit" class="btn btn-primary">Initialiser la devise XOF</button>';
    print '</form>';
    print '</div>';
} else {
    // Formulaire de mise à jour
    print '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update">';
    
    print '<div class="input-group">';
    print '<div class="input-group-prepend">' . $fixed_xof_amount . ' XOF =</div>';
    print '<input type="number" name="currency_amount" value="' . number_format($current_info['currency_amount'], 4) . '" step="0.0001" min="0.0001" class="form-control" required>';
    print '<div class="input-group-append">' . $base_currency . '</div>';
    print '</div>';
    
    // Calcul automatique
    print '<div id="autoCalc" style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 6px; display: none;">';
    print 'Nouveau rate = ' . $fixed_xof_amount . ' ÷ <span id="newAmount">0</span> = <span id="newRate">0</span>';
    print '</div>';
    
    print '<div style="margin-top: 20px;">';
    print '<button type="submit" class="btn btn-primary">Mettre à jour</button>';
    print '</div>';
    print '</form>';
}

print '</div>'; // Fin de la carte
// =====================================================
// 📜 HISTORIQUE DES TAUX XOF (pagination + recherche)
// =====================================================



// Récupération ID devise XOF
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."multicurrency WHERE code = 'XOF'";
$res = $db->query($sql);
if (!$res || !$db->num_rows($res)) {
    print '<div class="error">Devise XOF introuvable</div>';
    llxFooter();
    exit;
}
$xof_id = $db->fetch_object($res)->rowid;

// Construction WHERE
$where = " WHERE r.fk_multicurrency = ".$xof_id;

if (!empty($date_start)) {
    $where .= " AND r.date_sync >= '".$db->escape($date_start)." 00:00:00'";
}
if (!empty($date_end)) {
    $where .= " AND r.date_sync <= '".$db->escape($date_end)." 23:59:59'";
}

// Compter le total
$sqlcount = "SELECT COUNT(*) as total
             FROM ".MAIN_DB_PREFIX."multicurrency_rate r
             $where";
$rescount = $db->query($sqlcount);
$total = ($rescount) ? $db->fetch_object($rescount)->total : 0;

// Récupération des données
$sql = "SELECT r.rate, r.date_sync
        FROM ".MAIN_DB_PREFIX."multicurrency_rate r
        $where
        ORDER BY r.date_sync DESC
        ".$db->plimit($limit, $offset);

$resql = $db->query($sql);

// =====================================================
// 🎨 AFFICHAGE
// =====================================================

print '<div class="conversion-card">';
print '<h2>📊 Historique des taux XOF → '.$systemCurrency.'</h2>';

// 🔍 FILTRE
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="fichecenter">';

print '<div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px;">';

print '<div>';
print '<label>Date début</label><br>';
print '<input type="date" name="date_start" value="'.$date_start.'" class="flat">';
print '</div>';

print '<div>';
print '<label>Date fin</label><br>';
print '<input type="date" name="date_end" value="'.$date_end.'" class="flat">';
print '</div>';

print '<div style="align-self:flex-end;">';
print '<button class="butAction">Filtrer</button>';
print '</div>';

print '</div>';
print '</form>';

// 📋 TABLE
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<th>Date</th>';
print '<th>1 '.$systemCurrency.' = XOF</th>';
print '<th>5000 XOF = '.$systemCurrency.'</th>';
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
    while ($obj = $db->fetch_object($resql)) {

        // Calcul conversion
        $currency_value = $fixed_xof_amount / $obj->rate;

        print '<tr class="oddeven">';
        print '<td>'.dol_print_date($db->jdate($obj->date_sync), 'dayhour').'</td>';
        print '<td><b>'.number_format($obj->rate, 2, '.', ' ').'</b></td>';
print '<td><b>'.number_format($currency_value, 2, '.', ' ').'</b></td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="3" class="opacitymedium">Aucun résultat</td></tr>';
}

print '</table>';

// 📌 PAGINATION
print_fleche_navigation(
    (int)$page,
    $_SERVER['PHP_SELF'],
    '',
    (int) $limit,
    (int)$total
);

print '</div>'; // fin card

// JavaScript pour le calcul automatique
print '<script>
document.querySelector(\'[name="currency_amount"]\').addEventListener(\'input\', function() {
    const amount = parseFloat(this.value);
    const calcDiv = document.getElementById(\'autoCalc\');
    
    if (!isNaN(amount) && amount > 0) {
        const newRate = ' . $fixed_xof_amount . ' / amount;
        document.getElementById(\'newAmount\').textContent = amount.toFixed(4);
        document.getElementById(\'newRate\').textContent = newRate.toFixed(2);
        calcDiv.style.display = \'block\';
    } else {
        calcDiv.style.display = \'none\';
    }
});
</script>';

llxFooter();
$db->close();
?>