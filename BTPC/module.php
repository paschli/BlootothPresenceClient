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
    $stateId = $this->RegisterVariableInteger('STATE', 'Zustand', 'Presence_BTPC', 1);//Zustand Anwesenheit
    $presentId = $this->RegisterVariableInteger('PRESENT_SINCE', 'Anwesend seit', '~UnixTimestamp', 3);
    $absentId = $this->RegisterVariableInteger('ABSENT_SINCE', 'Abwesend seit', '~UnixTimestamp', 3);
    IPS_SetIcon($this->GetIDForIdent('STATE'), 'Motion'); 
    IPS_SetIcon($this->GetIDForIdent('PRESENT_SINCE'), 'Clock');
    IPS_SetIcon($this->GetIDForIdent('ABSENT_SINCE'), 'Clock');
    if($this->ReadPropertyInteger('idSourceString')!=0){  
    	$this->RegisterEvent('OnStringChange', 0, 'BTPC_Scan($id,1)','idSourceString');
    }
    if($this->ReadPropertyInteger('idBluetoothInfo')!=0){  
    	$this->RegisterEvent('OnBloutoothChange', 0, 'BTPC_Scan($id,2)','idBluetoothInfo');
    }
  }
  protected function RegisterEvent($ident, $interval, $script, $trigger) {
    $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
    if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
      IPS_DeleteEvent($id);
      IPS_LogMessage('BTPClient',"Event deleted");
      $id = 0;
    }
    if (!$id) {
      $id = IPS_CreateEvent(0);
      IPS_SetEventTrigger($id, 1, $this->ReadPropertyInteger($trigger)); //Bei Änderung von der gewählten Variable 
      IPS_SetEventActive($id, true);             //Ereignis aktivieren
      IPS_SetParent($id, $this->InstanceID);
      IPS_SetIdent($id, $ident);
    }
    IPS_SetName($id, $ident);
    IPS_SetHidden($id, true);
    IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");
    if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");
  }
  
  
  
  
  /*
   * Sucht nach dem Bluetoothdevice
   */
  public function Scan(int $var) {
    if(IPS_SemaphoreEnter('BTPCScan', 5000)) {
      $string=GetValueString($this->ReadPropertyInteger('idSourceString'));
      $bt_info= GetValueBoolean($this->ReadPropertyInteger('idBluetoothInfo'));
      $inst_id=IPS_GetParent($this->GetIDForIdent('STATE'));	// ID der aktuellen Instanz
      $aktState= $this->ReadPropertyInteger('aktState');
      $parent_id=IPS_GetParent($inst_id);  			// ID der übergeordneten Instanz  
      $inst_obj=IPS_GetObject($inst_id);   			// Objekt_Info der aktuellen Instanz lesen
      $inst_name=$inst_obj['ObjectName'];  			// Name der aktuellen Instanz, in der dieses Skript ausgeführt wird
      IPS_LogMessage('BTPClient',"_______________BTPClient-".$inst_name."____________");
      if($var==1)
      {
        IPS_LogMessage('BTPClient',"String eingelesen");
        $array=explode(";",$string);
        IPS_LogMessage('BTPClient',"zerlege String:");
        foreach($array as $item){
          if($item!=""){
              $subarray=explode("=",$item);
              $tag=$subarray[0];
              $value=$subarray[1];

              IPS_LogMessage('BTPClient',"Tag:".$tag." / Value:".$value);
              switch($tag){
                      case "User" : $user = $value; break;
                      //case "Zustand": $state = boolval($value); break;
                      case "Zustand": $state = $value; break;
                      case "Zeit": $time_stamp = intval($value); break;
                      default : IPS_LogMessage('BTPClient',"Tag=".$tag." nicht erkannt!");
                                IPS_SemaphoreLeave('BTPCScan');
                                exit();
                      }
           }
        }
          IPS_LogMessage('BTPClient',"String OK -> Auswertung:");

          $UserInstID = @IPS_GetInstanceIDByName($user, $parent_id); // Instanz mit Namen suchen, der im "USER"-Eintrag steht
          if ($UserInstID === false){				// Instanz nicht gefunden
           IPS_LogMessage('BTPClient',"Instanz mit Namen: ".$user." nicht gefunden! Muss neu angelegt werden!");
           IPS_LogMessage('BTPClient',"Anlegen in: ".$parent_id);	
           $NewInsID = IPS_CreateInstance("{58C01EE2-6859-492A-9B7B-25EDAA6D48FE}");
           IPS_SetName($NewInsID, $user); // Instanz benennen
           IPS_SetParent($NewInsID, $parent_id); // Instanz einsortieren unter der übergeordneten Instanz
           $UserInstID=$NewInsID;
          }
          else{							// instanz gefunden
           //IPS_LogMessage('BTPClient',"Instanz mit Namen: ".$user." gefunden! ID:".$UserInstID);
           if($user!=$inst_name){
               IPS_LogMessage('BTPClient',"Gefundener Username (".$user.") passt nicht zur Instanz (".$inst_name.") -> Abbruch");
               IPS_LogMessage('BTPClient',"_______________BTPClient-Ende____________");
               IPS_SemaphoreLeave('BTPCScan');
               exit();
           }
          }

          IPS_LogMessage('BTPClient',"Suche Zustand in ID: ".$UserInstID);
          $id_state=@IPS_GetVariableIDByName('Zustand', $UserInstID); 
          if($id_state === false){
                  IPS_LogMessage('BTPClient',"Fehler : Variable Zustand nicht gefunden!");
                  IPS_SemaphoreLeave('BTPCScan');
                  exit;
          }
          IPS_LogMessage('BTPClient',"Gefunden! ID: ".$id_state);
          //$aktState = GetValueInteger($id_state);

          
          $id_anw=@IPS_GetVariableIDByName('Anwesend seit', $UserInstID);
          if($id_anw === false){
                  IPS_LogMessage('BTPClient',"Fehler : Variable (Anwesend seit) nicht gefunden!");
                  exit;
          }  
          $id_abw=@IPS_GetVariableIDByName('Abwesend seit', $UserInstID);
          if($id_abw === false){
                  IPS_LogMessage('BTPClient',"Fehler : Variable (Abwesend seit) nicht gefunden!");
                  exit;
          } 
          $anw_alt= GetValueInteger($id_anw);
          $abw_alt= GetValueInteger($id_abw);
          $bt_State=(intval($bt_info));
          $changeState=200+10*$state+$bt_State;
          $aktState=$this->FSM_Zustand($aktState, $changeState);
          
          /*if(($time_stamp>$anw_alt)&&($time_stamp>$abw_alt)){
              if ($state) SetValueInteger($id_anw, $time_stamp);
              if (!$state) SetValueInteger($id_abw, $time_stamp);
              IPS_SetHidden($id_anw, !$state);
              IPS_SetHidden($id_abw, $state);
              SetValueInteger($id_state, $state);
              IPS_LogMessage('BTPClient',"Eintrag aktualisiert");
          }
          else {
              IPS_LogMessage('BTPClient',"Event ist älter als vorhande Zeitstempel -> keine Aktualisierung erforderlich");
          }*/
      }
      elseif ($var==2) {
        $aktState= GetValueInteger($this->GetIDforIdent('STATE'));
          
      }
      
        IPS_LogMessage('BTPClient',"_______________BTPClient-Ende____________");
        IPS_SemaphoreLeave('BTPCScan');
    } 
    else {
      IPS_LogMessage('BTPClient', 'Semaphore Timeout');
    }
  }
  
  private function FSM_Zustand($aktState, $changeState) {
      
      //Zustand: 
      //    0 = Abwesend (bt=0 / ifttt=0) 
      //    1 = Umgebung (bt=0 / ifttt=1) 
      //    2 = Haus     (bt=1 / ifttt=-) 
      //    3 = BT_unplausibel (bt=1 / ifttt=0)
      //    4 = IFTTT unplausibel 
      //Change:
      //    BT 10x    = Bluetooth von 1 nach 0
      //    BT 11x    = Bluetooth von 0 nach 1
      //    IFTTT 20x  = IFTTT von 1 nach 0
      //    IFTTT 21x  = IFTTT von 0 nach 1
      //    x = Zustand der jeweils anderen Methode
      
      switch ($aktState){
          case 0: 
              if(($changeState==110)||($changeState==111)) $newState=2;
              
              else if($changeState==210) $newState=1;
              break;
          case 1: 
              if($changeState==111) $newState=2;
              else if($changeState==200) $newState=0;
              break;
          case 2: 
              if($changeState==101) $newState=1;
              else if($changeState==201) $newState=3;
              else if($changeState==100) $newState=0;
              break;
          case 3: 
              if($changeState==100) $newState=0;
              else if($changeState==211) $newState=2;
              break;
          case 4: 
              if($changeState==100) $newState=0;
              else if($changeState==211) $newState=2;
              break;
      }
      return $newState;
  }
}
