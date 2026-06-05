<?php
function getDeviseEntrepot($db, $entrepot_id) {
    
    if (empty($entrepot_id)) {
        return 'MRO'; // Devise par défaut
    }
    
    $sql = "SELECT monnaie 
            FROM " . MAIN_DB_PREFIX . "entrepot_extrafields 
            WHERE fk_object = " . (int)$entrepot_id . " 
            LIMIT 1";
    
    $res = $db->query($sql);
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        // Retourne la valeur numérique (1, 2, etc.) ou 'MRO' par défaut
        return !empty($obj->monnaie) ? $obj->monnaie : 'MRO';
    }
    
    return 'MRO'; // Valeur par défaut si non trouvé
}

function getNomMonnaie($db, $monnaieId) {
    // Si c'est déjà MRO
    if ($monnaieId == 'MRO' || $monnaieId == '1' || empty($monnaieId)) {
        return 'Ouguiya mauritanien (MRO)';
    }
    
    // Si c'est un ID numérique, chercher dans la table multicurrency
    if (is_numeric($monnaieId)) {
        $sql = "SELECT code, name 
                FROM " . MAIN_DB_PREFIX . "multicurrency 
                WHERE rowid = " . (int)$monnaieId . " 
                LIMIT 1";
    } 
    // Sinon si c'est un code (3 lettres)
    elseif (is_string($monnaieId) && strlen($monnaieId) == 3) {
        $sql = "SELECT code, name 
                FROM " . MAIN_DB_PREFIX . "multicurrency 
                WHERE code = '" . $db->escape($monnaieId) . "' 
                LIMIT 1";
    } else {
        return 'Devise inconnue';
    }
    
    $res = $db->query($sql);
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        return $obj->name . ' (' . $obj->code . ')';
    }
    
    return 'Devise inconnue';
}


function getCodeDeviseFromId($db, $monnaieId) {
    // Si c'est déjà MRO
    if ($monnaieId == 'MRO' || $monnaieId == '1' || empty($monnaieId)) {
        return 'Ouguiya mauritanien (MRO)';
    }
    
    // Si c'est un ID numérique, chercher dans la table multicurrency
    if (is_numeric($monnaieId)) {
        $sql = "SELECT code, name 
                FROM " . MAIN_DB_PREFIX . "multicurrency 
                WHERE rowid = " . (int)$monnaieId . " 
                LIMIT 1";
    } 
    // Sinon si c'est un code (3 lettres)
    elseif (is_string($monnaieId) && strlen($monnaieId) == 3) {
        $sql = "SELECT code, name 
                FROM " . MAIN_DB_PREFIX . "multicurrency 
                WHERE code = '" . $db->escape($monnaieId) . "' 
                LIMIT 1";
    } else {
        return 'Devise inconnue';
    }
    
    $res = $db->query($sql);
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        return  $obj->code ;
    }
    
    return 'Devise inconnue';
}
// ============================================================================
// 🔹 FONCTION DE CONVERSION DES MONTANTS
// ============================================================================
/**
 * Convertit un montant de MRO vers une devise cible
 * @param object $db Instance de la base de données
 * @param float $amount Montant en MRO à convertir
 * @param string $targetCurrency Code devise ISO cible (EUR, USD, etc.)
 * @param string $date Date pour le taux (optionnel, par défaut aujourd'hui)
 * @return array|false Tableau avec conversion ou false si erreur
 */
// ============================================================================
// 🔹 FONCTION DE CONVERSION DES MONTANTS (version corrigée)
// ============================================================================
/**
 * Convertit un montant de MRO vers une devise cible
 * @param object $db Instance de la base de données
 * @param float $amount Montant en MRO à convertir
 * @param int $targetCurrencyId ID numérique de la devise cible (ex: 2 pour EUR)
 * @param string $date Date pour le taux (optionnel, par défaut aujourd'hui)
 * @return array|false Tableau avec conversion ou false si erreur
 */
