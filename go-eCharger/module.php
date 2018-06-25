<?
    class go_eCharger extends IPSModule {
 
        public function __construct($InstanceID) {
          /* Constructor is called before each function call */
          parent::__construct($InstanceID);
        }
        
        public function Create() {
          /* Create is called ONCE on Instance creation and start of IP-Symcon.
             Status-Variables und Modul-Properties for permanent usage should be created here  */
          parent::Create(); 
            
          // Properties Charger
          $this->RegisterPropertyString("IPAddressCharger", "0.0.0.0"); 
          $this->RegisterPropertyInteger("MaxAmperage", 6);
          $this->RegisterPropertyInteger("UpdateIdle", 0);  
          $this->RegisterPropertyInteger("UpdateCharging",0); 
          $this->RegisterPropertyBoolean("AutoReactivate",false); 
          $this->RegisterPropertyBoolean("AutoActivateOnStopSet",false);
          $this->RegisterPropertyFloat("AverageConsumption",0);
          $this->RegisterPropertyFloat("MaxLoadKw",0);
            
          // Timer
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
            $this->UnregisterTimer("GOeChargerTimer_UpdateTimer");
            // Never delete this line!
            parent::Destroy();
        }
        
        //=== Modul Funktionen =========================================================================================
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
            return $goEChargerEnergy[11]/100; 
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
            if ( $ampere < 6 or $ampere > 32 ) { return false; }
            if ( $ampere > $this->ReadPropertyInteger("MaxAmperage") ) { return false; }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }

            // first calculate the Button values
            $button[0] = 6; // min. Value
            $gaps = round( ( ( $ampere - 6 ) / 4 ) - 0.5 );
            $button[1] = $button[0] + $gaps;
            $button[2] = $button[1] + $gaps;
            $button[3] = $button[2] + $gaps;
            $button[4] = $ampere; // max. Value

            // set values to Charger
            // set button values
            $this->setValueToeCharger( 'al1', $button[0] );
            $this->setValueToeCharger( 'al2', $button[1] );
            $this->setValueToeCharger( 'al3', $button[2] );
            $this->setValueToeCharger( 'al4', $button[3] );
            $this->setValueToeCharger( 'al5', $button[4] );

            // set max available Ampere
            $goEChargerStatus = $this->setValueToeCharger( 'ama', $ampere );

            // set current available Ampere (if too high)
            if ( $goEChargerStatus->{'amp'} > $goEChargerStatus->{'ama'} ) {
              // set current available to max. available, as current was higher than new max.
              $goEChargerStatus = $this->setValueToeCharger( 'amp', $goEChargerStatus->{'ama'} );
            }  

            $this->Update();
            
            if ( $goEChargerStatus->{'ama'} == $ampere ) { return true; } else { return false; }
        }
        
        public function getCurrentChargingAmperage() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'amp'}; 
        }
        
        public function setCurrentChargingAmperage(int $ampere) {
            // Check input value
            if ( $ampere < 6 or $ampere > 32 ) { return false; }
            if ( $ampere > $this->ReadPropertyInteger("MaxAmperage") ) { return false; }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            
            // Check requested Ampere is <= max Ampere set in Instance
            if ( $ampere > $goEChargerStatus->{'ama'} ) { return false; }
                                 
            // set current available Ampere
            $resultStatus = $this->setValueToeCharger( 'amp', $ampere ); 
            
            // Update all data
            $this->Update();
            
            if ( $resultStatus->{'amp'} == $ampere ) { return true; } else { return false; }
        }

        public function isAccessControlActive() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            if ( $goEChargerStatus->{'ast'} == '1' ) { return true; } else { return false; } 
        }
        
        public function setAccessControlActive(bool $active) {
            if ( $active == true ) { $value = 1; } else { $value = 0; }
            $resultStatus = $this->setValueToeCharger( 'ast', $value ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'ast'} == $$value ) { return true; } else { return false; }
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
        
        public function isElectricallyGroundedCheck() { 
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            if ( $goEChargerStatus->{'nmo'} == '1' ) { return true; } else { return false; } 
        }
        
        public function getError() { 
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'err'}; 
        }
        
        public function getStatus() { 
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'car'}; 
        }
        
        public function getCableCapability() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'cbl'}; 
        }
        
        public function getAvailabilityOfPhases() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'pha'}; 
        }
        
        public function getMainboardTemperature() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'tmp'}; 
        }
        
        public function getUnlockRFID() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'uby'}; 
        }
        
        public function getSupplyLineVoltage(int $line) {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            switch ( $line ){
                case 1: 
                    return $goEChargerEnergy[0]; 
                    break;
                case 2:
                    return $goEChargerEnergy[1]; 
                    break;
                case 3: 
                    return $goEChargerEnergy[2]; 
                    break;
                case 4: 
                    return $goEChargerEnergy[3]; 
                    break;
            }
            return false; 
        }

        public function getSupplyLineEnergy() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            $availableEnergy = ( ( ( $goEChargerEnergy[0] + $goEChargerEnergy[1] + $goEChargerEnergy[2] ) / 3 ) * 3 * $goEChargerStatus->{'amp'} ) / 1000;
            return $availableEnergy; 
        }

        public function getAmpToCarByLine(int $line) {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            switch ( $line ){
                case 1: 
                    return $goEChargerEnergy[4]/100; 
                    break;
                case 2:
                    return $goEChargerEnergy[5]/100; 
                    break;
                case 3: 
                    return $goEChargerEnergy[6]/100; 
                    break;
            }
            return false; 
        }  
        
        public function getPowerToCarByLine(int $line) {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            switch ( $line ){
                case 1: 
                    return $goEChargerEnergy[7]/10; 
                    break;
                case 2:
                    return $goEChargerEnergy[8]/10; 
                    break;
                case 3: 
                    return $goEChargerEnergy[9]/10; 
                    break;
                case 4: 
                    return $goEChargerEnergy[10]/10; 
                    break;
            }
            return false; 
        }
        
        public function getTotalPowerToCar() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            return $goEChargerEnergy[11]/100; 
        }
        
        public function getPowerFactorByLine(int $line) {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            $goEChargerEnergy = $goEChargerStatus->{'nrg'};
            switch ( $line ){
                case 1: 
                    return $goEChargerEnergy[12]; 
                    break;
                case 2:
                    return $goEChargerEnergy[13]; 
                    break;
                case 3: 
                    return $goEChargerEnergy[14]; 
                    break;
                case 4: 
                    return $goEChargerEnergy[15]; 
                    break;
            }
            return false; 
        }  
        
        public function getSerialID() {
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            return $goEChargerStatus->{'sse'}; 
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
                IPS_SetVariableProfileValues('GOECHARGER_AutomaticStopKM', 0, 300, 5 );
                IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", 0, "deaktiviert", "", 0xFFFFFF);
                for($i=1; $i<=300; $i++){
                    IPS_SetVariableProfileAssociation("GOECHARGER_AutomaticStopKM", $i, number_format($i, 0)." km", "", 0xFF0000);
                }
                IPS_SetVariableProfileText('GOECHARGER_AutomaticStopKM', "", "" );
            }
            if ( IPS_VariableProfileExists('GOECHARGER_AutomaticStopKM') ) {
                if ( $this->ReadPropertyFloat("MaxLoadKw") > 0  and
                     $this->ReadPropertyFloat("AverageConsumption") > 0 ) {
                    $Km = Round($this->ReadPropertyFloat("MaxLoadKw")/$this->ReadPropertyFloat("AverageConsumption")*100,0);
                    $maxKm = round($Km/5,0)*5;
                    IPS_SetVariableProfileValues('GOECHARGER_AutomaticStopKM', 0, $maxKm, 5 );
                }
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
                IPS_CreateVariableProfile('GOECHARGER_Energy.1', 2 );
                IPS_SetVariableProfileDigits('GOECHARGER_Energy.1', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Energy.1', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Energy.1', "", " kw" );
            }   
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Power.1') ) {
                IPS_CreateVariableProfile('GOECHARGER_Power.1', 2 );
                IPS_SetVariableProfileDigits('GOECHARGER_Power.1', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Power.1', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Power.1', "", " kwh" );
            }   
            
            if ( !IPS_VariableProfileExists('GOECHARGER_CableUnlockMode') ) {
                IPS_CreateVariableProfile('GOECHARGER_CableUnlockMode', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_CableUnlockMode', 'Plug' );
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 0, "verriegelt, wenn Auto angeschlossen", "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 1, "am Ladeende entriegeln", "", 0xFFCC00);
                IPS_SetVariableProfileAssociation("GOECHARGER_CableUnlockMode", 2, "immer verriegelt", "", 0xFF0000);
            }  
        }
        
        protected function registerVariables() {
            
            //--- Basic Functions -------------------------------------------------------------
            if ( $this->GetIDForIdent("accessState") == false ) {
                $this->RegisterVariableBoolean("accessState", "Wallbox aktiv","~Switch",11);
                $this->EnableAction("accessState");  
            }  
            
            if ( $this->GetIDForIdent("status") == false ) {
                $this->RegisterVariableInteger("status", "Status","GOECHARGER_Status",12);
            }
            
            if ( $this->GetIDForIdent("automaticStop") == false ) {
                $this->RegisterVariableFloat("automaticStop", "Ladeende nach x kwh", "GOECHARGER_AutomaticStop", 13 );
                $this->EnableAction("automaticStop"); 
            }
            
            if ( $this->GetIDForIdent("automaticStopKm") == false ) {
                $this->RegisterVariableInteger("automaticStopKm", "Ladeende nach Energie für x km", "GOECHARGER_AutomaticStopKM", 14 );
                $this->EnableAction("automaticStopKm"); 
            }
            
            //--- Informations to the current loading cycle ------------------------------------
            if ( $this->GetIDForIdent("powerToCarTotal") == false ) {
                $this->RegisterVariableFloat("powerToCarTotal", "Aktuelle Leistung zum Fahrzeug","GOECHARGER_Power.1",31);
            }  
            
            if ( $this->GetIDForIdent("energyLoadCycle") == false ) {
                $this->RegisterVariableFloat("energyLoadCycle", "abgegebene Energie im Ladezyklus","GOECHARGER_Power.1",32);
            } 
            
            if ( $this->GetIDForIdent("unlockedByRFID") == false ) {
                $this->RegisterVariableInteger("unlockedByRFID", "entsperrt durch RFID","",33);
            } 
            
            //--- Power Consumption information ------------------------------------------------
            if ( $this->GetIDForIdent("energyTotal") == false ) {
                $this->RegisterVariableFloat("energyTotal", "bisher abgegebene Energie","GOECHARGER_Power.1",51);
            }    
            
            for($i=1; $i<=10; $i++){
                if ( $this->GetIDForIdent("energyChargedCard".$i) == false ) {
                    $this->RegisterVariableFloat("energyChargedCard".$i, "geladene Energie Karte ".$i,"GOECHARGER_Power.1",51+$i);
                }    
            } 
            
            //--- Setup -----------------------------------------------------------------------
            if ( $this->GetIDForIdent("maxAvailableAMP") == false ) {
                $this->RegisterVariableInteger("maxAvailableAMP", "max. verfügbarer Ladestrom","GOECHARGER_Ampere",71);
                $this->EnableAction("maxAvailableAMP");
            }
            
            if ( $this->GetIDForIdent("availableAMP") == false ) {
                $this->RegisterVariableInteger("availableAMP", "aktuell verfügbarer Ladestrom","GOECHARGER_Ampere",72);
                $this->EnableAction("availableAMP");
            }  
            
            if ( $this->GetIDForIdent("cableUnlockMode") == false ) {
                $this->RegisterVariableInteger("cableUnlockMode", "Kabel-Verriegelungsmodus","GOECHARGER_CableUnlockMode",73);
                $this->EnableAction("cableUnlockMode");
            } 
            
            if ( $this->GetIDForIdent("accessControl") == false ) {
                $this->RegisterVariableInteger("accessControl", "Zugangskontrolle via RFID/App","GOECHARGER_Access",74);
                $this->EnableAction("accessControl");
            }  
            
            if ( $this->GetIDForIdent("ledBrightness") == false ) {
                $this->RegisterVariableInteger("ledBrightness", "LED Helligkeit","~Intensity.255",75);
                $this->EnableAction("ledBrightness");
            }
            
            //--- Technical Informations ------------------------------------------------------
            if ( $this->GetIDForIdent("serialID") == false ) {
                $this->RegisterVariableString("serialID", "Seriennummer","~String",91);
            }
            
            if ( $this->GetIDForIdent("error") == false ) {
                $this->RegisterVariableInteger("error", "Fehler","GOECHARGER_Error",92);
            }
            
            if ( $this->GetIDForIdent("rebootCounter") == false ) {
                $this->RegisterVariableInteger("rebootCounter", "Reboot Zähler","",93);
            }

            if ( $this->GetIDForIdent("rebootTime") == false ) {
                $this->RegisterVariableString("rebootTime", "Reboot Zeitpunkt","",93);
            }
            
            if ( $this->GetIDForIdent("adapterAttached") == false ) {
                $this->RegisterVariableInteger("adapterAttached", "angeschlossener Adapter","GOECHARGER_Adapter",95);
            } 
            
            if ( $this->GetIDForIdent("cableCapability") == false ) {
                $this->RegisterVariableInteger("cableCapability", "Kabel-Leistungsfähigkeit","GOECHARGER_AmpereCable",96);
            }  
            
            if ( $this->GetIDForIdent("norwayMode") == false ) {
                $this->RegisterVariableBoolean("norwayMode", "Erdungsprüfung","~Switch",97);
            }  
            
            if ( $this->GetIDForIdent("mainboardTemperature") == false ) {
                $this->RegisterVariableFloat("mainboardTemperature", "Mainboard Temperatur","~Temperature",98);
            }  
            
            if ( $this->GetIDForIdent("availablePhases") == false ) {
                $this->RegisterVariableString("availablePhases", "verfügbare Phasen","~String",99);
            }  

            if ( $this->GetIDForIdent("supplyLineL1") == false ) {
                $this->RegisterVariableInteger("supplyLineL1", "Spannungsversorgung L1","GOECHARGER_Voltage",100);
            }
            
            if ( $this->GetIDForIdent("supplyLineL2") == false ) {
                $this->RegisterVariableInteger("supplyLineL2", "Spannungsversorgung L2","GOECHARGER_Voltage",101);
            }
            
            if ( $this->GetIDForIdent("supplyLineL3") == false ) {
                $this->RegisterVariableInteger("supplyLineL3", "Spannungsversorgung L3","GOECHARGER_Voltage",102);
            }
            
            if ( $this->GetIDForIdent("supplyLineN") == false ) {
                $this->RegisterVariableInteger("supplyLineN", "Spannungsversorgung N","GOECHARGER_Voltage",103);
            }
            
            if ( $this->GetIDForIdent("powerToCarLineL1") == false ) {
                $this->RegisterVariableFloat("powerToCarLineL1", "Leistung zum Fahrzeug L1","GOECHARGER_Power.1",104);
            }
            
            if ( $this->GetIDForIdent("powerToCarLineL2") == false ) {
                $this->RegisterVariableFloat("powerToCarLineL2", "Leistung zum Fahrzeug L2","GOECHARGER_Power.1",105);
            }
            
            if ( $this->GetIDForIdent("powerToCarLineL3") == false ) {
                $this->RegisterVariableFloat("powerToCarLineL3", "Leistung zum Fahrzeug L3","GOECHARGER_Power.1",106);
            }
            
            if ( $this->GetIDForIdent("powerToCarLineN") == false ) {
                $this->RegisterVariableFloat("powerToCarLineN", "Leistung zum Fahrzeug N","GOECHARGER_Power.1",107);
            }
            
            if ( $this->GetIDForIdent("ampToCarLineL1") == false ) {
                $this->RegisterVariableFloat("ampToCarLineL1", "Ampere zum Fahrzeug L1","GOECHARGER_Ampere.1",108);
            }
            
            if ( $this->GetIDForIdent("ampToCarLineL2") == false ) {
                $this->RegisterVariableFloat("ampToCarLineL2", "Ampere zum Fahrzeug L2","GOECHARGER_Ampere.1",109);
            }
            
            if ( $this->GetIDForIdent("ampToCarLineL3") == false ) {
                $this->RegisterVariableFloat("ampToCarLineL3", "Ampere zum Fahrzeug L3","GOECHARGER_Ampere.1",110);
            } 
            
            if ( $this->GetIDForIdent("powerFactorLineL1") == false ) {
                $this->RegisterVariableFloat("powerFactorLineL1", "Leistungsfaktor L1","~Humidity.F",111);
            }
            
            if ( $this->GetIDForIdent("powerFactorLineL2") == false ) {
                $this->RegisterVariableFloat("powerFactorLineL2", "Leistungsfaktor L2","~Humidity.F",112);
            }
            
            if ( $this->GetIDForIdent("powerFactorLineL3") == false ) {
                $this->RegisterVariableFloat("powerFactorLineL3", "Leistungsfaktor L3","~Humidity.F",113);
            }
            
            if ( $this->GetIDForIdent("powerFactorLineN") == false ) {
                $this->RegisterVariableFloat("powerFactorLineN", "Leistungsfaktor N","~Humidity.F",114);
            }
            
            if ( $this->GetIDForIdent("availableSupplyEnergy") == false ) {
                $this->RegisterVariableFloat("availableSupplyEnergy", "max. verfügbare Ladeleistung","GOECHARGER_Energy.1",115);
            }    
        }
    }
?>
