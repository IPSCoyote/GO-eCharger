<?php

trait goEChargerAPIConverter
{

    /** * Hilfsfunktion: Bool → int  */
    protected function boolInt($val): int { return $val ? 1 : 0; }

    /** Hilfsfunktion: Phasenarray → Bitmask (V1) */
    protected function phaToBitmask(array $pha): int
    {
        $mask = 0;
        foreach ($pha as $i => $p) {
            if ($p) {
                $mask |= 1 << $i;
            }
        }
        return $mask;
    }

    /** Hilfsfunktion: Zeitformat für V1 */
    protected function formatV1Time(string $iso): string
    {
        $dt = new DateTime($iso);
        return $dt->format('ymdHi'); // Beispiel: 0901262011
    }

    /** Konvertiert Wh (V2) in V1 dws */
    protected function convertWhToDws(float $wh): int
    {
        if ($wh === null) return 0;
        $factor = 360.0; // Faktor laut Beispiel
        return (int)round($wh * $factor);
    }

    protected function convertCardEnergies($v2, string $fwv): array
    {
        $result = [
            'eca' => 0, 'ecr' => 0, 'ecd' => 0, 'ec4' => 0, 'ec5' => 0, 'ec6' => 0, 'ec7' => 0, 'ec8' => 0, 'ec9' => 0, 'ec0' => 0
        ];

        $fwNum = floatval($fwv); // Firmware als Zahl

        if ($fwNum < 60) {
            // Werte aus cards array
            if (isset($v2->cards) && is_array($v2->cards)) {
                $map = ['eca', 'ecr', 'ecd', 'ec4', 'ec5', 'ec6', 'ec7', 'ec8', 'ec9', 'ec1'];
                foreach ($map as $i => $field) {
                    if (isset($v2->cards[$i]->energy)) {
                        $result[$field] = (int)round($v2->cards[$i]->energy / 10);
                    }
                }
            }
        } else {
            // Werte aus c1e..c0e
            $fields = ['c1e', 'c2e', 'c3e', 'c4e', 'c5e', 'c6e', 'c7e', 'c8e', 'c9e', 'c0e'];
            $map = ['eca', 'ecr', 'ecd', 'ec4', 'ec5', 'ec6', 'ec7', 'ec8', 'ec9', 'ec1'];
            foreach ($fields as $i => $f) {
                if (isset($v2->$f)) {
                    $result[$map[$i]] = (int)round($v2->$f / 10);
                }
            }
        }

        return $result;
    }

    protected function convertUby($v2, string $fwv): int
    {
        $fwNum = floatval($fwv); // Firmware als Zahl
        $uby = 0;

        if ($fwNum < 60) {
            // Suche im cards array
            if (isset($v2->cards) && is_array($v2->cards)) {
                foreach ($v2->cards as $i => $card) {
                    if (isset($card->cardId) && $card->cardId === true) {
                        $uby = $i; // Index als uby
                        break;
                    }
                }
            }
        } else {
            // Suche c1i..c0i
            $fields = ['c1i', 'c2i', 'c3i', 'c4i', 'c5i', 'c6i', 'c7i', 'c8i', 'c9i', 'c0i'];
            foreach ($fields as $i => $f) {
                if (isset($v2->$f) && $v2->$f === true) {
                    $uby = $i === 9 ? 0 : $i + 1; // 0er Karte → uby = 0
                    break;
                }
            }
        }

        return $uby;
    }

    protected function convertAst($v2): int
    {
        $acs = isset($v2->acs) ? (int)$v2->acs : 0;
        $awe = isset($v2->awe) ? boolval($v2->awe) : false;
        $lmo = isset($v2->lmo) ? (int)$v2->lmo : 0;

        if ($acs === 0) {
            return 0;
        } elseif ($acs === 1) {
            return ($awe || $lmo === 4) ? 2 : 1;
        }

        return 0; // Default
    }

