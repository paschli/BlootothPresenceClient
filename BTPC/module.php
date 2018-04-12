<?php
class BTPClient extends IPSModule {
  public function Create() {
    parent::Create();
    $this->RegisterPropertyInteger('idSourceString', 0); //zu überwachender String mit IFTTT Nachricht 
    $this->RegisterPropertyInteger('idBluetoothInfo', 0); //zu überwachender Boolean mit Info zum Mac-Scan 
    $this->RegisterPropertyInteger('aktState', 0);//aktueller Status
    
  }
  public function ApplyChanges() {
    parent::ApplyChanges();
    $stateId = $this->RegisterVariableInteger('STATE', 'Zustand', '', 1);//Zustand Anwesenheit
    $presentId = $this->RegisterVariableInteger('PRESENT_SINCE', 'Anwesend seit', '~UnixTimestamp', 4);
    $absentId = $this->RegisterVariableInteger('ABSENT_SINCE', 'Abwesend seit', '~UnixTimestamp', 5);
    IPS_SetIcon($this->GetIDForIdent('STATE'), 'Motion'); 
    IPS_SetIcon($this->GetIDForIdent('PRESENT_SINCE'), 'Clock');
    IPS_SetIcon($this->GetIDForIdent('ABSENT_SINCE'), 'Clock');
    if($this->ReadPropertyInteger('idSourceString')!=0){  
    	$this->RegisterEvent('OnStringChange', 0, 'BTPC_Start($id,1)','idSourceString',$this->InstanceID);
    }
    if($this->ReadPropertyInteger('idBluetoothInfo')!=0){  
    	$this->RegisterEvent('OnBloutoothChange', 0, 'BTPC_Start($id,2)','idBluetoothInfo',$this->InstanceID);
    }
  }
  
  
  protected function RegisterEvent($ident, $interval, $script, $trigger,$instanceID) {
 //   $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
    $id = @IPS_GetObjectIDByIdent($ident, $instanceID);
    if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
      IPS_DeleteEvent($id);
      IPS_LogMessage('BTPClient',"Event deleted");
      $id = 0;
    }
    if (!$id) {
      $id = IPS_CreateEvent(0);
      IPS_SetEventTrigger($id, 1, $this->ReadPropertyInteger($trigger)); //Bei Änderung von der gewählten Variable 
      IPS_SetEventActive($id, true);             //Ereignis aktivieren
//      IPS_SetParent($id, $this->InstanceID);
      IPS_SetParent($id, $instanceID);
      IPS_SetIdent($id, $ident);
      IPS_LogMessage('BTPClient',"Event created (".$id.")");
    }
    IPS_SetName($id, $ident);
    IPS_SetHidden($id, true);
    IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");
    if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");
  }
  
  
  public function Start($trigger) {
    if(IPS_SemaphoreEnter('BTPCScan', 5000)) {
    IPS_LogMessage('BTPClient',"_______________BTP-Start____________");
//--------------------------Init------------------------------------------------ 
    $string=GetValueString($this->ReadPropertyInteger('idSourceString'));
    $bt_info= GetValueBoolean($this->ReadPropertyInteger('idBluetoothInfo'));
    $inst_id=IPS_GetParent($this->GetIDForIdent('STATE'));	// ID der aktuellen Instanz
    $parent_id=IPS_GetParent($inst_id);  			// ID der übergeordneten Instanz  
    $inst_obj=IPS_GetObject($inst_id);   			// Objekt_Info der aktuellen Instanz lesen
    $inst_name=$inst_obj['ObjectName'];  			// Name der aktuellen Instanz, in der dieses Skript ausgeführt wird
    $id_aktState = IPS_GetObjectIDByIdent("STATE", $inst_id);   // Zustandsvariable suchen
    $aktState= GetValueInteger($id_aktState);                   // Zustandsvariable auslesen
    $oldState= $aktState;
    IPS_LogMessage('BTPClient',"Aktuelle Instanz: ".$inst_id." (".$inst_name.")");
    
//  IPS_LogMessage('BTPClient',"aktState=".$id_aktState);                
//  IPS_LogMessage('BTPClient',"_______________BTP-Client:".$inst_name."____________");
   

//--------------------------IFTTT Ereignis--------------------------------------
    if($trigger==1)
    {
        IPS_LogMessage('BTPClient',"String Ereignis");
        $output= $this->teile_string($string);  // String zerlegen
        if($output["Fehler"]>0){                //falls der String fehlerhaft ist
            IPS_LogMessage('BTPClient',"String Error = ".$output["Fehler"]);
            IPS_SemaphoreLeave('BTPCScan');
            return;
        }
        //Stringeinträge zuweisen
        $user=$output["User"];
        $state=$output["Zustand"];
        $time_stamp = intval($output["Zeit"]);
        IPS_LogMessage('BTPClient',"String OK -> Auswertung:");

        $UserInstID = @IPS_GetInstanceIDByName($user, $parent_id); // Instanz mit Namen suchen, der im "USER"-Eintrag steht
        if ($UserInstID === false){				// Instanz nicht gefunden
            $UserInstID= $this->create_instance($user, $parent_id);
        }
        else{							// instanz gefunden
            if($user!=$inst_name){
                IPS_LogMessage('BTPClient',"Gefundener Username (".$user.") passt nicht zur Instanz (".$inst_name.") -> Abbruch");
                IPS_LogMessage('BTPClient',"_______________BTP-Ende____________");
                IPS_SemaphoreLeave('BTPCScan');
                return;
            }
        }

//        IPS_LogMessage('BTPClient',"Suche Zustand in ID: ".$UserInstID);
        
        $id_state=@IPS_GetVariableIDByName('Zustand', $UserInstID);
        if($id_state === false){
            IPS_LogMessage('BTPClient',"Fehler : Variable Zustand nicht gefunden!");
            IPS_SemaphoreLeave('BTPCScan');
        return;
        }
//        IPS_LogMessage('BTPClient',"    Gefunden! ID: ".$id_state);

        
        $aktState=$aktState & 2; // zweite Stelle filtern
        $state=$aktState | $state;
        SetValueInteger($id_state, $state);
        IPS_LogMessage('BTPClient',"Eintrag (".$id_aktState.") aktualisiert!");

    }
//--------------------------Bluetooth Ereignis--------------------------------------
    else if ($trigger==2) {
        IPS_LogMessage('BTPClient',"Bluetooth Ereignis");
      //$bt_info ist der aktuelle BT-Zustand
        $aktState=$aktState & 1; // erste Stelle filtern
        $state=$aktState | ($bt_info<<1);
        SetValueInteger($id_aktState, $state);
        $time_stamp = time();
        IPS_LogMessage('BTPClient',"Eintrag (".$id_aktState.") aktualisiert!");
        
    }
      
        IPS_LogMessage('BTPClient',"_______________BTP-Ende____________");
        IPS_SemaphoreLeave('BTPCScan');
     
 //---------------------------Zeit Eintrag ---------------------------------------   
    
    if((($state>0)&&($oldState==0))||(($state==0)&&($oldState>0))){ //Falls eine Änderung des Status erfolgte
    $id_anw=@IPS_GetVariableIDByName('Anwesend seit', $inst_id);
        if($id_anw === false){
                IPS_LogMessage('BTPClient',"Fehler : Variable (Anwesend seit) nicht gefunden!");
                return;
        }  
        $id_abw=@IPS_GetVariableIDByName('Abwesend seit', $inst_id);

        if($id_abw === false){
                IPS_LogMessage('BTPClient',"Fehler : Variable (Abwesend seit) nicht gefunden!");
                return;
        } 
        if ($state) SetValueInteger($id_anw, $time_stamp);
        if (!$state) SetValueInteger($id_abw, $time_stamp);
        IPS_SetHidden($id_anw, !$state);
        IPS_SetHidden($id_abw, $state);
    }
    }
    else {
      IPS_LogMessage('BTPClient', 'Semaphore Timeout');
    }
      
  }
  
  private function teile_string($string) {
    $output=array("User"=>"","Zustand"=>"","Zeit"=>"","Fehler"=>3); 
        $array=explode(";",$string);
        IPS_LogMessage('BTPClient',"zerlege String:");
        foreach($array as $item){
          if($item!=""){
              $subarray=explode("=",$item);
              $tag=$subarray[0];
              $value=$subarray[1];

              IPS_LogMessage('BTPClient'," - Tag: ".$tag." (".$value.")");
              switch($tag){
                      case "User" : $output["User"] = $value; 
                                    $output["Fehler"]--; break;
                      case "Zustand": $output["Zustand"] = $value; 
                                      $output["Fehler"]--; break;
                      case "Zeit": $output["Zeit"] = $value;
                                      $output["Fehler"]--; break;
                      default :  $output["Fehler"]=4;
                                 return $output;
                      }
           }
        }
    return $output;
  }
  
  private function create_instance($user, $parent_id){
    IPS_LogMessage('BTPClient',"Instanz mit Namen: ".$user." nicht gefunden! Muss neu angelegt werden!");
    IPS_LogMessage('BTPClient',"Anlegen in: ".$parent_id);	
    $NewInsID = IPS_CreateInstance("{58C01EE2-6859-492A-9B7B-25EDAA6D48FE}");
    IPS_SetName($NewInsID, $user); // Instanz benennen
    IPS_SetParent($NewInsID, $parent_id); // Instanz einsortieren unter der übergeordneten Instanz
    $stringID=$this->ReadPropertyInteger('idSourceString');
    $this->RegisterEvent('OnStringChange', 0, 'BTPC_Start($id,1)','idSourceString',$NewInsID);
    return $NewInsID;
  }
  
  
  
  
  
  
}
