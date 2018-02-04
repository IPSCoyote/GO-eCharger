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
            
          // Properties Charger 1
          $this->RegisterPropertyString("IPAddressCharger", "0.0.0.0");  
          $this->RegisterPropertyString("MaxAmpCharger","6");   
        }
 
        public function ApplyChanges() {
          /* Called on 'apply changes' in the configuration UI and after creation of the instance */
          parent::ApplyChanges();
            
          $this->sendDebug( "go-eCharger", "Apply", 0 );  

          // Generate Profiles & Variables
          $this->registerProfiles();
          $this->registerVariables();

          // Update Data to Variables
          $this->Update();
        }

        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOeCharger_* 
        *
        * GOeCharger_CheckConnection($id);
        *
        */
        
        public function Update() {
          /* Check the connection to the go-eCharger */
          $this->sendDebug( "go-eCharger", "Update()", 0 );  
            
          $goEChargerStatus = $this->getStatusFromCharger();
            
          if ( $goEChargerStatus == false ) { return false; }
       
          // write values into variables
          SetValue($this->GetIDForIdent("serialID"), $goEChargerStatus->{'sse'});  
          SetValue($this->GetIDForIdent("error"), ( $goEChargerStatus->{'err'} == 0 ) ); 
          SetValue($this->GetIDForIdent("availableAMP"), $goEChargerStatus->{'amp'} ); 
          SetValue($this->GetIDForIdent("maxAvailableAMP"), $goEChargerStatus->{'ama'}); 
            
          $goEChargerEnergy = $goEChargerStatus->{'nrg'};
          SetValue($this->GetIDForIdent("availableVP1"), $goEChargerEnergy[0]);            
          SetValue($this->GetIDForIdent("availableVP2"), $goEChargerEnergy[1]);            
          SetValue($this->GetIDForIdent("availableVP3"), $goEChargerEnergy[2]);  
            
          $availableKW = ( ( ( $goEChargerEnergy[0] + $goEChargerEnergy[1] + $goEChargerEnergy[2] ) / 3 ) * 3 * $goEChargerStatus->{'amp'} ) / 1000;
          SetValue($this->GetIDForIdent("availableKW"), $availableKW);  
            
          return true;
        }
       
        public function SetCurrentAmpere(int $Ampere, boolean $orMaximum ) {
            // Check input value
            if ( $Ampere < 6 or $Ampere > 32 ) { return false };
            
            // Check requested Ampere is <= max Ampere set in Instance
            $goEChargerStatus = $this->getStatusFromCharger();
            if ( $goEChargerStatus == false ) { return false; }
            
            $setAmpere = $Ampere;
            if ( $setAmpere > $goEChargerStatus->{'ama'} ) {
                if ( $orMaximum == true ) { 
                    $setAmpere = $goEChargerStatus->{'ama'}; 
                } else {
                    return false;
                }
            }
            
            // set current available Ampere
            $this->setValueToeCharger( 'amp', $setAmpere );  
            
            $this->Update();
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
            if ( !IPS_VariableProfileExists('GOECHARGER_Ampere') ) {
                $profileID = IPS_CreateVariableProfile('GOECHARGER_Ampere', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER_Ampere', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_Ampere', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Ampere', "", " A" );
            }
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Voltage') ) {
                $profileID = IPS_CreateVariableProfile('GOECHARGER_Voltage', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER_Voltage', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_Voltage', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Voltage', "", " V" );
            }   
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Kilowatt') ) {
                $profileID = IPS_CreateVariableProfile('GOECHARGER_Kilowatt', 2 );
                IPS_SetVariableProfileDigits('GOECHARGER_Kilowatt', 2 );
                IPS_SetVariableProfileIcon('GOECHARGER_Kilowatt', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER_Kilowatt', "", " kw" );
            }    
            
            if ( !IPS_VariableProfileExists('GOECHARGER_Error') ) {
                $profileID = IPS_CreateVariableProfile('GOECHARGER_Error', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER_Error', 'Ok' );
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", true, "Ok",     "", 0x00FF00);
                IPS_SetVariableProfileAssociation("GOECHARGER_Error", false, "Fehler", "", 0xFF0000);
            }  
    
        }
        
        protected function registerVariables() {
            // Generate Variables
            if ( $this->GetIDForIdent("serialID") == false ) {
              $this->RegisterVariableString("serialID", "Seriennummer","~String",0);
            }
            if ( $this->GetIDForIdent("error") == false ) {
              $this->RegisterVariableBoolean("error", "Zustand","GOECHARGER_Error",0);
            }
            
            if ( $this->GetIDForIdent("availableAMP") == false ) {
              $this->RegisterVariableInteger("availableAMP", "derzeit verfügbarer Ladestrom","GOECHARGER_Ampere",1);
            }
            if ( $this->GetIDForIdent("maxAvailableAMP") == false ) {
              $this->RegisterVariableInteger("maxAvailableAMP", "maximal verfügbarer Ladestrom","GOECHARGER_Ampere",1);
            }
            if ( $this->GetIDForIdent("availableVP1") == false ) {
              $this->RegisterVariableInteger("availableVP1", "verfügbare Spannung Phase 1","GOECHARGER_Voltage",3);
            }
            if ( $this->GetIDForIdent("availableVP2") == false ) {
              $this->RegisterVariableInteger("availableVP2", "verfügbare Spannung Phase 2","GOECHARGER_Voltage",3);
            }
            if ( $this->GetIDForIdent("availableVP3") == false ) {
              $this->RegisterVariableInteger("availableVP3", "verfügbare Spannung Phase 3","GOECHARGER_Voltage",3);
            }
            if ( $this->GetIDForIdent("availableKW") == false ) {
              $this->RegisterVariableFloat("availableKW", "max. verfügbare Ladeleistung","GOECHARGER_Kilowatt",2);
            }           
            
        }
    }
?>