    /**
     * V2 → V1 Konverter
     */
   protected function convertV2toV1($v2)
    {
        // al1–al5 aus clp ableiten
        $al1 = $al2 = $al3 = $al4 = $al5 = 0;
        if (isset($v2->clp) && is_array($v2->clp)) {
            $al1 = isset($v2->clp[0]) ? (int)$v2->clp[0] : 0;
            $al2 = isset($v2->clp[1]) ? (int)$v2->clp[1] : 0;
            $al3 = isset($v2->clp[2]) ? (int)$v2->clp[2] : 0;
            $al4 = isset($v2->clp[3]) ? (int)$v2->clp[3] : 0;
            $al5 = isset($v2->clp[4]) ? (int)$v2->clp[4] : 0;
        }

        // ast konvertieren
        $ast = $this->convertAst($v2);

        // DWS aus Wh → V1
        $dws = isset($v2->wh) ? $this->convertWhToDws((float)$v2->wh) : 0;

        // LOT aus V2 → V1
        $lot = 0;
        if (isset($v2->lot) && isset($v2->lot->amp)) {
            $lot = (int)$v2->lot->amp;
        }

        // TOF konvertieren: Minuten → Stunden + 100
        $tof = isset($v2->tof) ? 100 + (int)round($v2->tof / 60) : 100;

        // Karten energies konvertieren
        $cardEnergies = $this->convertCardEnergies($v2, isset($v2->fwv) ? $v2->fwv : '0');

        // uby konvertieren
        $uby = $this->convertUby($v2, isset($v2->fwv) ? $v2->fwv : '0');

        $V1Data = [

            // a
            'adi' => isset($v2->adi) ? $this->boolInt($v2->adi) : 0,
            // 'afi' => 0, // keine Entsprechung in V2
            // 'aho' => 0, // keine Entsprechung in V2
            'al1' => $al1,
            'al2' => $al2,
            'al3' => $al3,
            'al4' => $al4,
            'al5' => $al5,
            'alw' => isset($v2->alw) ? $this->boolInt($v2->alw) : 0,
            'ama' => isset($v2->ama) ? (int)$v2->ama : 0,
            'amp' => isset($v2->amp) ? (int)$v2->amp : 0,
            'amt' => isset($v2->amt) ? (int)$v2->amt : 0,
            'amx' => isset($v2->amp) ? (int)$v2->amp : 0, // amx gibt es in V2 nicht, wird alternativ mit amp vorbelegt
            'ast' => $ast,
            // 'azo' => 0, // keine Entsprechung in V2

            // c
            'car' => isset($v2->car) ? (int)$v2->car : 0,
            'cbl' => isset($v2->cbl) ? (int)$v2->cbl : 0,
            'cch' => isset($v2->cch) ? $v2->cch : '',
            'cdi' => 0, // andere Bedeutung in V2 !!!
            'cfi' => isset($v2->cfi) ? $v2->cfi : '',
            'cid' => isset($v2->cid) ? $v2->cid : '',

            // d
            'dto' => 0, // keine Entsprechung in V2
            'dwo' => isset($v2->dwo) ? (int)round($v2->dwo / 100) : 0,
            'dws' => $dws,

            // e
            'eca' => $cardEnergies['eca'],
            'ecd' => $cardEnergies['ecd'],
            'ecr' => $cardEnergies['ecr'],
            'ec4' => $cardEnergies['ec4'],
            'ec5' => $cardEnergies['ec5'],
            'ec6' => $cardEnergies['ec6'],
            'ec7' => $cardEnergies['ec7'],
            'ec8' => $cardEnergies['ec8'],
            'ec9' => $cardEnergies['ec9'],
            'ec1' => $cardEnergies['ec1'],
            'ecd' => $cardEnergies['ecd'],
            'ecr' => $cardEnergies['ecr'],
            'eca' => $cardEnergies['eca'],
            'err' => isset($v2->err) ? (int)$v2->err : 0,
            'eto' => isset($v2->eto) ? (int)($v2->eto / 100) : 0,

            // f
            'frc' => isset($v2->frc) ? (int)$v2->frc : 0,
            'fsp' => isset($v2->fsp) ? $this->boolInt($v2->fsp) : 0,
            'fwv' => isset($v2->fwv) ? $v2->fwv : '',

            // l
            'lbr' => isset($v2->lbr) ? (int)$v2->lbr : 255,
            'lch' => isset($v2->lccfc) ? (int)$v2->lccfc : 0,
            'lmo' => isset($v2->lmo) ? (int)$v2->lmo : 3,
            'lof' => isset($v2->lof) ? (int)$v2->lof : 0,
            'loe' => isset($v2->loe) ? $this->boolInt($v2->loe) : 0,
            'loa' => isset($v2->loa) ? (int)$v2->loa : 0,
            'lop' => isset($v2->lop) ? (int)$v2->lop : 0,
            'lot' => $lot,
            'lse' => isset($v2->lse) ? $this->boolInt($v2->lse) : 0,

            // n
            'nmo' => isset($v2->nmo) ? $this->boolInt($v2->nmo) : 0,
            'nrg' => isset($v2->nrg) && is_array($v2->nrg) ? $v2->nrg : [],

            // p
            'pha' => isset($v2->pha) && is_array($v2->pha) ? $this->phaToBitmask($v2->pha) : 0,

            // r
            'rbc' => isset($v2->rbc) ? (int)$v2->rbc : 0,
            'rbt' => isset($v2->rbt) ? (int)$v2->rbt : 0,

            // s
            'sse' => isset($v2->sse) ? $v2->sse : '',
            'stp' => (isset($v2->dwo) && $v2->dwo > 0) ? 2 : 0,

            // t
            'tds' => isset($v2->tds) ? (int)$v2->tds : 0,
            'tma' => isset($v2->tma) && is_array($v2->tma) ? $v2->tma : [],
            'tme' => isset($v2->loc) ? $this->formatV1Time($v2->loc) : '',
            'tof' => $tof,

            // u
            'uby' => $uby, // TODO
            'ust' => isset($v2->ust) ? (int)$v2->ust : 0,

            // v
            //'version' => 'B', keine Entsprechung

            // w
            'wen' => 1,
            'wke' => '********',
            'wss' => isset($v2->wifis[0]->ssid) ? $v2->wifis[0]->ssid : '',
            'wst' => isset($v2->wst) ? (int)$v2->wst : 0,
        ];

        return (object)$V1Data;
    }

}
