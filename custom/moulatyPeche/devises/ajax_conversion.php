<?php
/**
 * Fichier : ajax_conversion.php
 * Conversion AJAX des devises
 */

require '../../../main.inc.php';

global $db, $langs, $conf;

header('Content-Type: application/json');

$amount = GETPOST('amount', 'float');
$from_currency = GETPOST('from_currency', 'alpha');
$to_currency = GETPOST('to_currency', 'alpha');
$token = GETPOST('token', 'alpha');

// Vérifier le token
if ($token != $_SESSION['newtoken']) {
    echo json_encode(array('success' => false, 'error' => $langs->trans("InvalidToken")));
    exit;
}

if ($amount <= 0) {
    echo json_encode(array('success' => false, 'error' => $langs->trans("AmountMustBePositive")));
    exit;
}

// Devise de base
$base_currency = !empty($conf->currency) ? $conf->currency : 'EUR';

// Si les devises sont identiques
if ($from_currency == $to_currency) {
    echo json_encode(array('success' => true, 'result' => $amount));
    exit;
}

// Récupérer les taux
$rate_from = 1.0;
$rate_to = 1.0;

if ($from_currency != $base_currency) {
    $rate_from = getCurrencyRate($from_currency);
}

if ($to_currency != $base_currency) {
    $rate_to = getCurrencyRate($to_currency);
}

// Calcul de la conversion
// Convertir d'abord en devise de base, puis vers la devise cible
if ($rate_from > 0 && $rate_to > 0) {
    $amount_in_base = $amount / $rate_from;
    $result = $amount_in_base * $rate_to;
    
    echo json_encode(array('success' => true, 'result' => $result));
} else {
    echo json_encode(array('success' => false, 'error' => $langs->trans("RateNotFound")));
}

// Fonction pour récupérer le taux d'une devise
function getCurrencyRate($currency_code) {
    global $db, $base_currency;
    
    $sql = "SELECT r.rate FROM " . MAIN_DB_PREFIX . "multicurrency m";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "multicurrency_rate r ON r.fk_multicurrency = m.rowid";
    $sql .= " WHERE m.code = '" . $db->escape($currency_code) . "'";
    $sql .= " AND r.rowid = (SELECT MAX(rowid) FROM " . MAIN_DB_PREFIX . "multicurrency_rate WHERE fk_multicurrency = m.rowid)";
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        return (float)$obj->rate;
    }
    
    return 0;
}

exit;
?>