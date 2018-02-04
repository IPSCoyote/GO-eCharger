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

          // Generate Profiles
          $this->registerProfiles();
          // Generate Variables
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
            
          // get IP of go-eCharger
          $IPAddress = trim($this->ReadPropertyString("IPAddressCharger"));
            
          // check if IP is ocnfigured and valid
          if ( $IPAddress == "0.0.0.0" ) {
              $this->SetStatus(200); // no configuration done
              return;
          } elseif (filter_var($IPAddress, FILTER_VALIDATE_IP) == false) { 
              $this->SetStatus(201); // no valid IP configured
              return;
          }
            
          // check if any HHTP device on IP can be reached
          if ( $this->ping( $IPAddress, 80, 1 ) == false ) {
              $this->SetStatus(202); // no http response
              return;
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
              return;
          };
            
          $goEChargerStatus = json_decode($json);
          if ( $goEChargerStatus === null ) {
              $this->SetStatus(203); // no http response
              return;
          } elseif ( isset( $goEChargerStatus->{'sse'} ) == false ) {
              $this->SetStatus(204); // no go-eCharger
              return;
          }   
            
          // so from here, $goEChargerStatus is the valid Status JSON from eCharger
          $this->SetStatus(102); // active as go-eCharger found
       
          // write values into variables
          SetValue($this->GetIDForIdent("SerialID"), $goEChargerStatus->{'sse'});     
            
            
        }
        
        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOeCharger_* 
        *
        * GOeCharger_CheckConnection($id);
        *
        */
        
        protected function ping($host, $port, $timeout) 
        { 
          $this->sendDebug( "go-eCharger", "ping( ".$host.", ".$port." )", 0 );  
          ob_start();
          $fP = fSockOpen($host, $port, $errno, $errstr, $timeout); 
          ob_clean();
          if (!$fP) { $this->sendDebug( "go-eCharger", "ping failed", 0 ); return false; } 
          return true; 
        }
        
        protected function registerProfiles() {
            // Generate Variable Profiles
            if ( !IPS_VariableProfileExists('GOECHARGER_Ampere') ) {
                IPS_CreateVariableProfile('GOECHARGER_Ampere', 1 );
                IPS_SetVariableProfileDigits('GOECHARGER', 0 );
                IPS_SetVariableProfileIcon('GOECHARGER', 'Electricity' );
                IPS_SetVariableProfileText('GOECHARGER', "", " A" );
            }
        }
        
        protected function registerVariables() {
            // Generate Variables
            if ( $this->GetIDForIdent("SerialID") == false ){
              $this->RegisterVariableString("SerialID", "Seriennummer","~String",0);
            }
            if ( $this->GetIDForIdent("CurrentAMP") == false ){
              $this->RegisterVariableString("CurrentAMP", "derzeit verfügbarer Ladestrom","GOECHARGER_Ampere",0);
            }
        }
    }
        
    }
?>
