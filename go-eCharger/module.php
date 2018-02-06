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
                      
          // Timer
          //$this->RegisterTimer("GOeChargerTimer_UpdateTimer", 0, "GOeCharger_Update($_IPS[\'TARGET\']);");
        }
 
        public function ApplyChanges() {
          /* Called on 'apply changes' in the configuration UI and after creation of the instance */
          parent::ApplyChanges();
            
          $this->sendDebug( "go-eCharger", "Apply", 0 );  

          // Set Data to Variables
          // $this->Update();
        }
        
    public function Destroy()
      {
      $this->UnregisterTimer("GOeChargerTimer_UpdateTimer");
       
      //Never delete this line!
      parent::Destroy();
      }
        
        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOeCharger_* 
        *
        * GOeCharger_CheckConnection($id);
        *
        */
        
        public function Update() {
   
        }
       
        public function SetMaximumChargingAmperage(int $Ampere) {
            // Check input value
            if ( $Ampere < 6 or $Ampere > 32 ) { return false; }
            if ( $Ampere > $this->ReadPropertyInteger("MaxAmperage") ) { return false; }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }

            // first calculate the Button values
            $button[0] = 6; // min. Value
            $gaps = round( ( ( $Ampere - 6 ) / 4 ) - 0.5 );
            $button[1] = $button[0] + $gaps;
            $button[2] = $button[1] + $gaps;
            $button[3] = $button[2] + $gaps;
            $button[4] = $Ampere; // max. Value

            // set values to Charger
            // set button values
            $this->setValueToeCharger( 'al1', $button[0] );
            $this->setValueToeCharger( 'al2', $button[1] );
            $this->setValueToeCharger( 'al3', $button[2] );
            $this->setValueToeCharger( 'al4', $button[3] );
            $this->setValueToeCharger( 'al5', $button[4] );

            // set max available Ampere
            $goEChargerStatus = $this->setValueToeCharger( 'ama', $Ampere );

            // set current available Ampere (if too high)
            if ( $goEChargerStatus->{'amp'} > $goEChargerStatus->{'ama'} ) {
              // set current available to max. available, as current was higher than new max.
              $goEChargerStatus = $this->setValueToeCharger( 'amp', $goEChargerStatus->{'ama'} );
            }  

            $this->Update();
            
            if ( $goEChargerStatus->{'ama'} == $Ampere ) { return true; } else { return false; }
        }
        
        public function SetCurrentChargingAmperage(int $Ampere) {
            // Check input value
            if ( $Ampere < 6 or $Ampere > 32 ) { return false; }
            if ( $Ampere > $this->ReadPropertyInteger("MaxAmperage") ) { return false; }
            
            // get current settings of goECharger
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            
            // Check requested Ampere is <= max Ampere set in Instance
            if ( $Ampere > $goEChargerStatus->{'ama'} ) { return false; }
                                 
            // set current available Ampere
            $resultStatus = $this->setValueToeCharger( 'amp', $Ampere ); 
            
            // Update all data
            $this->Update();
            
            if ( $resultStatus->{'amp'} == $Ampere ) { return true; } else { return false; }
        }
        
        public function SetAccessControll(bool $active) {
            if ( $active == true ) { $value = 1; } else { $value = 0; }
            $resultStatus = $this->setValueToeCharger( 'amp', $value ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'ast'} == $value ) { return true; } else { return false; }
        }
            
        public function SetAutomaticChargeStop(int $chargeStopKW) {
            if ( $chargeStopKW < 0 or $chargeStopKW > 100 ) { return false; }
            $value = number_format( $chargeStopKW*10, 0, '', '' );
            $resultStatus = $this->setValueToeCharger( 'dwo', $value ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'dwo'} == $value ) { return true; } else { return false; }
        }
            
        public function SetCableUnlockMode(int $unlockMode) {
            if ( $unlockMode < 0 or $unlockMode > 2 ) { return false; }
            $resultStatus = $this->setValueToeCharger( 'ust', $unlockMode ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'ust'} == $unlockMode ) { return true; } else { return false; }
        }
        
        public function SetActivation(bool $active) {
            if ( $active == true ) { $value = 1; } else { $value = 0; }
            $resultStatus = $this->setValueToeCharger( 'alw', $value ); 
            // Update all data
            $this->Update();
            if ( $resultStatus->{'alw'} == $value ) { return true; } else { return false; }
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
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 1, "bereit zum Laden"    , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 2, "ladend"              , "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("GOECHARGER_Status", 3, "warten auf Fahrzeug" , "", 0xFFFFFF);
            }    
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Error') ) {
                IPS_CreateVariableProfile('GOECHARGER_Error', 1 );
                IPS_SetVariableProfileIcon('GOECHARGER_Error', 'Ok' );
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 0,  "Ok"               , "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 1,  "FI Schutzschalter", "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 3,  "Fehler an Phase"  , "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 8,  "Keine Erdung"     , "", 0xFF0000);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", 10, "Interner Fehler"  , "", 0xFF0000);
            } 
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Access') ) {
                IPS_CreateVariableProfile('GOECHARGER_Access', 1 );
                IPS_SetVariableProfileAssociation("GOECHARGER_Access", 0, "frei zugänglich"     , "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_Access", 1, "RFID Identifizierung", "", 0xFF0000);
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
            // Generate Variables
            if ( $this->GetIDForIdent("status") == false ) {
              $this->RegisterVariableInteger("status", "Status","GOECHARGER_Status",0);
            }
            
            if ( $this->GetIDForIdent("availableAMP") == false ) {
              $this->RegisterVariableInteger("availableAMP", "aktuell verfügbarer Ladestrom","GOECHARGER_Ampere",0);
            }  
            
            if ( $this->GetIDForIdent("error") == false ) {
              $this->RegisterVariableInteger("error", "Fehler","GOECHARGER_Error",0);
            }
            
            if ( $this->GetIDForIdent("accessControl") == false ) {
              $this->RegisterVariableInteger("accessControl", "Zugangskontrolle via RFID/App","GOECHARGER_Access",0);
            }  
            
            if ( $this->GetIDForIdent("accessState") == false ) {
              $this->RegisterVariableBoolean("activation", "Wallbox aktiv","~Switch",0);
            }  
            
            if ( $this->GetIDForIdent("cableCapability") == false ) {
              $this->RegisterVariableInteger("cableCapability", "Kabel-Leistungsfähigkeit","GOECHARGER_AmpereCable",0);
            }  
            
            if ( $this->GetIDForIdent("numberOfPhases") == false ) {
              $this->RegisterVariableInteger("numberOfPhases", "Anzahl Phasen","",0);
            }  
            
            if ( $this->GetIDForIdent("mainboardTemperature") == false ) {
              $this->RegisterVariableFloat("mainboardTemperature", "Mainboard Temperatur","~Temperature",0);
            }  
            
            if ( $this->GetIDForIdent("automaticStop") == false ) {
              $this->RegisterVariableFloat("automaticStop", "Ladeende bei Akkustand (0kw = deaktiviert)", "GOECHARGER_AutomaticStop", 0 );
            }
            
            if ( $this->GetIDForIdent("adapterAttached") == false ) {
              $this->RegisterVariableInteger("adapterAttached", "angeschlossener Adapter","GOECHARGER_Adapter",0);
            } 
            
            if ( $this->GetIDForIdent("unlockedByRFID") == false ) {
              $this->RegisterVariableInteger("unlockedByRFID", "entsperrt durch RFID","",0);
            } 
            
            if ( $this->GetIDForIdent("energyTotal") == false ) {
              $this->RegisterVariableFloat("energyTotal", "bisher geladene Energie","GOECHARGER_Power.1",0);
            } 
            
            if ( $this->GetIDForIdent("leadVP1") == false ) {
              $this->RegisterVariableInteger("availableVP1", "Spannungsversorgung L1","GOECHARGER_Voltage",0);
            }
            
            if ( $this->GetIDForIdent("leadVP2") == false ) {
              $this->RegisterVariableInteger("availableVP2", "Spannungsversorgung L2","GOECHARGER_Voltage",0);
            }
            
            if ( $this->GetIDForIdent("leadP3") == false ) {
              $this->RegisterVariableInteger("availableVP3", "Spannungsversorgung L3","GOECHARGER_Voltage",0);
            }
            
            if ( $this->GetIDForIdent("leadN") == false ) {
              $this->RegisterVariableInteger("leadN", "Spannungsversorgung N","GOECHARGER_Voltage",0);
            }
            
            if ( $this->GetIDForIdent("availableLeadEnergy") == false ) {
              $this->RegisterVariableFloat("availableLeadEnergy", "max. verfügbare Ladeleistung","GOECHARGER_Energy.1",0);
            }    
            
            if ( $this->GetIDForIdent("serialID") == false ) {
              $this->RegisterVariableString("serialID", "Seriennummer","~String",0);
            }

            if ( $this->GetIDForIdent("ledBrightness") == false ) {
              $this->RegisterVariableInteger("ledBrightness", "LED Helligkeit","~Intensity.255",0);
            }
            
            if ( $this->GetIDForIdent("maxAvailableAMP") == false ) {
              $this->RegisterVariableInteger("maxAvailableAMP", "max. verfügbarer Ladestrom","GOECHARGER_Ampere",0);
            }
                   
            if ( $this->GetIDForIdent("cableUnlockMode") == false ) {
              $this->RegisterVariableInteger("cableUnlockMode", "Kabel-Verriegelungsmodus","GOECHARGER_CableUnlockMode",0);
            }    
            
            if ( $this->GetIDForIdent("norwayMode") == false ) {
              $this->RegisterVariableBoolean("norwayMode", "Erdungsprüfung","~Switch",0);
            }  
            
            for($i=1; $i<=10; $i++){
              if ( $this->GetIDForIdent("energyChargedCard".$i) == false ) {
                $this->RegisterVariableFloat("energyChargedCard".$i, "geladene Energie Karte ".$i,"GOECHARGER_Power.1",0);
              }    
            }
        }
    }
?>
