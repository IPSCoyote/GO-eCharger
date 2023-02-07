<?php

class goEChargerHWRevv2 extends IPSModule
{

    public function __construct($InstanceID)
    {
        /* Constructor is called before each function call */
        parent::__construct($InstanceID);
    }

    public function Create()
    {
        /* Create is called ONCE on Instance creation and start of IP-Symcon.
           Status-Variables und Modul-Properties for permanent usage should be created here  */
        parent::Create();

        //--- Properties
        $this->RegisterPropertyString("IPAddressCharger", "0.0.0.0");
        $this->RegisterPropertyInteger( "HardwareRevision", 2);
        $this->RegisterPropertyInteger("MaxAmperage", 6);
        $this->RegisterPropertyInteger("OutOfBoundAmperage", 17);

        // Update Intevals
        $this->RegisterPropertyInteger("UpdateIdle", 0);
        $this->RegisterPropertyInteger("UpdateCharging", 0);
        $this->RegisterPropertyBoolean("MQTTUpdateActive", false);

        // Comfort Functions
        $this->RegisterPropertyBoolean("AutoReactivate", false);
        $this->RegisterPropertyBoolean("AutoActivateOnStopSet", false);
        $this->RegisterPropertyBoolean("switchUsedPhasesAllowed", false);
        $this->RegisterPropertyInteger("ActiveSwitchWaitingTime", 5);
        $this->RegisterPropertyInteger("PhasesSwitchWaitingTime", 5);

        // Vehicle Data
        $this->RegisterPropertyFloat("AverageConsumption", 0);
        $this->RegisterPropertyFloat("MaxLoadKw", 0);
        $this->RegisterPropertyInteger("DefaultPhasesOfCar", 3);

        // Special Functions
        $this->RegisterPropertyBoolean("calculateCorrectedData", false);
        $this->RegisterPropertyInteger("verifiedSupplyPowerL1", 230);
        $this->RegisterPropertyInteger("verifiedSupplyPowerL2", 230);
        $this->RegisterPropertyInteger("verifiedSupplyPowerL3", 230);
        $this->RegisterPropertyBoolean("debugLog", false);

        //--- Register Timer
        $this->RegisterTimer("GOeChargerTimer_UpdateTimer", 0, 'GOeCharger_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        /* Called on 'apply changes' in the configuration UI and after creation of the instance */
        parent::ApplyChanges();

        // Generate Profiles & Variables
        $this->registerProfiles();
        $this->registerVariables();

        // Set Timer
        if ($this->ReadPropertyInteger("UpdateCharging") >= 0) {
            $this->SetTimerInterval("GOeChargerTimer_UpdateTimer", $this->ReadPropertyInteger("UpdateCharging") * 1000);
        }

        // Set Data to Variables (and update timer)
        $this->Update();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    //=== Form Functions =========================================================================================
    /* onChange Methods called from the Form
    */

    //=== Modul Functions =========================================================================================
    /* Own module functions called via the defined prefix GOeCharger_*
    *
    * GOeCharger_CheckConnection($id);
    *
    */

    public function Update()
    {
        /* Check the connection to the go-eCharger */
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false) {
            return false;
        }
        $this->UpdateWithData($goEChargerStatus);
        return true;
    }

    public function ReceiveData($JSONString)
    {
        // Data comes from MQTT

        if ($this->ReadPropertyBoolean("MQTTUpdateActive") == false) {
            // no handling of MQTT data
            return true;
        }

        if ($JSONString == '') {
            $this->log('No JSON');
            return true;
        }

        $jsonData = json_decode($JSONString, true);
        if ($jsonData === false or !isset($jsonData['Buffer'])) {
            $this->log('No MQTT Data');
            return true;
        }

        // check, if Buffer is for this module
        $needle = "/go-eCharger/" . GetValueString($this->GetIDForIdent("serialID")) . "/";
        if (strpos($jsonData['Buffer'], $needle) === false) {
            return true;
        }

        // cut out content
        $remainingContent = $jsonData['Buffer'];
        $whileBreaker = 0;
        while (strlen($remainingContent) > 5) {
            $whileBreaker++;
            if ($whileBreaker > 20) break;

            // remove first character (usually an HEX 31)
            $packageLength = ord($remainingContent[1]);
            $remainingContent = substr($remainingContent, 2, 8096);
            $package = substr($remainingContent, 2, $packageLength - 2);
            $remainingContent = substr($remainingContent, $packageLength, 8096);

            // handle package
            if (strpos($package, $needle) === 0) {
                $attribute = substr($package, strlen($needle), 3);
                $value = substr($package, strlen($needle) + 3, 8096);

                //--- transfer data into an array to become similar to the
                //    API_V1 /status format
                // create json
                if ((strpos($value, '[') !== false) or (strpos($value, '"') !== false)) {
                    // table or string
                    $json = '{ "' . $attribute . '": ' . $value . ' }';
                } else {
                    // plain value
                    $json = '{ "' . $attribute . '": "' . $value . '" }';
                }
                $data = json_decode($json);

                // data correction from MQTT API V2 to API V1 (which the module logic uses)
                $this->mqttDataCorrectionApiV2toApiV1($data);

                // data correction on incompatible changed data
                $this->dataCorrection($data, null);

                // update data
                $this->UpdateWithData($data);
            }

        }

        return true;
    }

    public function getPowerToCar()
    {
        return $this->getTotalPowerToCar();
    }

    public function getCurrentLoadingCycleConsumption()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'dws'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'dws'} / 361010.83;
    }

    public function getMaximumChargingAmperage()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'ama'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'ama'};
    }

    public function setMaximumChargingAmperage(int $ampere)
    {
        // Check input value
        $ampereToSet = $ampere;
        if ($ampere < 6 or $ampere > 32) {
            return false;
        }
        if ($ampere > $this->ReadPropertyInteger("MaxAmperage")) {
            $ampereToSet = $this->ReadPropertyInteger("MaxAmperage");
        }

        // get current settings of goECharger
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false) {
            return false;
        }

        // first calculate the Button values
        $button[0] = 6; // min. Value
        $gaps = round((($ampereToSet - 6) / 4) - 0.5);
        $button[1] = $button[0] + $gaps;
        $button[2] = $button[1] + $gaps;
        $button[3] = $button[2] + $gaps;
        $button[4] = $ampereToSet; // max. Value

        // set values to Charger
        // set button values
        $this->setValueToeCharger('al1', $button[0]);
        $this->setValueToeCharger('al2', $button[1]);
        $this->setValueToeCharger('al3', $button[2]);
        $this->setValueToeCharger('al4', $button[3]);
        $this->setValueToeCharger('al5', $button[4]);

        // set max available Ampere
        $goEChargerStatus = $this->setValueToeCharger('ama', $ampereToSet);

        // set current available Ampere (if too high)
        if (isset($goEChargerStatus->{'amp'}) == false or isset($goEChargerStatus->{'ama'}) == false) return false;
        if ($goEChargerStatus->{'amp'} > $goEChargerStatus->{'ama'}) {
            // set current available to max. available, as current was higher than new max.
            $goEChargerStatus = $this->setValueToeCharger('amx', $goEChargerStatus->{'ama'});
        }

        $this->Update();

        return $goEChargerStatus->{'ama'};
    }

    public function getCurrentChargingAmperage()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'amp'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'amp'};
    }

    public function setCurrentChargingAmperage(int $ampere)
    {
        // Check input value
        $ampereToSet = $ampere;
        if ($ampereToSet < 6 or $ampereToSet > 32) {
            return false;
        }
        if ($ampereToSet > $this->ReadPropertyInteger("MaxAmperage")) {
            $ampereToSet = $this->ReadPropertyInteger("MaxAmperage");
        }

        // get current settings of goECharger
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'ama'}) == false) {
            return false;
        }

        // Check requested Ampere is <= max Ampere set in Instance
        if ($ampereToSet > $goEChargerStatus->{'ama'}) {
            $ampereToSet = $goEChargerStatus->{'ama'};
        }

        // set current available Ampere
        $resultStatus = $this->setValueToeCharger('amx', $ampereToSet);

        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'amp'}) == false) {
            return false;
        }
        return $resultStatus->{'amp'};
    }

    public function setCurrentChargingWatt(int $wattToSet)
    {
        return $this->setCurrentChargingWattWithMinimumAmperage($wattToSet, 0);
    }

    public function setCurrentChargingWattWithMinimumAmperage(int $wattToSet, int $minimumChargingAmperage)
    {
        $this->debugLog("setCurrentChargingWattWithMinimumAmperage with ".$wattToSet."Wh and ".$minimumChargingAmperage."A minimum");
        $Semaphore = "GO-eCharger-" . GetValueString($this->GetIDForIdent("serialID"))."-SetWatt";
        if (IPS_SemaphoreEnter($Semaphore, 2000) == false) {
            // wait 2 seconds (for short processing time
            $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Semaphore blocked processing");
            return false;
        }

        $availableInstalledPhases = GetValueInteger($this->GetIDForIdent("availablePhasesInRow"));  // Installed Phases

        $switchUsedPhasesAllowed = $this->ReadPropertyBoolean("switchUsedPhasesAllowed"); // is switching between 1- and 3-phases allowed
        if (GetValueBoolean($this->GetIDForIdent("singlePhaseCharging"))) {
            $currentlySetupUsablePhases = 1;            // Aktuell genutzte Phasen lt. GO-eChatt
        } else {
            $currentlySetupUsablePhases = 3;            // Aktuell genutzte Phasen lt. GO-eChatt
        }
        $maxChargerAmperage = $this->ReadPropertyInteger("MaxAmperage");
        $outOfBalanceBorder = $this->ReadPropertyInteger("OutOfBoundAmperage");

        $defaultUsedPhasesByCar = $this->ReadPropertyInteger("DefaultPhasesOfCar");
        $currentlyUsedPhasesByCar = GetValueInteger($this->GetIDForIdent("usedSupplyLinesByCar"));

        // potential Actions
        $chargerActive = false;                     // default is: don't charge
        $amperageToSet = 0;                         // default is: don't charge
        $phasesToSet = $currentlySetupUsablePhases; // (1 or 3, default is no change

        // check, if phases can be changed at all!
        if (time() <= GetValueInteger($this->GetIDForIdent("lastUpdateSinglePhase")) + $this->ReadPropertyInteger("PhasesSwitchWaitingTime") * 60) {
            $switchUsedPhasesAllowed = false;
        }

        if ($switchUsedPhasesAllowed) {
            //--- number of phases can be adopted ------------------------------------------------------------------
            // calculate amperage on one phase
            $amperageToSet = max(floor($wattToSet / 230), $minimumChargingAmperage);
            if ($amperageToSet <= $outOfBalanceBorder) {
                // 1-phase charging
                $phasesToSet = 1;
                if ($amperageToSet > $maxChargerAmperage) {
                    // if more amperage should be set than the charger is capable for, switch to 3phases
                    $phasesToSet = 3;
                    $amperageToSet = max(floor($wattToSet / 230 / 3), $minimumChargingAmperage);
                    if (($amperageToSet * 230 * 3 > $wattToSet) or ($amperageToSet < 6)) {
                        // switch back to 1-phase, if not enough amperage results on 3-phases or
                        // with 3-phases more power would result than it should be set
                        $phasesToSet = 1;
                        $amperageToSet = min($outOfBalanceBorder, $maxChargerAmperage);
                    }
                }
            } else {
                // 3-phase charging
                $amperageToSet = max(floor($wattToSet / 230 / 3), $minimumChargingAmperage);
                $phasesToSet = 3;
                // x-check, if 3 phases are higher than the requested Watt
                if ($amperageToSet < 6) {
                    // 3-phases needed due to outOfBalance Border, but too much Power is resulting
                    // so set 1-phase loading with max. outOfBalanceBorder
                    $phasesToSet = 1;
                    $amperageToSet = min(max(floor($wattToSet / 230), $minimumChargingAmperage), $outOfBalanceBorder);
                }
            }

        } else {
            //--- number of used phases cannot be changed ----------------------------------------------------------
            // use phases given by installation and setup in the configuration
            $phasesToUse = min($currentlySetupUsablePhases, $availableInstalledPhases);
            switch ($currentlyUsedPhasesByCar) {
                case 0:
                    // we don't now (yet), on how many phases the current car will charge, so use default (if given)
                    if ($defaultUsedPhasesByCar > 0 and $defaultUsedPhasesByCar < $phasesToUse)
                        $phasesToUse = $defaultUsedPhasesByCar;
                    break;

                default:
                    // we know, on how many phases the current car charges, so use that
                    if ($currentlyUsedPhasesByCar > 0 and $currentlyUsedPhasesByCar < $phasesToUse)
                        $phasesToUse = $currentlyUsedPhasesByCar;
                    break;
            }

            // result
            $phasesToSet = $currentlySetupUsablePhases; // no change on phases
            $amperageToSet = min(max(floor($wattToSet / $phasesToUse / 230), $minimumChargingAmperage), $maxChargerAmperage);
            if ($amperageToSet < 6) {
                // minimum is 6A, so stop Charging and don't set Amperage
                $amperageToSet = 0;
            }
        }

        $chargerActive = ($amperageToSet >= 6); // no charging, if Amperage is not 6A minimum

        //--- Execute changes
        $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Target is ".$phasesToSet."phase(s) with ".$amperageToSet."A");
        // set phases with (switch limit)
        if (GetValueBoolean($this->GetIDForIdent("singlePhaseCharging")) and $phasesToSet == 3) {
            // set to 3 phases
            // save last status update time as changing the phases will stop/start charging, which should not
            // count
            $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Switch of phases needed");
            $lastUpdateTime = GetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus"));
            $this->setSinglePhaseCharging(false);
            sleep(25); // wait 25 seconds as phase shifting takes time...
            SetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus"), $lastUpdateTime);
        } elseif (GetValueBoolean($this->GetIDForIdent("singlePhaseCharging")) == false and $phasesToSet == 1) {
            // set to 1 phase
            // save last status update time as changing the phases will stop/start charging, which should not
            // count
            $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Switch of phases needed");
            $lastUpdateTime = GetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus"));
            $this->setSinglePhaseCharging(true);
            sleep(25); // wait 25 seconds as phase shifting takes time...
            SetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus"), $lastUpdateTime);
        };

        // set amperage
        if ($amperageToSet >= 6 and $amperageToSet <> GetValueInteger($this->GetIDForIdent("availableAMP"))) {
            $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Switch of amperes needed");
            $this->setCurrentChargingAmperage($amperageToSet);
            sleep(2);
        }

        // switch charger with (switch limit)
        if (GetValueBoolean($this->GetIDForIdent("accessState")) <> $chargerActive) {
            // check waiting time
            if (time() >= GetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus")) + $this->ReadPropertyInteger("ActiveSwitchWaitingTime") * 60) {
                $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Charger Active toggle");
                $this->setActive($chargerActive);
            } else {
                $this->debugLog("setCurrentChargingWattWithMinimumAmperage: Charger Active Toggle not possible due to minimum waiting time");
            }
        }

        // Leave Semaphore
        IPS_SemaphoreLeave( $Semaphore );
        return true;

    }

    public function getAccessControl()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'ast'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'ast'};
    }

    public function setAccessControlActive(int $mode)
    {
        if ($mode < 0 or $mode > 2) {
            return false;
        }
        $resultStatus = $this->setValueToeCharger('ast', $mode);
        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'ast'}) == false) {
            return false;
        }
        if ($resultStatus->{'ast'} == $mode) {
            return true;
        } else {
            return false;
        }
    }

    public function getAutomaticChargeStop()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'dwo'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'dwo'} / 10;
    }

    public function setAutomaticChargeStop(float $chargeStopKwh)
    {
        if ($chargeStopKwh < 0 or $chargeStopKwh > 100) {
            return false;
        }
        $value = number_format($chargeStopKwh * 10, 0, '', '');
        $resultStatus = $this->setValueToeCharger('dwo', $value);
        if ($this->ReadPropertyBoolean("AutoActivateOnStopSet") == true) {
            // activate Wallbox
            $this->setActive(true);
        } else
            $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'dwo'}) == false) {
            return false;
        }
        if ($resultStatus->{'dwo'} == $value) {
            return true;
        } else {
            return false;
        }
    }

    public function getAutomaticChargeStopKm()
    {
        if ($this->ReadPropertyFloat("AverageConsumption") > 0) {
            $chargeStopKwh = $this->getAutomaticChargeStop();
            if ($chargeStopKwh === false) {
                return false;
            } else {
                return $chargeStopKwh / $this->ReadPropertyFloat("AverageConsumption") * 100;
            }
        } else
            return false;
    }

    public function setAutomaticChargeStopKm(float $chargeStopKm)
    {
        if ($this->ReadPropertyFloat("AverageConsumption") > 0) {
            $chargeStopKwh = $this->ReadPropertyFloat("AverageConsumption") / 100 * $chargeStopKm;
            return $this->setAutomaticChargeStop($chargeStopKwh);
        } else
            return false;
    }

    public function getCableUnlockMode()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'ust'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'ust'};
    }

    public function setCableUnlockMode(int $unlockMode)
    {
        if ($unlockMode < 0 or $unlockMode > 2) {
            return false;
        }
        $resultStatus = $this->setValueToeCharger('ust', $unlockMode);
        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'ust'}) == false) {
            return false;
        }
        if ($resultStatus->{'ust'} == $unlockMode) {
            return true;
        } else {
            return false;
        }
    }

    public function isActive()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'alw'}) == false) {
            return false;
        }
        if ($goEChargerStatus->{'alw'} == '1') {
            return true;
        } else {
            return false;
        }
    }

    public function setActive(bool $active)
    {
        if ($active == true) {
            $value = 1;
        } else {
            $value = 0;
        }
        $resultStatus = $this->setValueToeCharger('alw', $value);
        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'alw'}) == false) {
            return false;
        }
        if ($resultStatus->{'alw'} == $value) {
            return true;
        } else {
            return false;
        }
    }

    public function getStatus()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'car'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'car'};
    }

    public function getUnlockRFID()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'uby'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'uby'};
    }

    public function getTotalPowerToCar()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'nrg'}) == false) {
            return false;
        }
        $goEChargerEnergy = $goEChargerStatus->{'nrg'};
        return $goEChargerEnergy[11] / 100;
    }

    public function getLEDBrightness()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'lbr'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'lbr'};
    }

    public function setLEDBrightness(int $brightness)
    {
        if ($brightness < 0 or $brightness > 255) {
            return false;
        }
        $resultStatus = $this->setValueToeCharger('lbr', $brightness);
        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'lbr'}) == false) {
            return false;
        }
        if ($resultStatus->{'lbr'} == $brightness) {
            return true;
        } else {
            return false;
        }
    }

    public function getLEDEngergySave()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'lse'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'lse'};
    }

    public function setLEDEnergySave(bool $energySaveActive)
    {
        if ($energySaveActive == true) {
            $value = 1;
        } else {
            $value = 0;
        }
        $resultStatus = $this->setValueToeCharger('r2x', $value); // based on issue report on GitHub the GO-eCharger needs "r2x" as parameter here!
        // Update all data
        $this->Update();
        if ($resultStatus == false or isset($resultStatus->{'lse'}) == false) {
            return false;
        }
        if ($resultStatus->{'lse'} == $value) {
            return true;
        } else {
            return false;
        }
    }

    public function getEnergyChargedInTotal()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'eto'}) == false) {
            return false;
        }
        return $goEChargerStatus->{'eto'} / 10;
    }

    public function getEnergyChargedByCard(int $cardID)
    {
        if ($cardID < 1 or $cardID > 10) {
            return false;
        }
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false) {
            return false;
        }
        switch ($cardID) {
            case 1:
                $code = 'eca';
                break;
            case 2:
                $code = 'ecr';
                break;
            case 3:
                $code = 'ecd';
                break;
            case 10:
                $code = 'ec1';
                break;
            default:
                $code = 'ec' . $cardID;
                break;
        }
        if (isset($goEChargerStatus->{$code})) {
            return $goEChargerStatus->{$code} / 10;
        }
    }

    public function getElectricityPriceMinChargeHours()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false or isset($goEChargerStatus->{'aho'}) == false) {
            return false;
        }
        if (isset($goEChargerStatus->{'aho'})) {
            return $goEChargerStatus->{'aho'};
        } else {
            return false;
        }
    }

    public function setElectricityPriceMinChargeHours(int $minChargeHours)
    {
        if ($minChargeHours < 0 or $minChargeHours > 23) {
            return false;
        }
        $resultStatus = $this->setValueToeCharger('aho', $minChargeHours);
        // Update all data
        $this->Update();
        if (isset($goEChargerStatus->{'aho'}) and $resultStatus->{'aho'} == $minChargeHours) {
            return true;
        } else {
            return false;
        }
    }

    public function getElectricityPriceChargeTill()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false) {
            return false;
        }
        if (isset($goEChargerStatus->{'afi'})) {
            return $goEChargerStatus->{'afi'};
        } else {
            return false;
        }
    }

    public function setElectricityPriceChargeTill(int $chargeTill)
    {
        if ($chargeTill < 0 or $chargeTill > 23) {
            return false;
        }
        $resultStatus = $this->setValueToeCharger('afi', $chargeTill);
        // Update all data
        $this->Update();
        if (isset($goEChargerStatus->{'afi'}) and $resultStatus->{'afi'} == $chargeTill) {
            return true;
        } else {
            return false;
        }
    }

    public function getSinglePhaseCharging()
    {
        $goEChargerStatus = $this->getStatusFromCharger();
        if ($goEChargerStatus == false) {
            return false;
        }
        if (isset($goEChargerStatus->{'fsp'})) {
            return $goEChargerStatus->{'fsp'};
        } else {
            return false;
        }
    }

    public function setSinglePhaseCharging(bool $forceSinglePhase)
    {
        if ($forceSinglePhase) {
            $resultStatus = $this->setValueToeCharger('fsp', 1);
        } else {
            $resultStatus = $this->setValueToeCharger('fsp', 0);
        }
        // Update all data
        $this->Update();
        if (isset($goEChargerStatus->{'fsp'}) and $resultStatus->{'fsp'} == $forceSinglePhase) {
            return true;
        } else {
            return false;
        }
    }


    //--- REQUEST ACTION ----------------------------------------------------------------------
    public function RequestAction($Ident, $Value)
    {

        switch ($Ident) {
            case "availableAMP":
                $this->setCurrentChargingAmperage($Value);
                break;

            case "maxAvailableAMP":
                $this->setMaximumChargingAmperage($Value);
                break;

            case "accessControl":
                $this->setAccessControlActive($Value);
                break;

            case "automaticStop":
                $this->setAutomaticChargeStop($Value);
                break;

            case "automaticStopKm":
                $this->setAutomaticChargeStopKm($Value);
                break;

            case "cableUnlockMode":
                $this->setCableUnlockMode($Value);
                break;

            case "accessState":
                $this->setActive($Value);
                break;

            case "ledBrightness":
                $this->setLEDBrightness($Value);
                break;

            case "ledEnergySave":
                $this->setLEDEnergySave($Value);
                break;

            case "electricityPriceMinChargeHours":
                $this->setElectricityPriceMinChargeHours($Value);
                break;

            case "electricityPriceChargeTill":
                $this->setElectricityPriceChargeTill($Value);
                break;

            case "singlePhaseCharging":
                $this->setSinglePhaseCharging($Value);
                break;

            default:
                throw new Exception("Invalid Ident");

        }

    }


    //=== PRIVATE/PRODUCTED FUNCTIONS ==============================================================================
    protected function debugLog( String $message ) {
        if ( $this->ReadPropertyBoolean("debugLog") == true ) {
            $this->SendDebug( "GO-eCharger", $message, 0 );
        };
    }

    protected function getStatusFromCharger()
    {
        // get IP of go-eCharger
        $IPAddress = trim($this->ReadPropertyString("IPAddressCharger"));

        // check if IP is ocnfigured and valid
        if ($IPAddress == "0.0.0.0") {
            $this->SetStatus(200); // no configuration done
            return false;
        } elseif (filter_var($IPAddress, FILTER_VALIDATE_IP) == false) {
            $this->SetStatus(201); // no valid IP configured
            return false;
        }

        // check if any HTTP device on IP can be reached
        if ($this->ping($IPAddress, 80, 1) == false) {
            $this->SetStatus(202); // no http response
            return false;
        }

        // get json from go-eCharger
        try {
            $ch = curl_init("http://" . $IPAddress . "/status");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $json = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            $this->SetStatus(203); // no http response
            return false;
        };

        $goEChargerStatusAPIv1 = json_decode($json);
        if ($goEChargerStatusAPIv1 === null) {
            $this->SetStatus(203); // no http response
            return false;
        } elseif (isset($goEChargerStatusAPIv1->{'sse'}) == false) {
            $this->SetStatus(204); // no go-eCharger
            return false;
        }

        $this->SetStatus(102);

        $goEChargerStatusAPIv2 = null; // default with null as API call might not happen

        if ( ( $this->ReadPropertyInteger("HardwareRevision") == 3 ) &&
             ( isset($goEChargerStatusAPIv1->{'fwv'}) == true ) &&
             ( floatval(preg_replace("/[^0-9.]/", "", $goEChargerStatusAPIv1->{'fwv'} ) ) >= 50 ) ) {
            // on Hardware Revision 3 and a Firmware >= 50 try to use the API v2 to support incompatible API V1 issues
            // examples: "dwo"
            try {
                $ch = curl_init("http://" . $IPAddress . "/api/status");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $json = curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                $this->SetStatus(203); // no http response
                return false;
            };

            $goEChargerStatusAPIv2 = json_decode($json);
        }



        $this->dataCorrection($goEChargerStatusAPIv1, $goEChargerStatusAPIv2);

        return $goEChargerStatusAPIv1;
    }

    protected function setValueToIdent($data, $ident, $apiKey)
    {
        // function to avoid invalid apiKey is accessed
        if (isset($data->{$apiKey})) {
            if ( $this->SetValue($ident, $data->{$apiKey}) == false ) $this->debugLog("setValueToIdent FAILED on ".$apiKey ); ;
        }
    }

    protected function setValueToeCharger($parameter, $value)
    {
        if ( $this->ReadPropertyInteger("HardwareRevision") == 3 )  {
            // if Hardware is Rev. 3 check, if a special handling is needed when setting a parameter
            if ( $parameter == "dwo" ) {
                // adopt data if needed
                switch ($parameter) {
                    case "dwo":
                        // for "dwo" (automatic charging stop) the v3 Chargers have to be setup into "automatic" mode
                        try {
                            $ch = curl_init("http://" . trim($this->ReadPropertyString("IPAddressCharger")) . "/api/set?frc=0");
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $json = curl_exec($ch);
                            curl_close($ch);
                            $ch = curl_init("http://" . trim($this->ReadPropertyString("IPAddressCharger")) . "/api/set?lmo=3");
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $json = curl_exec($ch);
                            curl_close($ch);
                        } catch (Exception $e) {
                        };
                        // and with default node
                        $value = $value * 100; // Conversion 0.1 kWh -> Wh
                        break;
                }
                try {
                    $ch = curl_init("http://" . trim($this->ReadPropertyString("IPAddressCharger")) . "/api/set?" . $parameter . "=" . $value);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $json = curl_exec($ch);
                    curl_close($ch);
                } catch (Exception $e) {
                };
                // get complete status from eCharger as conversion etc. is needed
                return $this->getStatusFromCharger();
            }
        }


        try {
            $ch = curl_init("http://" . trim($this->ReadPropertyString("IPAddressCharger")) . "/mqtt?payload=" . $parameter . "=" . $value);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $json = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
        };
        return json_decode($json);
    }

    protected function ping($host, $port, $timeout)
    {
        ob_start();
        $errno = 0;
        $errstr = "";
        $fP = fSockOpen($host, $port, $errno, $errstr, $timeout);
        ob_clean();
        if (!$fP) {
            return false;
        }
        return true;
    }

    //=== ATTRIBUTE HANDLING =======================================================================================

    protected function UpdateWithData($goEChargerStatus)
    {
        //--- ADI (adapter_in: Charging box is plugged in with adapter) ---------------------------
        $this->setValueToIdent($goEChargerStatus, "adapterAttached", "adi");

        //--- AFI (Hour "electricity price - automatically" the charge must have lasted -----------
        $this->setValueToIdent($goEChargerStatus, "electricityPriceChargeTill", "afi");

        //--- AHO (Minimum number of hours in which to load with "electricity price - automatic") -
        $this->setValueToIdent($goEChargerStatus, "electricityPriceMinChargeHours", "aho");

        //--- ALW (allow_charging: PWM signal may be present) -------------------------------------
        if (isset($goEChargerStatus->{'alw'})) {
            if (GetValueBoolean($this->GetIDForIdent("accessState")) != $goEChargerStatus->{'alw'}) {
                // remember status change
                SetValueInteger($this->GetIDForIdent("lastUpdateChargerStatus"), time());
            }
            $this->setValueToIdent($goEChargerStatus, "accessState", "alw");
        }

        //--- AMA (Absolute max. Ampere: Maximum value for ampere se) -----------------------------
        $this->setValueToIdent($goEChargerStatus, "maxAvailableAMP", "ama");

        //--- AMT (available max. Ampere by Temperature limitation) -------------------------------
        $this->setValueToIdent($goEChargerStatus, "availableAMPbyTemp", "amt");

        //--- AMP (Ampere value for the PWM signaling in whole ampere of 6-32A) -------------------
        $this->setValueToIdent($goEChargerStatus, "availableAMP", "amp");

        //--- AST (access_state: Access control) --------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "accessControl", "ast");

        //--- AZO (Awattar price zone) ------------------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "awattarPricezone", "azo");

        //--- CAR (Status PWM Signaling) ----------------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "status", "car");
        // Set Update Timer
        if (isset($goEChargerStatus->{'car'})) {
            if ($goEChargerStatus->{'car'} == "2") {
                if ($this->ReadPropertyInteger("UpdateCharging") >= 0) {
                    $this->SetTimerInterval("GOeChargerTimer_UpdateTimer", $this->ReadPropertyInteger("UpdateCharging") * 1000);
                }
            } else {
                if ($this->ReadPropertyInteger("UpdateIdle") >= 0) {
                    $this->SetTimerInterval("GOeChargerTimer_UpdateTimer", $this->ReadPropertyInteger("UpdateIdle") * 1000);
                }
            }
        }

        //--- CLA (Typ2 Cable Ampere encoding) ----------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "cableCapability", "cbl");

        //--- DWO (Abschaltwert in 0.1kWh if stp==2, for DWS parameter) ---------------------------
        if (isset($goEChargerStatus->{'dwo'})) {
           $this->SetValue("automaticStop", $goEChargerStatus->{'dwo'} / 10);
        } else {
            $this->SetValue("automaticStop", 0);
        }
        if ($this->ReadPropertyFloat("AverageConsumption") > 0) {
            if (isset($goEChargerStatus->{'dwo'})) {
                $this->SetValue("automaticStopKm", $goEChargerStatus->{'dwo'} / 10 / $this->ReadPropertyFloat("AverageConsumption") * 100);
            } else {
                $this->SetValue("automaticStopKm", 0 );
            }
        } else
            $this->SetValue("automaticStopKm", 0);

        //--- DWS (Charged energy in deca-watt seconds) -------------------------------------------
        if (isset($goEChargerStatus->{'dws'})) {
            if ($goEChargerStatus->{'dws'} < 200) {
                // Firmware Bug in 0.40/0.50 -> dws does not send Deka-Watt-Seconds but more 1/100 kWh
                $this->SetValue("energyLoadCycle", $goEChargerStatus->{'dws'} / 100);
            } else {
                // correct value in Deka-Watt-Seconds
                $this->SetValue("energyLoadCycle", $goEChargerStatus->{'dws'} / 361010.83);
            }
        }

        //--- ECx (Charged energy per RFID card from 1-10) ----------------------------------------
        // Card 1
        if (isset($goEChargerStatus->{'eca'})) {
            $this->SetValue("energyChargedCard1", $goEChargerStatus->{'eca'} / 10);
        }
        // Card 2
        if (isset($goEChargerStatus->{'ecr'})) {
            $this->SetValue("energyChargedCard2", $goEChargerStatus->{'ecr'} / 10);
        }
        // Card 3
        if (isset($goEChargerStatus->{'ecd'})) {
            $this->SetValue("energyChargedCard3", $goEChargerStatus->{'ecd'} / 10);
        }
        // Card 4
        if (isset($goEChargerStatus->{'ec4'})) {
            $this->SetValue("energyChargedCard4", $goEChargerStatus->{'ec4'} / 10);
        }
        // Card 5
        if (isset($goEChargerStatus->{'ec5'})) {
            $this->SetValue("energyChargedCard5", $goEChargerStatus->{'ec5'} / 10);
        }
        // Card 6
        if (isset($goEChargerStatus->{'ec6'})) {
            $this->SetValue("energyChargedCard6", $goEChargerStatus->{'ec6'} / 10);
        }
        // Card 7
        if (isset($goEChargerStatus->{'ec7'})) {
            $this->SetValue("energyChargedCard7", $goEChargerStatus->{'ec7'} / 10);
        }
        // Card 8
        if (isset($goEChargerStatus->{'ec8'})) {
            $this->SetValue("energyChargedCard8", $goEChargerStatus->{'ec8'} / 10);
        }
        // Card 9
        if (isset($goEChargerStatus->{'ec9'})) {
            $this->SetValue("energyChargedCard9", $goEChargerStatus->{'ec9'} / 10);
        }
        // Card 10
        if (isset($goEChargerStatus->{'ec1'})) {
            $this->SetValue("energyChargedCard10", $goEChargerStatus->{'ec1'} / 10);
        }

        //--- ETO (energy_total: Total charged energy in 0.1kWh) ----------------------------------
        if (isset($goEChargerStatus->{'eto'})) {
            $this->SetValue("energyTotal", $goEChargerStatus->{'eto'} / 10);
        }

        //--- ERR (Error) -------------------------------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "error", "err");

        //--- FSP (Force Single Phase) --------------------------------------------------------
        if (isset($goEChargerStatus->{'fsp'})) {
            if (GetValueBoolean($this->GetIDForIdent("singlePhaseCharging")) != $goEChargerStatus->{'fsp'}) {
                SetValueInteger($this->GetIDForIdent("lastUpdateSinglePhase"), time());
            }
            // simple phase charging
            $this->SetValue("singlePhaseCharging", $goEChargerStatus->{'fsp'});
        }

        //--- LBR (LED brightness from 0-255 ) ------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "ledBrightness", "lbr");

        //--- LSE (led_save_energy: Turn off the LED automatically after 10 seconds) ----------
        $this->setValueToIdent($goEChargerStatus, "ledEnergySave", "lse");

        //--- NMO (norway mode) ---------------------------------------------------------------
        if (isset($goEChargerStatus->{'nmo'})) {
            $groundCheck = true;
            if ($goEChargerStatus->{'nmo'} == '1') {
                $groundCheck = false;
            }
            $this->SetValue("norwayMode", $groundCheck);
        }

        //--- NRG (Array with values of the current and voltage sensor) -----------------------
        if (isset($goEChargerStatus->{'nrg'})) {
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            $this->SetValue("supplyLineL1", $goEChargerEnergy[0]);
            $this->SetValue("supplyLineL2", $goEChargerEnergy[1]);
            $this->SetValue("supplyLineL3", $goEChargerEnergy[2]);
            $this->SetValue("supplyLineN", $goEChargerEnergy[3]);
            $availableEnergy = ((($goEChargerEnergy[0] + $goEChargerEnergy[1] + $goEChargerEnergy[2]) / 3) * 3 * GetValue($this->GetIDForIdent("availableAMP"))) / 1000;
            $this->SetValue("availableSupplyEnergy", $availableEnergy);

            // calculate correction factors
            $correctionFactorL1 = 0.0;
            $correctionFactorL2 = 0.0;
            $correctionFactorL3 = 0.0;
            if ($this->ReadPropertyBoolean("calculateCorrectedData")) {
                if (GetValueInteger($this->GetIDForIdent("supplyLineL1")) > 0) {
                    $correctionFactorL1 = $this->ReadPropertyInteger("verifiedSupplyPowerL1") / $goEChargerEnergy[0];
                    $this->SetValue("correctionFactorL1", ($correctionFactorL1 - 1) * 100);
                }
                if (GetValueInteger($this->GetIDForIdent("supplyLineL2")) > 0) {
                    $correctionFactorL2 = $this->ReadPropertyInteger("verifiedSupplyPowerL2") / $goEChargerEnergy[1];
                    $this->SetValue("correctionFactorL2", ($correctionFactorL2 - 1) * 100);
                }
                if (GetValueInteger($this->GetIDForIdent("supplyLineL3")) > 0) {
                    $correctionFactorL3 = $this->ReadPropertyInteger("verifiedSupplyPowerL3") / $goEChargerEnergy[2];
                    $this->SetValue("correctionFactorL3", ($correctionFactorL3 - 1) * 100);
                }
                $correctedAvailableEnergy = (((($goEChargerEnergy[0] * $correctionFactorL1) + ($goEChargerEnergy[1] * $correctionFactorL2) + ($goEChargerEnergy[2] * $correctionFactorL3)) / 3) * 3 * GetValue($this->GetIDForIdent("availableAMP"))) / 1000;
                $this->SetValue("correctedAvailableSupplyEnergy", $correctedAvailableEnergy);
            }

            $this->SetValue("ampToCarLineL1", $goEChargerEnergy[4] / 10);
            $this->SetValue("ampToCarLineL2", $goEChargerEnergy[5] / 10);
            $this->SetValue("ampToCarLineL3", $goEChargerEnergy[6] / 10);
            $this->SetValue("powerToCarLineL1", $goEChargerEnergy[7] / 10);
            if ($correctionFactorL1 > 0) {
                $this->SetValue("correctedPowerToCarLineL1", $goEChargerEnergy[7] / 10 * $correctionFactorL1);
                $this->SetValue("correctedPowerFactorLineL1", $goEChargerEnergy[12] / 100 * $correctionFactorL1);
            }
            $this->SetValue("powerToCarLineL2", $goEChargerEnergy[8] / 10);
            if ($correctionFactorL2 > 0) {
                $this->SetValue("correctedPowerToCarLineL2", $goEChargerEnergy[8] / 10 * $correctionFactorL2);
                $this->SetValue("correctedPowerFactorLineL2", $goEChargerEnergy[13] / 100 * $correctionFactorL2);
            }
            $this->SetValue("powerToCarLineL3", $goEChargerEnergy[9] / 10);
            if ($correctionFactorL3 > 0) {
                $this->SetValue("correctedPowerToCarLineL3", $goEChargerEnergy[9] / 10 * $correctionFactorL3);
                $this->SetValue("correctedPowerFactorLineL3", $goEChargerEnergy[14] / 100 * $correctionFactorL3);
            }
            if ($correctionFactorL1 > 0) {
                $this->SetValue("correctedPowerToCarTotal", ((($goEChargerEnergy[0] * $goEChargerEnergy[4] / 10) * $correctionFactorL1) + (($goEChargerEnergy[1] * $goEChargerEnergy[5] / 10) * $correctionFactorL2) + (($goEChargerEnergy[2] * $goEChargerEnergy[6] / 10) * $correctionFactorL3)) / 1000);
            }

            $usedSupplyLinesByCar = 0;
            if ($goEChargerEnergy[7] / 10 > 0) $usedSupplyLinesByCar += 1;  // Phase 1
            if ($goEChargerEnergy[8] / 10 > 0) $usedSupplyLinesByCar += 1;  // Phase 2
            if ($goEChargerEnergy[9] / 10 > 0) $usedSupplyLinesByCar += 1;  // Phase 3
            $this->SetValue("usedSupplyLinesByCar", $usedSupplyLinesByCar);
            $this->SetValue("powerToCarLineN", $goEChargerEnergy[10] / 10);
            $this->SetValue("powerToCarTotal", $goEChargerEnergy[11] / 100);
            $this->SetValue("powerFactorLineL1", $goEChargerEnergy[12] / 100);
            $this->SetValue("powerFactorLineL2", $goEChargerEnergy[13] / 100);
            $this->SetValue("powerFactorLineL3", $goEChargerEnergy[14] / 100);
            $this->SetValue("powerFactorLineN", $goEChargerEnergy[15] / 100);
        }

        //--- PHA (Phasen before and after the contactor) -------------------------------------
        if (isset($goEChargerStatus->{'pha'})) {
            $Phasen = "";
            $AnzahlPhasen = 0;
            if ($goEChargerStatus->{'pha'} & (1 << 3)) {
                $Phasen = $Phasen . ' 1';
                $AnzahlPhasen = 1;
            }
            if ($goEChargerStatus->{'pha'} & (1 << 4)) {
                if ($Phasen <> "") {
                    $Phasen = $Phasen . ",";
                    $AnzahlPhasen += 1;
                }
                $Phasen = $Phasen . ' 2';
            }
            if ($goEChargerStatus->{'pha'} & (1 << 5)) {
                if ($Phasen <> "") {
                    $Phasen = $Phasen . " und";
                    $AnzahlPhasen += 1;
                }
                $Phasen = $Phasen . ' 3';
            }
            if ($Phasen <> "") {
                $Phasen = "Phasen" . $Phasen . " vorhanden";
            } else
                $Phasen = 'Keine Phasen vorhanden';
            $this->SetValue("availablePhases", $Phasen);
            $this->SetValue("availablePhasesInRow", $AnzahlPhasen);
        }

        //--- RBC (Reboot Counter) ------------------------------------------------------------
        $this->setValueToIdent($goEChargerStatus, "rebootCounter", "rbc");
        if (isset($goEChargerStatus->{'rbt'})) {
            $this->SetValue("rebootTime", date(DATE_RFC822, time() - round($goEChargerStatus->{'rbt'} / 1000, 0)));
        }

        //--- RBT (Reboot Timer) --------------------------------------------------------------
        if (isset($goEChargerStatus->{'rbt'})) {
            $rebootTime = date(DATE_RFC822, time() - round($goEChargerStatus->{'rbt'} / 1000, 0));
            if (substr($rebootTime, 0, 20) <> substr(GetValue($this->GetIDForIdent("rebootTime")), 0, 20)) {
                $this->SetValue("rebootTime", $rebootTime);
            }
        }

        //--- SSE (Serial number number formatted as %06d) ------------------------------------
        $this->setValueToIdent($goEChargerStatus, "serialID", "sse");

        //--- TMA (Temperatures of the controller °C, (from GO-E V3 on) -----------------------
        if (isset($goEChargerStatus->{'tma'})) {
            // array of temperatures is used, so calculate average temperature
            $goEChargerTemperatures = $goEChargerStatus->{'tma'};
            $counter = count($goEChargerTemperatures);
            $temperature = 0;
            for ($x = 0; $x < $counter; $x++) {
                $temperature = $temperature + $goEChargerTemperatures[$x];
            }
            $temperature = $temperature / $counter;
            $this->SetValue("mainboardTemperature", $temperature);
        }

        //--- TMP (Temperature of the controller in °C (till GO-E V2) -------------------------
        if (isset($goEChargerStatus->{'tmp'}) and ($goEChargerStatus->{'tmp'} > 0)) {
            // simple temperature value is used
            $this->SetValue("mainboardTemperature", $goEChargerStatus->{'tmp'});
        }

        //--- UBY (unlocked_by: Number of the RFID card) --------------------------------------
        $this->setValueToIdent($goEChargerStatus, "unlockedByRFID", "uby");

        //--- UST (unlock_state: Cable lock adjustment) ---------------------------------------
        $this->setValueToIdent($goEChargerStatus, "cableUnlockMode", "ust");


        //--- GENERAL ACTIONS AFTER A VARIABLE HAS CHANGED ------------------------------------
        // Handle Re-Activation
        if ($this->ReadPropertyBoolean("AutoReactivate") == true and
            GetValueBoolean($this->GetIDForIdent("accessState")) == false and // charger deactivated
            GetValueInteger($this->GetIDForIdent("status")) == 3)               // wait for car -> car plugged in
        {
            // Check, if car was just plugged in (so plugin took place after box was deactivated)
            $VariableWallboxActive = IPS_GetVariable($this->GetIDForIdent("accessState"));
            $VariableConnection = IPS_GetVariable($this->GetIDForIdent("status"));
            if ($VariableConnection['VariableChanged'] > $VariableWallboxActive['VariableChanged']) {
                // reactivate Wallbox
                $this->setValueToeCharger('alw', true);
                $this->SetValue("accessState", true);
            }

        }

        return true;
    }


    //=== VARIABLE CONFIGURATION ===================================================================================
    protected function registerProfiles()
    {
        // Generate Variable Profiles
        if (!IPS_VariableProfileExists('GOECHARGER_Status')) {
            IPS_CreateVariableProfile('GOECHARGER_Status', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Status', 'Ok');
            IPS_SetVariableProfileAssociation("GOECHARGER_Status", 1, "Ladestation bereit, kein Fahrzeug", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_Status", 2, "Fahrzeug lädt", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_Status", 3, "Warten auf Fahrzeug", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_Status", 4, "Ladung beendet, Fahrzeug noch verbunden", "", 0xFFFFFF);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Error')) {
            IPS_CreateVariableProfile('GOECHARGER_Error', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Error', 'Ok');
            IPS_SetVariableProfileAssociation("GOECHARGER_Error", 0, "Kein Fehler", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("GOECHARGER_Error", 1, "FI Schutzschalter", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_Error", 3, "Fehler an Phase", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_Error", 8, "Keine Erdung", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_Error", 10, "Interner Fehler", "", 0xFF0000);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Access')) {
            IPS_CreateVariableProfile('GOECHARGER_Access', 1);
            IPS_SetVariableProfileAssociation("GOECHARGER_Access", 0, "frei zugänglich", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("GOECHARGER_Access", 1, "RFID Identifizierung", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_Access", 2, "Strompreis / automatisch", "", 0xFF0000);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Ampere')) {
            IPS_CreateVariableProfile('GOECHARGER_Ampere', 1);
            IPS_SetVariableProfileDigits('GOECHARGER_Ampere', 0);
            IPS_SetVariableProfileIcon('GOECHARGER_Ampere', 'Electricity');
            IPS_SetVariableProfileText('GOECHARGER_Ampere', "", " A");
            IPS_SetVariableProfileValues('GOECHARGER_Ampere', 6, 32, 1);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Ampere.1')) {
            IPS_CreateVariableProfile('GOECHARGER_Ampere.1', 2);
            IPS_SetVariableProfileDigits('GOECHARGER_Ampere.1', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Ampere.1', 'Electricity');
            IPS_SetVariableProfileText('GOECHARGER_Ampere.1', "", " A");
        }

        if (!IPS_VariableProfileExists('GOECHARGER_AmpereCable')) {
            IPS_CreateVariableProfile('GOECHARGER_AmpereCable', 1);
            IPS_SetVariableProfileDigits('GOECHARGER_AmpereCable', 0);
            IPS_SetVariableProfileIcon('GOECHARGER_AmpereCable', 'Electricity');
            IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 0, "Kein Kabel", "", 0xFF0000);
            for ($i = 1; $i <= 25; $i++) {
                IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", $i, number_format($i, 0) . " A", "", 0xFFFFFF);
            }
            IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 30, "30 A", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 32, "32 A", "", 0xFFFFFF);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_AutomaticStop')) {
            IPS_CreateVariableProfile('GOECHARGER_AutomaticStop', 2);
            IPS_SetVariableProfileIcon('GOECHARGER_AutomaticStop', 'Battery');
            IPS_SetVariableProfileDigits('GOECHARGER_AutomaticStop', 1);
            IPS_SetVariableProfileValues('GOECHARGER_AutomaticStop', 0, 100, 0.1);
            IPS_SetVariableProfileText('GOECHARGER_AutomaticStop', "", " kw");
        }

        if (!IPS_VariableProfileExists('GOECHARGER_AutomaticStopKM')) {
            IPS_CreateVariableProfile('GOECHARGER_AutomaticStopKM', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_AutomaticStopKM', 'Close');
            IPS_SetVariableProfileDigits('GOECHARGER_AutomaticStopKM', 0);
            IPS_SetVariableProfileValues('GOECHARGER_AutomaticStopKM', 0, 100, 0);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 0, "deaktiviert", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 5, "5 km", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 10, "10 km", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 20, "20 km", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 50, "50 km", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 75, "75 km", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 100, "100 km", "", 0xFF0000);
            IPS_SetVariableProfileText('GOECHARGER_AutomaticStopKM', "", "");
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Adapter')) {
            IPS_CreateVariableProfile('GOECHARGER_Adapter', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Adapter', 'Ok');
            IPS_SetVariableProfileAssociation("GOECHARGER_Adapter", 0, "kein Adapter", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("GOECHARGER_Adapter", 1, "16A Adapter", "", 0xFFFFFF);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Voltage')) {
            IPS_CreateVariableProfile('GOECHARGER_Voltage', 1);
            IPS_SetVariableProfileDigits('GOECHARGER_Voltage', 0);
            IPS_SetVariableProfileIcon('GOECHARGER_Voltage', 'Electricity');
            IPS_SetVariableProfileText('GOECHARGER_Voltage', "", " V");
        }

        if (!IPS_VariableProfileExists('GOECHARGER_Energy.1')) {
            IPS_CreateVariableProfile('GOECHARGER_Energy.1', 2);
            IPS_SetVariableProfileDigits('GOECHARGER_Energy.1', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Energy.1', 'Electricity');
        }
        IPS_SetVariableProfileText('GOECHARGER_Energy.1', "", " kWh");


        if (!IPS_VariableProfileExists('GOECHARGER_Power.1')) {
            IPS_CreateVariableProfile('GOECHARGER_Power.1', 2);
            IPS_SetVariableProfileDigits('GOECHARGER_Power.1', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_Power.1', 'Electricity');
        }
        IPS_SetVariableProfileText('GOECHARGER_Power.1', "", " kW");

        if (!IPS_VariableProfileExists('GOECHARGER_CableUnlockMode')) {
            IPS_CreateVariableProfile('GOECHARGER_CableUnlockMode', 1);
            IPS_SetVariableProfileIcon('GOECHARGER_CableUnlockMode', 'Plug');
            IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 0, "verriegelt, wenn Auto angeschlossen", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 1, "am Ladeende entriegeln", "", 0xFFCC00);
            IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 2, "immer verriegelt", "", 0xFF0000);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_AwattarPricezone')) {
            IPS_CreateVariableProfile('GOECHARGER_AwattarPricezone', 1);
            IPS_SetVariableProfileAssociation("GOECHARGER_AwattarPricezone", 0, "Österreich", "", 0x000000);
            IPS_SetVariableProfileAssociation("GOECHARGER_AwattarPricezone", 1, "Deutschland", "", 0x000000);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_singlePhaseCharging')) {
            IPS_CreateVariableProfile('GOECHARGER_singlePhaseCharging', 0);
            IPS_SetVariableProfileAssociation("GOECHARGER_singlePhaseCharging", 0, "3phasig", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("GOECHARGER_singlePhaseCharging", 1, "1phasig", "", 0xFFCC00);
        }

        if (!IPS_VariableProfileExists('GOECHARGER_ElectricityPriceChargeTill')) {
            IPS_CreateVariableProfile('GOECHARGER_ElectricityPriceChargeTill', 1);
            for ($i = 0; $i <= 23; $i++) {
                IPS_SetVariableProfileAssociation("GOECHARGER_ElectricityPriceChargeTill", $i, str_pad($i, 2, "0", STR_PAD_LEFT) . ":00 Uhr", "", 0x000000);
            }
        }

        if (!IPS_VariableProfileExists('GOECHARGER_ElectricityPriceMinChargeHours')) {
            IPS_CreateVariableProfile('GOECHARGER_ElectricityPriceMinChargeHours', 1);
            for ($i = 0; $i <= 23; $i++) {
                IPS_SetVariableProfileAssociation("GOECHARGER_ElectricityPriceMinChargeHours", $i, $i . " Stunden", "", 0x000000);
            }
        }
    }

    protected function registerVariables()
    {

        //--- Basic Functions -------------------------------------------------------------
        $this->RegisterVariableBoolean("accessState", "Wallbox aktiv", "~Switch", 11);
        $this->EnableAction("accessState");

        $this->RegisterVariableInteger("status", "Status", "GOECHARGER_Status", 12);

        $this->RegisterVariableFloat("automaticStop", "Ladeende nach x kwh", "GOECHARGER_AutomaticStop", 13);
        $this->EnableAction("automaticStop");

        $this->RegisterVariableInteger("automaticStopKm", "Ladeende nach Energie für x km", "GOECHARGER_AutomaticStopKM", 14);
        $this->EnableAction("automaticStopKm");

        //--- Informations to the current loading cycle ------------------------------------
        $this->RegisterVariableFloat("powerToCarTotal", "Aktuelle Leistung zum Fahrzeug", "GOECHARGER_Power.1", 31);

        $this->RegisterVariableFloat("energyLoadCycle", "abgegebene Energie im Ladezyklus", "GOECHARGER_Energy.1", 33);

        $this->RegisterVariableInteger("unlockedByRFID", "entsperrt durch RFID", "", 34);


        //--- Power Consumption information ------------------------------------------------
        $this->RegisterVariableFloat("energyTotal", "bisher abgegebene Energie", "GOECHARGER_Energy.1", 51);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterVariableFloat("energyChargedCard" . $i, "geladene Energie Karte " . $i, "GOECHARGER_Energy.1", 51 + $i);
        }

        //--- Setup -----------------------------------------------------------------------
        $this->RegisterVariableInteger("maxAvailableAMP", "max. verfügbarer Ladestrom", "GOECHARGER_Ampere", 71);
        $this->EnableAction("maxAvailableAMP");

        $this->RegisterVariableInteger("availableAMP", "aktuell verfügbarer Ladestrom", "GOECHARGER_Ampere", 72);
        $this->EnableAction("availableAMP");

        $this->RegisterVariableInteger("availableAMPbyTemp", "aktuell temperaturbezogenes Ladestrom-Limit", "GOECHARGER_Ampere", 72);

        $this->RegisterVariableInteger("cableUnlockMode", "Kabel-Verriegelungsmodus", "GOECHARGER_CableUnlockMode", 73);
        $this->EnableAction("cableUnlockMode");

        $this->RegisterVariableInteger("accessControl", "Zugangskontrolle via RFID/App/Strompreis", "GOECHARGER_Access", 74);
        $this->EnableAction("accessControl");

        $this->RegisterVariableInteger("ledBrightness", "LED Helligkeit", "~Intensity.255", 75);
        $this->EnableAction("ledBrightness");
        $this->RegisterVariableBoolean("ledEnergySave", "LED Energiesparfunktion", "~Switch", 76);
        $this->EnableAction("ledEnergySave");

        //--- Ladung mittels Strompreis automatisch
        $this->RegisterVariableInteger("electricityPriceMinChargeHours", "minimale Ladezeit bei Strompreis-basiertem Laden", "GOECHARGER_ElectricityPriceMinChargeHours", 81);
        $this->EnableAction("electricityPriceMinChargeHours");
        $this->RegisterVariableInteger("electricityPriceChargeTill", "Laden beendet bis bei Strompreis-basiertem Laden", "GOECHARGER_ElectricityPriceChargeTill", 82);
        $this->EnableAction("electricityPriceChargeTill");

        //--- Technical Informations ------------------------------------------------------
        $this->RegisterVariableString("serialID", "Seriennummer", "", 91);
        $this->RegisterVariableInteger("error", "Fehler", "GOECHARGER_Error", 92);
        $this->RegisterVariableInteger("rebootCounter", "Reboot Zähler", "", 93);
        $this->RegisterVariableString("rebootTime", "Reboot Zeitpunkt", "", 93);
        $this->RegisterVariableInteger("adapterAttached", "angeschlossener Adapter", "GOECHARGER_Adapter", 95);
        $this->RegisterVariableInteger("cableCapability", "Kabel-Leistungsfähigkeit", "GOECHARGER_AmpereCable", 96);
        $this->RegisterVariableBoolean("norwayMode", "Erdungsprüfung", "~Switch", 97);
        $this->RegisterVariableFloat("mainboardTemperature", "Innentemperatur", "~Temperature", 98);
        $this->RegisterVariableString("availablePhases", "verfügbare Phasen", "", 99);
        $this->RegisterVariableInteger("availablePhasesInRow", "verfügbare Phasen in Reihe", "", 99);
        $this->RegisterVariableBoolean("singlePhaseCharging", "Mit wieviel Phasen laden?", "GOECHARGER_singlePhaseCharging", 99);
        $this->EnableAction("singlePhaseCharging");
        $this->RegisterVariableInteger("supplyLineL1", "Spannungsversorgung L1", "GOECHARGER_Voltage", 100);
        $this->RegisterVariableInteger("supplyLineL2", "Spannungsversorgung L2", "GOECHARGER_Voltage", 101);
        $this->RegisterVariableInteger("supplyLineL3", "Spannungsversorgung L3", "GOECHARGER_Voltage", 102);
        $this->RegisterVariableInteger("supplyLineN", "Spannungsversorgung N", "GOECHARGER_Voltage", 103);

        //--- Power to Car
        $this->RegisterVariableInteger("usedSupplyLinesByCar", "aktuell genutze Phasen beim Laden", "", 104);
        $this->RegisterVariableFloat("powerToCarLineL1", "Leistung zum Fahrzeug L1", "GOECHARGER_Power.1", 104);
        $this->RegisterVariableFloat("powerToCarLineL2", "Leistung zum Fahrzeug L2", "GOECHARGER_Power.1", 106);
        $this->RegisterVariableFloat("powerToCarLineL3", "Leistung zum Fahrzeug L3", "GOECHARGER_Power.1", 108);
        $this->RegisterVariableFloat("powerToCarLineN", "Leistung zum Fahrzeug N", "GOECHARGER_Power.1", 109);
        $this->RegisterVariableFloat("ampToCarLineL1", "Ampere zum Fahrzeug L1", "GOECHARGER_Ampere.1", 110);
        $this->RegisterVariableFloat("ampToCarLineL2", "Ampere zum Fahrzeug L2", "GOECHARGER_Ampere.1", 111);
        $this->RegisterVariableFloat("ampToCarLineL3", "Ampere zum Fahrzeug L3", "GOECHARGER_Ampere.1", 114);
        $this->RegisterVariableFloat("powerFactorLineL1", "Leistungsfaktor L1", "~Humidity.F", 115);
        $this->RegisterVariableFloat("powerFactorLineL2", "Leistungsfaktor L2", "~Humidity.F", 117);
        $this->RegisterVariableFloat("powerFactorLineL3", "Leistungsfaktor L3", "~Humidity.F", 119);
        $this->RegisterVariableFloat("powerFactorLineN", "Leistungsfaktor N", "~Humidity.F", 121);
        $this->RegisterVariableFloat("availableSupplyEnergy", "max. verfügbare Ladeleistung", "GOECHARGER_Power.1", 125);

        if ($this->ReadPropertyBoolean("calculateCorrectedData")) {
            //--- Attributes for data correction
            $this->RegisterVariableFloat("correctedPowerToCarTotal", "Aktuelle Leistung zum Fahrzeug - korrigiert", "GOECHARGER_Power.1", 32);
            $this->RegisterVariableFloat("correctedPowerToCarLineL1", "Leistung zum Fahrzeug L1 - korrigiert", "GOECHARGER_Power.1", 105);
            $this->RegisterVariableFloat("correctedPowerToCarLineL2", "Leistung zum Fahrzeug L2 - korrigiert", "GOECHARGER_Power.1", 107);
            $this->RegisterVariableFloat("correctedPowerToCarLineL3", "Leistung zum Fahrzeug L3 - korrigiert", "GOECHARGER_Power.1", 109);
            $this->RegisterVariableFloat("correctedPowerFactorLineL1", "Leistungsfaktor L1 - korrigiert", "~Humidity.F", 116);
            $this->RegisterVariableFloat("correctedPowerFactorLineL2", "Leistungsfaktor L2 - korrigiert", "~Humidity.F", 118);
            $this->RegisterVariableFloat("correctedPowerFactorLineL3", "Leistungsfaktor L3 - korrigiert", "~Humidity.F", 120);
            $this->RegisterVariableFloat("correctedAvailableSupplyEnergy", "max. verfügbare Ladeleistung - korrigiert", "GOECHARGER_Power.1", 126);
            $this->RegisterVariableFloat("correctionFactorL1", "Korrekturfaktor L1", "~Humidity.F", 127);
            $this->RegisterVariableFloat("correctionFactorL2", "Korrekturfaktor L2", "~Humidity.F", 128);
            $this->RegisterVariableFloat("correctionFactorL3", "Korrekturfaktor L3", "~Humidity.F", 129);
        }

        $this->RegisterVariableInteger("awattarPricezone", "Awattar Preiszone", "GOECHARGER_AwattarPricezone", 150);

        $this->RegisterVariableInteger("lastUpdateChargerStatus", "letzter Wechsel zwischen aktiv und inaktiv", "~UnixTimestamp", 999);
        $this->RegisterVariableInteger("lastUpdateSinglePhase", "letzter Wechsel zwischen 1- und 3-phasigem Laden", "~UnixTimestamp", 999);
    }

    protected function dataCorrection(&$goEChargerStatus, $goEChargerStatusV2) {
        /* This method maybe used to correct data returned from the Status API
           Usually these corrections should be only temporary needed */

        // issue on "nrg" with to high power factor!! (in FW 52.2)
        if (isset($goEChargerStatus->{'nrg'})) {
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};

            if ($goEChargerEnergy[7] > 77 ) $goEChargerEnergy[11] = $goEChargerEnergy[11]/10;

            if ($goEChargerEnergy[7] > 77 ) $goEChargerEnergy[7] = $goEChargerEnergy[7]/100;
            if ($goEChargerEnergy[8] > 77 ) $goEChargerEnergy[8] = $goEChargerEnergy[8]/100;
            if ($goEChargerEnergy[9] > 77 ) $goEChargerEnergy[9] = $goEChargerEnergy[9]/100;

            $goEChargerStatus->{'nrg'} = $goEChargerEnergy;
        }

        // transfer data from API v2 into API v1 structure
        if ( $goEChargerStatusV2 != null ) {
            if ((isset($goEChargerStatus->{'dwo'})) && (isset($goEChargerStatusV2->{'dwo'}))) {
                $value = intval($goEChargerStatusV2->{'dwo'})/100; // conversion Wh -> 0.1 kWh needed
                $goEChargerStatus->{'dwo'} = strval($value);
            }
        }
    }

    protected function mqttDataCorrectionApiV2toApiV1(&$goEChargerStatus) {
        /* This method is used to correct  MQTT Data which is sent in API V2 format to API V1 on which the
           normal logic is based (HTTP-API-V1) */

        if (isset($goEChargerStatus->{'eto'})) {
            // total energy is sent in API V2 in Wh, in API V1 it's .1 kWh, so we've to defice by 10
            $goEChargerStatus->{'eto'} = $goEChargerStatus->{'eto'} / 10;
        }

        if (isset($goEChargerStatus->{'dwo'})) {
            // total energy is sent in API V2 in Wh, in API V1 it's .1 kWh, so we've to defice by 10
            $goEChargerStatus->{'dwo'} = $goEChargerStatus->{'dwo'} / 100;
        }
    }
}

?>