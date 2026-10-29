<?php
/**
 * Codi "magic": el que s'escriu per afegir una línia, comú a scale/ i relacions/.
 * scale té teclat numèric (només xifres i * + - / .); relacions té teclat sencer
 * i també accepta el format amb lletres. Si hi ha cap * o +, és format numèric.
 *
 *   numèric                 lletres                   resultat
 *   62*2                    62x2                      2 unitats
 *   62+5                    62l5                      1 unitat, lot 5
 *   1*1+2+nov/27            1x1l2dnov/27              1 unitat, lot 2, caducitat nov/27
 *   62.1   62.1*3           62.1x3                    paquet tipus 1 del 62 (3 paquets)
 *   +3.5   +3.5*2+10        +3.5x2i10                 preu directe, 2 unitats, IVA 10%
 *   -<qualsevol>                                      abonament
 *
 * Retorna un array amb:
 *   tipus: buit | codi | paquet | preu_directe | legacy | ean | text | invalid
 *   ID, units, lot, data, abono ('-' o ''), paquet, preu, iva, original
 * units/lot/data/paquet/preu/iva són null si no s'han escrit.
 */
function parse_magic_code($ID, $context = 'scale') {
    $ID = trim($ID);
    if($ID === '') {
        return ['tipus' => 'buit'];
    }

    $abono = '';
    if($ID[0] == '-') {
        $ID = substr($ID, 1);
        $abono = '-';
    }

    $result = [
        'tipus' => null, 'ID' => null, 'units' => null, 'lot' => null, 'data' => null,
        'abono' => $abono, 'paquet' => null, 'preu' => null, 'iva' => null, 'original' => $ID
    ];
    if($ID === '') {
        $result['tipus'] = 'buit'; // només '-': s'està començant un abonament
        return $result;
    }

    // A scale només hi ha teclat numèric
    if($context === 'scale' && !is_numeric($ID[0]) && !in_array($ID[0], ['+', '*', '/'])) {
        $result['tipus'] = 'invalid';
        return $result;
    }

    // Codi de barres legacy (13 dígits començant per 2) i EAN (>8 dígits)
    if(ctype_digit($ID) && strlen($ID) == 13 && $ID[0] == '2') {
        $result['tipus'] = 'legacy';
        $result['ID'] = $ID;
        return $result;
    }
    if(ctype_digit($ID) && strlen($ID) > 8 && $ID[0] != '2') {
        $result['tipus'] = 'ean';
        $result['ID'] = $ID;
        return $result;
    }

    // Preu directe: +preu[*units][+iva]  o  +preu[xunits][iiva]
    if($ID[0] == '+') {
        $resta = substr($ID, 1);
        if(strpbrk($resta, '*+') !== false) {
            $parts = explode('+', $resta, 2);
            list($preu, $units) = array_pad(explode('*', $parts[0], 2), 2, null);
            $iva = $parts[1] ?? null;
        } elseif(preg_match('/^([^xi]*)(?:x([^i]*))?(?:i(.*))?$/', $resta, $m)) {
            $preu = $m[1];
            $units = $m[2] ?? null;
            $iva = $m[3] ?? null;
        }
        $result['tipus'] = 'preu_directe';
        $result['preu'] = magic_numero($preu);
        $result['units'] = magic_buit($units) === null ? null : magic_numero($units);
        $result['iva'] = magic_buit($iva);
        return $result;
    }

    // Codi (o codi.paquet) amb unitats, lot i caducitat
    if(strpbrk($ID, '*+') !== false) {
        $parts = explode('+', $ID, 3);
        list($codi, $units) = array_pad(explode('*', $parts[0], 2), 2, null);
        $lot = $parts[1] ?? null;
        $data = $parts[2] ?? null;
    } else {
        // Format amb lletres: el codi és el que hi ha fins a la primera x/l/d
        // que vingui després d'una xifra (un codi alfanumèric com GR061 queda sencer)
        preg_match('/^(.*?\d)(?:x([\d.,]*))?(?:l([^d]*))?(?:d(.*))?$/', $ID, $m);
        if(!$m) $m = [$ID, $ID];
        $codi = $m[1];
        $units = $m[2] ?? null;
        $lot = $m[3] ?? null;
        $data = $m[4] ?? null;
    }
    if($context === 'scale' && !preg_match('/^\d+(\.\d+)?$/', $codi)) {
        $result['tipus'] = 'invalid';
        return $result;
    }
    $result['units'] = magic_buit($units) === null ? null : magic_numero($units);
    $result['lot'] = magic_buit($lot);
    $result['data'] = magic_buit($data);

    if(preg_match('/^(\d+)\.(\d+)$/', $codi, $m)) {
        $result['tipus'] = 'paquet';
        $result['ID'] = $m[1];
        $result['paquet'] = $m[2];
        return $result;
    }
    if(ctype_digit($codi)) {
        $result['tipus'] = 'codi';
        $result['ID'] = $codi;
        return $result;
    }
    // Alfanumèric: a relacions pot ser un codi com GR061 (ho decideix qui crida,
    // mirant si existeix) o un text per cercar
    if($context === 'relacions') {
        $result['tipus'] = 'text';
        $result['ID'] = $codi;
        return $result;
    }
    $result['tipus'] = 'invalid';
    return $result;
}

function magic_buit($v) {
    return ($v === null || $v === '') ? null : $v;
}

// coma decimal (teclat sencer) a punt
function magic_numero($v) {
    return str_replace(',', '.', $v);
}

// Compatibilitat: abans parse_magic_code() convertia primer el format amb lletres
function convertir_format_mobil_a_estandard($input) {
    return $input;
}
