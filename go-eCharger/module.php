<?php
    class go_eCharger extends IPSModule {
 
        public function __construct($InstanceID) {
          /* Constructor is called before each function call */
          parent::__construct($InstanceID);
        }
        
        public function Create() {
          /* Create is called ONCE on Instance creation and start of IP-Symcon.
             Status-Variables und Modul-Properties for permanent usage should be created here  */
          parent::Create(); 
            
          //--- Properties
          $this->RegisterPropertyString("IPAddressCharger", "0.0.0.0"); 
          $this->RegisterPropertyInteger("MaxAmperage", 6);

          // Update Intevals
          $this->RegisterPropertyInteger("UpdateIdle", 0);  
          $this->RegisterPropertyInteger("UpdateCharging",0);

          // Comfort Functions
          $this->RegisterPropertyBoolean("AutoReactivate",false); 
          $this->RegisterPropertyBoolean("AutoActivateOnStopSet",false);

          // Vehicle Data
          $this->RegisterPropertyFloat("AverageConsumption",0);
          $this->RegisterPropertyFloat("MaxLoadKw",0);

          // Special Functions
          $this->RegisterPropertyBoolean("calculateCorrectedData",false);
          $this->RegisterPropertyInteger("verifiedSupplyPowerL1", 230);
          $this->RegisterPropertyInteger("verifiedSupplyPowerL2", 230);
          $this->RegisterPropertyInteger("verifiedSupplyPowerL3", 230);

          //--- Register Timer
          $this->RegisterTimer("GOeChargerTimer_UpdateTimer", 0, 'GOeCharger_Update($_IPS[\'TARGET\']);');
        }
 
        public function ApplyChanges() {
          /* Called on 'apply changes' in the configuration UI and after creation of the instance */
          parent::ApplyChanges(); 

          // Generate Profiles & Variables
          $this->registerProfiles();
          $this->registerVariables();  

          // Set Data to Variables (and update timer)
          $this->Update();
        } 

        public function Destroy() {
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

        public function Update() {
            /* Check the connection to the go-eCharger */
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
       
            // write values into variables
            SetValue($this->GetIDForIdent("status"),                  $goEChargerStatus->{'car'});    
            SetValue($this->GetIDForIdent("availableAMP"),            $goEChargerStatus->{'amp'}); 
            SetValue($this->GetIDForIdent("error"),                   $goEChargerStatus->{'err'}); 
            SetValue($this->GetIDForIdent("accessControl"),           $goEChargerStatus->{'ast'});
            SetValue($this->GetIDForIdent("accessState"),             $goEChargerStatus->{'alw'}); 
            SetValue($this->GetIDForIdent("cableCapability"),         $goEChargerStatus->{'cbl'});
            SetValue($this->GetIDForIdent("rebootCounter"),           $goEChargerStatus->{'rbc'});
            SetValue($this->GetIDForIdent("rebootTime"), date( DATE_RFC822, time()-round($goEChargerStatus->{'rbt'}/1000,0)) );
            
            $Phasen = "";
            if ( $goEChargerStatus->{'pha'}&(1<<3) ) $Phasen = $Phasen.' 1';
            if ( $goEChargerStatus->{'pha'}&(1<<4) ) { 
                if ( $Phasen <> "" ) {
                    $Phasen = $Phasen.",";
                }
                $Phasen = $Phasen.' 2';
            }
            if ( $goEChargerStatus->{'pha'}&(1<<5) ) { 
                if ( $Phasen <> "" ) {
                    $Phasen = $Phasen." und";
                }
                $Phasen = $Phasen.' 3';
            }
            if ( $Phasen <> "" ) {
                $Phasen = "Phasen".$Phasen." vorhanden";
            } else
                $Phasen = 'Keine Phasen vorhanden';
            SetValue($this->GetIDForIdent("availablePhases"), $Phasen );
            
            
            SetValue($this->GetIDForIdent("mainboardTemperature"),    $goEChargerStatus->{'tmp'});  
            SetValue($this->GetIDForIdent("automaticStop"),           $goEChargerStatus->{'dwo'}/10 );
            
            if ( $this->ReadPropertyFloat("AverageConsumption") > 0 )
            {
              SetValue($this->GetIDForIdent("automaticStopKm"), $goEChargerStatus->{'dwo'}/10/$this->ReadPropertyFloat("AverageConsumption")*100 );                
            } else 
              SetValue($this->GetIDForIdent("automaticStopKm"), 0 );
            
            SetValue($this->GetIDForIdent("adapterAttached"),         $goEChargerStatus->{'adi'});
            SetValue($this->GetIDForIdent("unlockedByRFID"),          $goEChargerStatus->{'uby'});
            SetValue($this->GetIDForIdent("energyTotal"),             $goEChargerStatus->{'eto'}/10);
            SetValue($this->GetIDForIdent("energyLoadCycle"),         $goEChargerStatus->{'dws'}/361010.83);
            
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            SetValue($this->GetIDForIdent("supplyLineL1"),            $goEChargerEnergy[0]);            
            SetValue($this->GetIDForIdent("supplyLineL2"),            $goEChargerEnergy[1]);            
            SetValue($this->GetIDForIdent("supplyLineL3"),            $goEChargerEnergy[2]);  
            SetValue($this->GetIDForIdent("supplyLineN"),             $goEChargerEnergy[3]);  
            $availableEnergy = ( ( ( $goEChargerEnergy[0] + $goEChargerEnergy[1] + $goEChargerEnergy[2] ) / 3 ) * 3 * $goEChargerStatus->{'amp'} ) / 1000;
            SetValue($this->GetIDForIdent("availableSupplyEnergy"),    $availableEnergy);
            SetValue($this->GetIDForIdent("ampToCarLineL1"),          $goEChargerEnergy[4]/10);            
            SetValue($this->GetIDForIdent("ampToCarLineL2"),          $goEChargerEnergy[5]/10);            
            SetValue($this->GetIDForIdent("ampToCarLineL3"),          $goEChargerEnergy[6]/10);  
            SetValue($this->GetIDForIdent("powerToCarLineL1"),        $goEChargerEnergy[7]/10);            
            SetValue($this->GetIDForIdent("powerToCarLineL2"),        $goEChargerEnergy[8]/10);            
            SetValue($this->GetIDForIdent("powerToCarLineL3"),        $goEChargerEnergy[9]/10);  
            SetValue($this->GetIDForIdent("powerToCarLineN"),         $goEChargerEnergy[10]/10); 
            SetValue($this->GetIDForIdent("powerToCarTotal"),         $goEChargerEnergy[11]/100); 
            SetValue($this->GetIDForIdent("powerFactorLineL1"),       $goEChargerEnergy[12]/100);            
            SetValue($this->GetIDForIdent("powerFactorLineL2"),       $goEChargerEnergy[13]/100);            
            SetValue($this->GetIDForIdent("powerFactorLineL3"),       $goEChargerEnergy[14]/100);  
            SetValue($this->GetIDForIdent("powerFactorLineN"),        $goEChargerEnergy[15]/100);             
            SetValue($this->GetIDForIdent("serialID"),                $goEChargerStatus->{'sse'});  
            SetValue($this->GetIDForIdent("ledBrightness"),           $goEChargerStatus->{'lbr'});  
            SetValue($this->GetIDForIdent("ledEnergySave"),           $goEChargerStatus->{'lse'});  
            if ( isset( $goEChargerStatus->{'azo'} ) ) SetValue( $this->GetIDForIdent("awattarPricezone"),               $goEChargerStatus->{'azo'} );  // new with Firmware 40.0
            if ( isset( $goEChargerStatus->{'aho'} ) ) SetValue( $this->GetIDForIdent("electricityPriceMinChargeHours"), $goEChargerStatus->{'aho'} );  // new with Firmware 40.0
            if ( isset( $goEChargerStatus->{'afi'} ) ) SetValue( $this->GetIDForIdent("electricityPriceChargeTill"),     $goEChargerStatus->{'afi'} );  // new with Firmware 40.0
            SetValue($this->GetIDForIdent("maxAvailableAMP"),         $goEChargerStatus->{'ama'}); 
            SetValue($this->GetIDForIdent("cableUnlockMode"),         $goEChargerStatus->{'ust'});
            $groundCheck = true;
            if ( $goEChargerStatus->{'nmo'} == '1' ) { $groundCheck = false; }
            SetValue($this->GetIDForIdent("norwayMode"),              $groundCheck);

            for($i=1; $i<=10; $i++){
                switch ( $i ){
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
                        $code = 'ec'.$i;
                        break;
                }
                SetValue($this->GetIDForIdent("energyChargedCard".$i),  $goEChargerStatus->{$code}/10);
            }
           
            // Set Timer
            if ( $goEChargerStatus->{'car'} == "2" ) {
                if ( $this->ReadPropertyInteger("UpdateCharging") >= 0 ) {
                    $this->SetTimerInterval("GOeChargerTimer_UpdateTimer", $this->ReadPropertyInteger("UpdateCharging")*1000);
                }
            } else { 
                if ( $this->ReadPropertyInteger("UpdateIdle") >= 0 ) {
                    $this->SetTimerInterval("GOeChargerTimer_UpdateTimer", $this->ReadPropertyInteger("UpdateIdle")*1000);
                }
            }
            
            // Handle Re-Activation
            if ( $this->ReadPropertyBoolean("AutoReactivate") == true and
                 GetValueBoolean( $this->GetIDForIdent("accessState") ) == false and    // charger deactivated
                 GetValueInteger( $this->GetIDForIdent("status") ) == 3 )               // wait for car -> car plugged in
            {
               // Check, if car was just plugged in (so plugin took place after box was deactivated)
               $VariableWallboxActive = IPS_GetVariable( $this->GetIDForIdent("accessState") );
               $VariableConnection    = IPS_GetVariable( $this->GetIDForIdent("status") );
               if ( $VariableConnection['VariableChanged'] > $VariableWallboxActive['VariableChanged'] )
               {
                 // reactivate Wallbox
                 $this->setValueToeCharger( 'alw', true );  
                 SetValue($this->GetIDForIdent("accessState"), true );
               }
                
            }
            
            return true;
        }
       
        public function getPowerToCar() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus[11]/100;
        }
        
        public function getCurrentLoadingCycleConsumption() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'dws'}/361010.83; 
        }
        
        public function getMaximumChargingAmperage() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'ama'}; 
        }
        
        public function setMaximumChargingAmperage(int $ampere) {
            // Check input value
            $ampereToSet = $ampere;
            if ( $ampere < 6 or $ampere > 32 ) { return false; }
            if ( $ampere > $this->ReadPropertyInteger("MaxAmperage") ) { 
                $ampereToSet = $this->ReadPropertyInteger("MaxAmperage"); 
            }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }

            // first calculate the Button values
            $button[0] = 6; // min. Value
            $gaps = round( ( ( $ampereToSet - 6 ) / 4 ) - 0.5 );
            $button[1] = $button[0] + $gaps;
            $button[2] = $button[1] + $gaps;
            $button[3] = $button[2] + $gaps;
            $button[4] = $ampereToSet; // max. Value

            // set values to Charger
            // set button values
            $this->setValueToeCharger( 'al1', $button[0] );
            $this->setValueToeCharger( 'al2', $button[1] );
            $this->setValueToeCharger( 'al3', $button[2] );
            $this->setValueToeCharger( 'al4', $button[3] );
            $this->setValueToeCharger( 'al5', $button[4] );

            // set max available Ampere
            $goEChargerStatus = $this->setValueToeCharger( 'ama', $ampereToSet );

            // set current available Ampere (if too high)
            if ( $goEChargerStatus->{'amp'} > $goEChargerStatus->{'ama'} ) {
              // set current available to max. available, as current was higher than new max.
              $goEChargerStatus = $this->setValueToeCharger( 'amp', $goEChargerStatus->{'ama'} );
            }  

            $this->Update();
            
            return $goEChargerStatus->{'ama'};
        }
        
        public function getCurrentChargingAmperage() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'amp'}; 
        }
        
        public function setCurrentChargingAmperage(int $ampere) {
            // Check input value
            $ampereToSet = $ampere;
            if ( $ampere < 6 or $ampere > 32 ) { return false; }
            if ( $ampere > $this->ReadPropertyInteger("MaxAmperage") ) { 
                $ampereToSet = $this->ReadPropertyInteger("MaxAmperage"); 
            }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            
            // Check requested Ampere is <= max Ampere set in Instance
            if ( $ampere > $goEChargerStatus->{'ama'} ) { 
                $ampereToSet = $goEChargerStatus->{'ama'};
            }
                                 
            // set current available Ampere
            $resultStatus = $this->setValueToeCharger( 'amp', $ampereToSet ); 
            
            // Update all data
            $this->Update();
            
            return $resultStatus->{'amp'};
        }

        public function getAccessControl() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'ast'};
        }
        
        public function setAccessControlActive(int $mode) {
            if ( $mode < 0 or $mode > 2 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'ast', $mode ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'ast'} == $mode ) { return true; } else { return false; }
        }
            
        public function getAutomaticChargeStop() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'dwo'}/10; 
        }
        
        public function setAutomaticChargeStop(float $chargeStopKwh) {
            if ( $chargeStopKwh < 0 or $chargeStopKwh > 100 ) { return false; }
            $value = number_format( $chargeStopKwh*10, 0, '', '' );
            $resultStatus = $this->setValueToeCharger( 'dwo', $value ); 
            if ( $this->ReadPropertyBoolean("AutoActivateOnStopSet") == true )
            {
              // activate Wallbox
              $this->setActive( true );
            } else
              $this->Update();
            if ( $resultStatus->{'dwo'} == $value ) { return true; } else { return false; }
        }
        
        public function getAutomaticChargeStopKm() {
            if ( $this->ReadPropertyFloat("AverageConsumption") > 0 )
            {
                $chargeStopKwh = $this->getAutomaticChargeStop();
                if ( $chargeStopKwh === false ) { 
                    return false;
                } else {
                    return $chargeStopKwh/$this->ReadPropertyFloat("AverageConsumption")*100;
                }
            }
            else
                return false;
        }
        
        public function setAutomaticChargeStopKm(float $chargeStopKm) {
            if ( $this->ReadPropertyFloat("AverageConsumption") > 0 )
            {
                $chargeStopKwh = $this->ReadPropertyFloat("AverageConsumption")/100*$chargeStopKm;
                return $this->setAutomaticChargeStop( $chargeStopKwh );
            }
            else
                return false;            
        }
            
        public function getCableUnlockMode() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'ust'}; 
        }
        
        public function setCableUnlockMode(int $unlockMode) {
            if ( $unlockMode < 0 or $unlockMode > 2 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'ust', $unlockMode ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'ust'} == $unlockMode ) { return true; } else { return false; }
        }
        
        public function isActive() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            if ( $goEChargerStatus->{'alw'} == '1' ) { return true; } else { return false; } 
        }
        
        public function setActive(bool $active) {
            if ( $active == true ) { $value = 1; } else { $value = 0; }
            $resultStatus = $this->setValueToeCharger( 'alw', $value ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'alw'} == $value ) { return true; } else { return false; }
        }
        
        public function getStatus() { 
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'car'}; 
        }

        public function getUnlockRFID() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'uby'}; 
        }
 
        public function getTotalPowerToCar() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            return $goEChargerEnergy[11]/100; 
        }
        
        public function getLEDBrightness() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'lbr'}; 
        }
        
        public function setLEDBrightness(int $brightness) {
            if ( $brightness < 0 or $brightness > 255 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'lbr', $brightness ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'lbr'} == $brightness ) { return true; } else { return false; }
        }
        
        public function getLEDEngergySave() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'lse'};  
        }
        
        public function setLEDEnergySave(bool $energySaveActive) {
            if ( $energySaveActive == true ) { $value = 1; } else { $value = 0; }
            $resultStatus = $this->setValueToeCharger( 'r2x', $value ); // based on issue report on GitHub the GO-eCharger needs "r2x" as parameter here!
            // Update all data
            $this->Update();
            if ( $resultStatus->{'lse'} == $value ) { return true; } else { return false; }
        }
        
        public function getEnergyChargedInTotal() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'eto'}/10;
        }
        
        public function getEnergyChargedByCard(int $cardID) {
            if ( $cardID < 1 or $cardID > 10 ) { return false; }
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            switch ( $cardID ){
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
                    $code = 'ec'.$cardID;
                    break;
            }
            return $goEChargerStatus->{$code}/10;
        }        
        
        public function getElectricityPriceMinChargeHours() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            if ( isset( $goEChargerStatus->{'aho'} ) ) { return $goEChargerStatus->{'aho'}; } else { return false; }
        }
        
        public function setElectricityPriceMinChargeHours(int $minChargeHours) {
            if ( $minChargeHours < 0 or $minChargeHours > 23 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'aho', $minChargeHours ); 
            // Update all data
            $this->Update();
            if ( isset( $goEChargerStatus->{'aho'} ) and $resultStatus->{'aho'} == $minChargeHours ) { return true; } else { return false; }
        }
        
        public function getElectricityPriceChargeTill() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            if ( isset( $goEChargerStatus->{'afi'} ) ) { return $goEChargerStatus->{'afi'}; } else { return false; }
        }
        
        public function setElectricityPriceChargeTill(int $chargeTill) {
            if ( $chargeTill < 0 or $chargeTill > 23 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'afi', $chargeTill ); 
            // Update all data
            $this->Update();
            if ( isset( $goEChargerStatus->{'afi'} ) and $resultStatus->{'afi'} == $chargeTill ) { return true; } else { return false; }
        }
        
        public function RequestAction($Ident, $Value) {
        
            switch($Ident) 
            {
                case "availableAMP":
                    $this->setMaximumChargingAmperage( $Value );
                    break;
                    
                case "maxAvailableAMP":
                    $this->setCurrentChargingAmperage( $Value );
                    break;
                    
                case "accessControl":
                    $this->setAccessControlActive( $Value );
                    break; 
                
                case "automaticStop":
                    $this->setAutomaticChargeStop( $Value );
                    break;
                    
               case "automaticStopKm":
                    $this->setAutomaticChargeStopKm( $Value );
                    break;
                    
                case "cableUnlockMode":
                    $this->setCableUnlockMode( $Value );
                    break;
                    
                case "accessState":
                    $this->setActive( $Value );  
                    break;
                    
                case "ledBrightness":
                    $this->setLEDBrightness( $Value );
                    break;
                    
                case "ledEnergySave":
                    $this->setLEDEnergySave( $Value );
                    break;
                    
                case "electricityPriceMinChargeHours":
                    $this->setElectricityPriceMinChargeHours( $Value );
                    break;
                    
                case "electricityPriceChargeTill":
                    $this->setElectricityPriceChargeTill( $Value );
                    break;
                    
                default:
                    throw new Exception("Invalid Ident"); 
                    
            }
            
        }
        
        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOeCharger_* 
        *
        * GOeCharger_CheckConnection($id);
        *
        */
        
        protected function getStatusFromCharger() {
            // get IP of go-eCharger
            $IPAddress = trim($this->ReadPropertyString("IPAddressCharger"));
            
            // check if IP is ocnfigured and valid
            if ( $IPAddress == "0.0.0.0" ) {
                $this->SetStatus(200); // no configuration done
                return false;
            } elseif (filter_var($IPAddress, FILTER_VALIDATE_IP) == false) { 
                $this->SetStatus(201); // no valid IP configured
                return false;
            }
            
            // check if any HHTP device on IP can be reached
            if ( $this->ping( $IPAddress, 80, 1 ) == false ) {
                $this->SetStatus(202); // no http response
                return false;
            }
              
            // get json from go-eCharger
            try {  
                $ch = curl_init("http://".$IPAddress."/status"); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
                curl_setopt($ch, CURLOPT_HEADER, 0); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                $json = curl_exec($ch); 
                curl_close ($ch);  
            } catch (Exception $e) { 
                $this->SetStatus(203); // no http response
                return false;
            };
            
            $goEChargerStatus = json_decode($json);
            if ( $goEChargerStatus === null ) {
                $this->SetStatus(203); // no http response
                return false;
            } elseif ( isset( $goEChargerStatus->{'sse'} ) == false ) {
                $this->SetStatus(204); // no go-eCharger
                return false;
            } 
            
            $this->SetStatus(102);
            return $goEChargerStatus;
        }
        
        protected function setValueToeCharger( $parameter, $value ){
            try {  
                $ch = curl_init("http://".trim($this->ReadPropertyString("IPAddressCharger"))."/mqtt?payload=".$parameter."=".$value); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
                curl_setopt($ch, CURLOPT_HEADER, 0); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                $json = curl_exec($ch); 
                curl_close ($ch);  
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
            if (!$fP) { return false; } 
            return true; 
        }
            
        protected function registerProfiles() {
            // Generate Variable Profiles
            if ( !IPS_VariableProfileExists('GOECHARGER_Status') ) {
                IPS_CreateVariableProfile('GOECHARGER_Status', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Status', 'Ok' );
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 1, "Ladestation bereit, kein Fahrzeug"       , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 2, "Fahrzeug lädt"                           , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 3, "Warten auf Fahrzeug"                     , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 4, "Ladung beendet, Fahrzeug noch verbunden" , "", 0xFFFFFF);
            }    
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Error') ) {
                IPS_CreateVariableProfile('GOECHARGER_Error', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Error', 'Ok' );
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 0,  "Kein Fehler"      , "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 1,  "FI Schutzschalter", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 3,  "Fehler an Phase"  , "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 8,  "Keine Erdung"     , "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 10, "Interner Fehler"  , "", 0xFF0000);
            } 
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Access') ) {
                IPS_CreateVariableProfile('GOECHARGER_Access', 1 );
                IPS_SetVariableProfileAssociation("GOECHARGER_Access", 0, "frei zugänglich"     , "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_Access", 1, "RFID Identifizierung", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Access", 2, "Strompreis / automatisch", "", 0xFF0000);
            }  
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Ampere') ) {
                IPS_CreateVariableProfile('GOECHARGER_Ampere', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER_Ampere', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_Ampere', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Ampere', "", " A" );
                IPS_SetVariableProfileValues('GOECHARGER_Ampere', 6, 32, 1 );
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Ampere.1') ) {
                IPS_CreateVariableProfile('GOECHARGER_Ampere.1', 2 );
                IPS_SetVariableProfileDigits('GOECHARGER_Ampere.1', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Ampere.1', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Ampere.1', "", " A" );
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_AmpereCable') ) {
                IPS_CreateVariableProfile('GOECHARGER_AmpereCable', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER_AmpereCable', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_AmpereCable', 'Electricity' );
                IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 0,  "Kein Kabel", "", 0xFF0000);
                for($i=1; $i<=25; $i++){
                    IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", $i, number_format($i, 0)." A", "", 0xFFFFFF);
                }
                IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 30, "30 A", "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_AmpereCable", 32, "32 A", "", 0xFFFFFF);
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_AutomaticStop') ) {
                IPS_CreateVariableProfile('GOECHARGER_AutomaticStop', 2 );
                IPS_SetVariableProfileIcon('GOECHARGER_AutomaticStop', 'Battery' );
                IPS_SetVariableProfileDigits('GOECHARGER_AutomaticStop',1);
                IPS_SetVariableProfileValues('GOECHARGER_AutomaticStop', 0, 100, 0.1 );
                IPS_SetVariableProfileText('GOECHARGER_AutomaticStop', "", " kw" );
            }  
            
            if ( !IPS_VariableProfileExists('GOECHARGER_AutomaticStopKM') ) {
                IPS_CreateVariableProfile('GOECHARGER_AutomaticStopKM', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_AutomaticStopKM', 'Close' );
                IPS_SetVariableProfileDigits('GOECHARGER_AutomaticStopKM',0);
                IPS_SetVariableProfileValues('GOECHARGER_AutomaticStopKM', 0, 100, 0 );
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 0, "deaktiviert", "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 5, "5 km", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 10, "10 km", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 20, "20 km", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 50, "50 km", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 75, "75 km", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 100, "100 km", "", 0xFF0000);
                IPS_SetVariableProfileText('GOECHARGER_AutomaticStopKM', "", "" );
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Adapter') ) {
                IPS_CreateVariableProfile('GOECHARGER_Adapter', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Adapter', 'Ok' );
                IPS_SetVariableProfileAssociation("GOECHARGER_Adapter", 0, "kein Adapter"    , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Adapter", 1, "16A Adapter"     , "", 0xFFFFFF);
            }    
           
            if ( !IPS_VariableProfileExists('GOECHARGER_Voltage') ) {
                IPS_CreateVariableProfile('GOECHARGER_Voltage', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER_Voltage', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_Voltage', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Voltage', "", " V" );
            }   
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Energy.1') ) {
                IPS_CreateVariableProfile('GOECHARGER_Energy.1', 2);
                IPS_SetVariableProfileDigits('GOECHARGER_Energy.1', 1);
                IPS_SetVariableProfileIcon('GOECHARGER_Energy.1', 'Electricity');
            }
            IPS_SetVariableProfileText('GOECHARGER_Energy.1', "", " kWh" );

            
            if ( !IPS_VariableProfileExists('GOECHARGER_Power.1') ) {
                IPS_CreateVariableProfile('GOECHARGER_Power.1', 2);
                IPS_SetVariableProfileDigits('GOECHARGER_Power.1', 1);
                IPS_SetVariableProfileIcon('GOECHARGER_Power.1', 'Electricity');
            }
            IPS_SetVariableProfileText('GOECHARGER_Power.1', "", " kW" );
            
            if ( !IPS_VariableProfileExists('GOECHARGER_CableUnlockMode') ) {
                IPS_CreateVariableProfile('GOECHARGER_CableUnlockMode', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_CableUnlockMode', 'Plug' );
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 0, "verriegelt, wenn Auto angeschlossen", "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 1, "am Ladeende entriegeln", "", 0xFFCC00);
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 2, "immer verriegelt", "", 0xFF0000);
            }  
            
            if ( !IPS_VariableProfileExists('GOECHARGER_AwattarPricezone') ) {
                IPS_CreateVariableProfile('GOECHARGER_AwattarPricezone', 1 );
                IPS_SetVariableProfileAssociation("GOECHARGER_AwattarPricezone", 0, "Österreich", "", 0x000000);
                IPS_SetVariableProfileAssociation("GOECHARGER_AwattarPricezone", 1, "Deutschland", "", 0x000000);
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_ElectricityPriceChargeTill') ) {
                IPS_CreateVariableProfile('GOECHARGER_ElectricityPriceChargeTill', 1 );
                for($i=0; $i<=23; $i++){
                    IPS_SetVariableProfileAssociation("GOECHARGER_ElectricityPriceChargeTill", $i, str_pad($i,2,"0", STR_PAD_LEFT).":00 Uhr", "", 0x000000 );
                }
            }
             
            if ( !IPS_VariableProfileExists('GOECHARGER_ElectricityPriceMinChargeHours') ) {
                IPS_CreateVariableProfile('GOECHARGER_ElectricityPriceMinChargeHours', 1 );
                for($i=0; $i<=23; $i++){
                    IPS_SetVariableProfileAssociation("GOECHARGER_ElectricityPriceMinChargeHours", $i, $i." Stunden", "", 0x000000);
                }
            }
        }
        
        protected function registerVariables() {
            
            //--- Basic Functions -------------------------------------------------------------
            $this->RegisterVariableBoolean("accessState", "Wallbox aktiv","~Switch",11);
            $this->EnableAction("accessState");   
            
            $this->RegisterVariableInteger("status", "Status","GOECHARGER_Status",12);

            $this->RegisterVariableFloat("automaticStop", "Ladeende nach x kwh", "GOECHARGER_AutomaticStop", 13 );
            $this->EnableAction("automaticStop"); 

            $this->RegisterVariableInteger("automaticStopKm", "Ladeende nach Energie für x km", "GOECHARGER_AutomaticStopKM", 14 );
            $this->EnableAction("automaticStopKm"); 
            
            //--- Informations to the current loading cycle ------------------------------------
            $this->RegisterVariableFloat("powerToCarTotal", "Aktuelle Leistung zum Fahrzeug","GOECHARGER_Power.1",31);

            $this->RegisterVariableFloat("energyLoadCycle", "abgegebene Energie im Ladezyklus","GOECHARGER_Energy.1",32);

            $this->RegisterVariableInteger("unlockedByRFID", "entsperrt durch RFID","",33);

            
            //--- Power Consumption information ------------------------------------------------
            $this->RegisterVariableFloat("energyTotal", "bisher abgegebene Energie","GOECHARGER_Energy.1",51);

            for($i=1; $i<=10; $i++){
               $this->RegisterVariableFloat("energyChargedCard".$i, "geladene Energie Karte ".$i,"GOECHARGER_Energy.1",51+$i);
            } 
            
            //--- Setup -----------------------------------------------------------------------
            $this->RegisterVariableInteger("maxAvailableAMP", "max. verfügbarer Ladestrom","GOECHARGER_Ampere",71);
            $this->EnableAction("maxAvailableAMP");

            $this->RegisterVariableInteger("availableAMP", "aktuell verfügbarer Ladestrom","GOECHARGER_Ampere",72);
            $this->EnableAction("availableAMP");

            $this->RegisterVariableInteger("cableUnlockMode", "Kabel-Verriegelungsmodus","GOECHARGER_CableUnlockMode",73);
            $this->EnableAction("cableUnlockMode");

            $this->RegisterVariableInteger("accessControl", "Zugangskontrolle via RFID/App/Strompreis","GOECHARGER_Access",74);
            $this->EnableAction("accessControl");

            $this->RegisterVariableInteger("ledBrightness", "LED Helligkeit","~Intensity.255",75);
            $this->EnableAction("ledBrightness");
            $this->RegisterVariableBoolean("ledEnergySave", "LED Energiesparfunktion","~Switch",76);
            $this->EnableAction("ledEnergySave");            
            
            //--- Ladung mittels Strompreis automatisch
            $this->RegisterVariableInteger("electricityPriceMinChargeHours", "minimale Ladezeit bei Strompreis-basiertem Laden","GOECHARGER_ElectricityPriceMinChargeHours",81);
            $this->EnableAction("electricityPriceMinChargeHours");
            $this->RegisterVariableInteger("electricityPriceChargeTill", "Laden beendet bis bei Strompreis-basiertem Laden","GOECHARGER_ElectricityPriceChargeTill",82);
            $this->EnableAction("electricityPriceChargeTill");
            
            //--- Technical Informations ------------------------------------------------------
            $this->RegisterVariableString("serialID", "Seriennummer","",91);
            $this->RegisterVariableInteger("error", "Fehler","GOECHARGER_Error",92);
            $this->RegisterVariableInteger("rebootCounter", "Reboot Zähler","",93);
            $this->RegisterVariableString("rebootTime", "Reboot Zeitpunkt","",93);
            $this->RegisterVariableInteger("adapterAttached", "angeschlossener Adapter","GOECHARGER_Adapter",95);
            $this->RegisterVariableInteger("cableCapability", "Kabel-Leistungsfähigkeit","GOECHARGER_AmpereCable",96);
            $this->RegisterVariableBoolean("norwayMode", "Erdungsprüfung","~Switch",97);
            $this->RegisterVariableFloat("mainboardTemperature", "Mainboard Temperatur","~Temperature",98);
            $this->RegisterVariableString("availablePhases", "verfügbare Phasen","",99);
            $this->RegisterVariableInteger("supplyLineL1", "Spannungsversorgung L1","GOECHARGER_Voltage",100);
            $this->RegisterVariableInteger("supplyLineL2", "Spannungsversorgung L2","GOECHARGER_Voltage",101);
            $this->RegisterVariableInteger("supplyLineL3", "Spannungsversorgung L3","GOECHARGER_Voltage",102);
            $this->RegisterVariableInteger("supplyLineN", "Spannungsversorgung N","GOECHARGER_Voltage",103);

            //--- Power to Car
            $this->RegisterVariableFloat("powerToCarLineL1", "Leistung zum Fahrzeug L1","GOECHARGER_Power.1",104);
            $this->RegisterVariableFloat("powerToCarLineL2", "Leistung zum Fahrzeug L2","GOECHARGER_Power.1",106);
            $this->RegisterVariableFloat("powerToCarLineL3", "Leistung zum Fahrzeug L3","GOECHARGER_Power.1",108);
            $this->RegisterVariableFloat("powerToCarLineN", "Leistung zum Fahrzeug N","GOECHARGER_Power.1",109);
            $this->RegisterVariableFloat("ampToCarLineL1", "Ampere zum Fahrzeug L1","GOECHARGER_Ampere.1",110);
            $this->RegisterVariableFloat("ampToCarLineL2", "Ampere zum Fahrzeug L2","GOECHARGER_Ampere.1",111);
            $this->RegisterVariableFloat("ampToCarLineL3", "Ampere zum Fahrzeug L3","GOECHARGER_Ampere.1",114);
            $this->RegisterVariableFloat("powerFactorLineL1", "Leistungsfaktor L1","~Humidity.F",115);
            $this->RegisterVariableFloat("powerFactorLineL2", "Leistungsfaktor L2","~Humidity.F",117);
            $this->RegisterVariableFloat("powerFactorLineL3", "Leistungsfaktor L3","~Humidity.F",119);
            $this->RegisterVariableFloat("powerFactorLineN", "Leistungsfaktor N","~Humidity.F",121);
            $this->RegisterVariableFloat("availableSupplyEnergy", "max. verfügbare Ladeleistung","GOECHARGER_Power.1",125);

            if ( $this->ReadPropertyBoolean( "calculateCorrectedData" ) ) {
                //--- Attributes for data correction

                $this->RegisterVariableFloat("correctedPowerToCarLineL1", "korrigierte Leistung zum Fahrzeug L1", "GOECHARGER_Power.1", 105);
                $this->RegisterVariableFloat("correctedPowerToCarLineL2", "korrigierte Leistung zum Fahrzeug L2", "GOECHARGER_Power.1", 107;
                $this->RegisterVariableFloat("correctedPowerToCarLineL3", "korrigierte Leistung zum Fahrzeug L3", "GOECHARGER_Power.1", 109);
                $this->RegisterVariableFloat("correctedPowerFactorLineL1", "korrigierte Leistungsfaktor L1", "~Humidity.F", 116);
                $this->RegisterVariableFloat("correctedPowerFactorLineL2", "korrigierte Leistungsfaktor L2", "~Humidity.F", 118);
                $this->RegisterVariableFloat("correctedPowerFactorLineL3", "korrigierte Leistungsfaktor L3", "~Humidity.F", 120);
                $this->RegisterVariableFloat("correctedAvailableSupplyEnergy", "korrigierte max. verfügbare Ladeleistung", "GOECHARGER_Power.1", 126);
                $this->RegisterVariableFloat( "correctionFactorL1", "Korrekturfaktor L1", "~Humidity.F", 127 );
                $this->RegisterVariableFloat( "correctionFactorL2", "Korrekturfaktor L2", "~Humidity.F", 128 );
                $this->RegisterVariableFloat( "correctionFactorL3", "Korrekturfaktor L3", "~Humidity.F", 129 );
            }

            $this->RegisterVariableInteger("awattarPricezone", "Awattar Preiszone","GOECHARGER_AwattarPricezone",130);
        }
    }
?>