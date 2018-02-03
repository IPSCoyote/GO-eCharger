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
            
          $IPAddress = $this->ReadPropertyString("IPAddressCharger1");
          $connectionOK = true;
            
          // get Status for go-eCharger 1
          $json = file_get_contents("http://".$IPAddress."/status");  
          if ( $json !== false ) {
            $goECharger1Status = json_decode($json);
              
            if ( isset( $goECharger1Status['sse'] ) == false ) {
                $connectionOK = false;
            } 
          }   
          else { 
              $connectionOK = false; 
          }
            
        }
    }
?>
