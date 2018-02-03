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
            
          $this->RegisterPropertyString("IPAddressCharger1", "0.0.0.0");  
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
          if (filter_var($IPAddress, FILTER_VALIDATE_IP)) {
            $json = file_get_contents("http://".$IPAddress."/status");  
          //  $goECharger1Status = json_decode($json);
          //  if ( $goECharger1Status === null ) {
          //      $connectionOK = false;
          }   
          else { 
              $connectionOK = false; 
          }
            
          if ( $connectionOK == true ) {
            $this->SetStatus(250);
          } else {
            $this->SetStatus(300);
          }
            
        }
    }
?>
