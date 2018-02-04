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
          $this->RegisterPropertyString("IPAddressCharger1", "0.0.0.0");  
          $this->RegisterPropertyString("MaxAmpCharger1","6");
            
          // Properties Charger 2
          $this->RegisterPropertyString("IPAddressCharger2", "0.0.0.0");  
          $this->RegisterPropertyString("MaxAmpCharger2","6");
        }
 
        public function ApplyChanges() {
          /* Called on 'apply changes' in the configuration UI and after creation of the instance */
          parent::ApplyChanges();
            
          $this->sendDebug( "go-eCharger", "Apply", 0 );  
            
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
            
          $IPAddress = trim($this->ReadPropertyString("IPAddressCharger1"));
            
          if ( $IPAddress == "0.0.0.0" ) {
              $this->SetStatus(200); // no configuration done
              return;
          } elseif (filter_var($IPAddress, FILTER_VALIDATE_IP) == false) { 
              $this->SetStatus(201); // no valid IP configured
              return;
          }
           
          // check, if go-eChargers are there...
          $connectionOK = true;
            
          // get Status for go-eCharger 1
          if (filter_var($IPAddress, FILTER_VALIDATE_IP) and $this->ping( $IPAddress, 80, 1 )) {
            try {  
                $ch    = curl_init("http://".$IPAddress."/status"); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
                curl_setopt($ch, CURLOPT_HEADER, 0); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                $json = curl_exec($ch); 
                curl_close ($ch);  
            } catch (Exception $e) { 
                $connectionOK = false;
            };
            $goECharger1Status = json_decode($json);
            if ( $goECharger1Status === null ) {
                $connectionOK = false;
            } elseif ( isset( $goECharger1Status->{'sse'} ) !== true )
                $connectionOK = false;
          }   
          else { 
              $this->sendDebug( "go-eCharger", "ip invalid", 0 );
              $connectionOK = false; 
          }
            
          if ( $connectionOK == true ) {
            $this->SetStatus(102);
          } else {
            $this->SetStatus(300);
          }
            
        }
        
        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOeCharger_* 
        *
        * GOeCharger_CheckConnection($id);
        *
        */
        
        function ping($host, $port, $timeout) 
        { 
          ob_start();
          $fP = fSockOpen($host, $port, $errno, $errstr, $timeout); 
          ob_clean();
          if (!$fP) { return false; } 
          return true; 
        }
        
    }
?>