function convertFromMRO($db, $amount, $targetCurrencyId, $date = null) {
    
    // Si ID devise est vide ou MRO (ID 1), pas de conversion
    if (empty($targetCurrencyId) || $targetCurrencyId == 1) {
        return [
            'converted' => $amount,
            'rate' => 1.0,
            'currency_id' => 1,
            'currency_code' => 'MRO',
            'original' => $amount,
            'date_rate' => date('Y-m-d H:i:s')
        ];
    }
    
    // Si montant est 0, pas besoin de conversion
    if ($amount == 0) {
        return [
            'converted' => 0,
            'rate' => 0,
            'currency_id' => $targetCurrencyId,
            'currency_code' => '',
            'original' => 0,
            'date_rate' => date('Y-m-d H:i:s')
        ];
    }
    
    // Récupérer d'abord le code devise pour les logs
    $codeDevise = getCodeDeviseFromId($db, $targetCurrencyId);
    
    // Préparer la requête selon la date
    if (empty($date)) {
        // Récupérer le taux le plus récent
        $sql = "SELECT rate, date_sync 
                FROM " . MAIN_DB_PREFIX . "multicurrency_rate 
                WHERE fk_multicurrency = " . (int)$targetCurrencyId . "
                ORDER BY date_sync DESC 
                LIMIT 1";
    } else {
        // Récupérer le taux pour une date spécifique
        $sql = "SELECT rate, date_sync 
                FROM " . MAIN_DB_PREFIX . "multicurrency_rate 
                WHERE fk_multicurrency = " . (int)$targetCurrencyId . "
                AND DATE(date_sync) <= '" . $db->escape($date) . "'
                ORDER BY date_sync DESC 
                LIMIT 1";
    }
    
    $res = $db->query($sql);
    
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $rate = (float)$obj->rate;
        
        if ($rate > 0) {
            $converted = $amount * $rate; // Conversion MRO → devise étrangère
            
            return [
                'converted' => round($converted, 2), // Arrondi à 2 décimales
                'rate' => $rate,
                'currency_id' => $targetCurrencyId,
                'currency_code' => $codeDevise,
                'original' => $amount,
                'date_rate' => $obj->date_sync,
                'formula' => $amount . ' MRO * ' . $rate . ' = ' . $converted . ' ' . $codeDevise
            ];
        }
    }
    
    // Si aucun taux trouvé, chercher n'importe quel taux pour cette devise
    $sql = "SELECT rate, date_sync 
            FROM " . MAIN_DB_PREFIX . "multicurrency_rate 
            WHERE fk_multicurrency = " . (int)$targetCurrencyId . "
            ORDER BY date_sync ASC 
            LIMIT 1";
    
    $res = $db->query($sql);
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $rate = (float)$obj->rate;
        
        if ($rate > 0) {
            $converted = $amount / $rate;
            
            return [
                'converted' => round($converted, 2),
                'rate' => $rate,
                'currency_id' => $targetCurrencyId,
                'currency_code' => $codeDevise,
                'original' => $amount,
                'date_rate' => $obj->date_sync,
                'warning' => 'Taux ancien (' . dol_print_date($db->jdate($obj->date_sync), '%d/%m/%Y') . ')',
                'formula' => $amount . ' MRO ÷ ' . $rate . ' = ' . $converted . ' ' . $codeDevise
            ];
        }
    }
    
    // Aucun taux disponible pour cette devise
    return false;
}

// ============================================================================
// 🔹 FONCTION DE CONVERSION INVERSE (devise étrangère → MRO)
// ============================================================================
/**
 * Convertit un montant d'une devise étrangère vers MRO
 * @param object $db Instance de la base de données
 * @param float $amount Montant en devise étrangère
 * @param string $sourceCurrency Code devise ISO source
 * @param string $date Date pour le taux (optionnel)
 * @return array|false Tableau avec conversion ou false si erreur
 */

// Fonction de conversion DEVISE ÉTRANGÈRE → MRO
function convertToMRO($db, $amount, $sourceCurrencyId, $date = null) {
    
    // Si ID devise est MRO (ID 1), pas de conversion
    if (empty($sourceCurrencyId) || $sourceCurrencyId == 1) {
        return [
            'converted' => $amount,
            'rate' => 1.0,
            'currency_id' => 1,
            'currency_code' => 'MRO',
            'original' => $amount,
            'date_rate' => date('Y-m-d H:i:s')
        ];
    }
    
    // Si montant est 0
    if ($amount == 0) {
        return [
            'converted' => 0,
            'rate' => 0,
            'currency_id' => $sourceCurrencyId,
            'currency_code' => '',
            'original' => 0,
            'date_rate' => date('Y-m-d H:i:s')
        ];
    }
    
    // Récupérer le code devise
    $codeDevise = getCodeDeviseFromId($db, $sourceCurrencyId);
    
    // Récupérer le taux (1 devise étrangère = X MRO)
    if (empty($date)) {
        $sql = "SELECT rate, date_sync 
                FROM " . MAIN_DB_PREFIX . "multicurrency_rate 
                WHERE fk_multicurrency = " . (int)$sourceCurrencyId . "
                ORDER BY date_sync DESC 
                LIMIT 1";
    } else {
        $sql = "SELECT rate, date_sync 
                FROM " . MAIN_DB_PREFIX . "multicurrency_rate 
                WHERE fk_multicurrency = " . (int)$sourceCurrencyId . "
                AND DATE(date_sync) <= '" . $db->escape($date) . "'
                ORDER BY date_sync DESC 
                LIMIT 1";
    }
    
    $res = $db->query($sql);
    
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $rate = (float)$obj->rate;
        
        if ($rate > 0) {
            // Conversion : devise étrangère → MRO
            $converted = $amount / $rate;
            
            return [
                'converted' => round($converted, 2),
                'rate' => $rate,
                'currency_id' => $sourceCurrencyId,
                'currency_code' => $codeDevise,
                'original' => $amount,
                'date_rate' => $obj->date_sync,
                'formula' => $amount . ' ' . $codeDevise . ' / ' . $rate . ' = ' . $converted . ' MRO'
            ];
        }
    }
    
    // Si aucun taux trouvé
    return false;
}