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
        }
 
        public function ApplyChanges() {
          /* Called on 'apply changes' in the configuration UI and after creation of the instance */
          parent::ApplyChanges();
        }
 
        //=== Modul Funktionen =========================================================================================
        /* Own module functions called via the defined prefix GOE_* 
        *
        * GOE_CheckConnection($id);
        *
        */
        
        public function CheckConnection($ID) {
          /* Check the connection to the go-eCharger */
          echo "GOE_CheckConnection for instance ".$ID;
        }
    }
?>